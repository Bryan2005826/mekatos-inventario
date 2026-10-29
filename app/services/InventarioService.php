<?php
declare(strict_types=1);

/** Inventario de loza y utensilios por áreas, con acceso por QR */
final class InventarioService
{
    public const TURNOS = ['mediodia' => 'Mediodía', 'noche' => 'Noche'];
    public const ESTADOS = ['bien' => 'Bien', 'novedad' => 'Con novedad', 'danado' => 'Dañado'];
    /** Por qué hay menos que en el conteo anterior */
    public const MOTIVOS_FALTA = [
        'roto'       => 'Se rompió',
        'mal_conteo' => 'El turno anterior contó mal',
        'perdido'    => 'No aparece',
        'otro'       => 'Otro motivo',
    ];
    /** Por qué hay más que en el conteo anterior */
    public const MOTIVOS_SOBRA = [
        'compro'     => 'El dueño compró loza',
        'mal_conteo' => 'El turno anterior contó mal',
    ];
    /** Motivos de versiones anteriores (para mostrar el historial) */
    private const MOTIVOS_ANTES = [
        'otra_area' => 'Está en otra área', 'en_uso' => 'En lavado o en uso', 'aparecio' => 'Apareció lo que faltaba',
        'de_otra_area' => 'Lo trajeron de otra área', 'nuevo' => 'Llegó nuevo', 'otro_sobra' => 'Otro motivo',
    ];
    public const MOTIVOS = self::MOTIVOS_FALTA + self::MOTIVOS_SOBRA + self::MOTIVOS_ANTES;
    /** Nombre del autor: trabajador o administrador */
    private const AUTOR = "COALESCE(t.nombre, 'Administrador') AS trabajador";

    public const TIPOS_ITEM = [
        'cantidad' => 'Cantidad (se cuenta)',
        'estado'   => 'Solo estado (bien / novedad / dañado)',
        'pregunta' => 'Pregunta (Sí / Poco / No)',
        'texto'    => 'Respuesta escrita',
    ];
    public const RESPUESTAS = ['si' => 'Sí', 'poco' => 'Poco', 'no' => 'No'];
    public const TIPOS_AREA = ['inventario' => 'Inventario (loza y utensilios)', 'pedido' => 'Lista de pedido'];

    /** Catálogo del establecimiento: área => [tipo, ayuda de novedades, [nombre => tipo de producto]] */
    private const SEMILLA = [
        'Fríos' => ['inventario', 'Novedades de Fríos (ej.: se partió la cuchilla, falta algo)', [
            'Vaso 14 oz' => 'cantidad', 'Copa aromática' => 'cantidad', 'Vaso cervecero' => 'cantidad', 'Copa pequeña' => 'cantidad',
            'Ceniceros' => 'cantidad', 'Jarras' => 'cantidad', 'Vaso 7 oz plástico' => 'cantidad', 'Shot' => 'cantidad',
        ]],
        'Cocina' => ['inventario', 'Novedades de cocina: carne, pollo, empanadas, etc.', [
            'Plato ovalado' => 'cantidad', 'Plato rectangular' => 'cantidad', 'Plato redondo' => 'cantidad',
            'Plato sopa' => 'cantidad', 'Taza de papas' => 'cantidad', 'Salseros' => 'cantidad',
        ]],
        'Greca' => ['inventario', 'Alguna novedad de greca', [
            'Pocillo tinto' => 'cantidad', 'Pocillo chocolatero' => 'cantidad', 'Platos' => 'cantidad', 'Pocillo expreso' => 'cantidad',
            'Pocillo aluminio' => 'cantidad', 'Platos de pan' => 'cantidad', 'Pocillo migao' => 'cantidad',
        ]],
        'Caja' => ['inventario', 'Observaciones de caja', [
            '¿Te quedan monedas o sencillo?' => 'pregunta', '¿Te quedan hojas de arqueo?' => 'pregunta', '¿Cuánto café queda?' => 'texto',
        ]],
        // Listas de pedido: los productos se agregan cuando lleguen los formatos
        'Gaseosa'       => ['pedido', 'Observaciones del pedido', []],
        'Plásticos'     => ['pedido', 'Observaciones del pedido', []],
        'Almacén'       => ['pedido', 'Observaciones del pedido', []],
        'Pulpas'        => ['pedido', 'Observaciones del pedido', []],
        'Salsamentaria' => ['pedido', 'Observaciones del pedido', []],
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
        LocalService::asegurar();
        self::migrar();
        if (Settings::get('catalogo_mekatos') !== '1') {
            Database::transaction(fn() => self::cargarCatalogo());
            Settings::set('catalogo_mekatos', '1');
        }
        if (Settings::get('catalogo_v2') !== '1') {
            Database::transaction(fn() => CatalogoMekatos::cargar());
            Settings::set('catalogo_v2', '1');
        }
    }

