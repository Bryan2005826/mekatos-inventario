<?php
declare(strict_types=1);

/**
 * Locales del negocio (Mekatos 1, Mekatos 2...). Cada local tiene sus trabajadores,
 * su celular de kiosco, su internet autorizado y sus categorías de inventario.
 */
final class LocalService
{
    public const INICIALES = ['Mekatos 1', 'Mekatos 2'];
    private static ?array $cache = null;

    /** Crea la tabla y las columnas local_id en instalaciones anteriores (una sola vez) */
    public static function asegurar(): void
    {
        static $hecho = false;
        if ($hecho) {
            return;
        }
        $hecho = true;
        if (Settings::get('locales_v') === '1') {
            return;
        }
        $pdo = Database::pdo();
        $pdo->exec("CREATE TABLE IF NOT EXISTS locales (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(60) NOT NULL,
            orden INT NOT NULL DEFAULT 0,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            creado DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        foreach (['trabajadores', 'dispositivos', 'redes_autorizadas', 'areas'] as $t) {
            if (Database::value("SHOW TABLES LIKE '$t'") && !Database::value("SHOW COLUMNS FROM `$t` LIKE 'local_id'")) {
                $pdo->exec("ALTER TABLE `$t` ADD local_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id, ADD KEY idx_local (local_id)");
            }
        }
        if (!(int)Database::value('SELECT COUNT(*) FROM locales')) {
            foreach (self::INICIALES as $i => $n) {
                Database::insert('locales', ['id' => $i + 1, 'nombre' => $n, 'orden' => $i + 1, 'activo' => 1, 'creado' => now_utc()]);
            }
        }
        Settings::set('locales_v', '1');
    }

    public static function todos(): array
    {
        return self::$cache ??= Database::all('SELECT * FROM locales WHERE activo = 1 ORDER BY orden, id');
    }

    public static function buscar(int $id): ?array
    {
        foreach (self::todos() as $l) {
            if ((int)$l['id'] === $id) {
                return $l;
            }
        }
        return null;
    }

    public static function nombre(int $id): string
    {
        return self::buscar($id)['nombre'] ?? 'Local ' . $id;
    }

    /** Local que el administrador está viendo en el panel */
    public static function actual(): array
    {
        $id = (int)($_SESSION['local_id'] ?? 0);
        return self::buscar($id) ?? self::todos()[0] ?? ['id' => 1, 'nombre' => 'Local'];
    }

    public static function actualId(): int
    {
        return (int)self::actual()['id'];
    }

    public static function cambiar(int $id): bool
    {
        if (!self::buscar($id)) {
            return false;
        }
        $_SESSION['local_id'] = $id;
        return true;
    }

    public static function flush(): void
    {
        self::$cache = null;
    }
}
