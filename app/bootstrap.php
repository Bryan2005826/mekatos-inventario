<?php
declare(strict_types=1);

define('APP_PATH', __DIR__);
define('ROOT_PATH', dirname(__DIR__));
define('APP_VERSION', '1.0.0-etapa1');

$configFile = APP_PATH . '/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit('Falta app/config.php. Copia app/config.example.php como app/config.php y completa los datos.');
}
$GLOBALS['config'] = require $configFile;

if (!empty($GLOBALS['config']['debug'])) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

date_default_timezone_set('UTC'); // todo se calcula en UTC; se convierte al mostrar

define('STORAGE_PATH', rtrim($GLOBALS['config']['storage_path'] ?? (ROOT_PATH . '/storage'), '/'));

spl_autoload_register(function (string $class): void {
    foreach (['core', 'services', 'controllers'] as $dir) {
        $file = APP_PATH . "/$dir/$class.php";
        if (is_file($file)) {
            require $file;
            return;
        }
    }
});

require APP_PATH . '/core/helpers.php';
