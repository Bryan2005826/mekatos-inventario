<?php
declare(strict_types=1);

// Ubica la carpeta "app": junto a esta carpeta (recomendado) o dentro de ella.
$appDir = is_dir(dirname(__DIR__) . '/app') ? dirname(__DIR__) . '/app' : __DIR__ . '/app';
require $appDir . '/bootstrap.php';

// Encabezados de seguridad
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header('Permissions-Policy: camera=(self), geolocation=(), microphone=()');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; script-src 'self'; connect-src 'self'; media-src 'self' blob:; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
if (is_https()) {
    header('Strict-Transport-Security: max-age=31536000');
}

Session::start();

// Ruta: ?r=... o la parte de la URL después de la carpeta base
$route = $_GET['r'] ?? null;
if ($route === null) {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $basePath = rtrim(parse_url(base_url(), PHP_URL_PATH) ?: '', '/');
    if ($basePath && str_starts_with($path, $basePath)) {
        $path = substr($path, strlen($basePath));
    }
    $route = $path;
}
$route = trim((string)$route, '/');
if ($route === 'index.php') {
    $route = '';
}
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$GLOBALS['route'] = $route;

$routes = require APP_PATH . '/routes.php';

// Antes de instalar, todo redirige al instalador
if ($route !== 'instalar' && !str_starts_with($route, 'assets') && Settings::get('instalado') !== '1') {
    redirect('instalar');
}

$handler = $routes[$method . ' ' . $route] ?? null;
if (!$handler) {
    abort(404, 'Revisa la dirección o vuelve al inicio.');
}

try {
    if ($method === 'POST') {
        Csrf::check();
    }
    [$class, $fn] = $handler;
    (new $class())->$fn();
} catch (Throwable $e) {
    error_log('[asistencia] ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
    if (str_starts_with($route, 'api/')) {
        json_response(['ok' => false, 'error' => 'Ocurrió un error en el servidor. Inténtalo de nuevo.'] + (config('debug') ? ['debug' => $e->getMessage()] : []), 500);
    }
    abort(500, config('debug') ? $e->getMessage() : 'Ocurrió un error inesperado. Inténtalo de nuevo.');
}
