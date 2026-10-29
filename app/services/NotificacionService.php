<?php
declare(strict_types=1);

/**
 * Cola de correos con reintentos. Cada envío guarda destino, estado,
 * identificador del proveedor, errores e intentos. InfinityFree no tiene
 * tareas programadas, así que los pendientes se reintentan cuando alguien
 * usa el panel (máximo 5 intentos, con espera creciente).
 */
final class NotificacionService
{
    private const MAX_INTENTOS = 5;

    public static function asegurar(): void
    {
        static $hecho = false;
        if (!$hecho) {
            $hecho = true;
            if (!Database::value("SHOW TABLES LIKE 'envios'")) {
                $schema = is_file(APP_PATH . '/schema.sql') ? APP_PATH . '/schema.sql' : ROOT_PATH . '/database/schema.sql';
                Database::ejecutarScript($schema);
            }
        }
    }

    public static function encolar(string $tipo, ?int $ref, string $asunto, string $html, string $texto, ?string $adjunto = null, ?string $adjuntoNombre = null, ?array $destinos = null): ?int
    {
        self::asegurar();
        $destinos ??= CorreoService::destinos();
        if (!$destinos) {
            return null;
        }
        return Database::insert('envios', [
            'tipo' => $tipo, 'referencia_id' => $ref, 'destinos' => implode(', ', $destinos),
            'asunto' => mb_substr($asunto, 0, 200), 'cuerpo_html' => $html, 'cuerpo_texto' => $texto,
            'adjunto' => $adjunto, 'adjunto_nombre' => $adjuntoNombre,
            'estado' => 'pendiente', 'intentos' => 0, 'proximo_intento' => now_utc(), 'creado' => now_utc(),
        ]);
    }

    /** Intenta enviar un registro de la cola. Devuelve el registro actualizado. */
    public static function intentar(int $id): array
    {
        $e = Database::one('SELECT * FROM envios WHERE id = ?', [$id]) ?? throw new InvalidArgumentException('El envío no existe.');
        if ($e['estado'] === 'enviado') {
            return $e;
        }
        if (!CorreoService::configurado()) {
            Database::update('envios', ['ultimo_error' => 'El correo no está configurado o está desactivado.'], 'id = :id', ['id' => $id]);
            return Database::one('SELECT * FROM envios WHERE id = ?', [$id]);
        }
        $adjuntos = [];
        if ($e['adjunto']) {
            $ruta = STORAGE_PATH . '/' . $e['adjunto'];
            if (str_contains($e['adjunto'], '..') || !is_file($ruta)) {
                Database::update('envios', ['estado' => 'error', 'ultimo_error' => 'El archivo adjunto ya no existe.'], 'id = :id', ['id' => $id]);
                return Database::one('SELECT * FROM envios WHERE id = ?', [$id]);
            }
            $adjuntos[] = ['nombre' => $e['adjunto_nombre'] ?: basename($ruta), 'ruta' => $ruta, 'tipo' => 'application/pdf'];
        }
        $r = CorreoService::enviar(CorreoService::validarDestinos($e['destinos']), $e['asunto'], $e['cuerpo_html'], $e['cuerpo_texto'], $adjuntos);
        $intentos = (int)$e['intentos'] + 1;
        if ($r['ok']) {
            Database::update('envios', [
                'estado' => 'enviado', 'intentos' => $intentos, 'enviado' => now_utc(), 'mensaje_id' => $r['id'] ?: null,
                'metodo' => $r['metodo'], 'ultimo_error' => null, 'proximo_intento' => null,
            ], 'id = :id', ['id' => $id]);
            if ($e['tipo'] === 'inventario' && $e['referencia_id']) {
                InventarioService::registrarEnvio((int)$e['referencia_id'], 'enviado_correo', 'Correo aceptado por el servidor (' . $r['metodo'] . ') para ' . $e['destinos'] . ($r['id'] ? ' · id ' . $r['id'] : ''));
            }
        } else {
            $espera = [1 => 5, 2 => 15, 3 => 60, 4 => 240][$intentos] ?? 0;
            Database::update('envios', [
                'estado' => $intentos >= self::MAX_INTENTOS ? 'error' : 'pendiente', 'intentos' => $intentos,
                'ultimo_error' => mb_substr((string)$r['error'], 0, 500), 'metodo' => $r['metodo'],
                'proximo_intento' => $espera ? gmdate('Y-m-d H:i:s', time() + $espera * 60) : null,
            ], 'id = :id', ['id' => $id]);
            if ($e['tipo'] === 'inventario' && $e['referencia_id']) {
                InventarioService::registrarEnvio((int)$e['referencia_id'], $intentos >= self::MAX_INTENTOS ? 'error_correo' : 'reintentando', "Intento $intentos: " . $r['error']);
            }
        }
        return Database::one('SELECT * FROM envios WHERE id = ?', [$id]);
    }

