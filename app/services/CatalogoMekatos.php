<?php
declare(strict_types=1);

/**
 * Carga (una sola vez) las listas de pedido de Mekatos 1 a partir de los formatos del local
 * y la loza de Mekatos 2 (copia independiente de la de Mekatos 1).
 * Todo queda editable desde Categorías y productos.
 */
final class CatalogoMekatos
{
    /** lista => [texto de novedades, [producto, mínimo, óptimo, unidad]] (null = sin dato) */
    public const PEDIDOS = [
        'Pedido Gaseosa y cerveza' => ['Novedades de la nevera', [
            ['Coca-Cola 350', null, 2, 'cajas'],
            ['Coca-Cola 400', null, 1, 'cajas'],
            ['Ginger 400', null, 2, 'cajas'],
            ['Soda 350', null, 2, 'cajas'],
            ['Sprite y Cuatro 350', null, 1, 'cajas'],
            ['Agua natural y con gas', null, 1, 'bandejas'],
            ['Jugos Hit en caja surtido', null, 1, 'cajas'],
            ['Coca-Cola 1.5 L', null, 2, 'unidades'],
            ['Cuatro o Sprite 1.5 L', null, 1, 'unidades'],
            ['Cuatro 3 L', null, 2, 'unidades'],
            ['Coca-Cola 3 L', null, 2, 'unidades'],
            ['Pony 1.5 L', null, 2, 'unidades'],
            ['Pony lata o vidrio', null, 1, 'unidades'],
            ['Águila', null, 0.5, 'canastas'],
            ['Coronita', null, 1, 'cajas'],
            ['Costeña', null, 0.5, 'bandejas'],
            ['Gatorade mandarina/tropical', null, 1, 'unidades'],
        ]],
        'Pedido Cocina' => ['Novedades de cocina', [
            ['Carne para desmechar', 10, 30, 'bolsas'],
            ['Pollo para desmechar', 10, 30, 'bolsas'],
            ['Carne para hamburguesa', 20, 60, 'unidades'],
            ['Pollo para hamburguesa', 3, 9, 'unidades'],
            ['Masa para empanadas', 6, 12, 'paquetes'],
            ['Queso doble crema (entero y tajado)', 0.5, 1, 'bloques'],
            ['Queso campesino', 2, 3, 'kilos'],
            ['Queso amarillo', 1, 3, 'paquetes'],
            ['Maíz tierno', 2, 6, 'kilos'],
            ['Tocineta', 2, 3, 'kilos'],
            ['Salchicha americana Zenú', 0.5, 2, 'paquetes'],
            ['Chorizo de ternera Zenú', 1, 3, 'paquetes'],
            ['Jalapeños', 0.5, 1, 'frascos'],
            ['Huevos', 1, 1, 'cubetas'],
            ['Papa perro chips naturales', 0.5, 1, 'paquetes'],
            ['Picante salsa roja', 0.5, 1, 'galones'],
            ['Paprika en polvo', 0.5, 1, 'paquete o tarro'],
            ['Cebolla en polvo', 0.5, 1, 'paquete o tarro'],
            ['Ajo en polvo', 0.5, 1, 'paquete o tarro'],
            ['Sal', 1, 2, 'libras'],
            ['Azúcar morena', 1, 2, 'libras'],
            ['Cebolla larga', 3, 6, 'varas'],
            ['Cebolla cabezona morada', 3, 6, 'unidades'],
            ['Tomate chonto', 6, 9, 'unidades'],
            ['Limones', 2, 3, 'docenas'],
            ['Lechuga crespa', 1, 2, 'paquetes'],
            ['Perejil crespo o liso', 0.5, 1, 'paquetes'],
            ['Cilantro', 0.5, 1, 'paquetes'],
            ['Ajo en pasta o cabezas', 1, 2, 'sobres'],
            ['Piña golden', 1, 6, 'piñas'],
            ['Especias', 0.25, 1, 'tarros'],
            ['Salsa de soya', 0.5, 1, 'galones'],
            ['Aguacate', 0.5, 1, 'unidades'],
            ['Leche de coco en polvo', 3, 6, 'unidades'],
            ['Helado', null, 1, 'cajas'],
            ['Bolsa ziplock', 3, 6, 'paquetes'],
            ['Leche condensada', 2, 2, 'tazas'],
        ]],
        'Pedido Fríos' => ['Novedades de fríos', [
            ['Pulpa de fruta (uva, mora, fresa, guanábana, maracuyá)', 2, 3, 'paquetes x 10'],
            ['Papa francesa y papa criolla', 2, 6, 'paquetes'],
            ['Salchicha americana', 1, 3, 'paquetes'],
            ['Salchicha llanera', 1, 3, 'paquetes'],
            ['Chorizo de ternera', 3, 6, 'paquetes'],
            ['Pulpa micheladas, cereza, coco, hierbabuena', 1, 1, 'paquetes por sabor'],
            ['Hielo', 3, 6, 'bolsas de 5 L'],
            ['Maracuyá en pepa', 3, 6, 'unidades'],
            ['Fresa fruta x 500 g', 0.5, 1, 'paquetes'],
            ['Cereza marrasquino (frasco de vidrio)', 0.5, 2, 'frascos'],
            ['Gomitas, gusanitos, banderas o dulces', 3, 6, 'paquetes'],
        ]],
        'Pedido Panadería' => ['Novedades de panadería', [
            ['Harina de trigo', 2, 4, 'bultos'],
            ['Azúcar a granel', 1, 3, 'bultos'],
            ['Bocadillo', 0.5, 2, 'arrobas'],
            ['Salvado', 1, 3, 'kilos'],
            ['Vitina', 3, 4, 'barras'],
            ['Astra mantequilla', 2, 6, 'cajas'],
            ['Maní partido', 1, 2, 'libras'],
            ['Uva pasa', 1, 3, 'libras'],
            ['Ajonjolí', 0.5, 1, 'libras'],
            ['Queso campesino', 2, 3, 'rodajas'],
            ['Pichero', 3, 12, 'botellas'],
            ['Mortadela', 3, 20, 'libras'],
            ['Papel para brownie', 0.5, 1, 'libras'],
            ['Aceite', 2, 10, 'litros'],
            ['Huevos', 6, 12, 'cubetas'],
            ['Sal', 3, 12, 'kilos'],
        ]],
        'Pedido Cafetería y Caja' => ['Novedades de cafetería y caja', [
            ['Aromática frutos rojos, verdes y tropicales', 3, 6, 'cajas'],
            ['Aromáticas líquidas', 3, 4, 'cajas'],
            ['Azúcar tubular o en sobre', 3, 6, 'paquetes'],
            ['Café en pepa', 6, 12, 'libras'],
            ['Salsa chocolate y café', 0.5, 2, 'tazas'],
            ['Chocolatina', 6, 12, 'unidades'],
            ['Mezclador de madera', 1, 6, 'paquetes'],
            ['Bolsa de papel 3 lb', 1, 5, 'paquetes'],
            ['Bolsa de papel 6 lb', 2, 3, 'paquetes'],
            ['Bolsa de papel 12 lb', 2, 3, 'paquetes'],
            ['Bolsa plástica Mekatos/blanca 3 kg', 3, 8, 'paquetes'],
            ['Bolsa plástica Mekatos/blanca 5 kg', 3, 3, 'paquetes'],
            ['Bolsa plástica Mekatos/blanca @', null, null, 'paquetes'],
            ['Bolsa de baguette', 2, 4, 'paquetes'],
            ['Servilletas 3 tacos Familia Practidiarias', 3, 6, 'paquetes'],
            ['Servilletas Familia tipo cafetería', 3, 6, 'paquetes'],
            ['Bolsa de basura negra grande', 1, 6, 'paquetes'],
            ['Bolsa polipropileno 8 x 16', 3, 6, 'paquetes'],
            ['Masato', 1, 2, 'jarras'],
            ['Queso campesino', 3, 4, 'rodajas'],
            ['C-1 desechable cuadrado para llevar', 1, 3, 'paquetes'],
            ['J-1 desechable grande para llevar', 1, 1, 'paquetes'],
            ['Vasos 6-7 oz de cartón para café', 2, 4, 'paquetes'],
            ['Vasos 8-9 oz de cartón para café', 2, 4, 'paquetes'],
            ['Vaso 7 oz desechable plástico', 3, 4, 'paquetes'],
            ['Vaso 14 oz desechable plástico', 3, 4, 'paquetes'],
            ['Guantes de manipulación', 2, 4, 'paquetes'],
            ['Bolsa de papel para cubiertos', 2, 4, 'paquetes'],
            ['Palillos grandes para hamburguesa', 2, 3, 'cajas'],
            ['Algodón', 1, 2, 'paquetes'],
            ['Rollo de impresora', 3, 18, 'paquetes'],
            ['Papel para hamburguesa', 3, 6, 'paquetes'],
            ['Delantales plásticos', 1, 1, 'paquetes'],
            ['Milo bolsa', 2, 3, 'paquetes'],
            ['Brevas', 1, 1, 'frascos'],
            ['Copias planilla de entrada', 12, 15, 'copias'],
            ['Copias de arqueo o entrega de caja', 6, 6, 'hojas'],
        ]],
    ];

