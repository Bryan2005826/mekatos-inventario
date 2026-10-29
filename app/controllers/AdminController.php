<?php
declare(strict_types=1);

final class AdminController
{
    public function loginForm(): void
    {
        if (Auth::admin()) {
            redirect('admin');
        }
        view('admin/login', ['establecimiento' => Settings::get('nombre_establecimiento')], 'plain');
    }

    public function login(): void
    {
        $r = Auth::attemptAdmin((string)input('usuario', ''), (string)($_POST['password'] ?? ''));
        if (!$r['ok']) {
            flash('error', $r['error']);
            redirect('admin/login');
        }
        $volver = $_SESSION['_volver'] ?? '';
        unset($_SESSION['_volver']);
        redirect(str_starts_with((string)$volver, 'admin') ? $volver : 'admin');
    }

    public function salir(): void
    {
        Auth::logoutAdmin();
        redirect('');
    }

    public function resumen(): void
    {
        Auth::requireAdmin();
        InventarioService::asegurar();
        PlanillaService::sincronizar();          // cierre automático de quincena
        try {
            NotificacionService::procesarPendientes();
        } catch (Throwable $e) {
            error_log('[asistencia] cola de correo: ' . $e->getMessage());
        }
        $loc = LocalService::actualId();
        view('admin/resumen', AsistenciaService::resumenDia($loc) + [
            'tablero' => InventarioService::tablero(hoy_local(), $loc),
            'alertasStock' => InventarioService::alertasStock($loc),
            'ultimosInv' => InventarioService::historial(['desde' => (new DateTimeImmutable('now', app_tz()))->modify('-3 days')->format('Y-m-d'), 'hasta' => hoy_local(), 'local_id' => $loc]),
            'dispositivos' => (int)Database::value('SELECT COUNT(*) FROM dispositivos WHERE activo = 1 AND token_hash IS NOT NULL AND local_id = ?', [$loc]),
            'local' => LocalService::actual(),
            'seccion' => 'resumen',
        ]);
    }

    /** Cambia el local que se está viendo en el panel */
    public function local(): void
    {
        Auth::requireAdmin();
        LocalService::cambiar((int)input('id'));
        $volver = (string)input('volver', 'admin');
        // vistas de un registro puntual (una planilla, una jornada...) vuelven a su listado
        $volver = preg_replace('#/(ver|editar|jornada|nueva)$#', '', $volver);
        redirect(str_starts_with($volver, 'admin') ? $volver : 'admin');
    }

    public function ayuda(): void
    {
        Auth::requireAdmin();
        view('admin/ayuda', ['seccion' => 'ayuda']);
    }

    public function cuenta(): void
    {
        $a = Auth::requireAdmin();
        view('admin/cuenta', ['admin' => $a, 'seccion' => 'cuenta']);
    }

    public function guardarCuenta(): void
    {
        $a = Auth::requireAdmin();
        $actual = (string)($_POST['actual'] ?? '');
        $nueva = (string)($_POST['nueva'] ?? '');
        $repetir = (string)($_POST['repetir'] ?? '');
        $row = Database::one('SELECT password_hash FROM administradores WHERE id = ?', [$a['id']]);
        if (!$row || !password_verify($actual, $row['password_hash'])) {
            flash('error', 'La contraseña actual no es correcta.');
            redirect('admin/cuenta');
        }
        if (mb_strlen($nueva) < 10) {
            flash('error', 'La nueva contraseña debe tener al menos 10 caracteres.');
            redirect('admin/cuenta');
        }
        if ($nueva !== $repetir) {
            flash('error', 'Las contraseñas nuevas no coinciden.');
            redirect('admin/cuenta');
        }
        Database::update('administradores', ['password_hash' => password_hash($nueva, PASSWORD_DEFAULT)], 'id = :id', ['id' => $a['id']]);
        Audit::log('admin_cambio_password', 'administrador', (int)$a['id']);
        Session::regenerate();
        flash('ok', 'Contraseña actualizada.');
        redirect('admin/cuenta');
    }
}
