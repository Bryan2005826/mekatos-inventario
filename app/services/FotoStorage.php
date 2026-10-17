<?php
declare(strict_types=1);

/**
 * Fotos de evidencia de las marcaciones. Se guardan en una carpeta privada
 * (fuera de la carpeta pública) y solo el administrador puede verlas.
 */
final class FotoStorage
{
    private const MAX_BYTES = 600_000;

    public static function dir(): string
    {
        $dir = STORAGE_PATH . '/fotos';
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        return $dir;
    }

    /**
     * Valida y guarda una imagen enviada como data URL (JPEG/WebP desde el canvas).
     * La imagen se re-codifica con GD para eliminar cualquier contenido no gráfico.
     * @return string nombre del archivo
     */
    public static function guardarDataUrl(string $dataUrl, int $trabajadorId, string $tipo): string
    {
        if (!preg_match('#^data:image/(jpeg|webp|png);base64,([A-Za-z0-9+/=]+)$#', $dataUrl, $m)) {
            throw new RuntimeException('La foto no tiene un formato válido.');
        }
        $bin = base64_decode($m[2], true);
        if ($bin === false || strlen($bin) < 2000) {
            throw new RuntimeException('La foto está vacía o es demasiado pequeña.');
        }
        if (strlen($bin) > self::MAX_BYTES) {
            throw new RuntimeException('La foto es demasiado grande.');
        }
        $img = @imagecreatefromstring($bin);
        if (!$img) {
            throw new RuntimeException('No se pudo leer la foto.');
        }
        $w = imagesx($img);
        $h = imagesy($img);
        if ($w < 120 || $h < 120) {
            imagedestroy($img);
            throw new RuntimeException('La foto tiene una resolución muy baja.');
        }
        // Escalar a máximo 640 px de ancho
        if ($w > 640) {
            $nh = (int)round($h * 640 / $w);
            $res = imagescale($img, 640, $nh);
            imagedestroy($img);
            $img = $res;
        }
        $local = new DateTimeImmutable('now', app_tz());
        $sub = $local->format('Y/m');
        $dir = self::dir() . '/' . $sub;
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        $nombre = $sub . '/' . $local->format('Ymd_His') . "_t{$trabajadorId}_{$tipo}_" . bin2hex(random_bytes(4)) . '.jpg';
        if (!imagejpeg($img, self::dir() . '/' . $nombre, 78)) {
            imagedestroy($img);
            throw new RuntimeException('No se pudo guardar la foto en el servidor.');
        }
        imagedestroy($img);
        return $nombre;
    }

    public static function ruta(string $nombre): ?string
    {
        if (!preg_match('#^\d{4}/\d{2}/[A-Za-z0-9_]+\.jpg$#', $nombre)) {
            return null;
        }
        $p = self::dir() . '/' . $nombre;
        return is_file($p) ? $p : null;
    }

    /** Elimina fotos más antiguas que la política de conservación. Devuelve cuántas borró. */
    public static function purgar(int $dias): int
    {
        if ($dias <= 0) {
            return 0;
        }
        $limite = gmdate('Y-m-d H:i:s', time() - $dias * 86400);
        $rows = Database::all('SELECT id, foto FROM marcaciones WHERE foto IS NOT NULL AND fecha_hora < ?', [$limite]);
        $n = 0;
        foreach ($rows as $r) {
            $p = self::ruta($r['foto']);
            if ($p) {
                @unlink($p);
            }
            Database::update('marcaciones', ['foto' => null], 'id = :id', ['id' => $r['id']]);
            $n++;
        }
        return $n;
    }
}