    /** Procesa pendientes vencidos (se llama al usar el panel). */
    public static function procesarPendientes(int $limite = 2): void
    {
        self::asegurar();
        if (!CorreoService::configurado()) {
            return;
        }
        $ids = Database::all(
            "SELECT id FROM envios WHERE estado = 'pendiente' AND (proximo_intento IS NULL OR proximo_intento <= ?) ORDER BY id LIMIT " . (int)$limite,
            [now_utc()]
        );
        foreach ($ids as $r) {
            self::intentar((int)$r['id']);
        }
    }

    public static function reintentar(int $id): array
    {
        Database::update('envios', ['estado' => 'pendiente', 'proximo_intento' => now_utc(), 'intentos' => 0], "id = :id AND estado <> 'enviado'", ['id' => $id]);
        Audit::log('envio_reintentado', 'envio', $id);
        return self::intentar($id);
    }

    /* ---------------- Mensajes ---------------- */

    private static function plantilla(string $titulo, string $cuerpoHtml): string
    {
        $local = e(Settings::get('nombre_establecimiento'));
        return '<!doctype html><html lang="es"><body style="margin:0;background:#F3F7F6;font-family:Arial,sans-serif;color:#0E2420">'
            . '<div style="max-width:560px;margin:0 auto;padding:24px">'
            . '<p style="margin:0 0 4px;color:#4A5F5B;font-size:14px">' . $local . '</p>'
            . '<h1 style="margin:0 0 16px;font-size:22px">' . e($titulo) . '</h1>'
            . '<div style="background:#fff;border:1px solid #D5E0DD;border-radius:12px;padding:18px;font-size:15px;line-height:1.5">' . $cuerpoHtml . '</div>'
            . '<p style="color:#4A5F5B;font-size:12px;margin-top:16px">Mensaje automático del sistema de asistencia e inventario.</p>'
            . '</div></body></html>';
    }

