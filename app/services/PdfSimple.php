<?php
declare(strict_types=1);

/**
 * Generador de PDF mínimo, sin dependencias (funciona en InfinityFree).
 * Hojas A4, fuentes Helvetica estándar (acentos y ñ vía WinAnsi),
 * texto, líneas, rectángulos e imágenes JPEG. Medidas en milímetros
 * con el origen arriba a la izquierda.
 */
final class PdfSimple
{
    private const K = 72 / 25.4;          // mm → puntos
    private float $ancho = 210.0;
    private float $alto = 297.0;
    private array $paginas = [];          // contenido de cada página
    private array $imagenes = [];         // ruta => [n, w, h, datos, color]
    private int $actual = -1;
    private string $titulo;

    /** Anchos aproximados de Helvetica (milésimas de em) para alinear y recortar */
    private const ANCHOS = [
        ' ' => 278, '.' => 278, ',' => 278, ':' => 278, ';' => 278, '-' => 333, '(' => 333, ')' => 333, '/' => 278,
        '$' => 556, '%' => 889, '#' => 556, '+' => 584, '=' => 584, "'" => 191, '"' => 355, '!' => 278, '?' => 556,
        'i' => 222, 'j' => 222, 'l' => 222, 'f' => 278, 't' => 278, 'r' => 333, 'm' => 833, 'w' => 722,
        'I' => 278, 'J' => 500, 'M' => 833, 'W' => 944,
    ];

    public function __construct(string $titulo = 'Documento')
    {
        $this->titulo = $titulo;
    }

    public function agregarPagina(): void
    {
        $this->paginas[] = '';
        $this->actual = count($this->paginas) - 1;
    }

    public function altoPagina(): float
    {
        return $this->alto;
    }

    private function y(float $mm): float
    {
        return ($this->alto - $mm) * self::K;
    }

    private function n(float $v): string
    {
        return rtrim(rtrim(number_format($v, 3, '.', ''), '0'), '.') ?: '0';
    }

    private function emitir(string $s): void
    {
        $this->paginas[$this->actual] .= $s . "\n";
    }

