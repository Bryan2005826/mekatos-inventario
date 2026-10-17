<?php
declare(strict_types=1);

/**
 * Planillas por quincena (1-15 y 16-fin de mes).
 * Las horas salen siempre de las jornadas; la planilla guarda valor de la
 * hora, bono, descuentos y estado (abierta → cerrada → pagada).
 * El cierre es automático: al pasar la medianoche del último día del
 * período, la primera consulta lo cierra y abre el siguiente.
 */
final class PlanillaService
{
    /* ---------------- Períodos ---------------- */

    /** @return array{inicio:string, fin:string, nombre:string, rango:string} */
    public static function periodoDe(string $fecha): array
    {
        $d = new DateTimeImmutable(substr($fecha, 0, 10));
        $dia = (int)$d->format('j');
        $mes = (int)$d->format('n');
        $anio = $d->format('Y');
        if ($dia <= 15) {
            $inicio = $d->format('Y-m-01');
            $fin = $d->format('Y-m-15');
            $nombre = 'Primera quincena de ' . nombre_mes($mes) . " $anio";
            $rango = '1 al 15 de ' . nombre_mes($mes) . " de $anio";
        } else {
            $inicio = $d->format('Y-m-16');
            $fin = $d->format('Y-m-t');
            $nombre = 'Segunda quincena de ' . nombre_mes($mes) . " $anio";
            $rango = '16 al ' . $d->format('t') . ' de ' . nombre_mes($mes) . " de $anio";
        }
        return compact('inicio', 'fin', 'nombre', 'rango');
    }

    public static function periodoActual(): array
    {
        return self::periodoDe(hoy_local());
    }

    public static function periodoAnterior(string $inicio): array
    {
        return self::periodoDe((new DateTimeImmutable($inicio))->modify('-1 day')->format('Y-m-d'));
    }

    public static function periodoSiguiente(string $inicio): array
    {
        $p = self::periodoDe($inicio);
        return self::periodoDe((new DateTimeImmutable($p['fin']))->modify('+1 day')->format('Y-m-d'));
    }

    /* ---------------- Sincronización y cierre automático ---------------- */