    /** Llamado después de enviar un inventario */
    public static function inventarioEnviado(int $inventarioId): ?array
    {
        if (!CorreoService::configurado()) {
            return null;
        }
        $inv = InventarioService::buscar($inventarioId);
        if (!$inv || !$inv['pdf']) {
            return null;
        }
        $lineas = InventarioService::lineas($inventarioId);
        $faltan = $sobran = 0;
        foreach ($lineas as $l) {
            if (!$l['es_base'] && $l['hay'] === null && $l['tipo'] === 'cantidad' && $l['cantidad_esperada'] !== null && $l['cantidad'] !== null) {
                $d = (int)$l['cantidad'] - (int)$l['cantidad_esperada'];
                $faltan += max(0, -$d);
                $sobran += max(0, $d);
            }
        }
        // Siempre se avisa si falta o sobra (aunque los avisos de inventario normales estén apagados)
        if (!Settings::bool('notificar_inventario') && !(($faltan || $sobran) && Settings::bool('notificar_faltantes'))) {
            return null;
        }
        $turno = InventarioService::TURNOS[$inv['turno']];
        $hora = utc_to_local($inv['enviado'])->format('H:i');
        $porPedir = count(array_filter($lineas, fn($l) => (float)$l['pedir'] > 0));
        $local = LocalService::nombre((int)$inv['local_id']);
        $asunto = "$local · " . ($inv['area_tipo'] === 'pedido'
            ? InventarioService::titulo($inv) . ' · ' . ($porPedir ? "$porPedir producto(s) por pedir" : 'no hay que pedir') . ' · ' . fmt_fecha($inv['fecha'])
            : ($faltan ? "FALTAN $faltan · " : '') . ($sobran ? "SOBRAN $sobran · " : '') . "Inventario {$inv['area']} · $turno · " . fmt_fecha($inv['fecha'])
            . ((int)$inv['diferencias'] && !$faltan ? " · {$inv['diferencias']} novedad(es)" : '') . ($inv['es_base'] ? ' · stock inicial' : ''));
        $filas = '';
        foreach ($lineas as $l) {
            $dif = ($l['tipo'] === 'cantidad' && $l['hay'] === null && $l['cantidad_esperada'] !== null && $l['cantidad'] !== null) ? (int)$l['cantidad'] - (int)$l['cantidad_esperada'] : null;
            $alerta = ($dif !== null && $dif !== 0) || in_array($l['estado'], ['novedad', 'danado'], true);
            $filas .= '<tr style="' . ($alerta ? 'background:#FCECEA' : '') . '"><td style="padding:4px 6px">' . e($l['item_nombre']) . '</td><td style="padding:4px 6px;text-align:right">'
                . e($l['hay'] !== null ? InventarioService::numero($l['hay']) . ' / ' . InventarioService::numero($l['optimo'])
                    : ($l['cantidad'] ?? (InventarioService::RESPUESTAS[$l['respuesta'] ?? ''] ?? ($l['respuesta'] ?? 'n/a'))) . ($l['cantidad_esperada'] !== null ? ' / ' . (int)$l['cantidad_esperada'] : ''))
                . ((float)$l['pedir'] > 0 ? ' · <strong>pedir ' . e(InventarioService::numero($l['pedir'])) . '</strong>' : '') . '</td><td style="padding:4px 6px">'
                . e(InventarioService::ESTADOS[$l['estado']] ?? '')
                . ($l['motivo_faltante'] ? ' · <strong>' . e(InventarioService::MOTIVOS[$l['motivo_faltante']] ?? '') . '</strong>' : '')
                . ($l['es_base'] ? ' · stock inicial' : '')
                . ($l['observacion'] ? ' · ' . e($l['observacion']) : '') . '</td></tr>';
        }
        $html = self::plantilla(InventarioService::titulo($inv) . " ($turno) · $local",
            '<p style="margin:0 0 12px"><strong>' . e($inv['trabajador']) . '</strong> lo envió el ' . e(fmt_fecha($inv['fecha'])) . ' a las <strong>' . $hora . '</strong>.</p>'
            . '<table style="width:100%;border-collapse:collapse;font-size:14px"><tr style="color:#4A5F5B"><th style="text-align:left;padding:4px 6px">Elemento</th><th style="text-align:right;padding:4px 6px">' . ($inv['area_tipo'] === 'pedido' ? 'Hay / debe haber' : 'Contó / conteo anterior') . '</th><th style="text-align:left;padding:4px 6px">Estado</th></tr>' . $filas . '</table>'
            . ($inv['observaciones'] ? '<p><strong>Observaciones:</strong> ' . e($inv['observaciones']) . '</p>' : '')
            . (($faltan || $sobran) ? '<p style="color:#C23B2E;margin:12px 0 0"><strong>' . trim(($faltan ? "Faltan $faltan pieza(s). " : '') . ($sobran ? "Sobran $sobran pieza(s)." : '')) . '</strong> Mira los motivos en <a href="' . e(url('admin/stock', ['area' => $inv['area_id']])) . '">Control de loza</a>. Lo contado ya quedó como las unidades que hay.</p>' : '')
            . '<p style="margin:12px 0 0">El PDF va adjunto y también queda en el panel, en Historial de inventarios.</p>');
        $texto = "Inventario {$inv['area']} ($turno) enviado por {$inv['trabajador']} el {$inv['fecha']} a las $hora. Novedades: {$inv['diferencias']}. El PDF va adjunto.";
        $id = self::encolar('inventario', $inventarioId, $asunto, $html, $texto, 'inventarios/' . $inv['pdf'], InventarioService::nombreArchivo($inv));
        return $id ? self::intentar($id) : null;
    }

