<?php
declare(strict_types=1);

final class InstalarController
{
    private function instalado(): bool
    {
        try {
            return Settings::get('instalado') === '1'
                && (int)Database::value('SELECT COUNT(*) FROM administradores') > 0;
        } catch (Throwable) {
            return false;
        }
    }

    private function revisar(): array
    {
        $c = [];
        $c[] = ['PHP 8.1 o superior', version_compare(PHP_VERSION, '8.1.0', '>='), 'Versión actual: ' . PHP_VERSION];
        $c[] = ['Extensión PDO MySQL', extension_loaded('pdo_mysql'), 'Necesaria para la base de datos.'];
        $c[] = ['Extensión GD', extension_loaded('gd'), 'Necesaria para procesar las fotos de evidencia.'];
        $key = (string)config('app_key');
        $c[] = ['Clave secreta (app_key)', strlen($key) >= 32 && !str_contains($key, 'CAMBIAR'), 'Edita app/config.php y pon una clave aleatoria de 32 caracteres o más.'];
        $db = false;
        $tablas = false;
        $msg = '';
        try {
            Database::pdo();
            $db = true;
            // Crea las tablas que falten (todas usan CREATE TABLE IF NOT EXISTS)
            $schema = APP_PATH . '/schema.sql';
            if (!is_file($schema)) {
                $schema = ROOT_PATH . '/database/schema.sql';
            }
            if (is_file($schema)) {
                Database::ejecutarScript($schema);
            }
            $tablas = (bool)Database::value("SHOW TABLES LIKE 'marcaciones'") && (bool)Database::value("SHOW TABLES LIKE 'planillas'");
        } catch (Throwable $e) {
            $msg = 'No se pudo conectar: revisa los datos en app/config.php.' . (config('debug') ? ' (' . $e->getMessage() . ')' : '');
        }
        $c[] = ['Conexión a MySQL', $db, $msg ?: 'Conectado.'];
        $c[] = ['Tablas creadas', $tablas, $tablas ? 'Listo.' : 'No se pudieron crear. Importa schema.sql en phpMyAdmin.'];
        $dir = STORAGE_PATH;
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        $c[] = ['Carpeta privada de archivos', is_dir($dir) && is_writable($dir), 'Ruta: ' . $dir];
        $c[] = ['Conexión segura (HTTPS)', is_https(), is_https() ? 'Activa.' : 'La cámara del kiosco solo funciona con HTTPS. En InfinityFree activa el SSL gratuito.'];
        return $c;
    }

    public function form(): void
    {
        if ($this->instalado()) {
            abort(404, 'El sistema ya está instalado.');
        }
        $checks = $this->revisar();
        $listo = true;
        foreach ($checks as $i => [$n, $ok]) {
            if (!$ok && $n !== 'Conexión segura (HTTPS)') {
                $listo = false;
            }
        }
        $resultado = $_SESSION['_instalacion'] ?? null;
        unset($_SESSION['_instalacion']);
        view('instalar', ['checks' => $checks, 'listo' => $listo, 'resultado' => $resultado], 'plain');
    }

    public function instalar(): void
    {
        if ($this->instalado()) {
            abort(404, 'El sistema ya está instalado.');
        }
        $usuario = trim((string)input('usuario', ''));
        $nombre = trim((string)input('nombre', ''));
        $pass = (string)($_POST['password'] ?? '');
        $establecimiento = trim((string)input('establecimiento', ''));
        $errores = [];
        if (!preg_match('/^[a-zA-Z0-9._-]{3,40}$/', $usuario)) {
            $errores[] = 'El usuario debe tener entre 3 y 40 letras, números, punto o guion.';
        }
        if (mb_strlen($nombre) < 2) {
            $errores[] = 'Escribe el nombre del administrador.';
        }
        if (mb_strlen($pass) < 10) {
            $errores[] = 'La contraseña debe tener al menos 10 caracteres.';
        }
        if ($pass !== (string)($_POST['password2'] ?? '')) {
            $errores[] = 'Las contraseñas no coinciden.';
        }
        if ($errores) {
            foreach ($errores as $er) {
                flash('error', $er);
            }
            redirect('instalar');
        }

        $pins = [];
        Database::transaction(function () use ($usuario, $nombre, $pass, $establecimiento, &$pins) {
            foreach (Settings::DEFAULTS as $k => $v) {
                Database::run('INSERT IGNORE INTO configuracion (clave, valor, actualizado) VALUES (?, ?, ?)', [$k, $v, now_utc()]);
            }
            Settings::flush();
            if ($establecimiento !== '') {
                Settings::set('nombre_establecimiento', mb_substr($establecimiento, 0, 120));
            }
            Database::insert('administradores', [
                'usuario' => $usuario, 'nombre' => $nombre,
                'password_hash' => password_hash($pass, PASSWORD_DEFAULT), 'activo' => 1, 'creado' => now_utc(),
            ]);
            $iniciales = array_filter(array_map('trim', explode(',', (string)input('trabajadores', ''))));
            foreach (array_slice($iniciales, 0, 50) as $n) {
                try {
                    $r = TrabajadorService::crear($n, null, null);
                    $pins[] = ['nombre' => $n, 'pin' => $r['pin']];
                } catch (InvalidArgumentException) {
                    // nombre inválido: se omite
                }
            }
            InventarioService::asegurar();   // áreas Cocina, Fríos y Caja con sus elementos iniciales
            Settings::set('instalado', '1');
            Audit::log('sistema_instalado', null, null, null, null, ['admin' => $usuario, 'trabajadores' => count($pins)]);
        });

        // La página de resultado se muestra una sola vez con los PIN
        view('instalado', ['pins' => $pins, 'usuario' => $usuario], 'plain');
    }
}
