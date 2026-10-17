<?php
declare(strict_types=1);

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_name('jt_sesion');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => self::cookiePath(),
            'secure'   => is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_start();
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function cookiePath(): string
    {
        $path = parse_url(base_url(), PHP_URL_PATH) ?: '/';
        return rtrim($path, '/') . '/';
    }

    /** Cookie de larga duración (token del kiosco) */
    public static function setCookie(string $name, string $value, int $days): void
    {
        setcookie($name, $value, [
            'expires'  => time() + $days * 86400,
            'path'     => self::cookiePath(),
            'secure'   => is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[$name] = $value;
    }

    public static function clearCookie(string $name): void
    {
        setcookie($name, '', ['expires' => time() - 3600, 'path' => self::cookiePath()]);
        unset($_COOKIE[$name]);
    }
}
