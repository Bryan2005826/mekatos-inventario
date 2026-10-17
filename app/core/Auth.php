<?php
declare(strict_types=1);

final class Auth
{
    private const ADMIN_TIMEOUT = 7200;   // 2 h de inactividad
    private const WORKER_TIMEOUT = 180;   // 3 min: el trabajador marca y se cierra sola

    /* ---------------- Administrador ---------------- */

    public static function attemptAdmin(string $usuario, string $password): array
    {
        $ipKey = 'admin_ip:' . client_ip();
        $userKey = 'admin_user:' . mb_strtolower($usuario);
        if (RateLimiter::tooMany($ipKey, 10, 15) || RateLimiter::tooMany($userKey, 5, 15)) {
            $min = max(RateLimiter::minutesLeft($ipKey, 10, 15), RateLimiter::minutesLeft($userKey, 5, 15));
            Audit::rechazo('admin_login', 'Bloqueado por intentos repetidos');
            return ['ok' => false, 'error' => "Demasiados intentos. Espera $min minutos e inténtalo de nuevo."];
        }
        $admin = Database::one('SELECT * FROM administradores WHERE usuario = ? AND activo = 1', [$usuario]);
        // Se verifica un hash ficticio si no existe el usuario para no revelar cuáles existen por tiempo de respuesta
        $hash = $admin['password_hash'] ?? '$2y$10$sYKSY3hQCl3zOnpYgj9Pouv0WgNGD7Z4CblAx27CT/H9zv06VAV4i';
        if (!password_verify($password, $hash) || !$admin) {
            RateLimiter::hit($ipKey);
            RateLimiter::hit($userKey);
            Audit::rechazo('admin_login', 'Usuario o contraseña incorrectos (' . mb_substr($usuario, 0, 40) . ')');
            return ['ok' => false, 'error' => 'Usuario o contraseña incorrectos.'];
        }
        if (password_needs_rehash($admin['password_hash'], PASSWORD_DEFAULT)) {
            Database::update('administradores', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = :id', ['id' => $admin['id']]);
        }
        RateLimiter::clear($userKey);
        Session::regenerate();
        unset($_SESSION['worker']);
        $_SESSION['admin'] = ['id' => (int)$admin['id'], 'nombre' => $admin['nombre'], 'usuario' => $admin['usuario'], 't' => time()];
        Database::update('administradores', ['ultimo_acceso' => now_utc()], 'id = :id', ['id' => $admin['id']]);
        Audit::log('admin_login', 'administrador', (int)$admin['id']);
        return ['ok' => true];
    }

    public static function admin(): ?array
    {
        $a = $_SESSION['admin'] ?? null;
        if (!$a) {
            return null;
        }
        if (time() - $a['t'] > self::ADMIN_TIMEOUT) {
            unset($_SESSION['admin']);
            return null;
        }
        $_SESSION['admin']['t'] = time();
        return $a;
    }

    public static function requireAdmin(): array
    {
        $a = self::admin();
        if (!$a) {
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
                $_SESSION['_volver'] = $_GET['r'] ?? '';
            }
            flash('info', 'Ingresa con tu cuenta de administrador.');
            redirect('admin/login');
        }
        return $a;
    }

    public static function logoutAdmin(): void
    {
        if (self::admin()) {
            Audit::log('admin_logout', 'administrador', (int)$_SESSION['admin']['id']);
        }
        unset($_SESSION['admin']);
        Session::regenerate();
    }

    /* ---------------- Trabajador (kiosco) ---------------- */

    public static function workerKey(int $id): string
    {
        return 'pin_trab:' . $id;
    }

    public static function attemptWorker(int $trabajadorId, string $pin, ?int $dispositivoId): array
    {
        $max = Settings::int('pin_intentos_max') ?: 5;
        $mins = Settings::int('pin_bloqueo_minutos') ?: 15;
        $key = self::workerKey($trabajadorId);
        $ipKey = 'pin_ip:' . client_ip();

        $t = Database::one('SELECT id, nombre, pin_hash, consentimiento_datos FROM trabajadores WHERE id = ? AND activo = 1', [$trabajadorId]);
        if (!$t) {
            return ['ok' => false, 'error' => 'Trabajador no disponible.'];
        }
        if (RateLimiter::tooMany($key, $max, $mins) || RateLimiter::tooMany($ipKey, 30, 15)) {
            $left = RateLimiter::minutesLeft($key, $max, $mins);
            Audit::rechazo('login', 'PIN bloqueado por intentos repetidos', $trabajadorId, $dispositivoId);
            return ['ok' => false, 'error' => 'Demasiados intentos con PIN incorrecto. Espera ' . max(1, $left) . ' minutos o pide al administrador que te lo restablezca.'];
        }
        if (!preg_match('/^\d{6}$/', $pin) || !password_verify($pin, $t['pin_hash'])) {
            RateLimiter::hit($key);
            RateLimiter::hit($ipKey);
            Audit::rechazo('login', 'PIN incorrecto', $trabajadorId, $dispositivoId);
            $quedan = $max - (int)Database::value(
                'SELECT COUNT(*) FROM limites_acceso WHERE clave = ? AND creado >= ?',
                [$key, gmdate('Y-m-d H:i:s', time() - $mins * 60)]
            );
            return ['ok' => false, 'error' => $quedan > 0
                ? "PIN incorrecto. Te quedan $quedan intentos."
                : "PIN incorrecto. Acceso bloqueado por $mins minutos."];
        }
        RateLimiter::clear($key);
        Session::regenerate();
        unset($_SESSION['admin']);
        $_SESSION['worker'] = ['id' => (int)$t['id'], 'nombre' => $t['nombre'], 't' => time()];
        return ['ok' => true, 'trabajador' => $t];
    }

    public static function kioscoWorker(): ?array
    {
        $w = $_SESSION['worker'] ?? null;
        if (!$w) {
            return null;
        }
        if (time() - $w['t'] > self::WORKER_TIMEOUT) {
            unset($_SESSION['worker']);
            return null;
        }
        return $w;
    }

    public static function touchWorker(): void
    {
        if (isset($_SESSION['worker'])) {
            $_SESSION['worker']['t'] = time();
        }
    }

    public static function logoutWorker(): void
    {
        unset($_SESSION['worker']);
    }
}
