<?php
declare(strict_types=1);

final class AsistenciaController
{
    private function rango(): array
    {
        $hoy = hoy_local();
        $desde = (string)input('desde', '');
        $hasta = (string)input('hasta', '');
        $ok = fn($d) => (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
        if (!$ok($desde)) {
            $desde = substr($hoy, 0, 8) . (((int)substr($hoy, 8, 2) <= 15) ? '01' : '16');
        }
        if (!$ok($hasta)) {
            $hasta = $hoy;
        }
        if ($hasta < $desde) {
            [$desde, $hasta] = [$hasta, $desde];
        }
        return [$desde, $hasta];
    }

    public function index(): void
    {
        Auth::requireAdmin();
        [$desde, $hasta] = $this->rango();
        $trab = (int)input('trabajador', 0) ?: null;
        $estado = in_array(input('estado'), ['abierta', 'completa', 'incompleta', 'anulada'], true) ? input('estado') : null;
        $jornadas = AsistenciaService::listar($trab, $desde, $hasta, $estado);
        $totales = [];
        foreach ($jornadas as $j) {
            $id = $j['trabajador_id'];
            $totales[$id] ??= ['nombre' => $j['nombre'], 'color' => $j['color'], 'min' => 0, 'jornadas' => 0, 'pendientes' => 0];
            if ($j['estado'] === 'completa') {
                $totales[$id]['min'] += (int)$j['minutos_trabajados'];
                $totales[$id]['jornadas']++;
            } elseif (in_array($j['estado'], ['abierta', 'incompleta'], true)) {
                $totales[$id]['pendientes']++;
            }
        }
        view('admin/asistencia', [
            'jornadas'     => $jornadas,
            'totales'      => $totales,
            'trabajadores' => Database::all('SELECT id, nombre FROM trabajadores ORDER BY activo DESC, orden, nombre'),
            'desde' => $desde, 'hasta' => $hasta, 'trab' => $trab, 'estado' => $estado,
            'seccion' => 'asistencia',
        ]);
    }

    public function jornada(): void
    {
        Auth::requireAdmin();
        $id = (int)input('id');
        $j = Database::one('SELECT j.*, t.nombre, t.color FROM jornadas j JOIN trabajadores t ON t.id = j.trabajador_id WHERE j.id = ?', [$id])
            ?? abort(404, 'La jornada no existe.');
        view('admin/jornada', [
            'j'           => $j,
            'marcaciones' => AsistenciaService::marcacionesDe($id),
            'historial'   => Database::all("SELECT * FROM auditoria WHERE entidad = 'jornada' AND entidad_id = ? ORDER BY creado DESC", [$id]),
            'seccion'     => 'asistencia',
        ]);
    }

    public function corregir(): void
    {
        Auth::requireAdmin();
        $id = (int)input('id');
        try {
            AsistenciaService::corregir($id, (string)input('entrada', ''), (string)input('salida', '') ?: null, (string)input('motivo', ''));
            flash('ok', 'Jornada corregida. El cambio quedó en la auditoría.');
        } catch (InvalidArgumentException $e) {
            flash('error', $e->getMessage());
        }
        redirect('admin/asistencia/jornada', ['id' => $id]);
    }

    public function anular(): void
    {
        Auth::requireAdmin();
        $id = (int)input('id');
        try {
            AsistenciaService::anular($id, (string)input('motivo', ''));
            flash('ok', 'Jornada anulada. No se tendrá en cuenta en los cálculos.');
        } catch (InvalidArgumentException $e) {
            flash('error', $e->getMessage());
        }
        redirect('admin/asistencia/jornada', ['id' => $id]);
    }

    public function nueva(): void
    {
        Auth::requireAdmin();
        view('admin/jornada_nueva', [
            'trabajadores' => TrabajadorService::activos(),
            'trab'         => (int)input('trabajador', 0),
            'seccion'      => 'asistencia',
        ]);
    }

    public function manual(): void
    {
        Auth::requireAdmin();
        try {
            $id = AsistenciaService::crearManual((int)input('trabajador'), (string)input('entrada', ''), (string)input('salida', '') ?: null, (string)input('motivo', ''));
            flash('ok', 'Jornada registrada manualmente.');
            redirect('admin/asistencia/jornada', ['id' => $id]);
        } catch (InvalidArgumentException $e) {
            flash('error', $e->getMessage());
            redirect('admin/asistencia/nueva', ['trabajador' => (int)input('trabajador')]);
        }
    }

    /** Sirve la foto de evidencia solo al administrador */
    public function foto(): void
    {
        Auth::requireAdmin();
        $m = Database::one('SELECT foto FROM marcaciones WHERE id = ?', [(int)input('m')]);
        $path = $m && $m['foto'] ? FotoStorage::ruta($m['foto']) : null;
        if (!$path) {
            abort(404, 'La foto ya no está disponible.');
        }
        header('Content-Type: image/jpeg');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: private, max-age=600');
        readfile($path);
        exit;
    }

    public function rechazos(): void
    {
        Auth::requireAdmin();
        $rows = Database::all(
            'SELECT r.*, t.nombre, d.nombre AS dispositivo FROM intentos_rechazados r
             LEFT JOIN trabajadores t ON t.id = r.trabajador_id
             LEFT JOIN dispositivos d ON d.id = r.dispositivo_id
             ORDER BY r.creado DESC LIMIT 300'
        );
        view('admin/rechazos', ['rechazos' => $rows, 'seccion' => 'rechazos']);
    }
}