    private static function codificar(string $texto): string
    {
        $t = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $texto);
        if ($t === false) {
            $t = mb_convert_encoding($texto, 'Windows-1252', 'UTF-8');
        }
        return strtr($t, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)', "\r" => '', "\n" => ' ']);
    }

    /** Ancho estimado del texto en mm */
    public function anchoTexto(string $texto, float $tam, bool $negrita = false): float
    {
        $w = 0;
        foreach (mb_str_split($texto) as $c) {
            if (isset(self::ANCHOS[$c])) {
                $w += self::ANCHOS[$c];
            } elseif (ctype_digit($c)) {
                $w += 556;
            } elseif (preg_match('/\p{Lu}/u', $c)) {
                $w += 667;
            } else {
                $w += 520;
            }
        }
        return $w / 1000 * $tam / self::K * ($negrita ? 1.06 : 1);
    }

    /** Recorta con "…" para que quepa en el ancho dado */
    public function recortar(string $texto, float $anchoMax, float $tam, bool $negrita = false): string
    {
        if ($this->anchoTexto($texto, $tam, $negrita) <= $anchoMax) {
            return $texto;
        }
        while (mb_strlen($texto) > 1 && $this->anchoTexto($texto . '...', $tam, $negrita) > $anchoMax) {
            $texto = mb_substr($texto, 0, -1);
        }
        return rtrim($texto) . '...';
    }

    /** Texto con la línea base en y (mm). $alin: L, C o R respecto a x (C/R usan $ancho) */
    public function texto(float $x, float $y, string $texto, float $tam = 10, bool $negrita = false, string $alin = 'L', float $ancho = 0, float $gris = 0): void
    {
        if ($texto === '') {
            return;
        }
        $w = $this->anchoTexto($texto, $tam, $negrita);
        if ($alin === 'C') {
            $x += ($ancho - $w) / 2;
        } elseif ($alin === 'R') {
            $x += $ancho - $w;
        }
        $color = $gris > 0 ? $this->n($gris) . ' g ' : '0 g ';
        $this->emitir(sprintf('BT %s/%s %s Tf %s %s Td (%s) Tj ET', $color, $negrita ? 'F2' : 'F1', $this->n($tam),
            $this->n($x * self::K), $this->n($this->y($y)), self::codificar($texto)));
    }

    public function linea(float $x1, float $y1, float $x2, float $y2, float $grosor = 0.2, bool $punteada = false): void
    {
        $this->emitir(sprintf('%s %s w %s %s m %s %s l S %s', $punteada ? '[1 1] 0 d' : '[] 0 d', $this->n($grosor * self::K),
            $this->n($x1 * self::K), $this->n($this->y($y1)), $this->n($x2 * self::K), $this->n($this->y($y2)), '[] 0 d'));
    }

    /** Rectángulo; $relleno en gris 0 (negro) a 1 (blanco), o un color [r,g,b] 0-1 */
    public function rect(float $x, float $y, float $w, float $h, $relleno = null, bool $borde = true, float $grosor = 0.2): void
    {
        $op = $borde ? ($relleno !== null ? 'B' : 'S') : 'f';
        $fill = '';
        if ($relleno !== null) {
            $fill = is_array($relleno)
                ? sprintf('%s %s %s rg ', $this->n($relleno[0]), $this->n($relleno[1]), $this->n($relleno[2]))
                : $this->n((float)$relleno) . ' g ';
        }
        $this->emitir(sprintf('%s%s w 0 G %s %s %s %s re %s 0 g', $fill, $this->n($grosor * self::K),
            $this->n($x * self::K), $this->n($this->y($y + $h)), $this->n($w * self::K), $this->n($h * self::K), $op));
    }

    /** Imagen JPEG dentro de la caja (mantiene proporción, centrada) */
    public function imagen(string $ruta, float $x, float $y, float $w, float $h): void
    {
        if (!is_file($ruta)) {
            return;
        }
        if (!isset($this->imagenes[$ruta])) {
            $info = @getimagesize($ruta);
            if (!$info || $info[2] !== IMAGETYPE_JPEG) {
                return;
            }
            $this->imagenes[$ruta] = [
                'n' => count($this->imagenes) + 1, 'w' => $info[0], 'h' => $info[1],
                'color' => ($info['channels'] ?? 3) === 1 ? 'DeviceGray' : (($info['channels'] ?? 3) === 4 ? 'DeviceCMYK' : 'DeviceRGB'),
                'datos' => file_get_contents($ruta),
            ];
        }
        $im = $this->imagenes[$ruta];
        $esc = min($w / $im['w'], $h / $im['h']);
        $dw = $im['w'] * $esc;
        $dh = $im['h'] * $esc;
        $dx = $x + ($w - $dw) / 2;
        $dy = $y + ($h - $dh) / 2;
        $this->emitir(sprintf('q %s 0 0 %s %s %s cm /Im%d Do Q', $this->n($dw * self::K), $this->n($dh * self::K),
            $this->n($dx * self::K), $this->n($this->y($dy + $dh)), $im['n']));
    }

    public function salida(): string
    {
        if (!$this->paginas) {
            $this->agregarPagina();
        }
        $objs = [];
        // 1 catálogo, 2 páginas, 3 F1, 4 F2, 5 info; luego imágenes, luego páginas
        $objs[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objs[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $objs[5] = sprintf('<< /Title (%s) /Producer (JOSTECH) /CreationDate (D:%s) >>', self::codificar($this->titulo), gmdate('YmdHis') . 'Z');
        $num = 6;
        $xobj = [];
        foreach ($this->imagenes as $im) {
            $objs[$num] = sprintf("<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /%s /BitsPerComponent 8 /Filter /DCTDecode /Length %d >>\nstream\n%s\nendstream",
                $im['w'], $im['h'], $im['color'], strlen($im['datos']), $im['datos']);
            $xobj[] = '/Im' . $im['n'] . ' ' . $num . ' 0 R';
            $num++;
        }
        $recursos = '<< /Font << /F1 3 0 R /F2 4 0 R >>' . ($xobj ? ' /XObject << ' . implode(' ', $xobj) . ' >>' : '') . ' >>';
        $kids = [];
        foreach ($this->paginas as $contenido) {
            $c = gzcompress($contenido);
            $objs[$num] = sprintf("<< /Length %d /Filter /FlateDecode >>\nstream\n%s\nendstream", strlen($c), $c);
            $objs[$num + 1] = sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %s %s] /Resources %s /Contents %d 0 R >>',
                $this->n($this->ancho * self::K), $this->n($this->alto * self::K), $recursos, $num);
            $kids[] = ($num + 1) . ' 0 R';
            $num += 2;
        }
        $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objs[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($kids) . ' >>';
        ksort($objs);

        $out = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objs as $i => $cuerpo) {
            $offsets[$i] = strlen($out);
            $out .= "$i 0 obj\n$cuerpo\nendobj\n";
        }
        $xref = strlen($out);
        $total = max(array_keys($objs)) + 1;
        $out .= "xref\n0 $total\n0000000000 65535 f \n";
        for ($i = 1; $i < $total; $i++) {
            $out .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        }
        $out .= "trailer\n<< /Size $total /Root 1 0 R /Info 5 0 R >>\nstartxref\n$xref\n%%EOF\n";
        return $out;
    }
}
