<?php
declare(strict_types=1);

/** Validación opcional de la IP pública de la conexión a internet del local */
final class RedService
{
    public static function activa(): bool
    {
        return Settings::bool('validar_red');
    }

    public static function ipAutorizada(string $ip, ?int $localId = null): bool
    {
        return $localId
            ? (bool)Database::value('SELECT 1 FROM redes_autorizadas WHERE ip = ? AND activo = 1 AND local_id = ?', [$ip, $localId])
            : (bool)Database::value('SELECT 1 FROM redes_autorizadas WHERE ip = ? AND activo = 1', [$ip]);
    }

    /** @return array{ok:bool, codigo:string} */
    public static function validar(?int $localId = null): array
    {
        if (!self::activa()) {
            return ['ok' => true, 'codigo' => 'red_no_exigida'];
        }
        return self::ipAutorizada(client_ip(), $localId)
            ? ['ok' => true, 'codigo' => 'red_ok']
            : ['ok' => false, 'codigo' => 'red_no_autorizada'];
    }
}
