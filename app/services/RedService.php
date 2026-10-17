<?php
declare(strict_types=1);

/** Validación opcional de la IP pública de la conexión a internet del local */
final class RedService
{
    public static function activa(): bool
    {
        return Settings::bool('validar_red');
    }

    public static function ipAutorizada(string $ip): bool
    {
        return (bool)Database::value('SELECT 1 FROM redes_autorizadas WHERE ip = ? AND activo = 1', [$ip]);
    }

    /** @return array{ok:bool, codigo:string} */
    public static function validar(): array
    {
        if (!self::activa()) {
            return ['ok' => true, 'codigo' => 'red_no_exigida'];
        }
        return self::ipAutorizada(client_ip())
            ? ['ok' => true, 'codigo' => 'red_ok']
            : ['ok' => false, 'codigo' => 'red_no_autorizada'];
    }
}