    /**
     * Carga el catálogo del establecimiento. Los productos de prueba anteriores se borran
     * (o se desactivan si ya tienen inventarios, para no perder el historial).
     * Las áreas existentes conservan su código QR.
     */
    private static function cargarCatalogo(): void
    {
        $existentes = (int)Database::value('SELECT COUNT(*) FROM areas') > 0;
        if ($existentes) {
            Database::run("UPDATE areas SET nombre = 'Greca' WHERE nombre = 'Caja o greca'");
            foreach (Database::all('SELECT id FROM inventario_items') as $it) {
                self::eliminarItemInterno((int)$it['id']);
            }
        }
        $orden = 0;
        foreach (self::SEMILLA as $nombre => [$tipo, $ayuda, $items]) {
            $orden++;
            $a = Database::one('SELECT * FROM areas WHERE nombre = ? AND local_id = 1', [$nombre]);
            if ($a) {
                Database::update('areas', ['tipo' => $tipo, 'ayuda_obs' => $ayuda, 'orden' => $orden, 'activo' => 1], 'id = :id', ['id' => $a['id']]);
                $aid = (int)$a['id'];
            } else {
                $aid = Database::insert('areas', [
                    'local_id' => 1, 'nombre' => $nombre, 'token' => self::nuevoToken(), 'tipo' => $tipo, 'ayuda_obs' => $ayuda,
                    'orden' => $orden, 'activo' => 1, 'creado' => now_utc(),
                ]);
            }
            $i = 0;
            foreach ($items as $item => $tipoItem) {
                Database::insert('inventario_items', [
                    'area_id' => $aid, 'nombre' => $item, 'tipo' => $tipoItem, 'orden' => ++$i,
                    'activo' => 1, 'creado' => now_utc(), 'actualizado' => now_utc(),
                ]);
            }
        }
        if ($existentes) {
            Audit::log('catalogo_cargado', null, null, 'Catálogo de Fríos, Cocina, Greca, Caja y listas de pedido');
        }
    }

    /** Borra un producto sin historial; si ya tiene inventarios lo desactiva. Devuelve true si lo borró. */
    public static function eliminarItemInterno(int $id): bool
    {
        $usado = (int)Database::value('SELECT COUNT(*) FROM inventario_lineas l JOIN inventarios i ON i.id = l.inventario_id WHERE l.item_id = ? AND i.estado = \'enviado\'', [$id]) > 0;
        if ($usado) {
            Database::update('inventario_items', ['activo' => 0, 'actualizado' => now_utc()], 'id = :id', ['id' => $id]);
            return false;
        }
        $foto = Database::value('SELECT foto FROM inventario_items WHERE id = ?', [$id]);
        if ($r = self::rutaFoto($foto ? (string)$foto : null)) {
            @unlink($r);
        }
        Database::run('DELETE FROM stock_movimientos WHERE item_id = ?', [$id]);
        Database::run('UPDATE inventario_lineas SET item_id = NULL WHERE item_id = ?', [$id]);
        Database::run('DELETE FROM inventario_items WHERE id = ?', [$id]);
        return true;
    }

    public static function eliminarItem(int $id): string
    {
        $it = self::item($id) ?? throw new InvalidArgumentException('El elemento no existe.');
        $borrado = self::eliminarItemInterno($id);
        Audit::log($borrado ? 'item_eliminado' : 'item_desactivado', 'inventario_item', $id, null, ['nombre' => $it['nombre']], null);
        return $borrado ? 'eliminado' : 'desactivado';
    }

    public static function crearArea(string $nombre, string $tipo, string $ayuda, ?int $localId = null): int
    {
        $localId ??= LocalService::actualId();
        $nombre = trim(preg_replace('/\s+/', ' ', $nombre));
        if (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 60) {
            throw new InvalidArgumentException('El nombre de la categoría debe tener entre 2 y 60 caracteres.');
        }
        if (Database::value('SELECT 1 FROM areas WHERE nombre = ? AND activo = 1 AND local_id = ?', [$nombre, $localId])) {
            throw new InvalidArgumentException('Ya existe una categoría con ese nombre en este local.');
        }
        $id = Database::insert('areas', [
            'local_id' => $localId, 'nombre' => $nombre, 'token' => self::nuevoToken(), 'tipo' => isset(self::TIPOS_AREA[$tipo]) ? $tipo : 'inventario',
            'ayuda_obs' => mb_substr(trim($ayuda), 0, 160) ?: null,
            'orden' => (int)Database::value('SELECT COALESCE(MAX(orden), 0) + 1 FROM areas WHERE local_id = ?', [$localId]), 'activo' => 1, 'creado' => now_utc(),
        ]);
        Audit::log('area_creada', 'area', $id, null, null, ['nombre' => $nombre, 'tipo' => $tipo, 'local' => LocalService::nombre($localId)]);
        return $id;
    }

    public static function editarArea(int $id, string $nombre, string $ayuda): void
    {
        $a = self::area($id) ?? throw new InvalidArgumentException('La categoría no existe.');
        $nombre = trim(preg_replace('/\s+/', ' ', $nombre));
        if (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 60) {
            throw new InvalidArgumentException('El nombre de la categoría debe tener entre 2 y 60 caracteres.');
        }
        if (Database::value('SELECT 1 FROM areas WHERE nombre = ? AND activo = 1 AND local_id = ? AND id <> ?', [$nombre, $a['local_id'], $id])) {
            throw new InvalidArgumentException('Ya existe una categoría con ese nombre en este local.');
        }
        Database::update('areas', ['nombre' => $nombre, 'ayuda_obs' => mb_substr(trim($ayuda), 0, 160) ?: null], 'id = :id', ['id' => $id]);
        Audit::log('area_editada', 'area', $id, null, ['nombre' => $a['nombre'], 'ayuda' => $a['ayuda_obs']], ['nombre' => $nombre, 'ayuda' => $ayuda]);
    }

