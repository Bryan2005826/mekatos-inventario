<?php
declare(strict_types=1);

/**
 * Personal inicial de Mekatos 1 y Mekatos 2 (se carga una sola vez).
 * Solo se guarda el hash del PIN; los PIN se entregaron en una hoja aparte.
 */
final class PersonalMekatos
{
    /** [nombre, local, hash del PIN] */
    private const PERSONAL = [
        ['Rodolfo', 1, '$2y$10$ayk/XRuFD0qFPZEK1Zy8xOt5n9QwIGqwAd5eB44XCmTw0rYmRzD2m'],
        ['Abner', 1, '$2y$10$tseX.u5ilCSvoC6zbQUG9.ow9YxLV9xcSByC94ufPDFMav3nHFHv.'],
        ['Carolina', 1, '$2y$10$.mqUuhhqSusbGCZK6Oo.KeW.3bW9OvXKczHn4H0pj2bbSGIhdSleO'],
        ['Melissa', 1, '$2y$10$bOor9QqlqF8IoVwqRdyTEuzI03IQn7Cpgs4Ub6k18VnqUYgyJlzg2'],
        ['Amy', 1, '$2y$10$DLGSnfKNZPkzJ7qSFxjohOQma44F/MYVeY9EZx9Eou4LXlYIpqwXG'],
        ['Brayan', 1, '$2y$10$Urw85x.cEGGW9U1TIPCzfuGDjMnPvl/T.fgq7diVjiu/0uXEd.9Yi'],
        ['Asdrúbal', 1, '$2y$10$MjMP7z/vQW.tnn2KZE8XgO9DsACRrqnRR07kS4HD75sFXLQjlkiia'],
        ['Luis', 1, '$2y$10$Hp8IyoAaXMLrW3JF6fz4j.COyI7aNk3TMqbTbCUqLOTVzrKJkrqqy'],
        ['Sebastián', 1, '$2y$10$bE1wm606hqJrvsX1llqG1e76.Odj90YxVH9zTaAtcyy21nB8WRtGy'],
        ['Ronaldo', 1, '$2y$10$9x9BosZoRjQ4jPYgcsME5ekggcutPMpMbt3rHKLILjjsxIyzqWBRq'],
        ['Milena', 2, '$2y$10$JoHmMvcOuApLeS6gS4kT/.xCuh14lRB5l3T7q3Soi4.bZAf5xmSTK'],
    ];

    public static function asegurar(): void
    {
        if (Settings::get('personal_mekatos') === '1') {
            return;
        }
        Database::transaction(function () {
            $ahora = now_utc();
            $orden = (int)Database::value('SELECT COALESCE(MAX(orden), 0) FROM trabajadores');
            foreach (self::PERSONAL as $i => [$nombre, $local, $hash]) {
                // si ya existe alguien con ese nombre, se actualiza (local y PIN de la hoja) en vez de duplicarlo
                $existe = Database::one('SELECT id FROM trabajadores WHERE LOWER(nombre) = LOWER(?) ORDER BY activo DESC, id LIMIT 1', [$nombre]);
                if ($existe) {
                    Database::update('trabajadores', [
                        'local_id' => $local, 'pin_hash' => $hash, 'pin_generado' => $ahora, 'activo' => 1, 'actualizado' => $ahora,
                    ], 'id = :id', ['id' => $existe['id']]);
                    continue;
                }
                $orden++;
                $id = Database::insert('trabajadores', [
                    'local_id' => $local, 'nombre' => $nombre, 'pin_hash' => $hash, 'pin_generado' => $ahora, 'activo' => 1,
                    'orden' => $orden, 'color' => TrabajadorService::COLORES[$i % count(TrabajadorService::COLORES)],
                    'creado' => $ahora, 'actualizado' => $ahora,
                ]);
                Audit::log('trabajador_creado', 'trabajador', $id, 'Personal inicial', null, ['nombre' => $nombre, 'local' => LocalService::nombre($local)]);
            }
            Settings::set('personal_mekatos', '1');
        });
    }
}
