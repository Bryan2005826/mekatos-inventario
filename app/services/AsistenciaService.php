<?php
declare(strict_types=1);

final class AsistenciaService
{
    /** Jornada abierta del trabajador (o null) */
    public static function jornadaAbierta(int $trabajadorId): ?array
    {
        return Database::one(
            "SELECT * FROM jornadas WHERE trabajador_id = ? AND estado = 'abierta' ORDER BY entrada DESC LIMIT 1",
            [$trabajadorId]
        );
    }

    /**
     * Cierra como "incompleta" una jornada abierta que superó el máximo de horas.
     * No se calcula: el administrador debe registrar la salida real.
     */
    private static function vencerSiCorresponde(?array $j): ?array
    {
        if (!$j) {
            return null;
        }
        $max = max(1, Settings::int('max_horas_jornada')) * 3600;
        if (time() - strtotime($j['entrada'] . ' UTC') <= $max) {
            return $j;
        }
        $obs = trim(($j['observaciones'] ?? '') . "\nSin salida registrada: requiere revisión del administrador.");
        Database::update('jornadas', [
            'estado' => 'incompleta', 'observaciones' => $obs, 'actualizado' => now_utc(),
        ], 'id = :id', ['id' => $j['id']]);
        Audit::log('jornada_incompleta_auto', 'jornada', (int)$j['id'], 'Superó ' . Settings::int('max_horas_jornada') . ' h sin salida');
        return null;
    }

    public static function estado(int $trabajadorId): array
    {
        $abierta = self::vencerSiCorresponde(self::jornadaAbierta($trabajadorId));
        $ultima = Database::one(
            "SELECT tipo, fecha_hora FROM marcaciones WHERE trabajador_id = ? AND estado = 'valida' ORDER BY fecha_hora DESC, id DESC LIMIT 1",
            [$trabajadorId]
        );
        return [
            'abierta'          => $abierta,
            'puede_entrada'    => $abierta === null,
            'puede_salida'     => $abierta !== null,
            'entrada_hora'     => $abierta ? fmt_hora($abierta['entrada']) : null,
            'ultima'           => $ultima,
        ];
    }

