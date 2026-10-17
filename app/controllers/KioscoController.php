<?php
declare(strict_types=1);

final class KioscoController
{
    public function inicio(): void
    {
        Auth::logoutWorker();
        view('kiosco/inicio', [
            'trabajadores' => TrabajadorService::activos(),
            'dispositivo'  => DispositivoService::actual(),
            'establecimiento' => Settings::get('nombre_establecimiento'),
            'fotoObligatoria' => Settings::bool('foto_obligatoria'),
        ], 'kiosco');
    }

    public function activarForm(): void
    {
        view('kiosco/activar', ['dispositivo' => DispositivoService::actual()], 'kiosco');
    }

    public function activar(): void
    {
        $r = DispositivoService::activar((string)input('codigo', ''));
        if ($r['ok']) {
            flash('ok', 'Este dispositivo quedó autorizado como "' . $r['dispositivo']['nombre'] . '". Ya se puede registrar asistencia aquí.');
            redirect('');
        }
        flash('error', $r['error']);
        redirect('activar');
    }

    /** Hora oficial del servidor (para el reloj del kiosco) */
    public function hora(): void
    {
        $d = new DateTimeImmutable('now', app_tz());
        json_response(['ok' => true, 'epoch' => time(), 'tz' => app_tz()->getName(), 'offset' => $d->getOffset()]);
    }

    public function login(): void
    {
        $in = json_input();
        $id = (int)($in['trabajador_id'] ?? 0);
        $pin = (string)($in['pin'] ?? '');
        $disp = DispositivoService::actual();
        $r = Auth::attemptWorker($id, $pin, $disp ? (int)$disp['id'] : null);
        if (!$r['ok']) {
            json_response(['ok' => false, 'error' => $r['error']], 401);
        }
        $t = $r['trabajador'];
        $estado = AsistenciaService::estado($id);
        json_response([
            'ok'             => true,
            'csrf'           => Csrf::token(),
            'nombre'         => $t['nombre'],
            'consentimiento' => $t['consentimiento_datos'] !== null,
            'puede_marcar'   => $disp !== null,
            'puede_entrada'  => $estado['puede_entrada'],
            'puede_salida'   => $estado['puede_salida'],
            'entrada_hora'   => $estado['entrada_hora'],
        ]);
    }

    private function requireWorker(): array
    {
        $w = Auth::kioscoWorker();
        if (!$w) {
            json_response(['ok' => false, 'error' => 'Tu sesión terminó. Vuelve a seleccionar tu nombre.', 'expirada' => true], 401);
        }
        Auth::touchWorker();
        return $w;
    }

    public function consentimiento(): void
    {
        $w = $this->requireWorker();
        TrabajadorService::registrarConsentimiento((int)$w['id']);
        json_response(['ok' => true]);
    }

    private function rechazo(array $w, string $tipo, string $motivoInterno, string $mensaje, ?int $dispId): never
    {
        Audit::rechazo($tipo, $motivoInterno, (int)$w['id'], $dispId);
        json_response(['ok' => false, 'error' => $mensaje], 422);
    }

    public function marcar(): void
    {
        $w = $this->requireWorker();
        $in = json_input();
        $tipo = (string)($in['tipo'] ?? '');
        $foto = isset($in['foto']) && is_string($in['foto']) && $in['foto'] !== '' ? $in['foto'] : null;
        if (!in_array($tipo, ['entrada', 'salida'], true)) {
            json_response(['ok' => false, 'error' => 'Tipo de registro no válido.'], 422);
        }

        $base = 'No fue posible validar que estés en el establecimiento.';
        if (Settings::bool('mensaje_informal')) {
            $base = '¡No seas deshonesto! Este registro solo se puede hacer desde el local.';
        }

        // 1. Dispositivo autorizado (kiosco del local)
        $disp = DispositivoService::actual();
        if (!$disp) {
            $this->rechazo($w, $tipo, 'Dispositivo no autorizado', "$base Motivo: este dispositivo no está autorizado como kiosco del local.", null);
        }
        $dispId = (int)$disp['id'];

        // 2. Red del local (si está activada)
        $red = RedService::validar();
        if (!$red['ok']) {
            $this->rechazo($w, $tipo, 'IP fuera de la red autorizada: ' . client_ip(),
                "$base Motivo: el kiosco no está conectado al internet del local. Revisa el Wi-Fi y vuelve a intentarlo.", $dispId);
        }

        // 3. Consentimiento y foto de evidencia
        $t = TrabajadorService::buscar((int)$w['id']);
        if (!$t || !(int)$t['activo']) {
            json_response(['ok' => false, 'error' => 'Trabajador no disponible.'], 422);
        }
        if (Settings::bool('foto_obligatoria')) {
            if ($t['consentimiento_datos'] === null) {
                json_response(['ok' => false, 'error' => 'Debes aceptar la autorización de tratamiento de datos antes de registrar.', 'requiere_consentimiento' => true], 422);
            }
            if (!$foto) {
                $this->rechazo($w, $tipo, 'Sin foto de evidencia',
                    'No se pudo tomar la foto de evidencia. Permite el uso de la cámara y vuelve a intentarlo.', $dispId);
            }
        }

        $verif = [
            'identidad' => 'pin_ok' . ($foto ? '+foto' : ''),
            'ubicacion' => 'dispositivo_ok+' . $red['codigo'],
        ];
        $r = AsistenciaService::marcar((int)$w['id'], $tipo, $dispId, $foto, $verif);
        if (!$r['ok']) {
            $this->rechazo($w, $tipo, $r['error'], $r['error'], $dispId);
        }
        Auth::logoutWorker();
        json_response($r);
    }

    public function historial(): void
    {
        $w = $this->requireWorker();
        $desde = (new DateTimeImmutable('now', app_tz()))->modify('-30 days')->format('Y-m-d');
        $rows = AsistenciaService::listar((int)$w['id'], $desde, hoy_local());
        $out = [];
        $total = 0;
        foreach ($rows as $j) {
            if ($j['estado'] === 'anulada') {
                continue;
            }
            $total += (int)$j['minutos_trabajados'];
            $out[] = [
                'fecha'     => fmt_fecha($j['fecha']),
                'entrada'   => fmt_hora($j['entrada']),
                'salida'    => fmt_hora($j['salida']),
                'trabajado' => $j['estado'] === 'completa' ? fmt_minutos((int)$j['minutos_trabajados']) : null,
                'estado'    => $j['estado'],
                'corregida' => (bool)$j['corregida'],
            ];
        }
        $per = PlanillaService::periodoActual();
        $minQ = (int)Database::value(
            "SELECT COALESCE(SUM(minutos_trabajados), 0) FROM jornadas WHERE trabajador_id = ? AND estado = 'completa' AND fecha BETWEEN ? AND ?",
            [(int)$w['id'], $per['inicio'], $per['fin']]
        );
        json_response(['ok' => true, 'jornadas' => $out, 'total' => fmt_minutos($total),
            'quincena' => $per['nombre'], 'quincena_total' => fmt_minutos($minQ) . ' (' . fmt_horas_dec($minQ) . ' horas)']);
    }

    public function salir(): void
    {
        Auth::logoutWorker();
        json_response(['ok' => true]);
    }
}
