<?php
declare(strict_types=1);

/** Inventario de loza y utensilios por áreas, con acceso por QR */
final class InventarioService
{
    public const TURNOS = ['mediodia' => 'Mediodía', 'noche' => 'Noche'];
    public const ESTADOS = ['bien' => 'Bien', 'novedad' => 'Con novedad', 'danado' => 'Dañado'];
    /** Por qué falta */
    public const MOTIVOS_FALTA = [
        'roto'      => 'Se rompió',
        'perdido'   => 'No aparece',
        'otra_area' => 'Está en otra área',
        'en_uso'    => 'En lavado o en uso',
        'otro'      => 'Otro motivo',
    ];
    /** Por qué sobra */
    public const MOTIVOS_SOBRA = [
        'aparecio'    => 'Apareció lo que faltaba',
        'de_otra_area'=> 'Lo trajeron de otra área',
        'nuevo'       => 'Llegó nuevo',
        'mal_conteo'  => 'El conteo anterior estaba mal',
        'otro_sobra'  => 'Otro motivo',
    ];
    public const MOTIVOS = self::MOTIVOS_FALTA + self::MOTIVOS_SOBRA;
    /** Nombre del autor: trabajador o administrador */
    private const AUTOR = "COALESCE(t.nombre, 'Administrador') AS trabajador";

    private const SEMILLA = [
        'Cocina'       => ['Plato grande', 'Plato mediano', 'Plato pequeño'],
        'Fríos'        => ['Vaso de 14 onzas', 'Vaso de vidrio'],
        'Caja o greca' => ['Pocillo pequeño', 'Pocillo mediano', 'Pocillo grande'],
    ];

    /** Crea las tablas si faltan (actualización desde etapas anteriores) y las áreas iniciales */
    public static function asegurar(): void
    {
        static $hecho = false;
        if ($hecho) {
            return;
        }
        $hecho = true;
        if (!Database::value("SHOW TABLES LIKE 'inventario_lineas'")) {
            $schema = is_file(APP_PATH . '/schema.sql') ? APP_PATH . '/schema.sql' : ROOT_PATH . '/database/schema.sql';
            Database::ejecutarScript($schema);
        }
        self::migrar();
        if ((int)Database::value('SELECT COUNT(*) FROM areas') === 0) {
            $orden = 0;
            foreach (self::SEMILLA as $area => $items) {
                $aid = Database::insert('areas', ['nombre' => $area, 'token' => self::nuevoToken(), 'orden' => ++$orden, 'activo' => 1, 'creado' => now_utc()]);
                foreach ($items as $i => $nombre) {
                    Database::insert('inventario_items', [
                        'area_id' => $aid, 'nombre' => $nombre, 'tipo' => 'cantidad', 'orden' => $i + 1,
                        'activo' => 1, 'creado' => now_utc(), 'actualizado' => now_utc(),
                    ]);
                }
            }
        }
    }

    /** Cambios de estructura para instalaciones anteriores (stock y faltantes) */
    private static function migrar(): void
    {
        $col = fn(string $t, string $c) => (bool)Database::value("SHOW COLUMNS FROM `$t` LIKE '$c'");
        $pdo = Database::pdo();
        if (!$col('inventarios', 'origen')) {
            $pdo->exec('ALTER TABLE inventarios MODIFY trabajador_id INT UNSIGNED NULL');
            $pdo->exec("ALTER TABLE inventarios ADD admin_id INT UNSIGNED NULL AFTER trabajador_id,
                        ADD origen ENUM('trabajador','admin') NOT NULL DEFAULT 'trabajador' AFTER admin_id,
                        ADD es_base TINYINT(1) NOT NULL DEFAULT 0 AFTER origen");
        }
        if (!$col('inventarios', 'motivos_pendientes')) {
            $pdo->exec('ALTER TABLE inventarios ADD motivos_pendientes TINYINT(1) NOT NULL DEFAULT 0 AFTER es_base');
        }
        if (!$col('inventario_lineas', 'motivo_faltante')) {
            $pdo->exec('ALTER TABLE inventario_lineas ADD motivo_faltante VARCHAR(20) NULL AFTER observacion,
                        ADD es_base TINYINT(1) NOT NULL DEFAULT 0 AFTER motivo_faltante');
        }
        if (!Database::value("SHOW TABLES LIKE 'stock_movimientos'")) {
            $schema = is_file(APP_PATH . '/schema.sql') ? APP_PATH . '/schema.sql' : ROOT_PATH . '/database/schema.sql';
            Database::ejecutarScript($schema);
        }
    }

    public static function nuevoToken(): string
    {
        return bin2hex(random_bytes(12));
    }

    /* ---------------- Áreas e ítems ---------------- */

    public static function areas(bool $soloActivas = false): array
    {
        return Database::all('SELECT * FROM areas' . ($soloActivas ? ' WHERE activo = 1' : '') . ' ORDER BY orden, nombre');
    }

    public static function area(int $id): ?array
    {
        return Database::one('SELECT * FROM areas WHERE id = ?', [$id]);
    }

    public static function areaPorToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{24}$/', $token)) {
            return null;
        }
        return Database::one('SELECT * FROM areas WHERE token = ? AND activo = 1', [$token]);
    }

    public static function items(int $areaId, bool $soloActivos = true): array
    {
        return Database::all(
            'SELECT * FROM inventario_items WHERE area_id = ?' . ($soloActivos ? ' AND activo = 1' : '') . ' ORDER BY activo DESC, orden, nombre',
            [$areaId]
        );
    }

    public static function item(int $id): ?array
    {
        return Database::one('SELECT * FROM inventario_items WHERE id = ?', [$id]);
    }

    private static function validarItem(array $d): array
    {
        $nombre = trim(preg_replace('/\s+/', ' ', (string)($d['nombre'] ?? '')));
        if (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 80) {
            throw new InvalidArgumentException('El nombre del elemento debe tener entre 2 y 80 caracteres.');
        }
        $area = self::area((int)($d['area_id'] ?? 0)) ?? throw new InvalidArgumentException('Selecciona un área válida.');
        $tipo = ($d['tipo'] ?? 'cantidad') === 'estado' ? 'estado' : 'cantidad';
        $esp = trim((string)($d['cantidad_esperada'] ?? ''));
        if ($esp !== '' && (!ctype_digit($esp) || (int)$esp > 100000)) {
            throw new InvalidArgumentException('La cantidad esperada debe ser un número entero.');
        }
        return [
            'area_id'           => (int)$area['id'],
            'nombre'            => $nombre,
            'tipo'              => $tipo,
            'cantidad_esperada' => ($tipo === 'cantidad' && $esp !== '') ? (int)$esp : null,
            'orden'             => max(0, min(999, (int)($d['orden'] ?? 0))),
        ];
    }

