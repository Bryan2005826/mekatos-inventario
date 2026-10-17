<?php
declare(strict_types=1);

/**
 * Copia de seguridad descargable: base de datos completa (SQL) + archivos
 * privados (fotos de evidencia, fotos de utensilios y PDF de inventarios).
 * Restaurar: importar respaldo.sql en phpMyAdmin y subir la carpeta storage.
 */
final class RespaldoService
{
    private const TABLAS = [
        'configuracion', 'administradores', 'trabajadores', 'dispositivos', 'redes_autorizadas',
        'jornadas', 'marcaciones', 'intentos_rechazados', 'auditoria', 'planillas',
        'areas', 'inventario_items', 'inventarios', 'inventario_lineas',
    ];

    public static function volcadoSql(): string
    {
        $pdo = Database::pdo();
        $out = "-- Copia de seguridad · " . Settings::get('nombre_establecimiento') . "\n";
        $out .= '-- Generada: ' . (new DateTimeImmutable('now', app_tz()))->format('Y-m-d H:i:s') . " (hora del establecimiento)\n";
        $out .= "-- Restaurar: importar este archivo en phpMyAdmin sobre una base vacía.\n\n";
        $out .= "SET NAMES utf8mb4;\nSET time_zone = '+00:00';\nSET FOREIGN_KEY_CHECKS = 0;\n\n";
        foreach (self::TABLAS as $t) {
            if (!Database::value('SHOW TABLES LIKE ' . $pdo->quote($t))) {
                continue;
            }
            $crear = Database::one("SHOW CREATE TABLE `$t`");
            $out .= "DROP TABLE IF EXISTS `$t`;\n" . array_values($crear)[1] . ";\n\n";
            $st = $pdo->query("SELECT * FROM `$t`");
            $lote = [];
            $cols = null;
            while ($fila = $st->fetch(PDO::FETCH_ASSOC)) {
                $cols ??= array_keys($fila);
                $lote[] = '(' . implode(', ', array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string)$v), $fila)) . ')';
                if (count($lote) === 200) {
                    $out .= self::insert($t, $cols, $lote);
                    $lote = [];
                }
            }
            if ($lote) {
                $out .= self::insert($t, $cols, $lote);
            }
            $out .= "\n";
        }
        return $out . "SET FOREIGN_KEY_CHECKS = 1;\n";
    }

    private static function insert(string $tabla, array $cols, array $lote): string
    {
        return "INSERT INTO `$tabla` (`" . implode('`, `', $cols) . "`) VALUES\n" . implode(",\n", $lote) . ";\n";
    }

    /** Crea el ZIP en una carpeta temporal privada y devuelve su ruta */
    public static function crear(): string
    {
        $dir = STORAGE_PATH . '/tmp';
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        foreach (glob($dir . '/respaldo_*') ?: [] as $viejo) {
            if (filemtime($viejo) < time() - 3600) {
                @unlink($viejo);
            }
        }
        $sello = (new DateTimeImmutable('now', app_tz()))->format('Y-m-d_Hi');
        $sql = self::volcadoSql();
        if (!class_exists('ZipArchive')) {
            $ruta = "$dir/respaldo_$sello.sql";
            file_put_contents($ruta, $sql);
            return $ruta;
        }
        $ruta = "$dir/respaldo_$sello.zip";
        $zip = new ZipArchive();
        if ($zip->open($ruta, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el archivo de respaldo.');
        }
        $zip->addFromString('respaldo.sql', $sql);
        $zip->addFromString('LEEME.txt', "Para restaurar:\n1. En phpMyAdmin, importa respaldo.sql.\n2. Sube la carpeta storage/ a tu hosting (reemplazando la actual).\n");
        foreach (['fotos', 'items', 'inventarios'] as $sub) {
            $base = STORAGE_PATH . "/$sub";
            if (!is_dir($base)) {
                continue;
            }
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if ($f->isFile() && preg_match('/\.(jpg|pdf)$/', $f->getFilename())) {
                    $zip->addFile($f->getPathname(), "storage/$sub/" . substr($f->getPathname(), strlen($base) + 1));
                }
            }
        }
        $zip->close();
        return $ruta;
    }
}