    /**
     * Registra una entrada o salida desde el kiosco.
     * @param array $verif ['identidad' => 'pin_ok+foto', 'ubicacion' => 'dispositivo_ok+red_ok']
     */
    public static function marcar(int $trabajadorId, string $tipo, ?int $dispositivoId, ?string $fotoDataUrl, array $verif): array
    {
        if (!in_array($tipo, ['entrada', 'salida'], true)) {
            return ['ok' => false, 'error' => 'Tipo de registro no válido.'];
        }
        $fotoGuardada = null;
        try {
            return Database::transaction(function () use ($trabajadorId, $tipo, $dispositivoId, $fotoDataUrl, $verif, &$fotoGuardada) {
                // Bloquea la fila del trabajador: evita dobles registros simultáneos
                $t = Database::one('SELECT id, nombre, activo FROM trabajadores WHERE id = ? FOR UPDATE', [$trabajadorId]);
                if (!$t || !(int)$t['activo']) {
                    return ['ok' => false, 'error' => 'Trabajador no disponible.'];
                }

                $ahora = now_utc();
                $minEntre = max(0, Settings::int('min_minutos_entre_marcas')) * 60;
                $ultima = Database::one(
                    "SELECT tipo, fecha_hora FROM marcaciones WHERE trabajador_id = ? AND estado = 'valida' ORDER BY fecha_hora DESC, id DESC LIMIT 1",
                    [$trabajadorId]
                );
                if ($ultima && time() - strtotime($ultima['fecha_hora'] . ' UTC') < $minEntre) {
                    $accion = $ultima['tipo'] === 'entrada' ? 'una entrada' : 'una salida';
                    return ['ok' => false, 'error' => "Acabas de registrar $accion (" . fmt_hora($ultima['fecha_hora']) . '). Espera unos minutos antes de volver a marcar.'];
                }

                $antesAbierta = self::jornadaAbierta($trabajadorId);
                $abierta = self::vencerSiCorresponde($antesAbierta);
                $aviso = null;
                if ($antesAbierta && !$abierta) {
                    $aviso = 'Tu entrada del ' . fmt_fecha_hora($antesAbierta['entrada']) . ' no tenía salida. Quedó marcada para revisión del administrador.';
                }

                if ($tipo === 'entrada' && $abierta) {
                    return ['ok' => false, 'error' => 'Ya tienes una entrada abierta (' . fmt_hora($abierta['entrada']) . '). Registra tu salida primero.'];
                }
                if ($tipo === 'salida' && !$abierta) {
                    return ['ok' => false, 'error' => $aviso
                        ? $aviso . ' No se puede registrar la salida sin una entrada válida; avisa al administrador.'
                        : 'No tienes una entrada abierta. Registra primero tu entrada.'];
                }

                if ($fotoDataUrl) {
                    $fotoGuardada = FotoStorage::guardarDataUrl($fotoDataUrl, $trabajadorId, $tipo);
                }

                if ($tipo === 'entrada') {
                    $jornadaId = Database::insert('jornadas', [
                        'trabajador_id' => $trabajadorId,
                        'fecha'         => utc_to_local($ahora)->format('Y-m-d'),
                        'entrada'       => $ahora,
                        'estado'        => 'abierta',
                        'creado'        => $ahora,
                        'actualizado'   => $ahora,
                    ]);
                } else {
                    $jornadaId = (int)$abierta['id'];
                    $c = CalculoHoras::calcular($abierta['entrada'], $ahora, CalculoHoras::reglasActuales());
                    Database::update('jornadas', [
                        'salida'             => $ahora,
                        'estado'             => 'completa',
                        'minutos_brutos'     => $c['brutos'],
                        'minutos_descanso'   => $c['descanso'],
                        'minutos_trabajados' => $c['trabajados'],
                        'minutos_nocturnos'  => $c['nocturnos'],
                        'actualizado'        => $ahora,
                    ], 'id = :id', ['id' => $jornadaId]);
                }

                $marcId = Database::insert('marcaciones', [
                    'trabajador_id'   => $trabajadorId,
                    'jornada_id'      => $jornadaId,
                    'tipo'            => $tipo,
                    'fecha_hora'      => $ahora,
                    'origen'          => 'kiosco',
                    'dispositivo_id'  => $dispositivoId,
                    'ip'              => client_ip(),
                    'verif_identidad' => $verif['identidad'] ?? 'pin_ok',
                    'verif_ubicacion' => $verif['ubicacion'] ?? 'dispositivo_ok',
                    'foto'            => $fotoGuardada,
                    'estado'          => 'valida',
                    'creado'          => $ahora,
                ]);

                $hora = fmt_hora($ahora);
                $res = [
                    'ok'       => true,
                    'tipo'     => $tipo,
                    'hora'     => $hora,
                    'id'       => $marcId,
                    'mensaje'  => ($tipo === 'entrada' ? 'Entrada' : 'Salida') . " registrada correctamente. Hora: $hora",
                    'aviso'    => $aviso,
                ];
                if ($tipo === 'salida') {
                    $res['trabajado'] = fmt_minutos($c['trabajados']);
                }
                return $res;
            });
        } catch (RuntimeException $e) {
            if ($fotoGuardada && ($p = FotoStorage::ruta($fotoGuardada))) {
                @unlink($p);
            }
            return ['ok' => false, 'error' => $e->getMessage()];
        } catch (Throwable $e) {
            if ($fotoGuardada && ($p = FotoStorage::ruta($fotoGuardada))) {
                @unlink($p);
            }
            throw $e;
        }
    }

    /* ---------------- Correcciones del administrador ---------------- */

    private static function verificarNoPagada(int $trabajadorId, string ...$fechas): void
    {
        foreach ($fechas as $f) {
            if ($f && PlanillaService::fechaPagada($trabajadorId, $f)) {
                throw new InvalidArgumentException('La planilla de esa quincena ya está pagada. Reábrela en Planillas antes de cambiar sus jornadas.');
            }
        }
    }