    /** Llamado por el cierre automático de una quincena */
    public static function quincenaCerrada(string $inicio): void
    {
        if (!Settings::bool('notificar_cierre') || !CorreoService::configurado()) {
            return;
        }
        if (Database::value("SELECT 1 FROM envios WHERE tipo = 'cierre_quincena' AND asunto LIKE ?", ['%' . $inicio . '%'])) {
            return;
        }
        $per = PlanillaService::periodoDe($inicio);
        $filas = '';
        $texto = "{$per['nombre']} cerrada.\n";
        $pend = 0;
        foreach (PlanillaService::delPeriodo($inicio) as $p) {
            $c = PlanillaService::calcular($p);
            $p['nombre'] .= ' (' . LocalService::nombre((int)Database::value('SELECT local_id FROM trabajadores WHERE id = ?', [$p['trabajador_id']])) . ')';
            $pend += $c['pendientes'];
            $filas .= '<tr><td style="padding:4px 6px">' . e($p['nombre']) . '</td><td style="padding:4px 6px;text-align:right">' . e($c['horas_dec']) . ' h</td><td style="padding:4px 6px;text-align:right">'
                . ($c['liquidable'] ? e(fmt_pesos($c['total_entregar'])) . ($c['pago_fijo'] ? ' (fijo)' : '') : 'sin valor de pago') . '</td></tr>';
            $texto .= "{$p['nombre']}: {$c['horas_dec']} horas\n";
        }
        $html = self::plantilla($per['nombre'] . ' cerrada',
            '<p style="margin:0 0 12px">La quincena del ' . e($per['rango']) . ' se cerró automáticamente. Ya puedes revisar e imprimir las planillas.</p>'
            . '<table style="width:100%;border-collapse:collapse;font-size:14px"><tr style="color:#4A5F5B"><th style="text-align:left;padding:4px 6px">Trabajador</th><th style="text-align:right;padding:4px 6px">Horas</th><th style="text-align:right;padding:4px 6px">A entregar</th></tr>' . $filas . '</table>'
            . ($pend ? '<p style="color:#C23B2E"><strong>' . $pend . ' jornada(s) sin salida</strong> no se sumaron. Corrígelas en Asistencia.</p>' : '')
            . '<p><a href="' . e(url('admin/planillas', ['periodo' => $inicio])) . '">Abrir las planillas</a></p>');
        $id = self::encolar('cierre_quincena', null, "Quincena cerrada · {$per['nombre']} [$inicio]", $html, $texto);
        if ($id) {
            self::intentar($id);
        }
    }

    public static function prueba(string $destino): array
    {
        $html = self::plantilla('Correo de prueba', '<p style="margin:0">Si lees esto, el envío de correos funciona. Los inventarios y cierres de quincena llegarán a esta dirección.</p>');
        $id = self::encolar('prueba', null, 'Prueba de correo · ' . Settings::get('nombre_establecimiento'), $html, 'Si lees esto, el envío de correos funciona.', null, null, [$destino]);
        return self::intentar((int)$id);
    }
}
