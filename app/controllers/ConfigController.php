<?php
declare(strict_types=1);

final class ConfigController
{
    public function dispositivos(): void
    {
        Auth::requireAdmin();
        $codigo = $_SESSION['_codigo'] ?? null;
        unset($_SESSION['_codigo']);
        view('admin/dispositivos', [
            'dispositivos' => Database::all('SELECT * FROM dispositivos WHERE local_id = ? ORDER BY activo DESC, creado DESC', [LocalService::actualId()]),
            'local'        => LocalService::actual(),
            'actual'       => DispositivoService::actual(),
            'codigo'       => $codigo,
            'seccion'      => 'dispositivos',
        ]);
    }

    public function codigo(): void
    {
        Auth::requireAdmin();
        $id = (int)input('id', 0) ?: null;
        $nombre = trim((string)input('nombre', ''));
        if (!$id && (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 60)) {
            flash('error', 'Escribe un nombre para el dispositivo, por ejemplo "Tablet de la entrada".');
            redirect('admin/dispositivos');
        }
        if ($id) {
            $d = Database::one('SELECT * FROM dispositivos WHERE id = ? AND activo = 1 AND local_id = ?', [$id, LocalService::actualId()]);
            if (!$d) {
                flash('error', 'El dispositivo no existe o fue revocado.');
                redirect('admin/dispositivos');
            }
            $nombre = $d['nombre'];
        }
        $r = DispositivoService::crearCodigo($nombre, $id, LocalService::actualId());
        Audit::log('kiosco_codigo_generado', 'dispositivo', $r['id'], null, null, ['nombre' => $nombre, 'local' => LocalService::actual()['nombre']]);
        $_SESSION['_codigo'] = $r + ['nombre' => $nombre];
        redirect('admin/dispositivos');
    }

    public function revocar(): void
    {
        Auth::requireAdmin();
        $id = (int)input('id');
        $d = Database::one('SELECT nombre FROM dispositivos WHERE id = ?', [$id]);
        if ($d) {
            DispositivoService::revocar($id);
            Audit::log('kiosco_revocado', 'dispositivo', $id, (string)input('motivo', '') ?: null, ['nombre' => $d['nombre']]);
            flash('ok', 'Dispositivo revocado. Ya no podrá registrar asistencia.');
        }
        redirect('admin/dispositivos');
    }

    public function index(): void
    {
        Auth::requireAdmin();
        view('admin/configuracion', [
            's'        => Settings::all(),
            'redes'    => Database::all('SELECT * FROM redes_autorizadas WHERE local_id = ? ORDER BY creado DESC', [LocalService::actualId()]),
            'local'    => LocalService::actual(),
            'ipActual' => client_ip(),
            'seccion'  => 'configuracion',
        ]);
    }