    private static function haySolapamiento(int $trabajadorId, string $entrada, ?string $salida, ?int $excluirId): bool
    {
        $fin = $salida ?? '9999-12-31 00:00:00';
        $sql = "SELECT COUNT(*) FROM jornadas
                WHERE trabajador_id = ? AND estado <> 'anulada'
                  AND entrada IS NOT NULL
                  AND entrada < ? AND COALESCE(salida, '9999-12-31 00:00:00') > ?";
        $params = [$trabajadorId, $fin, $entrada];
        if ($excluirId) {
            $sql .= ' AND id <> ?';
            $params[] = $excluirId;
        }
        return (int)Database::value($sql, $params) > 0;
    }

    /** Valida fechas locales "Y-m-d\TH:i" y las devuelve en UTC */
    private static function validarRango(string $entradaLocal, ?string $salidaLocal): array
    {
        $fmt = fn($v) => DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $v, app_tz());
        $e = $fmt($entradaLocal);
        if (!$e) {
            throw new InvalidArgumentException('La hora de entrada no es válida.');
        }
        $s = null;
        if ($salidaLocal !== null && $salidaLocal !== '') {
            $s = $fmt($salidaLocal);
            if (!$s) {
                throw new InvalidArgumentException('La hora de salida no es válida.');
            }
            if ($s <= $e) {
                throw new InvalidArgumentException('La salida debe ser posterior a la entrada.');
            }
            if ($s->getTimestamp() - $e->getTimestamp() > 24 * 3600) {
                throw new InvalidArgumentException('Una jornada no puede durar más de 24 horas.');
            }
        }
        $limite = time() + 300;
        if ($e->getTimestamp() > $limite || ($s && $s->getTimestamp() > $limite)) {
            throw new InvalidArgumentException('No se pueden registrar horas en el futuro.');
        }
        $u = new DateTimeZone('UTC');
        return [
            $e->setTimezone($u)->format('Y-m-d H:i:s'),
            $s ? $s->setTimezone($u)->format('Y-m-d H:i:s') : null,
            $e->format('Y-m-d'),
        ];
    }

    private static function camposCalculados(string $entrada, ?string $salida): array
    {
        if (!$salida) {
            return ['minutos_brutos' => null, 'minutos_descanso' => null, 'minutos_trabajados' => null, 'minutos_nocturnos' => null];
        }
        $c = CalculoHoras::calcular($entrada, $salida, CalculoHoras::reglasActuales());
        return [
            'minutos_brutos'     => $c['brutos'],
            'minutos_descanso'   => $c['descanso'],
            'minutos_trabajados' => $c['trabajados'],
            'minutos_nocturnos'  => $c['nocturnos'],
        ];
    }

    public static function corregir(int $jornadaId, string $entradaLocal, ?string $salidaLocal, string $motivo): void
    {
        $motivo = trim($motivo);
        if (mb_strlen($motivo) < 5) {
            throw new InvalidArgumentException('Escribe el motivo de la corrección (mínimo 5 caracteres).');
        }
        Database::transaction(function () use ($jornadaId, $entradaLocal, $salidaLocal, $motivo) {
            $j = Database::one('SELECT * FROM jornadas WHERE id = ? FOR UPDATE', [$jornadaId]);
            if (!$j || $j['estado'] === 'anulada') {
                throw new InvalidArgumentException('La jornada no existe o está anulada.');
            }
            [$entrada, $salida, $fecha] = self::validarRango($entradaLocal, $salidaLocal);
            self::verificarNoPagada((int)$j['trabajador_id'], $j['fecha'], $fecha);
            if (self::haySolapamiento((int)$j['trabajador_id'], $entrada, $salida, $jornadaId)) {
                throw new InvalidArgumentException('Las horas se cruzan con otra jornada del mismo trabajador.');
            }
            if (!$salida && self::jornadaAbiertaDistinta((int)$j['trabajador_id'], $jornadaId)) {
                throw new InvalidArgumentException('El trabajador ya tiene otra jornada abierta. Registra la salida.');
            }
            $nuevo = array_merge([
                'fecha'       => $fecha,
                'entrada'     => $entrada,
                'salida'      => $salida,
                'estado'      => $salida ? 'completa' : 'abierta',
                'corregida'   => 1,
                'actualizado' => now_utc(),
            ], self::camposCalculados($entrada, $salida));
            Database::update('jornadas', $nuevo, 'id = :id', ['id' => $jornadaId]);
            Audit::log('jornada_corregida', 'jornada', $jornadaId, $motivo, self::resumen($j), self::resumen(array_merge($j, $nuevo)));
        });
    }

    private static function jornadaAbiertaDistinta(int $trabajadorId, ?int $excluir): bool
    {
        return (bool)Database::value(
            "SELECT 1 FROM jornadas WHERE trabajador_id = ? AND estado = 'abierta' AND id <> ?",
            [$trabajadorId, $excluir ?? 0]
        );
    }

    public static function crearManual(int $trabajadorId, string $entradaLocal, ?string $salidaLocal, string $motivo): int
    {
        $motivo = trim($motivo);
        if (mb_strlen($motivo) < 5) {
            throw new InvalidArgumentException('Escribe el motivo del registro manual (mínimo 5 caracteres).');
        }
        return Database::transaction(function () use ($trabajadorId, $entradaLocal, $salidaLocal, $motivo) {
            $t = Database::one('SELECT id FROM trabajadores WHERE id = ? FOR UPDATE', [$trabajadorId]);
            if (!$t) {
                throw new InvalidArgumentException('El trabajador no existe.');
            }
            [$entrada, $salida, $fecha] = self::validarRango($entradaLocal, $salidaLocal);
            self::verificarNoPagada($trabajadorId, $fecha);
            if (self::haySolapamiento($trabajadorId, $entrada, $salida, null)) {
                throw new InvalidArgumentException('Las horas se cruzan con otra jornada del mismo trabajador.');
            }
            if (!$salida && self::jornadaAbiertaDistinta($trabajadorId, null)) {
                throw new InvalidArgumentException('El trabajador ya tiene una jornada abierta.');
            }
            $ahora = now_utc();
            $id = Database::insert('jornadas', array_merge([
                'trabajador_id' => $trabajadorId,
                'fecha'         => $fecha,
                'entrada'       => $entrada,
                'salida'        => $salida,
                'estado'        => $salida ? 'completa' : 'abierta',
                'corregida'     => 1,
                'observaciones' => 'Registro manual del administrador.',
                'creado'        => $ahora,
                'actualizado'   => $ahora,
            ], self::camposCalculados($entrada, $salida)));
            foreach (array_filter(['entrada' => $entrada, 'salida' => $salida]) as $tipo => $hora) {
                Database::insert('marcaciones', [
                    'trabajador_id'   => $trabajadorId,
                    'jornada_id'      => $id,
                    'tipo'            => $tipo,
                    'fecha_hora'      => $hora,
                    'origen'          => 'admin',
                    'ip'              => client_ip(),
                    'verif_identidad' => 'manual_admin',
                    'verif_ubicacion' => 'manual_admin',
                    'estado'          => 'valida',
                    'observaciones'   => $motivo,
                    'creado'          => $ahora,
                ]);
            }
            Audit::log('jornada_manual', 'jornada', $id, $motivo, null, ['trabajador_id' => $trabajadorId, 'entrada' => $entrada, 'salida' => $salida]);
            return $id;
        });
    }

    public static function anular(int $jornadaId, string $motivo): void
    {
        $motivo = trim($motivo);
        if (mb_strlen($motivo) < 5) {
            throw new InvalidArgumentException('Escribe el motivo de la anulación (mínimo 5 caracteres).');
        }
        $j = Database::one('SELECT * FROM jornadas WHERE id = ?', [$jornadaId]);
        if (!$j || $j['estado'] === 'anulada') {
            throw new InvalidArgumentException('La jornada no existe o ya está anulada.');
        }
        self::verificarNoPagada((int)$j['trabajador_id'], $j['fecha']);
        Database::update('jornadas', ['estado' => 'anulada', 'corregida' => 1, 'actualizado' => now_utc()], 'id = :id', ['id' => $jornadaId]);
        Audit::log('jornada_anulada', 'jornada', $jornadaId, $motivo, self::resumen($j), ['estado' => 'anulada']);
    }

    private static function resumen(array $j): array
    {
        return [
            'entrada'    => $j['entrada'] ? fmt_fecha_hora($j['entrada']) : null,
            'salida'     => $j['salida'] ? fmt_fecha_hora($j['salida']) : null,
            'estado'     => $j['estado'],
            'trabajados' => $j['minutos_trabajados'] ?? null,
        ];
    }

    /* ---------------- Consultas ---------------- */

    public static function listar(?int $trabajadorId, string $desde, string $hasta, ?string $estado = null): array
    {
        $sql = 'SELECT j.*, t.nombre, t.color FROM jornadas j JOIN trabajadores t ON t.id = j.trabajador_id
                WHERE j.fecha BETWEEN ? AND ?';
        $p = [$desde, $hasta];
        if ($trabajadorId) {
            $sql .= ' AND j.trabajador_id = ?';
            $p[] = $trabajadorId;
        }
        if ($estado) {
            $sql .= ' AND j.estado = ?';
            $p[] = $estado;
        }
        $sql .= ' ORDER BY j.fecha DESC, j.entrada DESC';
        return Database::all($sql, $p);
    }

    public static function marcacionesDe(int $jornadaId): array
    {
        return Database::all(
            'SELECT m.*, d.nombre AS dispositivo FROM marcaciones m LEFT JOIN dispositivos d ON d.id = m.dispositivo_id
             WHERE m.jornada_id = ? ORDER BY m.fecha_hora',
            [$jornadaId]
        );
    }

    public static function resumenDia(): array
    {
        $hoy = hoy_local();
        $trabajadores = Database::all('SELECT id, nombre, color FROM trabajadores WHERE activo = 1 ORDER BY orden, nombre');
        $jornadasHoy = Database::all(
            "SELECT * FROM jornadas WHERE estado <> 'anulada' AND (fecha = ? OR estado = 'abierta') ORDER BY entrada",
            [$hoy]
        );
        $porTrab = [];
        foreach ($jornadasHoy as $j) {
            $porTrab[$j['trabajador_id']][] = $j;
        }
        $con = $sin = $abiertas = [];
        $minutosHoy = 0;
        foreach ($trabajadores as $t) {
            $js = $porTrab[$t['id']] ?? [];
            $t['jornadas'] = $js;
            if ($js) {
                $con[] = $t;
            } else {
                $sin[] = $t;
            }
            foreach ($js as $j) {
                if ($j['estado'] === 'abierta') {
                    $abiertas[] = $j + ['nombre' => $t['nombre'], 'color' => $t['color']];
                    $minutosHoy += intdiv(time() - strtotime($j['entrada'] . ' UTC'), 60);
                } elseif ($j['fecha'] === $hoy) {
                    $minutosHoy += (int)$j['minutos_trabajados'];
                }
            }
        }
        $incompletas = Database::all(
            "SELECT j.*, t.nombre, t.color FROM jornadas j JOIN trabajadores t ON t.id = j.trabajador_id
             WHERE j.estado = 'incompleta' ORDER BY j.entrada DESC LIMIT 20"
        );
        $desde = local_to_utc($hoy . ' 00:00:00');
        $rechazos = Database::all(
            'SELECT r.*, t.nombre FROM intentos_rechazados r LEFT JOIN trabajadores t ON t.id = r.trabajador_id
             WHERE r.creado >= ? ORDER BY r.creado DESC LIMIT 8',
            [$desde]
        );
        return compact('con', 'sin', 'abiertas', 'incompletas', 'rechazos', 'minutosHoy', 'hoy');
    }
}