    /** Quita la categoría (su QR deja de funcionar); el historial se conserva */
    public static function quitarArea(int $id): void
    {
        $a = self::area($id) ?? throw new InvalidArgumentException('La categoría no existe.');
        Database::update('areas', ['activo' => 0], 'id = :id', ['id' => $id]);
        Audit::log('area_quitada', 'area', $id, null, ['nombre' => $a['nombre']], null);
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
        if (!$col('areas', 'tipo')) {
            $pdo->exec("ALTER TABLE areas ADD tipo ENUM('inventario','pedido') NOT NULL DEFAULT 'inventario' AFTER token, ADD ayuda_obs VARCHAR(160) NULL AFTER tipo");
        }
        if (!$col('inventario_items', 'unidad')) {
            $pdo->exec("ALTER TABLE inventario_items MODIFY tipo ENUM('cantidad','estado','pregunta','texto') NOT NULL DEFAULT 'cantidad',
                        ADD unidad VARCHAR(20) NULL AFTER cantidad_esperada, ADD precio DECIMAL(12,2) NULL AFTER unidad");
        }
        if (!$col('inventario_lineas', 'respuesta')) {
            $pdo->exec("ALTER TABLE inventario_lineas MODIFY tipo ENUM('cantidad','estado','pregunta','texto') NOT NULL DEFAULT 'cantidad',
                        ADD respuesta VARCHAR(255) NULL AFTER cantidad, ADD pedir INT NULL AFTER respuesta,
                        ADD responsable_id INT UNSIGNED NULL AFTER pedir, ADD responsable_otro VARCHAR(120) NULL AFTER responsable_id");
        }
        if (!Database::value("SHOW TABLES LIKE 'stock_movimientos'") || !Database::value("SHOW TABLES LIKE 'roturas'")) {
            $schema = is_file(APP_PATH . '/schema.sql') ? APP_PATH . '/schema.sql' : ROOT_PATH . '/database/schema.sql';
            Database::ejecutarScript($schema);
        }
        if (!$col('inventario_items', 'minimo')) {
            $pdo->exec('ALTER TABLE inventario_items ADD minimo DECIMAL(10,2) NULL AFTER precio, ADD optimo DECIMAL(10,2) NULL AFTER minimo');
        }
        if (!$col('inventario_lineas', 'hay')) {
            $pdo->exec('ALTER TABLE inventario_lineas MODIFY pedir DECIMAL(10,2) NULL,
                        ADD hay DECIMAL(10,2) NULL AFTER respuesta, ADD minimo DECIMAL(10,2) NULL AFTER hay, ADD optimo DECIMAL(10,2) NULL AFTER minimo');
        }
        if (!str_contains((string)(Database::one("SHOW COLUMNS FROM stock_movimientos LIKE 'tipo'")['Type'] ?? ''), 'conteo')) {
            $pdo->exec("ALTER TABLE stock_movimientos MODIFY tipo ENUM('base','baja','ingreso','ajuste','reinicio','conteo') NOT NULL");
        }
    }

    public static function nuevoToken(): string
    {
        return bin2hex(random_bytes(12));
    }

    /* ---------------- Áreas e ítems ---------------- */

    /** Categorías; con $localId, solo las de ese local */
    public static function areas(bool $soloActivas = false, ?int $localId = null): array
    {
        $w = [];
        if ($soloActivas) {
            $w[] = 'activo = 1';
        }
        if ($localId) {
            $w[] = 'local_id = ' . (int)$localId;
        }
        return Database::all('SELECT * FROM areas' . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY orden, nombre');
    }

    /** Categorías de inventario (no listas de pedido) */
    public static function areasInventario(?int $localId = null): array
    {
        return array_values(array_filter(self::areas(true, $localId), fn($a) => $a['tipo'] === 'inventario'));
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
        $tipo = isset(self::TIPOS_ITEM[$d['tipo'] ?? '']) ? $d['tipo'] : 'cantidad';
        if ($area['tipo'] === 'pedido') {
            $tipo = 'cantidad';   // en las listas de pedido todo se cuenta
        }
        $esp = $area['tipo'] === 'pedido' ? '' : trim((string)($d['cantidad_esperada'] ?? ''));
        if ($esp !== '' && (!ctype_digit($esp) || (int)$esp > 100000)) {
            throw new InvalidArgumentException('La cantidad esperada debe ser un número entero.');
        }
        $dec = function (string $campo, string $etiqueta) use ($d): ?float {
            $v = str_replace(',', '.', trim((string)($d[$campo] ?? '')));
            if ($v === '') {
                return null;
            }
            if (preg_match('#^(\d+)/(\d+)$#', $v, $m) && (int)$m[2] > 0) {   // admite 1/2
                return round((int)$m[1] / (int)$m[2], 2);
            }
            if (!is_numeric($v) || (float)$v < 0 || (float)$v > 100000) {
                throw new InvalidArgumentException("$etiqueta debe ser un número (puede llevar decimales, como 0.5).");
            }
            return round((float)$v, 2);
        };
        $minimo = $area['tipo'] === 'pedido' ? $dec('minimo', 'El mínimo') : null;
        $optimo = $area['tipo'] === 'pedido' ? $dec('optimo', 'La cantidad óptima') : null;
        if ($minimo !== null && $optimo !== null && $minimo > $optimo) {
            throw new InvalidArgumentException('El mínimo no puede ser mayor que la cantidad óptima.');
        }
        $precio = str_replace(['$', '.', ' '], '', trim((string)($d['precio'] ?? '')));
        if ($precio !== '' && (!ctype_digit($precio) || (int)$precio > 100000000)) {
            throw new InvalidArgumentException('El precio debe ser un número entero, sin decimales.');
        }
        return [
            'area_id'           => (int)$area['id'],
            'nombre'            => $nombre,
            'tipo'              => $tipo,
            'cantidad_esperada' => ($tipo === 'cantidad' && $esp !== '') ? (int)$esp : null,
            'unidad'            => $tipo === 'cantidad' ? (mb_substr(trim((string)($d['unidad'] ?? '')), 0, 20) ?: null) : null,
            'precio'            => $tipo === 'cantidad' && $precio !== '' && $area['tipo'] !== 'pedido' ? (int)$precio : null,
            'minimo'            => $minimo,
            'optimo'            => $optimo,
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
        $esPedido = (self::area($areaId)['tipo'] ?? 'inventario') === 'pedido';
        $lineas = [];
        foreach (self::items($areaId) as $it) {
            $d = $datos[$it['id']] ?? $datos[(string)$it['id']] ?? [];
            $cant = trim((string)($d['cantidad'] ?? ''));
            $resp = mb_substr(trim((string)($d['respuesta'] ?? '')), 0, 255);
            if ($it['tipo'] === 'pregunta' && $resp !== '' && !isset(self::RESPUESTAS[$resp])) {
                throw new InvalidArgumentException('Respuesta no válida en "' . $it['nombre'] . '".');
            }
            if ($exigirCompleto && in_array($it['tipo'], ['pregunta', 'texto'], true) && $resp === '') {
                throw new InvalidArgumentException('Responde: ' . $it['nombre']);
            }
            $estado = (string)($d['estado'] ?? '');
            $obs = mb_substr(trim((string)($d['observacion'] ?? '')), 0, 255);
            $motivo = '';   // el motivo se pregunta después de enviar el conteo
            if ($esPedido) {
                // Listas de pedido: se cuenta lo que hay (admite medios: 0.5) y se calcula cuánto pedir
                $cant = str_replace(',', '.', $cant);
                if (preg_match('#^(\d+)/(\d+)$#', $cant, $m) && (int)$m[2] > 0) {
                    $cant = (string)round((int)$m[1] / (int)$m[2], 2);
                }
                if ($cant !== '' && (!is_numeric($cant) || (float)$cant < 0 || (float)$cant > 100000)) {
                    throw new InvalidArgumentException('Lo que hay de "' . $it['nombre'] . '" debe ser un número (puedes usar 0.5 para medio).');
                }
                if ($exigirCompleto && $cant === '') {
                    throw new InvalidArgumentException('Falta contar "' . $it['nombre'] . '". Si no hay, escribe 0.');
                }
                $hay = $cant !== '' ? round((float)$cant, 2) : null;
                $lineas[] = [
                    'item_id' => (int)$it['id'], 'item_nombre' => $it['nombre'], 'tipo' => 'cantidad',
                    'cantidad_esperada' => null, 'cantidad' => null, 'respuesta' => null,
                    'hay' => $hay, 'minimo' => $it['minimo'], 'optimo' => $it['optimo'],
                    'pedir' => $hay !== null ? self::cuantoPedir($hay, $it['minimo'], $it['optimo']) : null,
                    'estado' => $exigirCompleto ? 'bien' : null, 'observacion' => $obs !== '' ? $obs : null,
                    'motivo_faltante' => null, 'es_base' => 0,
                ];
                continue;
            }
            if ($cant !== '' && (!ctype_digit($cant) || (int)$cant > 100000)) {
                throw new InvalidArgumentException('La cantidad de "' . $it['nombre'] . '" debe ser un número entero.');
            }

            $esperada = $it['cantidad_esperada'] !== null ? (int)$it['cantidad_esperada'] : null;
            // Primer conteo de un elemento sin stock definido: esa cantidad queda como stock
            // (en las listas de pedido el "debe haber" lo pone el administrador)
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
                'respuesta'         => in_array($it['tipo'], ['pregunta', 'texto'], true) && $resp !== '' ? $resp : null,
                'hay'               => null, 'minimo' => null, 'optimo' => null,
                'pedir'             => null,
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

    /**
     * Cuánto pedir: si lo que hay llega al mínimo (o menos), se pide lo que falta para el óptimo.
     * Sin mínimo, se pide cuando hay menos del óptimo. Sin óptimo, se completa el mínimo.
     */
    public static function cuantoPedir(float $hay, $minimo, $optimo): float
    {
        $min = $minimo !== null && $minimo !== '' ? (float)$minimo : null;
        $opt = $optimo !== null && $optimo !== '' ? (float)$optimo : null;
        $meta = $opt ?? $min;
        if ($meta === null) {
            return 0.0;
        }
        $toca = $min !== null ? $hay <= $min : $hay < $meta;
        return $toca ? max(0.0, round($meta - $hay, 2)) : 0.0;
    }

    /** 1.50 → "1½" ; 2.00 → "2" ; 0.25 → "0.25" */
    /** "Pedido Cocina" (sin repetir "Pedido" si la lista ya se llama así) */
    public static function titulo(array $inv): string
    {
        if (($inv['area_tipo'] ?? '') !== 'pedido') {
            return 'Inventario ' . $inv['area'];
        }
        return str_starts_with(mb_strtolower($inv['area']), 'pedido') ? $inv['area'] : 'Pedido ' . $inv['area'];
    }

    public static function numero($n): string
    {
        if ($n === null || $n === '') {
            return '—';
        }
        $f = round((float)$n, 2);
        $ent = (int)floor($f);
        $dec = round($f - $ent, 2);
        if ($dec == 0.0) {
            return (string)$ent;
        }
        if ($dec == 0.5) {
            return ($ent ? $ent : '') . '½';
        }
        return rtrim(rtrim(number_format($f, 2, '.', ''), '0'), '.');
    }

    public static function contarDiferencias(array $lineas): int
    {
        $n = 0;
        foreach ($lineas as $l) {
            if (($l['tipo'] === 'cantidad' && ($l['hay'] ?? null) === null && $l['cantidad_esperada'] !== null && $l['cantidad'] !== null && (int)$l['cantidad'] !== (int)$l['cantidad_esperada'])
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
            if (self::aplicarConteo($lineas, $id, $nombreT)) {
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
        $inv = Database::one('SELECT a.tipo FROM inventarios i JOIN areas a ON a.id = i.area_id WHERE i.id = ?', [$inventarioId]);
        if (($inv['tipo'] ?? '') === 'pedido') {
            return [];
        }
        foreach (self::lineas($inventarioId) as $l) {
            if ($l['es_base'] || $l['tipo'] !== 'cantidad' || $l['cantidad_esperada'] === null || $l['cantidad'] === null) {
                continue;
            }
            $d = (int)$l['cantidad'] - (int)$l['cantidad_esperada'];
            if ($d !== 0) {
                $ant = $l['item_id'] ? self::conteoAnterior($inventarioId, (int)$l['item_id']) : null;
                $out[] = ['linea' => (int)$l['id'], 'nombre' => $l['item_nombre'], 'dif' => $d, 'contado' => (int)$l['cantidad'],
                    'antes' => (int)$l['cantidad_esperada'], 'motivo' => $l['motivo_faltante'],
                    'anterior' => $ant ? $ant['trabajador'] . ' (' . mb_strtolower(self::TURNOS[$ant['turno']]) . ($ant['fecha'] !== self::buscar($inventarioId)['fecha'] ? ' del ' . fmt_fecha($ant['fecha']) : '') . ')' : null];
            }
        }
        return $out;
    }

    /** Quién hizo el conteo anterior de ese elemento (el que dejó el stock contra el que se compara) */
    public static function conteoAnterior(int $inventarioId, int $itemId): ?array
    {
        $inv = Database::one('SELECT enviado FROM inventarios WHERE id = ?', [$inventarioId]);
        if (!$inv) {
            return null;
        }
        return Database::one(
            "SELECT i.id, i.fecha, i.turno, i.trabajador_id, " . self::AUTOR . " FROM inventario_lineas l
             JOIN inventarios i ON i.id = l.inventario_id LEFT JOIN trabajadores t ON t.id = i.trabajador_id
             WHERE l.item_id = ? AND l.cantidad IS NOT NULL AND i.estado = 'enviado' AND i.id <> ? AND i.enviado <= ?
             ORDER BY i.enviado DESC, i.id DESC LIMIT 1",
            [$itemId, $inventarioId, $inv['enviado']]
        );
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
            // "El turno anterior contó mal": queda el nombre de quien hizo el conteo anterior (no se cobra)
            if ($motivo === 'mal_conteo') {
                $previa = (string)Database::value('SELECT observacion FROM inventario_lineas WHERE id = ?', [$p['linea']]);
                if ($obs === '' && !str_starts_with($previa, 'Contó mal')) {
                    $obs = $previa;
                }
                $obs = 'Contó mal: ' . ($p['anterior'] ?? 'el conteo anterior') . ($obs !== '' && !str_starts_with($obs, 'Contó mal') ? ' · ' . $obs : '');
            }
            $c = ['motivo_faltante' => $motivo, 'responsable_id' => null, 'responsable_otro' => null] + ($obs !== '' ? ['observacion' => mb_substr($obs, 0, 255)] : []);
            // Si se rompió: ¿quién lo partió?
            if ($motivo === 'roto') {
                $resp = (string)($r['responsable'] ?? '');
                if ($resp === 'otro') {
                    $quien = mb_substr(trim((string)($r['responsable_otro'] ?? '')), 0, 120);
                    if (mb_strlen($quien) < 3) {
                        throw new InvalidArgumentException('Escribe quién partió "' . $p['nombre'] . '" (por ejemplo: un cliente).');
                    }
                    $c['responsable_otro'] = $quien;
                } elseif (ctype_digit($resp) && Database::value('SELECT 1 FROM trabajadores WHERE id = ? AND activo = 1', [(int)$resp])) {
                    $c['responsable_id'] = (int)$resp;
                } else {
                    throw new InvalidArgumentException('Elige quién partió "' . $p['nombre'] . '".');
                }
                $c['_cantidad'] = -$p['dif'];
            }
            $cambios[$p['linea']] = $c;
        }
        Database::transaction(function () use ($inventarioId, $cambios, $inv) {
            Database::run('DELETE FROM roturas WHERE inventario_id = ?', [$inventarioId]);
            foreach ($cambios as $lineaId => $c) {
                $cant = $c['_cantidad'] ?? null;
                unset($c['_cantidad']);
                Database::update('inventario_lineas', $c, 'id = :id AND inventario_id = :inv', ['id' => $lineaId, 'inv' => $inventarioId]);
                if ($cant) {
                    $l = Database::one('SELECT * FROM inventario_lineas WHERE id = ?', [$lineaId]);
                    $precio = $l['item_id'] ? (float)Database::value('SELECT COALESCE(precio, 0) FROM inventario_items WHERE id = ?', [$l['item_id']]) : 0;
                    Database::insert('roturas', [
                        'inventario_id' => $inventarioId, 'linea_id' => $lineaId, 'item_id' => $l['item_id'], 'item_nombre' => $l['item_nombre'],
                        'trabajador_id' => $c['responsable_id'], 'responsable_otro' => $c['responsable_otro'], 'cantidad' => $cant,
                        'fecha' => $inv['fecha'], 'observacion' => $l['observacion'], 'precio_unitario' => $precio,
                        'creado' => now_utc(), 'actualizado' => now_utc(),
                    ]);
                }
            }
            Database::update('inventarios', ['motivos_pendientes' => 0, 'actualizado' => now_utc()], 'id = :id', ['id' => $inventarioId]);
        });
        Audit::log('inventario_explicado', 'inventario', $inventarioId, null, null, array_map(fn($c) => self::MOTIVOS[$c['motivo_faltante']]
            . (!empty($c['responsable_id']) ? ' · ' . Database::value('SELECT nombre FROM trabajadores WHERE id = ?', [$c['responsable_id']]) : '')
            . (!empty($c['responsable_otro']) ? ' · ' . $c['responsable_otro'] : ''), $cambios));
        self::generarPdf($inventarioId);
    }

    /* ---------------- Stock (cantidad que debe haber) ---------------- */

    /**
     * Lo que se contó queda como las unidades que hay en el local (falte o sobre):
     * el siguiente turno se compara contra este conteo.
     * @return bool si hubo algún primer conteo (stock inicial)
     */
    private static function aplicarConteo(array $lineas, int $inventarioId, string $actor): bool
    {
        $base = false;
        foreach ($lineas as $l) {
            if ($l['tipo'] !== 'cantidad' || $l['cantidad'] === null || !$l['item_id']) {
                continue;
            }
            $it = Database::one('SELECT id, cantidad_esperada FROM inventario_items WHERE id = ? FOR UPDATE', [$l['item_id']]);
            if (!$it) {
                continue;
            }
            $antes = $it['cantidad_esperada'] !== null ? (int)$it['cantidad_esperada'] : null;
            $ahora = (int)$l['cantidad'];
            $base = $base || !empty($l['es_base']);
            if ($antes === $ahora) {
                continue;
            }
            Database::update('inventario_items', ['cantidad_esperada' => $ahora, 'actualizado' => now_utc()], 'id = :id', ['id' => $it['id']]);
            self::movimiento((int)$it['id'], $antes === null ? 'base' : 'conteo', $ahora - (int)$antes, $antes, $ahora,
                $antes === null ? 'Primer conteo: queda como stock' : 'Conteo del turno: quedan ' . $ahora, $inventarioId, $actor);
        }
        return $base;
    }

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
            // como cualquier conteo, lo contado queda como las unidades que hay
            self::aplicarConteo($lineas, $id, 'Administrador ' . $admin['nombre']);
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
                "SELECT l.cantidad, l.cantidad_esperada AS antes, l.motivo_faltante, l.observacion, l.es_base, l.responsable_id, l.responsable_otro,
                        i.id AS inventario_id, i.fecha, i.turno, i.enviado, " . self::AUTOR . "
                 FROM inventario_lineas l JOIN inventarios i ON i.id = l.inventario_id LEFT JOIN trabajadores t ON t.id = i.trabajador_id
                 WHERE l.item_id = ? AND i.estado = 'enviado' AND l.cantidad IS NOT NULL ORDER BY i.enviado DESC, i.id DESC LIMIT 1",
                [$it['id']]
            ) : null;
            // diferencia del último conteo frente al anterior (después del conteo, el stock ya es lo contado)
            $dif = ($ult && !$ult['es_base'] && $ult['antes'] !== null) ? (int)$ult['cantidad'] - (int)$ult['antes'] : null;
            $out[] = ['item' => $it, 'ultimo' => $ult, 'dif' => $dif];
        }
        return $out;
    }

    /** Reportes de faltantes y daños (no incluye conteos que fijaron stock) */
    public static function faltantes(string $desde, string $hasta, ?int $areaId = null): array
    {
        $sql = "SELECT l.*, i.fecha, i.turno, i.enviado, i.area_id, a.nombre AS area, " . self::AUTOR . ",
                       COALESCE(rp.nombre, l.responsable_otro) AS responsable
                FROM inventario_lineas l JOIN inventarios i ON i.id = l.inventario_id JOIN areas a ON a.id = i.area_id
                LEFT JOIN trabajadores t ON t.id = i.trabajador_id LEFT JOIN trabajadores rp ON rp.id = l.responsable_id
                WHERE i.estado = 'enviado' AND l.es_base = 0 AND a.tipo = 'inventario' AND l.tipo = 'cantidad' AND i.fecha BETWEEN ? AND ?
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
    public static function alertasStock(?int $localId = null): array
    {
        $out = [];
        foreach (self::areasInventario($localId) as $a) {
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
            'SELECT i.*, a.nombre AS area, a.tipo AS area_tipo, a.local_id, ' . self::AUTOR . ' FROM inventarios i
             JOIN areas a ON a.id = i.area_id LEFT JOIN trabajadores t ON t.id = i.trabajador_id WHERE i.id = ?',
            [$id]
        );
    }

    public static function historial(array $f): array
    {
        $sql = "SELECT i.*, a.nombre AS area, a.tipo AS area_tipo, a.local_id, " . self::AUTOR . ",
                       (SELECT COUNT(*) FROM inventario_lineas x WHERE x.inventario_id = i.id AND x.pedir > 0) AS por_pedir FROM inventarios i
                JOIN areas a ON a.id = i.area_id LEFT JOIN trabajadores t ON t.id = i.trabajador_id
                WHERE i.estado = 'enviado' AND i.fecha BETWEEN ? AND ?";
        $p = [$f['desde'], $f['hasta']];
        foreach (['area_id' => 'i.area_id', 'trabajador_id' => 'i.trabajador_id', 'turno' => 'i.turno', 'local_id' => 'a.local_id', 'tipo' => 'a.tipo'] as $k => $col) {
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
            // si es el último conteo del elemento, la corrección también corrige las unidades que hay
            foreach ($nuevas as $l) {
                if ($l['tipo'] !== 'cantidad' || $l['cantidad'] === null || !$l['item_id'] || ($l['hay'] ?? null) !== null) {
                    continue;
                }
                $ultimo = (int)Database::value(
                    "SELECT i.id FROM inventario_lineas x JOIN inventarios i ON i.id = x.inventario_id
                     WHERE x.item_id = ? AND x.cantidad IS NOT NULL AND i.estado = 'enviado' ORDER BY i.enviado DESC, i.id DESC LIMIT 1",
                    [$l['item_id']]
                );
                $it = self::item((int)$l['item_id']);
                if ($ultimo === $id && $it && (int)$it['cantidad_esperada'] !== (int)$l['cantidad']) {
                    Database::update('inventario_items', ['cantidad_esperada' => (int)$l['cantidad'], 'actualizado' => now_utc()], 'id = :id', ['id' => $it['id']]);
                    self::movimiento((int)$it['id'], 'ajuste', (int)$l['cantidad'] - (int)$it['cantidad_esperada'], (int)$it['cantidad_esperada'], (int)$l['cantidad'], 'Corrección del inventario N.º ' . $id, $id);
                }
            }
        });
        $resumen = fn($ls) => array_combine(
            array_map(fn($l) => $l['item_nombre'], $ls),
            array_map(fn($l) => ($l['cantidad'] ?? '—') . ' / ' . (self::ESTADOS[$l['estado']] ?? '—') . ($l['observacion'] ? ' (' . $l['observacion'] . ')' : ''), $ls)
        );
        Audit::log('inventario_corregido', 'inventario', $id, $motivo, $resumen($antes) + ['observaciones' => $inv['observaciones']], $resumen($nuevas) + ['observaciones' => trim($obs)]);
        self::generarPdf($id);
    }

    /** Estado de los inventarios de un día: [area_id][turno] => inventario enviado o null */
    public static function tablero(string $fecha, ?int $localId = null): array
    {
        $out = [];
        foreach (self::areasInventario($localId) as $a) {
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
        return $limpio(LocalService::nombre((int)($inv['local_id'] ?? 1))) . '_' . (($inv['area_tipo'] ?? '') === 'pedido' ? '' : 'Inventario_') . $limpio($inv['area']) . '_' . $inv['fecha'] . '_' . $inv['turno'] . '.pdf';
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

        $esPedido = ($inv['area_tipo'] ?? '') === 'pedido';
        $unidades = [];
        foreach ($lineas as $l) {
            $unidades[$l['id']] = $l['item_id'] ? (string)Database::value('SELECT unidad FROM inventario_items WHERE id = ?', [$l['item_id']]) : '';
        }
        $pdf = new PdfSimple(self::titulo($inv) . ' ' . $inv['fecha']);
        $x0 = 15.0;
        $ancho = 180.0;
        $cols = $esPedido
            ? [['Foto', 18], ['Producto', 52], ['Mín. / Ópt.', 22], ['Hay', 18], ['Pedir', 20], ['Unidad', 24], ['Observación', 26]]
            : [['Foto', 18], ['Elemento', 52], ['Esperado', 20], ['Reportado', 22], ['Diferencia', 22], ['Estado', 22], ['Observación', 24]];
        $encabezado = function () use ($pdf, $inv, $x0, $ancho, $cols, $esPedido): float {
            $pdf->agregarPagina();
            $y = 12.0;
            if ($logo = self::logoJpg()) {
                $pdf->imagen($logo, $x0, $y, 34, 20);
            }
            $pdf->texto($x0 + 40, $y + 8, LocalService::nombre((int)$inv['local_id']), 11, false, 'L', 0, 0.35);
            $pdf->texto($x0 + 40, $y + 17, $pdf->recortar(self::titulo($inv), $ancho - 70, 18, true), 18, true);
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
                $pdf->texto($cx + 1.5, $y + 5.5, $t, 8.5, true, in_array($t, ['Esperado', 'Reportado', 'Diferencia', 'Mín. / Ópt.', 'Hay', 'Pedir'], true) ? 'R' : 'L', $w - 3, 1);
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
            $dif = (!$esPedido && $l['tipo'] === 'cantidad' && $l['cantidad_esperada'] !== null && $l['cantidad'] !== null)
                ? (int)$l['cantidad'] - (int)$l['cantidad_esperada'] : null;
            $alerta = ($dif !== null && $dif !== 0) || in_array($l['estado'], ['novedad', 'danado'], true)
                || ($esPedido && (float)$l['pedir'] > 0) || ($l['tipo'] === 'pregunta' && $l['respuesta'] === 'no');
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
            $pdf->texto($cx, $base, $esPedido
                ? self::numero($l['minimo']) . ' / ' . self::numero($l['optimo'])
                : ($l['cantidad_esperada'] !== null ? (string)$l['cantidad_esperada'] : '—'), 10.5, false, 'R', $cols[2][1] - 1.5);
            $cx += $cols[2][1];
            $reportado = $esPedido ? self::numero($l['hay']) : match ($l['tipo']) {
                'cantidad' => (string)($l['cantidad'] ?? '—'),
                'pregunta' => self::RESPUESTAS[$l['respuesta'] ?? ''] ?? '—',
                'texto'    => 'ver nota',
                default    => '—',
            };
            $pdf->texto($cx, $base, $reportado, $l['tipo'] === 'texto' ? 8.5 : 12, $l['tipo'] !== 'texto', 'R', $cols[3][1] - 1.5);
            $cx += $cols[3][1];
            if ($esPedido) {
                $pdf->texto($cx, $base, $l['pedir'] === null ? '—' : self::numero($l['pedir']), 12, (float)$l['pedir'] > 0, 'R', $cols[4][1] - 1.5);
                $cx += $cols[4][1];
                $pdf->texto($cx + 1.5, $base, $pdf->recortar($unidades[$l['id']] ?? '', $cols[5][1] - 3, 9), 9);
            } else {
                $pdf->texto($cx, $base, $dif === null ? '—' : ($dif > 0 ? '+' . $dif : (string)$dif), 11, $dif !== null && $dif !== 0, 'R', $cols[4][1] - 1.5);
                $cx += $cols[4][1];
                $pdf->texto($cx + 1.5, $base, in_array($l['tipo'], ['cantidad', 'estado'], true) ? (self::ESTADOS[$l['estado']] ?? '—') : '—', 9.5, $l['estado'] !== 'bien');
            }
            $cx += $cols[5][1];
            $quien = $l['responsable_id'] ? (string)Database::value('SELECT nombre FROM trabajadores WHERE id = ?', [$l['responsable_id']]) : ($l['responsable_otro'] ?? '');
            $obs = trim(implode(' · ', array_filter([
                $l['tipo'] === 'texto' ? (string)$l['respuesta'] : '',
                !empty($l['es_base']) ? 'Stock inicial' : '',
                ($l['motivo_faltante'] ?? '') === 'mal_conteo' ? '' : (self::MOTIVOS[$l['motivo_faltante'] ?? ''] ?? ((!$l['es_base'] && $l['cantidad_esperada'] !== null && $l['cantidad'] !== null && (int)$l['cantidad'] !== (int)$l['cantidad_esperada']) ? 'Sin explicar' : '')),
                $quien !== '' ? 'Lo partió: ' . $quien : '',
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
        $porPedir = count(array_filter($lineas, fn($l) => (float)$l['pedir'] > 0));
        $pdf->texto($x0, $y + 4, $esPedido
            ? ($porPedir ? "Productos por pedir: $porPedir" : 'No hay que pedir nada')
            : ($dif ? "Diferencias o novedades: $dif elemento(s)" : 'Sin diferencias ni novedades'), 11, true);
        $y += 10;
        if (!empty($inv['es_base'])) {
            $pdf->texto($x0, $y, $inv['origen'] === 'admin'
                ? 'Conteo del administrador: estas cantidades quedaron como las unidades que hay.'
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

    /* ---------------- WhatsApp ---------------- */

    /** Texto del reporte (inventario) o del pedido (lista) para enviar por WhatsApp */
    public static function mensajeWhatsapp(int $id): string
    {
        $inv = self::buscar($id);
        if (!$inv) {
            return '';
        }
        $lineas = self::lineas($id);
        $hora = utc_to_local($inv['enviado'])?->format('H:i') ?? '';
        $esPedido = $inv['area_tipo'] === 'pedido';
        $local = LocalService::nombre((int)$inv['local_id']);
        $t = '*' . self::titulo($inv) . '*' . ($esPedido ? '' : ' (' . self::TURNOS[$inv['turno']] . ')')
            . "\n" . $local . ' · ' . fmt_fecha($inv['fecha']) . ", $hora · " . $inv['trabajador'] . "\n";
        if ($esPedido) {
            $lista = [];
            foreach ($lineas as $l) {
                if ((float)$l['pedir'] > 0) {
                    $u = $l['item_id'] ? (string)Database::value('SELECT unidad FROM inventario_items WHERE id = ?', [$l['item_id']]) : '';
                    $lista[] = '• ' . $l['item_nombre'] . ': pedir *' . self::numero($l['pedir']) . '*' . ($u ? " $u" : '') . ' (hay ' . self::numero($l['hay']) . ')';
                }
            }
            $t .= $lista ? "\n" . implode("\n", $lista) : "\nNo hay que pedir nada.";
        } else {
            $dif = [];
            foreach ($lineas as $l) {
                if ($l['tipo'] === 'pregunta' || $l['tipo'] === 'texto') {
                    $dif[] = '• ' . $l['item_nombre'] . ' ' . ($l['tipo'] === 'pregunta' ? (self::RESPUESTAS[$l['respuesta'] ?? ''] ?? '—') : (string)$l['respuesta']);
                    continue;
                }
                if ($l['es_base'] || $l['cantidad_esperada'] === null || $l['cantidad'] === null) {
                    continue;
                }
                $d = (int)$l['cantidad'] - (int)$l['cantidad_esperada'];
                if ($d === 0 && $l['estado'] === 'bien') {
                    continue;
                }
                $quien = $l['responsable_id'] ? (string)Database::value('SELECT nombre FROM trabajadores WHERE id = ?', [$l['responsable_id']]) : ($l['responsable_otro'] ?? '');
                $dif[] = '• ' . $l['item_nombre'] . ': ' . ($d < 0 ? 'faltan ' . (-$d) : ($d > 0 ? 'sobran ' . $d : self::ESTADOS[$l['estado']]))
                    . ($d !== 0 ? ' (hay ' . (int)$l['cantidad'] . ', antes ' . (int)$l['cantidad_esperada'] . ')' : '')
                    . ($l['motivo_faltante'] && $l['motivo_faltante'] !== 'mal_conteo' ? ' · ' . (self::MOTIVOS[$l['motivo_faltante']] ?? '') : (!$l['motivo_faltante'] && $d !== 0 ? ' · sin explicar' : ''))
                    . ($quien !== '' ? ' · lo partió ' . $quien : '')
                    . ($l['observacion'] ? ' · ' . $l['observacion'] : '');
            }
            $t .= $dif ? "\n" . implode("\n", $dif) : "\nTodo completo.";
            if ($inv['es_base']) {
                $t .= "\n(Primer conteo: quedó como stock)";
            }
        }
        if ($inv['observaciones']) {
            $t .= "\n\nNovedades: " . $inv['observaciones'];
        }
        return $t;
    }

    /** Enlace de WhatsApp al número del administrador (configuración) con el mensaje listo */
    public static function urlWhatsapp(string $texto): string
    {
        $num = preg_replace('/\D/', '', Settings::get('whatsapp_admin'));
        return 'https://wa.me/' . $num . '?text=' . rawurlencode($texto);
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
