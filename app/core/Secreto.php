<?php
declare(strict_types=1);

/** Cifra contraseñas de servicios externos (SMTP, API) antes de guardarlas en la base de datos */
final class Secreto
{
    private static function clave(): string
    {
        return hash('sha256', 'secretos|' . (string)config('app_key'), true);
    }

    public static function cifrar(string $texto): string
    {
        if ($texto === '') {
            return '';
        }
        $iv = random_bytes(12);
        $tag = '';
        $c = openssl_encrypt($texto, 'aes-256-gcm', self::clave(), OPENSSL_RAW_DATA, $iv, $tag);
        return 'v1:' . base64_encode($iv . $tag . $c);
    }

    public static function descifrar(string $guardado): string
    {
        if (!str_starts_with($guardado, 'v1:')) {
            return '';
        }
        $b = base64_decode(substr($guardado, 3), true);
        if ($b === false || strlen($b) < 29) {
            return '';
        }
        $r = openssl_decrypt(substr($b, 28), 'aes-256-gcm', self::clave(), OPENSSL_RAW_DATA, substr($b, 0, 12), substr($b, 12, 16));
        return $r === false ? '' : $r;
    }
}
