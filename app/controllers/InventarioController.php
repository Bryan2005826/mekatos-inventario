<?php
declare(strict_types=1);

/** Formulario de inventario que abre cada QR (celular del trabajador) */
final class InventarioController
{
    private const TIMEOUT = 1800; // 30 min de inactividad para llenar el inventario

    private function area(): array
    {
        InventarioService::asegurar();
        $token = (string)(input('a') ?? (json_input()['a'] ?? ''));
        $a = InventarioService::areaPorToken($token);
        if (!$a) {
            if (str_starts_with((string)($GLOBALS['route'] ?? ''), 'api/')) {
                json_response(['ok' => false, 'error' => 'Este código QR ya no es válido. Pide al administrador el código nuevo del área.'], 404);
            }
            abort(404, 'Este código QR ya no es válido. Pide al administrador el código nuevo del área.');
        }
        return $a;
    }

    /** Sesión del trabajador para inventario (separada de la del kiosco) */
    private function sesion(array $area): ?array
    {
        $s = $_SESSION['inv'] ?? null;
        if (!$s || (int)$s['area'] !== (int)$area['id'] || time() - $s['t'] > self::TIMEOUT) {
            return null;
        }
        $_SESSION['inv']['t'] = time();
        return $s;
    }

    private function requerirSesion(array $area): array
    {
        $s = $this->sesion($area);
        if (!$s) {
            json_response(['ok' => false, 'error' => 'Tu sesión terminó. Vuelve a seleccionar tu nombre.', 'expirada' => true], 401);
        }
        return $s;
    }

    public function formulario(): void
    {
        $a = $this->area();
        view('inventario/formulario', [
            'area'         => $a,
            'trabajadores' => TrabajadorService::activos((int)$a['local_id']),   // personal de ese local
            'turno'        => InventarioService::turnoSugerido(),
            'establecimiento' => LocalService::nombre((int)$a['local_id']),
        ], 'inventario');
    }

    public function login(): void
    {
        $a = $this->area();
        $in = json_input();
        $tid = (int)($in['trabajador_id'] ?? 0);
        $t = TrabajadorService::buscar($tid);
        if ($t && (int)$t['local_id'] !== (int)$a['local_id']) {
            json_response(['ok' => false, 'error' => 'Este código QR es de ' . LocalService::nombre((int)$a['local_id']) . '.'], 401);
        }
        $r = Auth::attemptWorker($tid, (string)($in['pin'] ?? ''), null);
        if (!$r['ok']) {
            json_response(['ok' => false, 'error' => $r['error']], 401);
        }
        Auth::logoutWorker();
        $_SESSION['inv'] = ['id' => (int)$r['trabajador']['id'], 'nombre' => $r['trabajador']['nombre'], 'area' => (int)$a['id'], 't' => time()];
        json_response(['ok' => true, 'csrf' => Csrf::token(), 'nombre' => $r['trabajador']['nombre'], 'turno' => InventarioService::turnoSugerido()]);
    }