    public static function crearItem(array $d): int
    {
        $v = self::validarItem($d);
        if (!$v['orden']) {
            $v['orden'] = (int)Database::value('SELECT COALESCE(MAX(orden), 0) + 1 FROM inventario_items WHERE area_id = ?', [$v['area_id']]);
        }
        $id = Database::insert('inventario_items', $v + ['activo' => 1, 'creado' => now_utc(), 'actualizado' => now_utc()]);
        Audit::log('item_creado', 'inventario_item', $id, null, null, $v);
        return $id;
    }

    public static function editarItem(int $id, array $d): void
    {
        $it = self::item($id) ?? throw new InvalidArgumentException('El elemento no existe.');
        $v = self::validarItem($d);
        Database::update('inventario_items', $v + ['actualizado' => now_utc()], 'id = :id', ['id' => $id]);
        $antesStock = $it['cantidad_esperada'] !== null ? (int)$it['cantidad_esperada'] : null;
        if ($antesStock !== $v['cantidad_esperada']) {
            self::movimiento($id, $v['cantidad_esperada'] === null ? 'reinicio' : 'ajuste', (int)$v['cantidad_esperada'] - (int)$antesStock,
                $antesStock, $v['cantidad_esperada'], 'Editado en el catálogo', null);
        }
        Audit::log('item_editado', 'inventario_item', $id, null,
            array_intersect_key($it, $v), $v);
    }

    public static function estadoItem(int $id, bool $activo): void
    {
        $it = self::item($id) ?? throw new InvalidArgumentException('El elemento no existe.');
        Database::update('inventario_items', ['activo' => $activo ? 1 : 0, 'actualizado' => now_utc()], 'id = :id', ['id' => $id]);
        Audit::log($activo ? 'item_activado' : 'item_desactivado', 'inventario_item', $id, null, null, ['nombre' => $it['nombre']]);
    }

    /* ---------------- Fotos de referencia ---------------- */

    private static function dirFotos(): string
    {
        $d = STORAGE_PATH . '/items';
        if (!is_dir($d)) {
            mkdir($d, 0750, true);
        }
        return $d;
    }

    public static function rutaFoto(?string $nombre): ?string
    {
        if (!$nombre || !preg_match('/^item\d+_[a-f0-9]{8}\.jpg$/', $nombre)) {
            return null;
        }
        $p = self::dirFotos() . '/' . $nombre;
        return is_file($p) ? $p : null;
    }