    public function guardar(): void
    {
        Auth::requireAdmin();
        $antes = Settings::all();
        $hora = fn($v) => preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string)$v) ? (string)$v : null;
        $num = fn($v, $min, $max) => is_numeric($v) && (int)$v >= $min && (int)$v <= $max ? (string)(int)$v : null;

        $campos = [
            'nombre_establecimiento'   => mb_substr(trim((string)input('nombre_establecimiento', '')), 0, 120) ?: null,
            'descanso_minutos'         => $num(input('descanso_minutos'), 0, 240),
            'descanso_umbral_minutos'  => $num((int)round((float)input('descanso_umbral_horas', 0) * 60), 0, 1440),
            'nocturna_inicio'          => $hora(input('nocturna_inicio')),
            'nocturna_fin'             => $hora(input('nocturna_fin')),
            'max_horas_jornada'        => $num(input('max_horas_jornada'), 4, 24),
            'min_minutos_entre_marcas' => $num(input('min_minutos_entre_marcas'), 0, 60),
            'pin_intentos_max'         => $num(input('pin_intentos_max'), 3, 10),
            'pin_bloqueo_minutos'      => $num(input('pin_bloqueo_minutos'), 1, 120),
            'retencion_fotos_dias'     => $num(input('retencion_fotos_dias'), 0, 3650),
            'valor_hora_defecto'       => $num(input('valor_hora_defecto') === '' ? 0 : input('valor_hora_defecto'), 0, 1000000),
            'mensaje_planilla'         => mb_substr(trim((string)input('mensaje_planilla', '')), 0, 160),
            'whatsapp_admin'           => self::numeroWhatsapp((string)input('whatsapp_admin', '')),
            'foto_obligatoria'         => input('foto_obligatoria') === '1' ? '1' : '0',
            'validar_red'              => input('validar_red') === '1' ? '1' : '0',
            'mensaje_informal'         => input('mensaje_informal') === '1' ? '1' : '0',
        ];
        $invalidos = array_keys(array_filter($campos, fn($v) => $v === null));
        if ($invalidos) {
            flash('error', 'Revisa los campos marcados: hay valores fuera de rango.');
            $_SESSION['_invalidos'] = $invalidos;
            redirect('admin/configuracion');
        }
        if ($campos['validar_red'] === '1' && !(int)Database::value('SELECT COUNT(*) FROM redes_autorizadas WHERE activo = 1')) {
            flash('error', 'Para exigir la red del local primero agrega al menos una IP autorizada.');
            redirect('admin/configuracion');
        }
        $cambios = [];
        foreach ($campos as $k => $v) {
            if (($antes[$k] ?? null) !== $v) {
                $cambios[$k] = ['antes' => $antes[$k] ?? null, 'despues' => $v];
                Settings::set($k, $v);
            }
        }
        if ($cambios) {
            Audit::log('configuracion_actualizada', 'configuracion', null, (string)input('motivo', '') ?: null,
                array_map(fn($c) => $c['antes'], $cambios), array_map(fn($c) => $c['despues'], $cambios));
        }
        flash('ok', $cambios ? 'Configuración guardada.' : 'No hubo cambios.');
        redirect('admin/configuracion');
    }

    public function agregarRed(): void
    {
        Auth::requireAdmin();
        $ip = trim((string)input('ip', ''));
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            flash('error', 'La IP no es válida.');
            redirect('admin/configuracion');
        }
        if (Database::value('SELECT 1 FROM redes_autorizadas WHERE ip = ?', [$ip])) {
            Database::update('redes_autorizadas', ['activo' => 1, 'local_id' => LocalService::actualId()], 'ip = :ip', ['ip' => $ip]);
        } else {
            Database::insert('redes_autorizadas', [
                'local_id' => LocalService::actualId(), 'ip' => $ip, 'descripcion' => mb_substr((string)input('descripcion', ''), 0, 120) ?: null, 'activo' => 1, 'creado' => now_utc(),
            ]);
        }
        Audit::log('red_autorizada', 'red', null, null, null, ['ip' => $ip, 'local' => LocalService::actual()['nombre']]);
        flash('ok', "IP $ip autorizada para " . LocalService::actual()['nombre'] . '.');
        redirect('admin/configuracion');
    }

    public function eliminarRed(): void
    {
        Auth::requireAdmin();
        $id = (int)input('id');
        $r = Database::one('SELECT * FROM redes_autorizadas WHERE id = ?', [$id]);
        if ($r) {
            Database::run('DELETE FROM redes_autorizadas WHERE id = ?', [$id]);
            Audit::log('red_eliminada', 'red', $id, null, ['ip' => $r['ip']]);
            if (Settings::bool('validar_red') && !(int)Database::value('SELECT COUNT(*) FROM redes_autorizadas WHERE activo = 1')) {
                Settings::set('validar_red', '0');
                flash('info', 'No quedan IP autorizadas, así que se desactivó la validación de red.');
            }
            flash('ok', 'IP eliminada.');
        }
        redirect('admin/configuracion');
    }

    public function purgarFotos(): void
    {
        Auth::requireAdmin();
        $dias = Settings::int('retencion_fotos_dias');
        $n = FotoStorage::purgar($dias);
        Audit::log('fotos_purgadas', 'marcacion', null, "Política de conservación: $dias días", null, ['eliminadas' => $n]);
        flash('ok', $n ? "Se eliminaron $n fotos con más de $dias días." : 'No había fotos para eliminar.');
        redirect('admin/configuracion');
    }

    public function auditoria(): void
    {
        Auth::requireAdmin();
        $pagina = max(1, (int)input('p', 1));
        $porPagina = 50;
        $total = (int)Database::value('SELECT COUNT(*) FROM auditoria');
        $rows = Database::all('SELECT * FROM auditoria ORDER BY creado DESC, id DESC LIMIT ' . $porPagina . ' OFFSET ' . (($pagina - 1) * $porPagina));
        view('admin/auditoria', [
            'rows' => $rows, 'pagina' => $pagina, 'paginas' => max(1, (int)ceil($total / $porPagina)), 'seccion' => 'auditoria',
        ]);
    }

    /** Número de WhatsApp con indicativo de país (Colombia por defecto si son 10 dígitos) */
    private static function numeroWhatsapp(string $n): ?string
    {
        $n = preg_replace('/\D/', '', $n);
        if ($n === '') {
            return '';
        }
        if (strlen($n) === 10 && $n[0] === '3') {
            $n = '57' . $n;
        }
        return (strlen($n) >= 11 && strlen($n) <= 15) ? $n : null;
    }
}