    /** Listas vacías de la versión anterior que estos formatos reemplazan */
    private const PROVISIONALES = ['Gaseosa', 'Plásticos', 'Almacén', 'Pulpas', 'Salsamentaria'];
    /** Categorías de loza que se copian a Mekatos 2 */
    private const LOZA = ['Fríos', 'Cocina', 'Greca'];

    public static function cargar(): void
    {
        $ahora = now_utc();
        // Productos de pedido de la versión anterior: el "debe haber" pasa a ser el óptimo
        Database::run(
            "UPDATE inventario_items it JOIN areas a ON a.id = it.area_id
             SET it.optimo = it.cantidad_esperada, it.cantidad_esperada = NULL
             WHERE a.tipo = 'pedido' AND it.cantidad_esperada IS NOT NULL AND it.optimo IS NULL"
        );

        // Mekatos 1: listas provisionales vacías se quitan; "Gaseosa" conserva su QR con el nombre nuevo
        foreach (self::PROVISIONALES as $n) {
            $a = Database::one("SELECT * FROM areas WHERE nombre = ? AND local_id = 1 AND tipo = 'pedido' AND activo = 1", [$n]);
            if (!$a) {
                continue;
            }
            if ($n === 'Gaseosa' && !Database::value("SELECT 1 FROM areas WHERE nombre = 'Pedido Gaseosa y cerveza' AND local_id = 1")) {
                Database::update('areas', ['nombre' => 'Pedido Gaseosa y cerveza'], 'id = :id', ['id' => $a['id']]);
                continue;
            }
            if (!(int)Database::value('SELECT COUNT(*) FROM inventario_items WHERE area_id = ? AND activo = 1', [$a['id']])) {
                Database::update('areas', ['activo' => 0], 'id = :id', ['id' => $a['id']]);
            }
        }

        $orden = (int)Database::value('SELECT COALESCE(MAX(orden), 0) FROM areas WHERE local_id = 1');
        foreach (self::PEDIDOS as $lista => [$ayuda, $productos]) {
            $a = Database::one('SELECT * FROM areas WHERE nombre = ? AND local_id = 1', [$lista]);
            if ($a) {
                $aid = (int)$a['id'];
                Database::update('areas', ['tipo' => 'pedido', 'activo' => 1, 'ayuda_obs' => $a['ayuda_obs'] ?: $ayuda], 'id = :id', ['id' => $aid]);
            } else {
                $aid = Database::insert('areas', [
                    'local_id' => 1, 'nombre' => $lista, 'token' => InventarioService::nuevoToken(), 'tipo' => 'pedido',
                    'ayuda_obs' => $ayuda, 'orden' => ++$orden, 'activo' => 1, 'creado' => $ahora,
                ]);
            }
            $i = (int)Database::value('SELECT COALESCE(MAX(orden), 0) FROM inventario_items WHERE area_id = ?', [$aid]);
            foreach ($productos as [$nombre, $min, $opt, $unidad]) {
                if (Database::value('SELECT 1 FROM inventario_items WHERE area_id = ? AND nombre = ?', [$aid, $nombre])) {
                    continue;
                }
                Database::insert('inventario_items', [
                    'area_id' => $aid, 'nombre' => $nombre, 'tipo' => 'cantidad', 'unidad' => $unidad,
                    'minimo' => $min, 'optimo' => $opt, 'orden' => ++$i, 'activo' => 1, 'creado' => $ahora, 'actualizado' => $ahora,
                ]);
            }
        }

        // Mekatos 2: la misma loza, con su propio stock (se fija con su primer conteo)
        if (LocalService::buscar(2) && !(int)Database::value('SELECT COUNT(*) FROM areas WHERE local_id = 2')) {
            $o = 0;
            foreach (self::LOZA as $n) {
                $orig = Database::one("SELECT * FROM areas WHERE nombre = ? AND local_id = 1 AND tipo = 'inventario'", [$n]);
                $aid = Database::insert('areas', [
                    'local_id' => 2, 'nombre' => $n, 'token' => InventarioService::nuevoToken(), 'tipo' => 'inventario',
                    'ayuda_obs' => $orig['ayuda_obs'] ?? null, 'orden' => ++$o, 'activo' => 1, 'creado' => $ahora,
                ]);
                if (!$orig) {
                    continue;
                }
                foreach (InventarioService::items((int)$orig['id']) as $it) {
                    $nuevo = Database::insert('inventario_items', [
                        'area_id' => $aid, 'nombre' => $it['nombre'], 'tipo' => $it['tipo'], 'unidad' => $it['unidad'],
                        'precio' => $it['precio'], 'orden' => $it['orden'], 'activo' => 1, 'creado' => $ahora, 'actualizado' => $ahora,
                    ]);
                    // la foto de referencia se copia (cada local puede cambiarla)
                    if ($ruta = InventarioService::rutaFoto($it['foto'])) {
                        $foto = 'item' . $nuevo . '_' . bin2hex(random_bytes(4)) . '.jpg';
                        if (@copy($ruta, dirname($ruta) . '/' . $foto)) {
                            Database::update('inventario_items', ['foto' => $foto], 'id = :id', ['id' => $nuevo]);
                        }
                    }
                }
            }
        }
        Audit::log('catalogo_cargado', null, null, 'Listas de pedido de Mekatos 1 y loza de Mekatos 2');
    }
}
