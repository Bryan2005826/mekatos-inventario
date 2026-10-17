<?php
declare(strict_types=1);

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::token()) . '">';
    }

    public static function check(): void
    {
        $sent = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? (json_input()['_csrf'] ?? ''));
        if (!is_string($sent) || empty($_SESSION['_csrf']) || !hash_equals($_SESSION['_csrf'], $sent)) {
            if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') || !empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
                json_response(['ok' => false, 'error' => 'La sesión expiró. Recarga la página e inténtalo de nuevo.', 'csrf' => true], 403);
            }
            abort(403, 'La sesión expiró. Recarga la página e inténtalo de nuevo.', 'La sesión expiró');
        }
    }
}
