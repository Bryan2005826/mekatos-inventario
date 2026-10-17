<?php
declare(strict_types=1);

/** Bloqueo temporal tras varios intentos fallidos */
final class RateLimiter
{
    public static function tooMany(string $key, int $max, int $minutes): bool
    {
        $since = gmdate('Y-m-d H:i:s', time() - $minutes * 60);
        $n = (int)Database::value('SELECT COUNT(*) FROM limites_acceso WHERE clave = ? AND creado >= ?', [$key, $since]);
        return $n >= $max;
    }

    /** Minutos que faltan para que se libere el bloqueo */
    public static function minutesLeft(string $key, int $max, int $minutes): int
    {
        $since = gmdate('Y-m-d H:i:s', time() - $minutes * 60);
        $rows = Database::all(
            'SELECT creado FROM limites_acceso WHERE clave = ? AND creado >= ? ORDER BY creado DESC LIMIT ' . (int)$max,
            [$key, $since]
        );
        if (count($rows) < $max) {
            return 0;
        }
        $oldest = strtotime(end($rows)['creado'] . ' UTC');
        return max(1, (int)ceil(($oldest + $minutes * 60 - time()) / 60));
    }

    public static function hit(string $key): void
    {
        Database::insert('limites_acceso', ['clave' => $key, 'creado' => now_utc()]);
        // limpieza ocasional
        if (random_int(1, 50) === 1) {
            Database::run('DELETE FROM limites_acceso WHERE creado < ?', [gmdate('Y-m-d H:i:s', time() - 86400)]);
        }
    }

    public static function clear(string $key): void
    {
        Database::run('DELETE FROM limites_acceso WHERE clave = ?', [$key]);
    }
}