    /** Recibe un archivo subido ($_FILES['foto']) y lo guarda re-codificado en JPEG */
    public static function subirFoto(int $itemId, array $archivo): void
    {
        $it = self::item($itemId) ?? throw new InvalidArgumentException('El elemento no existe.');
        if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('No se recibió la foto. Intenta con una imagen de menos de 5 MB.');
        }
        if ($archivo['size'] > 6 * 1024 * 1024) {
            throw new InvalidArgumentException('La foto pesa demasiado (máximo 6 MB).');
        }
        $bin = file_get_contents($archivo['tmp_name']);
        $img = $bin !== false ? @imagecreatefromstring($bin) : false;
        if (!$img) {
            throw new InvalidArgumentException('El archivo no es una imagen válida (usa JPG o PNG).');
        }
        // Orientación EXIF de fotos tomadas con celular
        if (function_exists('exif_read_data') && str_starts_with($bin, "\xFF\xD8")) {
            $exif = @exif_read_data($archivo['tmp_name']);
            $rot = [3 => 180, 6 => -90, 8 => 90][$exif['Orientation'] ?? 1] ?? 0;
            if ($rot) {
                $img = imagerotate($img, $rot, 0);
            }
        }
        $w = imagesx($img);
        $h = imagesy($img);
        $lado = 480;
        $esc = min(1, $lado / max($w, $h));
        $nw = max(1, (int)round($w * $esc));
        $nh = max(1, (int)round($h * $esc));
        $out = imagecreatetruecolor($nw, $nh);
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255)); // fondo blanco para PNG transparentes
        imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        $nombre = 'item' . $itemId . '_' . bin2hex(random_bytes(4)) . '.jpg';
        imagejpeg($out, self::dirFotos() . '/' . $nombre, 82);
        imagedestroy($img);
        imagedestroy($out);
        if ($anterior = self::rutaFoto($it['foto'])) {
            @unlink($anterior);
        }
        Database::update('inventario_items', ['foto' => $nombre, 'actualizado' => now_utc()], 'id = :id', ['id' => $itemId]);
        Audit::log('item_foto', 'inventario_item', $itemId, null, null, ['nombre' => $it['nombre']]);
    }

    /* ---------------- Registro del trabajador ---------------- */

    /** Fecha del turno: un inventario de noche enviado de madrugada pertenece al día anterior */
    public static function fechaTurno(string $turno, ?DateTimeImmutable $ahora = null): string
    {
        $ahora ??= new DateTimeImmutable('now', app_tz());
        if ($turno === 'noche' && (int)$ahora->format('G') < 6) {
            return $ahora->modify('-1 day')->format('Y-m-d');
        }
        return $ahora->format('Y-m-d');
    }

    public static function turnoSugerido(?DateTimeImmutable $ahora = null): string
    {
        $ahora ??= new DateTimeImmutable('now', app_tz());
        $h = (int)$ahora->format('G');
        return ($h >= 6 && $h < 17) ? 'mediodia' : 'noche';
    }

    public static function enviadoDe(int $areaId, string $fecha, string $turno): ?array
    {
        return Database::one(
            "SELECT i.*, " . self::AUTOR . " FROM inventarios i LEFT JOIN trabajadores t ON t.id = i.trabajador_id
             WHERE i.area_id = ? AND i.fecha = ? AND i.turno = ? AND i.estado = 'enviado' AND i.origen = 'trabajador' LIMIT 1",
            [$areaId, $fecha, $turno]
        );
    }

    public static function borradorDe(int $areaId, int $trabajadorId, string $fecha, string $turno): ?array
    {
        return Database::one(
            "SELECT * FROM inventarios WHERE area_id = ? AND trabajador_id = ? AND fecha = ? AND turno = ? AND estado = 'borrador' LIMIT 1",
            [$areaId, $trabajadorId, $fecha, $turno]
        );
    }

    public static function lineas(int $inventarioId): array
    {
        return Database::all('SELECT * FROM inventario_lineas WHERE inventario_id = ? ORDER BY id', [$inventarioId]);
    }

    /**
     * Valida las líneas enviadas por el formulario contra el catálogo del área.
     * @param array $datos [item_id => ['cantidad' => '12', 'estado' => 'bien', 'observacion' => '']]
     */
    private static function normalizarLineas(int $areaId, array $datos, bool $exigirCompleto): array
    {
        $lineas = [];
        foreach (self::items($areaId) as $it) {
            $d = $datos[$it['id']] ?? $datos[(string)$it['id']] ?? [];
            $cant = trim((string)($d['cantidad'] ?? ''));
            $estado = (string)($d['estado'] ?? '');
            $obs = mb_substr(trim((string)($d['observacion'] ?? '')), 0, 255);
            $motivo = '';   // el motivo se pregunta después de enviar el conteo
            if ($cant !== '' && (!ctype_digit($cant) || (int)$cant > 100000)) {
                throw new InvalidArgumentException('La cantidad de "' . $it['nombre'] . '" debe ser un número entero.');
            }

            $esperada = $it['cantidad_esperada'] !== null ? (int)$it['cantidad_esperada'] : null;
            // Primer conteo de un elemento sin stock definido: esa cantidad queda como stock
            $esBase = $it['tipo'] === 'cantidad' && $esperada === null && $cant !== '';
            $falta = ($it['tipo'] === 'cantidad' && $esperada !== null && $cant !== '') ? $esperada - (int)$cant : 0;
            // El motivo debe corresponder: de faltante si falta, de sobrante si sobra
            $validos = $falta > 0 ? self::MOTIVOS_FALTA : ($falta < 0 ? self::MOTIVOS_SOBRA : []);
            if (!isset($validos[$motivo])) {
                $motivo = '';
            }
            if ($estado !== '' && !isset(self::ESTADOS[$estado])) {
                throw new InvalidArgumentException('Estado no válido en "' . $it['nombre'] . '".');
            }
            if ($exigirCompleto) {
                if ($it['tipo'] === 'cantidad' && $cant === '') {
                    throw new InvalidArgumentException('Falta la cantidad de "' . $it['nombre'] . '".');
                }
                if ($it['tipo'] === 'estado' && $estado === '') {
                    throw new InvalidArgumentException('Falta el estado de "' . $it['nombre'] . '".');
                }
                if ($estado === 'danado' && $obs === '') {
                    throw new InvalidArgumentException('Escribe qué le pasó a "' . $it['nombre'] . '" (marcado como dañado).');
                }
            }
            $lineas[] = [
                'item_id'           => (int)$it['id'],
                'item_nombre'       => $it['nombre'],
                'tipo'              => $it['tipo'],
                'cantidad_esperada' => $esBase ? (int)$cant : $esperada,
                'cantidad'          => $it['tipo'] === 'cantidad' && $cant !== '' ? (int)$cant : null,
                'estado'            => $estado !== '' ? $estado : ($exigirCompleto ? 'bien' : null),
                'observacion'       => $obs !== '' ? $obs : null,
                'motivo_faltante'   => $falta !== 0 && $motivo !== '' ? $motivo : null,
                'es_base'           => $esBase ? 1 : 0,
            ];
        }
        if (!$lineas) {
            throw new InvalidArgumentException('Esta área no tiene elementos configurados. Avisa al administrador.');
        }
        return $lineas;
    }

    public static function contarDiferencias(array $lineas): int
    {
        $n = 0;
        foreach ($lineas as $l) {
            if (($l['tipo'] === 'cantidad' && $l['cantidad_esperada'] !== null && $l['cantidad'] !== null && (int)$l['cantidad'] !== (int)$l['cantidad_esperada'])
                || in_array($l['estado'], ['novedad', 'danado'], true)) {
                $n++;
            }
        }
        return $n;
    }

    private static function guardarLineas(int $inventarioId, array $lineas): void
    {
        Database::run('DELETE FROM inventario_lineas WHERE inventario_id = ?', [$inventarioId]);
        foreach ($lineas as $l) {
            Database::insert('inventario_lineas', ['inventario_id' => $inventarioId] + $l);
        }
    }

    public static function guardarBorrador(int $areaId, int $trabajadorId, string $turno, array $datos, string $obs): int
    {
        if (!isset(self::TURNOS[$turno])) {
            throw new InvalidArgumentException('Selecciona el turno.');
        }
        $fecha = self::fechaTurno($turno);
        $lineas = self::normalizarLineas($areaId, $datos, false);
        return Database::transaction(function () use ($areaId, $trabajadorId, $turno, $fecha, $lineas, $obs) {
            $b = self::borradorDe($areaId, $trabajadorId, $fecha, $turno);
            $ahora = now_utc();
            if ($b) {
                $id = (int)$b['id'];
                Database::update('inventarios', ['observaciones' => mb_substr(trim($obs), 0, 1000) ?: null, 'actualizado' => $ahora], 'id = :id', ['id' => $id]);
            } else {
                $id = Database::insert('inventarios', [
                    'area_id' => $areaId, 'trabajador_id' => $trabajadorId, 'fecha' => $fecha, 'turno' => $turno,
                    'estado' => 'borrador', 'observaciones' => mb_substr(trim($obs), 0, 1000) ?: null,
                    'ip' => client_ip(), 'creado' => $ahora, 'actualizado' => $ahora,
                ]);
            }
            self::guardarLineas($id, $lineas);
            return $id;
        });
    }

    /** Envía el inventario. Impide duplicados del mismo área, fecha y turno. */
    public static function enviar(int $areaId, int $trabajadorId, string $turno, array $datos, string $obs): array
    {
        if (!isset(self::TURNOS[$turno])) {
            throw new InvalidArgumentException('Selecciona el turno.');
        }
        $fecha = self::fechaTurno($turno);
        $lineas = self::normalizarLineas($areaId, $datos, true);
        $id = Database::transaction(function () use ($areaId, $trabajadorId, $turno, $fecha, $lineas, $obs) {
            // Bloquea el área para que dos envíos simultáneos no se dupliquen
            Database::one('SELECT id FROM areas WHERE id = ? FOR UPDATE', [$areaId]);
            if ($ya = self::enviadoDe($areaId, $fecha, $turno)) {
                throw new InvalidArgumentException('El inventario de este turno ya lo envió ' . $ya['trabajador'] . ' a las ' . fmt_hora($ya['enviado']) . '. Si hay que corregirlo, avisa al administrador.');
            }
            $ahora = now_utc();
            $b = self::borradorDe($areaId, $trabajadorId, $fecha, $turno);
            $campos = [
                'estado' => 'enviado', 'enviado' => $ahora, 'observaciones' => mb_substr(trim($obs), 0, 1000) ?: null,
                'diferencias' => self::contarDiferencias($lineas), 'ip' => client_ip(), 'actualizado' => $ahora,
            ];
            if ($b) {
                $id = (int)$b['id'];
                Database::update('inventarios', $campos, 'id = :id', ['id' => $id]);
            } else {
                $id = Database::insert('inventarios', $campos + [
                    'area_id' => $areaId, 'trabajador_id' => $trabajadorId, 'fecha' => $fecha, 'turno' => $turno, 'creado' => $ahora,
                ]);
            }
            self::guardarLineas($id, $lineas);
            $nombreT = (string)Database::value('SELECT nombre FROM trabajadores WHERE id = ?', [$trabajadorId]);
            if (self::aplicarBase($lineas, $id, $nombreT, false)) {
                Database::update('inventarios', ['es_base' => 1], 'id = :id', ['id' => $id]);
            }
            // otros borradores del mismo turno quedan sin efecto
            Database::run("DELETE FROM inventarios WHERE area_id = ? AND fecha = ? AND turno = ? AND estado = 'borrador'", [$areaId, $fecha, $turno]);
            return $id;
        });
        $preguntas = self::preguntas($id);
        if ($preguntas) {
            Database::update('inventarios', ['motivos_pendientes' => 1], 'id = :id', ['id' => $id]);
        }
        $pdf = self::generarPdf($id);
        Audit::log('inventario_enviado', 'inventario', $id, null, null, ['area' => self::area($areaId)['nombre'], 'turno' => $turno, 'fecha' => $fecha]);
        return ['id' => $id, 'pdf' => $pdf, 'fecha' => $fecha, 'preguntas' => $preguntas];
    }

    /** Diferencias del conteo que el trabajador debe explicar (las cantidades ya quedaron fijas) */
    public static function preguntas(int $inventarioId): array
    {
        $out = [];
        foreach (self::lineas($inventarioId) as $l) {
            if ($l['es_base'] || $l['cantidad_esperada'] === null || $l['cantidad'] === null) {
                continue;
            }
            $d = (int)$l['cantidad'] - (int)$l['cantidad_esperada'];
            if ($d !== 0) {
                $out[] = ['linea' => (int)$l['id'], 'nombre' => $l['item_nombre'], 'dif' => $d, 'contado' => (int)$l['cantidad'], 'motivo' => $l['motivo_faltante']];
            }
        }
        return $out;
    }

    /**
     * El trabajador explica por qué falta o sobra. No puede cambiar las cantidades.
     * @param array $respuestas [linea_id => ['motivo' => 'roto', 'observacion' => '...']]
     */
    public static function explicar(int $inventarioId, int $trabajadorId, array $respuestas): void
    {
        $inv = self::buscar($inventarioId) ?? throw new InvalidArgumentException('El inventario no existe.');
        if ((int)$inv['trabajador_id'] !== $trabajadorId) {
            throw new InvalidArgumentException('Este inventario lo envió otra persona.');
        }
        $preg = self::preguntas($inventarioId);
        $cambios = [];
        foreach ($preg as $p) {
            $r = $respuestas[$p['linea']] ?? $respuestas[(string)$p['linea']] ?? [];
            $motivo = (string)($r['motivo'] ?? '');
            $obs = mb_substr(trim((string)($r['observacion'] ?? '')), 0, 255);
            $validos = $p['dif'] < 0 ? self::MOTIVOS_FALTA : self::MOTIVOS_SOBRA;
            $txt = $p['dif'] < 0 ? 'faltan ' . (-$p['dif']) : 'sobran ' . $p['dif'];
            if (!isset($validos[$motivo])) {
                throw new InvalidArgumentException('Elige por qué ' . $txt . ' de "' . $p['nombre'] . '".');
            }
            if (in_array($motivo, ['otro', 'otro_sobra'], true) && $obs === '') {
                throw new InvalidArgumentException('Explica qué pasó con "' . $p['nombre'] . '".');
            }
            $cambios[$p['linea']] = ['motivo_faltante' => $motivo] + ($obs !== '' ? ['observacion' => $obs] : []);
        }
        Database::transaction(function () use ($inventarioId, $cambios) {
            foreach ($cambios as $lineaId => $c) {
                Database::update('inventario_lineas', $c, 'id = :id AND inventario_id = :inv', ['id' => $lineaId, 'inv' => $inventarioId]);
            }
            Database::update('inventarios', ['motivos_pendientes' => 0, 'actualizado' => now_utc()], 'id = :id', ['id' => $inventarioId]);
        });
        Audit::log('inventario_explicado', 'inventario', $inventarioId, null, null, array_map(fn($c) => self::MOTIVOS[$c['motivo_faltante']], $cambios));
        self::generarPdf($inventarioId);
    }

    /* ---------------- Stock (cantidad que debe haber) ---------------- */

    /**
     * Guarda como stock las cantidades de las líneas marcadas como base.
     * Con $forzar, reemplaza el stock aunque ya existiera (conteo del administrador).
     * @return bool si cambió algún stock
     */
    private static function aplicarBase(array $lineas, int $inventarioId, string $actor, bool $forzar): bool
    {
        $cambio = false;
        foreach ($lineas as $l) {
            if (empty($l['es_base']) || $l['cantidad'] === null) {
                continue;
            }
            $it = Database::one('SELECT id, cantidad_esperada FROM inventario_items WHERE id = ? FOR UPDATE', [$l['item_id']]);
            if (!$it || (!$forzar && $it['cantidad_esperada'] !== null)) {
                continue;
            }
            $antes = $it['cantidad_esperada'] !== null ? (int)$it['cantidad_esperada'] : null;
            if ($antes === (int)$l['cantidad']) {
                continue;
            }
            Database::update('inventario_items', ['cantidad_esperada' => (int)$l['cantidad'], 'actualizado' => now_utc()], 'id = :id', ['id' => $it['id']]);
            self::movimiento((int)$it['id'], $antes === null ? 'base' : 'ajuste', (int)$l['cantidad'] - (int)$antes, $antes, (int)$l['cantidad'],
                $antes === null ? 'Primer conteo: queda como stock' : 'Conteo del administrador', $inventarioId, $actor);
            $cambio = true;
        }
        return $cambio;
    }

    private static function movimiento(int $itemId, string $tipo, int $cantidad, ?int $antes, ?int $despues, ?string $motivo, ?int $inventarioId, ?string $actor = null): void
    {
        if ($actor === null) {
            $a = Auth::admin();
            $actor = $a['nombre'] ?? 'Sistema';
        }
        Database::insert('stock_movimientos', [
            'item_id' => $itemId, 'tipo' => $tipo, 'cantidad' => $cantidad, 'antes' => $antes, 'despues' => $despues,
            'motivo' => $motivo !== null ? mb_substr($motivo, 0, 255) : null, 'inventario_id' => $inventarioId,
            'actor' => mb_substr((string)$actor, 0, 120), 'creado' => now_utc(),
        ]);
    }

    /** Descontar (baja) o sumar (ingreso) loza al stock de un elemento */
    public static function moverStock(int $itemId, string $tipo, int $cantidad, string $motivo): void
    {
        $motivo = trim($motivo);
        if (!in_array($tipo, ['baja', 'ingreso'], true)) {
            throw new InvalidArgumentException('Movimiento no válido.');
        }
        if ($cantidad < 1 || $cantidad > 10000) {
            throw new InvalidArgumentException('La cantidad debe ser mayor que cero.');
        }
        if (mb_strlen($motivo) < 3) {
            throw new InvalidArgumentException('Escribe el motivo del movimiento.');
        }
        Database::transaction(function () use ($itemId, $tipo, $cantidad, $motivo) {
            $it = Database::one('SELECT * FROM inventario_items WHERE id = ? FOR UPDATE', [$itemId]) ?? throw new InvalidArgumentException('El elemento no existe.');
            if ($it['cantidad_esperada'] === null) {
                throw new InvalidArgumentException('"' . $it['nombre'] . '" todavía no tiene stock. Se fijará con el primer conteo.');
            }
            $antes = (int)$it['cantidad_esperada'];
            $cambio = $tipo === 'baja' ? -$cantidad : $cantidad;
            if ($antes + $cambio < 0) {
                throw new InvalidArgumentException('No se pueden descontar más de ' . $antes . '.');
            }
            Database::update('inventario_items', ['cantidad_esperada' => $antes + $cambio, 'actualizado' => now_utc()], 'id = :id', ['id' => $itemId]);
            self::movimiento($itemId, $tipo, $cambio, $antes, $antes + $cambio, $motivo, null);
            Audit::log($tipo === 'baja' ? 'stock_baja' : 'stock_ingreso', 'inventario_item', $itemId, $motivo,
                ['nombre' => $it['nombre'], 'stock' => $antes], ['stock' => $antes + $cambio]);
        });
    }

    /** El próximo conteo de cada elemento del área volverá a fijar el stock */
    public static function reiniciarStock(int $areaId, string $motivo): int
    {
        $n = 0;
        foreach (self::items($areaId) as $it) {
            if ($it['tipo'] !== 'cantidad' || $it['cantidad_esperada'] === null) {
                continue;
            }
            Database::update('inventario_items', ['cantidad_esperada' => null, 'actualizado' => now_utc()], 'id = :id', ['id' => $it['id']]);
            self::movimiento((int)$it['id'], 'reinicio', -(int)$it['cantidad_esperada'], (int)$it['cantidad_esperada'], null, $motivo ?: 'Reinicio de stock', null);
            $n++;
        }
        Audit::log('stock_reiniciado', 'area', $areaId, $motivo ?: null, null, ['elementos' => $n]);
        return $n;
    }

    /**
     * Conteo hecho por el administrador desde el panel.
     * Con $fijarStock, las cantidades contadas pasan a ser el stock de cada elemento.
     */
    public static function contarAdmin(int $areaId, string $turno, array $datos, string $obs, bool $fijarStock): array
    {
        $admin = Auth::admin() ?? throw new InvalidArgumentException('Solo el administrador puede registrar este conteo.');
        if (!isset(self::TURNOS[$turno])) {
            throw new InvalidArgumentException('Selecciona el turno.');
        }
        $area = self::area($areaId) ?? throw new InvalidArgumentException('El área no existe.');
        $fecha = self::fechaTurno($turno);
        $lineas = [];
        foreach (self::items($areaId) as $it) {
            $d = $datos[$it['id']] ?? [];
            $cant = trim((string)($d['cantidad'] ?? ''));
            if ($it['tipo'] === 'cantidad' && ($cant === '' || !ctype_digit($cant) || (int)$cant > 100000)) {
                throw new InvalidArgumentException('Escribe la cantidad de "' . $it['nombre'] . '".');
            }
            $estado = isset(self::ESTADOS[$d['estado'] ?? '']) ? $d['estado'] : 'bien';
            $esperada = $it['cantidad_esperada'] !== null ? (int)$it['cantidad_esperada'] : null;
            $lineas[] = [
                'item_id' => (int)$it['id'], 'item_nombre' => $it['nombre'], 'tipo' => $it['tipo'],
                'cantidad_esperada' => $fijarStock && $it['tipo'] === 'cantidad' ? (int)$cant : $esperada,
                'cantidad' => $it['tipo'] === 'cantidad' ? (int)$cant : null,
                'estado' => $estado,
                'observacion' => mb_substr(trim((string)($d['observacion'] ?? '')), 0, 255) ?: null,
                'motivo_faltante' => null,
                'es_base' => $fijarStock && $it['tipo'] === 'cantidad' ? 1 : 0,
            ];
        }
        if (!$lineas) {
            throw new InvalidArgumentException('Esta área no tiene elementos. Agrégalos primero.');
        }
        $id = Database::transaction(function () use ($areaId, $admin, $turno, $fecha, $lineas, $obs, $fijarStock) {
            $ahora = now_utc();
            $id = Database::insert('inventarios', [
                'area_id' => $areaId, 'trabajador_id' => null, 'admin_id' => (int)$admin['id'], 'origen' => 'admin',
                'es_base' => $fijarStock ? 1 : 0, 'fecha' => $fecha, 'turno' => $turno, 'estado' => 'enviado', 'enviado' => $ahora,
                'observaciones' => mb_substr(trim($obs), 0, 1000) ?: null, 'diferencias' => $fijarStock ? 0 : self::contarDiferencias($lineas),
                'envio_estado' => 'pendiente', 'ip' => client_ip(), 'creado' => $ahora, 'actualizado' => $ahora,
            ]);
            self::guardarLineas($id, $lineas);
            if ($fijarStock) {
                self::aplicarBase($lineas, $id, 'Administrador ' . $admin['nombre'], true);
            }
            return $id;
        });
        self::generarPdf($id);
        Audit::log('inventario_admin', 'inventario', $id, $fijarStock ? 'Conteo del administrador que fija el stock' : 'Conteo del administrador',
            null, ['area' => $area['nombre'], 'turno' => $turno, 'fecha' => $fecha]);
        return ['id' => $id];
    }

    /** Stock de cada elemento con su último conteo (para el control de loza) */
    public static function controlStock(int $areaId): array
    {
        $out = [];
        foreach (self::items($areaId) as $it) {
            $ult = $it['tipo'] === 'cantidad' ? Database::one(
                "SELECT l.cantidad, l.motivo_faltante, l.observacion, l.es_base, i.id AS inventario_id, i.fecha, i.turno, i.enviado, " . self::AUTOR . "
                 FROM inventario_lineas l JOIN inventarios i ON i.id = l.inventario_id LEFT JOIN trabajadores t ON t.id = i.trabajador_id
                 WHERE l.item_id = ? AND i.estado = 'enviado' AND l.cantidad IS NOT NULL ORDER BY i.enviado DESC, i.id DESC LIMIT 1",
                [$it['id']]
            ) : null;
            $dif = ($ult && $it['cantidad_esperada'] !== null) ? (int)$ult['cantidad'] - (int)$it['cantidad_esperada'] : null;
            $out[] = ['item' => $it, 'ultimo' => $ult, 'dif' => $dif];
        }
        return $out;
    }

    /** Reportes de faltantes y daños (no incluye conteos que fijaron stock) */
    public static function faltantes(string $desde, string $hasta, ?int $areaId = null): array
    {
        $sql = "SELECT l.*, i.fecha, i.turno, i.enviado, i.area_id, a.nombre AS area, " . self::AUTOR . "
                FROM inventario_lineas l JOIN inventarios i ON i.id = l.inventario_id JOIN areas a ON a.id = i.area_id
                LEFT JOIN trabajadores t ON t.id = i.trabajador_id
                WHERE i.estado = 'enviado' AND l.es_base = 0 AND i.fecha BETWEEN ? AND ?
                  AND ((l.cantidad_esperada IS NOT NULL AND l.cantidad IS NOT NULL AND l.cantidad <> l.cantidad_esperada) OR l.estado = 'danado')";
        $p = [$desde, $hasta];
        if ($areaId) {
            $sql .= ' AND i.area_id = ?';
            $p[] = $areaId;
        }
        return Database::all($sql . ' ORDER BY i.enviado DESC LIMIT 300', $p);
    }

    public static function movimientos(int $areaId, int $limite = 50): array
    {
        return Database::all(
            'SELECT m.*, it.nombre FROM stock_movimientos m JOIN inventario_items it ON it.id = m.item_id
             WHERE it.area_id = ? ORDER BY m.creado DESC, m.id DESC LIMIT ' . (int)$limite,
            [$areaId]
        );
    }

    /** Elementos cuyo último conteo quedó por debajo del stock (para el resumen del día) */
    public static function alertasStock(): array
    {
        $out = [];
        foreach (self::areas(true) as $a) {
            foreach (self::controlStock((int)$a['id']) as $c) {
                if ($c['dif'] !== null && $c['dif'] !== 0) {
                    $out[] = $c + ['area' => $a['nombre'], 'area_id' => (int)$a['id']];
                }
            }
        }
        return $out;
    }

    /* ---------------- Consulta y corrección (administrador) ---------------- */

    public static function buscar(int $id): ?array
    {
        return Database::one(
            'SELECT i.*, a.nombre AS area, ' . self::AUTOR . ' FROM inventarios i
             JOIN areas a ON a.id = i.area_id LEFT JOIN trabajadores t ON t.id = i.trabajador_id WHERE i.id = ?',
            [$id]
        );
    }

    public static function historial(array $f): array
    {
        $sql = "SELECT i.*, a.nombre AS area, " . self::AUTOR . " FROM inventarios i
                JOIN areas a ON a.id = i.area_id LEFT JOIN trabajadores t ON t.id = i.trabajador_id
                WHERE i.estado = 'enviado' AND i.fecha BETWEEN ? AND ?";
        $p = [$f['desde'], $f['hasta']];
        foreach (['area_id' => 'i.area_id', 'trabajador_id' => 'i.trabajador_id', 'turno' => 'i.turno'] as $k => $col) {
            if (!empty($f[$k])) {
                $sql .= " AND $col = ?";
                $p[] = $f[$k];
            }
        }
        $sql .= ' ORDER BY i.fecha DESC, i.enviado DESC LIMIT 500';
        return Database::all($sql, $p);
    }

    public static function corregir(int $id, array $datos, string $obs, string $motivo): void
    {
        $motivo = trim($motivo);
        if (mb_strlen($motivo) < 5) {
            throw new InvalidArgumentException('Escribe el motivo de la corrección (mínimo 5 caracteres).');
        }
        $inv = self::buscar($id) ?? throw new InvalidArgumentException('El inventario no existe.');
        if ($inv['estado'] !== 'enviado') {
            throw new InvalidArgumentException('Solo se corrigen inventarios enviados.');
        }
        $antes = self::lineas($id);
        $nuevas = [];
        foreach ($antes as $l) {
            $d = $datos[$l['id']] ?? [];
            $cant = trim((string)($d['cantidad'] ?? ($l['cantidad'] ?? '')));
            $estado = (string)($d['estado'] ?? ($l['estado'] ?? 'bien'));
            if ($cant !== '' && (!ctype_digit($cant) || (int)$cant > 100000)) {
                throw new InvalidArgumentException('La cantidad de "' . $l['item_nombre'] . '" debe ser un número entero.');
            }
            if (!isset(self::ESTADOS[$estado])) {
                $estado = 'bien';
            }
            $nuevas[] = array_merge($l, [
                'cantidad' => $l['tipo'] === 'cantidad' && $cant !== '' ? (int)$cant : null,
                'estado' => $estado,
                'observacion' => mb_substr(trim((string)($d['observacion'] ?? ($l['observacion'] ?? ''))), 0, 255) ?: null,
                'motivo_faltante' => isset(self::MOTIVOS[$d['motivo'] ?? '']) ? $d['motivo'] : ($l['motivo_faltante'] ?? null),
            ]);
        }
        Database::transaction(function () use ($id, $nuevas, $obs) {
            foreach ($nuevas as $l) {
                $faltaAun = $l['cantidad_esperada'] !== null && $l['cantidad'] !== null && (int)$l['cantidad'] !== (int)$l['cantidad_esperada'];
                Database::update('inventario_lineas', [
                    'cantidad' => $l['cantidad'], 'estado' => $l['estado'], 'observacion' => $l['observacion'],
                    'motivo_faltante' => $faltaAun ? $l['motivo_faltante'] : null,
                ], 'id = :id', ['id' => $l['id']]);
            }
            Database::update('inventarios', [
                'observaciones' => mb_substr(trim($obs), 0, 1000) ?: null,
                'diferencias' => self::contarDiferencias($nuevas), 'corregido' => 1, 'actualizado' => now_utc(),
            ], 'id = :id', ['id' => $id]);
        });
        $resumen = fn($ls) => array_combine(
            array_map(fn($l) => $l['item_nombre'], $ls),
            array_map(fn($l) => ($l['cantidad'] ?? '—') . ' / ' . (self::ESTADOS[$l['estado']] ?? '—') . ($l['observacion'] ? ' (' . $l['observacion'] . ')' : ''), $ls)
        );
        Audit::log('inventario_corregido', 'inventario', $id, $motivo, $resumen($antes) + ['observaciones' => $inv['observaciones']], $resumen($nuevas) + ['observaciones' => trim($obs)]);
        self::generarPdf($id);
    }

    /** Estado de los inventarios de un día: [area_id][turno] => inventario enviado o null */
    public static function tablero(string $fecha): array
    {
        $out = [];
        foreach (self::areas(true) as $a) {
            foreach (array_keys(self::TURNOS) as $t) {
                $out[$a['id']]['area'] = $a['nombre'];
                $out[$a['id']][$t] = self::enviadoDe((int)$a['id'], $fecha, $t);
            }
        }
        return $out;
    }

    /* ---------------- PDF ---------------- */

    private static function dirPdf(): string
    {
        $d = STORAGE_PATH . '/inventarios';
        if (!is_dir($d)) {
            mkdir($d, 0750, true);
        }
        return $d;
    }

    public static function rutaPdf(?string $nombre): ?string
    {
        if (!$nombre || !preg_match('#^\d{4}/\d{2}/[A-Za-z0-9_-]+\.pdf$#', $nombre)) {
            return null;
        }
        $p = self::dirPdf() . '/' . $nombre;
        return is_file($p) ? $p : null;
    }

    public static function nombreArchivo(array $inv): string
    {
        $limpio = fn($s) => trim(preg_replace('/[^A-Za-z0-9]+/', '_', iconv('UTF-8', 'ASCII//TRANSLIT', $s) ?: $s), '_');
        return 'Inventario_' . $limpio($inv['area']) . '_' . $inv['fecha'] . '_' . $inv['turno'] . '.pdf';
    }

    private static function logoJpg(): ?string
    {
        $png = is_file(ROOT_PATH . '/public/assets/img/logo.png') ? ROOT_PATH . '/public/assets/img/logo.png' : ROOT_PATH . '/assets/img/logo.png';
        if (!is_file($png)) {
            return null;
        }
        $jpg = STORAGE_PATH . '/logo_pdf.jpg';
        if (!is_file($jpg) || filemtime($jpg) < filemtime($png)) {
            $im = @imagecreatefrompng($png);
            if (!$im) {
                return null;
            }
            $fondo = imagecreatetruecolor(imagesx($im), imagesy($im));
            imagefill($fondo, 0, 0, imagecolorallocate($fondo, 255, 255, 255));
            imagecopy($fondo, $im, 0, 0, 0, 0, imagesx($im), imagesy($im));
            imagejpeg($fondo, $jpg, 88);
            imagedestroy($im);
            imagedestroy($fondo);
        }
        return $jpg;
    }

    /** Genera (o regenera) el PDF del inventario y devuelve el nombre del archivo */
    public static function generarPdf(int $id): string
    {
        $inv = self::buscar($id) ?? throw new InvalidArgumentException('El inventario no existe.');
        $lineas = self::lineas($id);
        $fotos = [];
        foreach ($lineas as $l) {
            if ($l['item_id'] && ($it = self::item((int)$l['item_id'])) && ($r = self::rutaFoto($it['foto']))) {
                $fotos[$l['id']] = $r;
            }
        }

        $pdf = new PdfSimple('Inventario ' . $inv['area'] . ' ' . $inv['fecha']);
        $x0 = 15.0;
        $ancho = 180.0;
        $cols = [['Foto', 18], ['Elemento', 52], ['Esperado', 20], ['Reportado', 22], ['Diferencia', 22], ['Estado', 22], ['Observación', 24]];
        $encabezado = function () use ($pdf, $inv, $x0, $ancho, $cols): float {
            $pdf->agregarPagina();
            $y = 12.0;
            if ($logo = self::logoJpg()) {
                $pdf->imagen($logo, $x0, $y, 34, 20);
            }
            $pdf->texto($x0 + 40, $y + 8, Settings::get('nombre_establecimiento'), 11, false, 'L', 0, 0.35);
            $pdf->texto($x0 + 40, $y + 17, 'Inventario · ' . $inv['area'], 18, true);
            $pdf->texto($x0, $y + 8, 'N.º ' . $inv['id'], 10, false, 'R', $ancho);
            $y += 27;
            $pdf->rect($x0, $y, $ancho, 16, 0.95, false);
            $local = utc_to_local($inv['enviado']);
            $datos = [
                ['Trabajador', $inv['trabajador']],
                ['Fecha', fmt_fecha($inv['fecha'])],
                ['Turno', self::TURNOS[$inv['turno']]],
                ['Hora de envío', $local ? $local->format('H:i:s') : '—'],
            ];
            foreach ($datos as $i => [$k, $v]) {
                $cx = $x0 + 3 + $i * 45;
                $pdf->texto($cx, $y + 6, $k, 8, false, 'L', 0, 0.4);
                $pdf->texto($cx, $y + 12.5, $pdf->recortar($v, 42, 11, true), 11, true);
            }
            $y += 22;
            $cx = $x0;
            $pdf->rect($x0, $y, $ancho, 8, [0.07, 0.23, 0.21], false);
            foreach ($cols as [$t, $w]) {
                $pdf->texto($cx + 1.5, $y + 5.5, $t, 8.5, true, in_array($t, ['Esperado', 'Reportado', 'Diferencia'], true) ? 'R' : 'L', $w - 3, 1);
                $cx += $w;
            }
            return $y + 8;
        };

        $y = $encabezado();
        $fila = 16.0;
        foreach ($lineas as $i => $l) {
            if ($y + $fila > 270) {
                $y = $encabezado();
            }
            $dif = ($l['tipo'] === 'cantidad' && $l['cantidad_esperada'] !== null && $l['cantidad'] !== null)
                ? (int)$l['cantidad'] - (int)$l['cantidad_esperada'] : null;
            $alerta = ($dif !== null && $dif !== 0) || in_array($l['estado'], ['novedad', 'danado'], true);
            if ($alerta) {
                $pdf->rect($x0, $y, $ancho, $fila, [1, 0.93, 0.91], false);
            } elseif ($i % 2) {
                $pdf->rect($x0, $y, $ancho, $fila, 0.97, false);
            }
            $cx = $x0;
            if (isset($fotos[$l['id']])) {
                $pdf->imagen($fotos[$l['id']], $cx + 1.5, $y + 1.5, 15, $fila - 3);
            }
            $cx += $cols[0][1];
            $base = $y + $fila / 2 + 1.5;
            $pdf->texto($cx + 1.5, $base, $pdf->recortar($l['item_nombre'], $cols[1][1] - 3, 10.5, true), 10.5, true);
            $cx += $cols[1][1];
            $pdf->texto($cx, $base, $l['cantidad_esperada'] !== null ? (string)$l['cantidad_esperada'] : '—', 10.5, false, 'R', $cols[2][1] - 1.5);
            $cx += $cols[2][1];
            $pdf->texto($cx, $base, $l['tipo'] === 'cantidad' ? (string)($l['cantidad'] ?? '—') : 'n/a', 12, true, 'R', $cols[3][1] - 1.5);
            $cx += $cols[3][1];
            $pdf->texto($cx, $base, $dif === null ? '—' : ($dif > 0 ? '+' . $dif : (string)$dif), 11, $dif !== null && $dif !== 0, 'R', $cols[4][1] - 1.5, $dif !== null && $dif < 0 ? 0 : 0);
            $cx += $cols[4][1];
            $pdf->texto($cx + 1.5, $base, self::ESTADOS[$l['estado']] ?? '—', 9.5, $l['estado'] !== 'bien');
            $cx += $cols[5][1];
            $obs = trim(implode(' · ', array_filter([
                !empty($l['es_base']) ? 'Stock inicial' : '',
                self::MOTIVOS[$l['motivo_faltante'] ?? ''] ?? ((!$l['es_base'] && $l['cantidad_esperada'] !== null && $l['cantidad'] !== null && (int)$l['cantidad'] !== (int)$l['cantidad_esperada']) ? 'Sin explicar' : ''),
                (string)($l['observacion'] ?? ''),
            ])));
            if ($obs !== '') {
                $renglones = self::partirTexto($pdf, $obs, $cols[6][1] - 3, 8);
                if (count($renglones) > 3) {
                    $renglones = array_slice($renglones, 0, 3);
                    $renglones[2] = $pdf->recortar($renglones[2] . ' ...', $cols[6][1] - 3, 8);
                }
                $oy = $y + $fila / 2 - (count($renglones) - 1) * 1.6 + 1.2;
                foreach ($renglones as $k => $r) {
                    $pdf->texto($cx + 1.5, $oy + $k * 3.2, $r, 8);
                }
            }
            $pdf->linea($x0, $y + $fila, $x0 + $ancho, $y + $fila, 0.15);
            $y += $fila;
        }

        // Resumen y observaciones
        $y += 6;
        if ($y > 245) {
            $y = $encabezado();
        }
        $dif = (int)$inv['diferencias'];
        $pdf->texto($x0, $y + 4, $dif ? "Diferencias o novedades: $dif elemento(s)" : 'Sin diferencias ni novedades', 11, true);
        $y += 10;
        if (!empty($inv['es_base'])) {
            $pdf->texto($x0, $y, $inv['origen'] === 'admin'
                ? 'Conteo del administrador: estas cantidades quedaron como el stock del área.'
                : 'Primer conteo: las cantidades marcadas como "Stock inicial" quedaron como el stock.', 9.5, false, 'L', 0, 0.25);
            $y += 7;
        }
        if ($inv['observaciones']) {
            $pdf->texto($x0, $y, 'Observaciones', 9, true, 'L', 0, 0.35);
            $y += 5;
            foreach (self::partirTexto($pdf, (string)$inv['observaciones'], $ancho, 10) as $renglon) {
                $pdf->texto($x0, $y, $renglon, 10);
                $y += 5;
            }
        }
        $y += 4;
        $estadoEnvio = $inv['origen'] === 'admin' ? 'Registrado por el administrador desde el panel'
            : ($inv['corregido'] ? 'Enviado y corregido por el administrador' : 'Enviado desde el formulario del área');
        $pdf->texto($x0, $y, 'Estado: ' . $estadoEnvio . ' · Registrado el ' . fmt_fecha_hora($inv['enviado']), 8.5, false, 'L', 0, 0.4);
        $pdf->texto($x0, 288, 'Documento generado por el sistema de inventario · ' . (new DateTimeImmutable('now', app_tz()))->format('Y-m-d H:i'), 7.5, false, 'L', 0, 0.5);

        $local = utc_to_local($inv['enviado']) ?? new DateTimeImmutable('now', app_tz());
        $sub = $local->format('Y/m');
        if (!is_dir(self::dirPdf() . '/' . $sub)) {
            mkdir(self::dirPdf() . '/' . $sub, 0750, true);
        }
        $nombre = $inv['pdf'] && self::rutaPdf($inv['pdf']) ? $inv['pdf'] : $sub . '/inv_' . $id . '_' . bin2hex(random_bytes(3)) . '.pdf';
        file_put_contents(self::dirPdf() . '/' . $nombre, $pdf->salida());
        Database::update('inventarios', ['pdf' => $nombre], 'id = :id', ['id' => $id]);
        return $nombre;
    }

    private static function partirTexto(PdfSimple $pdf, string $texto, float $ancho, float $tam): array
    {
        $out = [];
        foreach (preg_split('/\R/', $texto) as $parrafo) {
            $linea = '';
            foreach (preg_split('/\s+/', trim($parrafo)) as $palabra) {
                $prueba = $linea === '' ? $palabra : "$linea $palabra";
                if ($pdf->anchoTexto($prueba, $tam) > $ancho && $linea !== '') {
                    $out[] = $linea;
                    $linea = $palabra;
                } else {
                    $linea = $prueba;
                }
            }
            $out[] = $linea;
        }
        return array_slice($out, 0, 12);
    }

    public static function registrarEnvio(int $id, string $estado, string $detalle): void
    {
        $inv = self::buscar($id);
        if (!$inv) {
            return;
        }
        $log = trim(($inv['envio_detalle'] ?? '') . "\n" . (new DateTimeImmutable('now', app_tz()))->format('Y-m-d H:i:s') . " $estado: $detalle");
        Database::update('inventarios', ['envio_estado' => $estado, 'envio_detalle' => mb_substr($log, -2000)], 'id = :id', ['id' => $id]);
    }
}