    private static function crear(int $trabajadorId, array $periodo): int
    {
        $t = Database::one('SELECT valor_hora, pago_fijo, valor_fijo FROM trabajadores WHERE id = ?', [$trabajadorId]) ?? [];
        $valor = ($t['valor_hora'] ?? null) !== null ? (float)$t['valor_hora'] : (float)Settings::get('valor_hora_defecto');
        $ahora = now_utc();
        // La nueva quincena hereda la forma de pago del trabajador (por horas o fijo)
        Database::run(
            'INSERT IGNORE INTO planillas (trabajador_id, periodo_inicio, periodo_fin, estado, valor_hora, pago_fijo, valor_fijo, creado, actualizado)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$trabajadorId, $periodo['inicio'], $periodo['fin'], $periodo['fin'] < hoy_local() ? 'cerrada' : 'abierta', $valor,
             (int)($t['pago_fijo'] ?? 0), (float)($t['valor_fijo'] ?? 0), $ahora, $ahora]
        );
        return (int)Database::value('SELECT id FROM planillas WHERE trabajador_id = ? AND periodo_inicio = ?', [$trabajadorId, $periodo['inicio']]);
    }

    /**
     * Garantiza que existan las planillas de la quincena actual (trabajadores activos)
     * y de toda quincena que tenga jornadas; cierra las vencidas.
     */
    public static function sincronizar(): void
    {
        // Si se actualizó desde la etapa 1, crea la tabla de planillas que falte
        static $revisado = false;
        if (!$revisado) {
            $revisado = true;
            if (!Database::value("SHOW TABLES LIKE 'planillas'")) {
                $schema = is_file(APP_PATH . '/schema.sql') ? APP_PATH . '/schema.sql' : ROOT_PATH . '/database/schema.sql';
                Database::ejecutarScript($schema);
            }
            // Pago fijo (actualización de instalaciones anteriores)
            if (!Database::value("SHOW COLUMNS FROM planillas LIKE 'pago_fijo'")) {
                Database::pdo()->exec('ALTER TABLE planillas ADD pago_fijo TINYINT(1) NOT NULL DEFAULT 0 AFTER valor_hora, ADD valor_fijo DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER pago_fijo');
            }
            if (!Database::value("SHOW COLUMNS FROM trabajadores LIKE 'pago_fijo'")) {
                Database::pdo()->exec('ALTER TABLE trabajadores ADD pago_fijo TINYINT(1) NOT NULL DEFAULT 0 AFTER valor_hora, ADD valor_fijo DECIMAL(12,2) NULL AFTER pago_fijo');
            }
        }
        $actual = self::periodoActual();
        foreach (Database::all('SELECT id FROM trabajadores WHERE activo = 1') as $t) {
            self::crear((int)$t['id'], $actual);
        }

        // Jornadas que todavía no tienen planilla
        $sueltas = Database::all(
            "SELECT DISTINCT j.trabajador_id, j.fecha FROM jornadas j
             LEFT JOIN planillas p ON p.trabajador_id = j.trabajador_id AND j.fecha BETWEEN p.periodo_inicio AND p.periodo_fin
             WHERE j.estado <> 'anulada' AND p.id IS NULL"
        );
        foreach ($sueltas as $s) {
            self::crear((int)$s['trabajador_id'], self::periodoDe($s['fecha']));
        }

        // Cierre automático de las quincenas que ya terminaron
        $vencidas = Database::all("SELECT * FROM planillas WHERE estado = 'abierta' AND periodo_fin < ?", [hoy_local()]);
        $periodos = [];
        foreach ($vencidas as $p) {
            $c = self::calcular($p);
            Database::update('planillas', [
                'estado' => 'cerrada', 'cerrada' => now_utc(), 'minutos_cierre' => $c['minutos'], 'actualizado' => now_utc(),
            ], "id = :id AND estado = 'abierta'", ['id' => $p['id']]);
            $periodos[$p['periodo_inicio']] = true;
        }
        foreach (array_keys($periodos) as $inicio) {
            Audit::log('planillas_cierre_auto', 'planilla', null, 'Cierre automático: ' . self::periodoDe($inicio)['nombre']);
            try {
                NotificacionService::quincenaCerrada($inicio);
            } catch (Throwable $e) {
                error_log('[asistencia] correo cierre: ' . $e->getMessage());
            }
        }
    }

    /* ---------------- Cálculo ---------------- */

    public static function jornadas(array $planilla): array
    {
        return Database::all(
            "SELECT * FROM jornadas WHERE trabajador_id = ? AND fecha BETWEEN ? AND ? AND estado <> 'anulada'
             ORDER BY entrada",
            [$planilla['trabajador_id'], $planilla['periodo_inicio'], $planilla['periodo_fin']]
        );
    }

    /**
     * Cálculo puro a partir de los minutos (se usa en pantalla, impresión y pruebas).
     */
    public static function liquidar(int $minutos, array $p): array
    {
        $valorHora = (float)$p['valor_hora'];
        $fijo = !empty($p['pago_fijo']);
        // Pago fijo: se paga el valor que escribió el administrador, sin importar las horas
        $pagoHoras = $fijo ? (int)round((float)($p['valor_fijo'] ?? 0)) : (int)round($minutos * $valorHora / 60);
        $bono = (float)$p['bono_valor'];
        $descuentos = (float)$p['desc_comida'] + (float)$p['desc_adelanto'] + (float)$p['desc_otros'];
        $totalQuincena = $pagoHoras + $bono;
        return [
            'minutos'        => $minutos,
            'horas'          => intdiv($minutos, 60),
            'min_resto'      => $minutos % 60,
            'horas_dec'      => fmt_horas_dec($minutos),
            'valor_hora'     => $valorHora,
            'pago_fijo'      => $fijo,
            'valor_fijo'     => (float)($p['valor_fijo'] ?? 0),
            'liquidable'     => $fijo ? (float)($p['valor_fijo'] ?? 0) > 0 : $valorHora > 0,
            'pago_horas'     => $pagoHoras,
            'bono'           => $bono,
            'descuentos'     => $descuentos,
            'total_quincena' => $totalQuincena,
            'total_entregar' => $totalQuincena - $descuentos,
        ];
    }

    public static function calcular(array $planilla, ?array $jornadas = null): array
    {
        $jornadas ??= self::jornadas($planilla);
        $min = 0;
        $pendientes = 0;
        foreach ($jornadas as $j) {
            if ($j['estado'] === 'completa') {
                $min += (int)$j['minutos_trabajados'];
            } elseif (in_array($j['estado'], ['abierta', 'incompleta'], true)) {
                $pendientes++;
            }
        }
        return self::liquidar($min, $planilla) + ['pendientes' => $pendientes, 'jornadas_total' => count($jornadas)];
    }

    /* ---------------- Consultas ---------------- */

    public static function buscar(int $id): ?array
    {
        return Database::one(
            'SELECT p.*, t.nombre, t.documento, t.color, t.activo AS trabajador_activo
             FROM planillas p JOIN trabajadores t ON t.id = p.trabajador_id WHERE p.id = ?',
            [$id]
        );
    }

    public static function delPeriodo(string $inicio): array
    {
        return Database::all(
            'SELECT p.*, t.nombre, t.documento, t.color, t.activo AS trabajador_activo
             FROM planillas p JOIN trabajadores t ON t.id = p.trabajador_id
             WHERE p.periodo_inicio = ? ORDER BY t.orden, t.nombre',
            [$inicio]
        );
    }

    /** Lista de quincenas que tienen planillas, de la más reciente a la más antigua */
    public static function periodos(): array
    {
        return array_map(
            fn($r) => self::periodoDe($r['periodo_inicio']),
            Database::all('SELECT DISTINCT periodo_inicio FROM planillas ORDER BY periodo_inicio DESC')
        );
    }

    /** ¿La fecha de ese trabajador cae en una planilla ya pagada? */
    public static function fechaPagada(int $trabajadorId, string $fecha): bool
    {
        return (bool)Database::value(
            "SELECT 1 FROM planillas WHERE trabajador_id = ? AND ? BETWEEN periodo_inicio AND periodo_fin AND estado = 'pagada'",
            [$trabajadorId, substr($fecha, 0, 10)]
        );
    }

    /* ---------------- Cambios del administrador ---------------- */

    private static function dinero($v, string $campo): float
    {
        $v = str_replace(['$', ' '], '', trim((string)$v));
        if (preg_match('/^\d{1,3}(\.\d{3})+(,\d+)?$/', $v)) {      // "61.750" o "1.200,50"
            $v = str_replace('.', '', $v);
        }
        $v = str_replace(',', '.', $v);                            // decimales con coma
        if ($v === '') {
            return 0.0;
        }
        if (!is_numeric($v) || (float)$v < 0 || (float)$v > 100000000) {
            throw new InvalidArgumentException("El valor de \"$campo\" no es válido.");
        }
        return round((float)$v, 2);
    }

    public static function guardar(int $id, array $datos): void
    {
        $p = self::buscar($id) ?? throw new InvalidArgumentException('La planilla no existe.');
        if ($p['estado'] === 'pagada') {
            throw new InvalidArgumentException('La planilla ya está pagada. Reábrela para hacer cambios.');
        }
        $nuevo = [
            'valor_hora'       => self::dinero($datos['valor_hora'] ?? 0, 'Valor hora'),
            'pago_fijo'        => !empty($datos['pago_fijo']) && $datos['pago_fijo'] !== '0' ? 1 : 0,
            'valor_fijo'       => self::dinero($datos['valor_fijo'] ?? 0, 'Pago fijo'),
            'bono_descripcion' => mb_substr(trim((string)($datos['bono_descripcion'] ?? '')), 0, 120) ?: null,
            'bono_valor'       => self::dinero($datos['bono_valor'] ?? 0, 'Bono extra'),
            'desc_comida'      => self::dinero($datos['desc_comida'] ?? 0, 'Comida'),
            'desc_adelanto'    => self::dinero($datos['desc_adelanto'] ?? 0, 'Adelanto en efectivo'),
            'desc_otros'       => self::dinero($datos['desc_otros'] ?? 0, 'Otros'),
            'observaciones'    => mb_substr(trim((string)($datos['observaciones'] ?? '')), 0, 1000) ?: null,
        ];
        $campos = array_keys($nuevo);
        $antes = array_intersect_key($p, array_flip($campos));
        $cambios = [];
        foreach ($nuevo as $k => $v) {
            if ((string)$antes[$k] !== (string)$v && !(is_numeric($antes[$k]) && is_numeric($v) && (float)$antes[$k] === (float)$v)) {
                $cambios[$k] = $v;
            }
        }
        if ($nuevo['pago_fijo'] && $nuevo['valor_fijo'] <= 0) {
            throw new InvalidArgumentException('Escribe cuánto se le paga a este trabajador en la quincena.');
        }
        if (!$cambios) {
            return;
        }
        Database::update('planillas', $nuevo + ['actualizado' => now_utc()], 'id = :id', ['id' => $id]);
        // El valor de la hora queda como predeterminado para las siguientes quincenas
        if (isset($cambios['valor_hora'])) {
            Database::update('trabajadores', ['valor_hora' => $nuevo['valor_hora'], 'actualizado' => now_utc()], 'id = :id', ['id' => $p['trabajador_id']]);
            // y se aplica a la quincena actual si sigue abierta y aún no tenía valor
            Database::run(
                "UPDATE planillas SET valor_hora = ? WHERE trabajador_id = ? AND estado = 'abierta' AND valor_hora = 0 AND id <> ?",
                [$nuevo['valor_hora'], $p['trabajador_id'], $id]
            );
        }
        // La forma de pago (fijo o por horas) queda guardada para las siguientes quincenas
        if (isset($cambios['pago_fijo']) || isset($cambios['valor_fijo'])) {
            Database::update('trabajadores', [
                'pago_fijo' => $nuevo['pago_fijo'], 'valor_fijo' => $nuevo['valor_fijo'] ?: null, 'actualizado' => now_utc(),
            ], 'id = :id', ['id' => $p['trabajador_id']]);
            Database::run(
                "UPDATE planillas SET pago_fijo = ?, valor_fijo = ? WHERE trabajador_id = ? AND estado = 'abierta' AND id <> ? AND periodo_inicio > ?",
                [$nuevo['pago_fijo'], $nuevo['valor_fijo'], $p['trabajador_id'], $id, $p['periodo_inicio']]
            );
        }
        Audit::log('planilla_editada', 'planilla', $id, null,
            array_intersect_key($antes, $cambios), $cambios);
    }

    public static function pagar(int $id): void
    {
        $p = self::buscar($id) ?? throw new InvalidArgumentException('La planilla no existe.');
        if ($p['estado'] === 'pagada') {
            return;
        }
        if ($p['estado'] !== 'cerrada') {
            throw new InvalidArgumentException('La quincena todavía no ha terminado. Se puede marcar como pagada después del cierre.');
        }
        $c = self::calcular($p);
        if ($c['pendientes'] > 0) {
            throw new InvalidArgumentException('Hay ' . $c['pendientes'] . ' jornada(s) sin salida. Corrígelas en Asistencia antes de pagar.');
        }
        if ($c['pago_fijo'] && $c['valor_fijo'] <= 0) {
            throw new InvalidArgumentException('Escribe el valor del pago fijo antes de marcar como pagada.');
        }
        if (!$c['pago_fijo'] && (float)$p['valor_hora'] <= 0 && $c['minutos'] > 0) {
            throw new InvalidArgumentException('Escribe el valor de la hora antes de marcar como pagada.');
        }
        Database::update('planillas', [
            'estado' => 'pagada', 'pagada' => now_utc(), 'total_pagado' => $c['total_entregar'],
            'minutos_cierre' => $c['minutos'], 'actualizado' => now_utc(),
        ], 'id = :id', ['id' => $id]);
        Audit::log('planilla_pagada', 'planilla', $id, null, null, [
            'horas' => $c['horas_dec'], 'total_entregar' => $c['total_entregar'],
        ]);
    }

    public static function reabrir(int $id, string $motivo): void
    {
        $motivo = trim($motivo);
        if (mb_strlen($motivo) < 5) {
            throw new InvalidArgumentException('Escribe el motivo para reabrir la planilla (mínimo 5 caracteres).');
        }
        $p = self::buscar($id) ?? throw new InvalidArgumentException('La planilla no existe.');
        if ($p['estado'] !== 'pagada') {
            throw new InvalidArgumentException('Solo se reabren planillas pagadas.');
        }
        $estado = $p['periodo_fin'] < hoy_local() ? 'cerrada' : 'abierta';
        Database::update('planillas', ['estado' => $estado, 'pagada' => null, 'actualizado' => now_utc()], 'id = :id', ['id' => $id]);
        Audit::log('planilla_reabierta', 'planilla', $id, $motivo, ['estado' => 'pagada', 'total_pagado' => $p['total_pagado']], ['estado' => $estado]);
    }
}