    public function datos(): void
    {
        $a = $this->area();
        $s = $this->requerirSesion($a);
        $turno = (string)input('turno', '');
        if (!isset(InventarioService::TURNOS[$turno])) {
            json_response(['ok' => false, 'error' => 'Selecciona el turno.'], 422);
        }
        $fecha = InventarioService::fechaTurno($turno);
        $ya = InventarioService::enviadoDe((int)$a['id'], $fecha, $turno);
        $borrador = InventarioService::borradorDe((int)$a['id'], (int)$s['id'], $fecha, $turno);
        $valores = [];
        if ($borrador) {
            foreach (InventarioService::lineas((int)$borrador['id']) as $l) {
                $valores[$l['item_id']] = ['cantidad' => $l['hay'] !== null ? InventarioService::numero($l['hay']) : $l['cantidad'], 'estado' => $l['estado'], 'observacion' => $l['observacion'], 'respuesta' => $l['respuesta']];
            }
        }
        $esPedido = $a['tipo'] === 'pedido';
        $items = array_map(fn($it) => [
            'id'       => (int)$it['id'],
            'nombre'   => $it['nombre'],
            'tipo'     => $it['tipo'],
            'unidad'   => $it['unidad'],
            // Inventario: conteo a ciegas (no ve cuánto debe haber). Pedido: ve cuánto debe haber.
            'nuevo'    => !$esPedido && $it['tipo'] === 'cantidad' && $it['cantidad_esperada'] === null,
            // listas de pedido: ve el mínimo y el óptimo para comparar
            'minimo'   => $esPedido && $it['minimo'] !== null ? InventarioService::numero($it['minimo']) : null,
            'optimo'   => $esPedido && $it['optimo'] !== null ? InventarioService::numero($it['optimo']) : null,
            'foto'     => $it['foto'] ? url('inventario/foto', ['a' => $a['token'], 'i' => $it['id'], 'v' => substr(md5($it['foto']), 0, 6)]) : null,
        ], InventarioService::items((int)$a['id']));
        json_response([
            'ok'         => true,
            'fecha'      => fmt_fecha($fecha),
            'turno'      => InventarioService::TURNOS[$turno],
            'enviado'    => $ya ? ['por' => $ya['trabajador'], 'hora' => fmt_hora($ya['enviado'])] : null,
            'items'      => $items,
            'valores'    => $valores,
            'obs'        => $borrador['observaciones'] ?? '',
            'borrador'   => $borrador ? fmt_hora($borrador['actualizado']) : null,
            'estados'    => InventarioService::ESTADOS,
            'motivos'    => InventarioService::MOTIVOS_FALTA,
            'motivosSobra' => InventarioService::MOTIVOS_SOBRA,
            'respuestas' => InventarioService::RESPUESTAS,
            'pedido'     => $esPedido,
            'ayudaObs'   => $a['ayuda_obs'] ?: 'Observaciones generales (opcional)',
            'personal'   => array_map(fn($t) => ['id' => (int)$t['id'], 'nombre' => $t['nombre']], TrabajadorService::activos((int)$a['local_id'])),
        ]);
    }

    public function borrador(): void
    {
        $a = $this->area();
        $s = $this->requerirSesion($a);
        $in = json_input();
        try {
            InventarioService::guardarBorrador((int)$a['id'], (int)$s['id'], (string)($in['turno'] ?? ''), (array)($in['lineas'] ?? []), (string)($in['obs'] ?? ''));
            json_response(['ok' => true, 'hora' => fmt_hora(now_utc())]);
        } catch (InvalidArgumentException $e) {
            json_response(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function enviar(): void
    {
        $a = $this->area();
        $s = $this->requerirSesion($a);
        $in = json_input();
        try {
            $r = InventarioService::enviar((int)$a['id'], (int)$s['id'], (string)($in['turno'] ?? ''), (array)($in['lineas'] ?? []), (string)($in['obs'] ?? ''));
        } catch (InvalidArgumentException $e) {
            json_response(['ok' => false, 'error' => $e->getMessage()], 422);
        }
        $_SESSION['inv']['ultimo'] = $r['id'];
        // Si todo cuadra se avisa de una vez; si no, después de que explique las diferencias
        $correo = $r['preguntas'] ? null : $this->notificar($r['id']);
        json_response($this->respuesta($a, $r['id'], $correo) + ['preguntas' => $r['preguntas']]);
    }

    /** El trabajador explica por qué falta o sobra (las cantidades ya no cambian) */
    public function explicar(): void
    {
        $a = $this->area();
        $s = $this->requerirSesion($a);
        $in = json_input();
        $id = (int)($in['id'] ?? 0);
        if (!$id || $id !== (int)($s['ultimo'] ?? 0)) {
            json_response(['ok' => false, 'error' => 'No se encontró el inventario que acabas de enviar.'], 422);
        }
        try {
            InventarioService::explicar($id, (int)$s['id'], (array)($in['respuestas'] ?? []));
        } catch (InvalidArgumentException $e) {
            json_response(['ok' => false, 'error' => $e->getMessage()], 422);
        }
        json_response($this->respuesta($a, $id, $this->notificar($id)) + ['preguntas' => []]);
    }

    private function notificar(int $id): ?string
    {
        try {
            $e = NotificacionService::inventarioEnviado($id);
            if ($e) {
                return $e['estado'] === 'enviado' ? 'Se envió el reporte por correo al administrador.' : 'El correo quedó pendiente; se reintentará solo.';
            }
        } catch (Throwable $ex) {
            error_log('[asistencia] correo inventario: ' . $ex->getMessage());
        }
        return null;
    }

    private function respuesta(array $a, int $id, ?string $correo): array
    {
        $inv = InventarioService::buscar($id);
        return [
            'ok'          => true,
            'mensaje'     => 'Inventario enviado correctamente. Hora: ' . fmt_hora($inv['enviado']),
            'hora'        => fmt_hora($inv['enviado']),
            'diferencias' => (int)$inv['diferencias'],
            'pdf'         => url('inventario/pdf', ['a' => $a['token'], 'id' => $id]),
            'archivo'     => InventarioService::nombreArchivo($inv),
            'id'          => $id,
            'correo'      => $correo,
            'whatsapp'    => InventarioService::urlWhatsapp(InventarioService::mensajeWhatsapp($id)),
            'whatsappNumero' => Settings::get('whatsapp_admin') !== '',
            'pedido'      => $inv['area_tipo'] === 'pedido',
            'porPedir'    => count(array_filter(InventarioService::lineas($id), fn($l) => (float)$l['pedir'] > 0)),
        ];
    }

    public function compartido(): void
    {
        $a = $this->area();
        $s = $this->requerirSesion($a);
        $id = (int)(json_input()['id'] ?? 0);
        if ($id && $id === (int)($s['ultimo'] ?? 0)) {
            $metodo = (string)(json_input()['metodo'] ?? 'compartir');
            InventarioService::registrarEnvio($id, 'compartido', ($metodo === 'descarga' ? 'Descargado por ' : 'Compartido desde el celular por ') . $s['nombre'] . ' (sin confirmación de entrega)');
        }
        json_response(['ok' => true]);
    }

    /** PDF: el trabajador solo puede bajar el que acaba de enviar; el administrador, cualquiera */
    public function pdf(): void
    {
        $id = (int)input('id');
        $inv = InventarioService::buscar($id) ?? abort(404, 'El inventario no existe.');
        if (!Auth::admin()) {
            $a = $this->area();
            $s = $this->sesion($a);
            if (!$s || (int)($s['ultimo'] ?? 0) !== $id || (int)$inv['area_id'] !== (int)$a['id']) {
                abort(403, 'Solo el administrador puede ver los inventarios anteriores.');
            }
        }
        $ruta = InventarioService::rutaPdf($inv['pdf']) ?? InventarioService::rutaPdf(InventarioService::generarPdf($id));
        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . (input('descargar') ? 'attachment' : 'inline') . '; filename="' . InventarioService::nombreArchivo($inv) . '"');
        header('Content-Length: ' . filesize($ruta));
        header('Cache-Control: private, no-store');
        readfile($ruta);
        exit;
    }

    /** Foto de referencia: administrador o trabajador con sesión en esa área */
    public function foto(): void
    {
        $it = InventarioService::item((int)input('i')) ?? abort(404, 'No existe.');
        if (!Auth::admin()) {
            $a = $this->area();
            if (!$this->sesion($a) || (int)$it['area_id'] !== (int)$a['id']) {
                abort(403, 'Ingresa con tu PIN para ver las fotos.');
            }
        }
        $ruta = InventarioService::rutaFoto($it['foto']) ?? abort(404, 'Sin foto.');
        header('Content-Type: image/jpeg');
        header('Content-Length: ' . filesize($ruta));
        header('Cache-Control: private, max-age=86400');
        readfile($ruta);
        exit;
    }

    public function salir(): void
    {
        unset($_SESSION['inv']);
        json_response(['ok' => true]);
    }
}
