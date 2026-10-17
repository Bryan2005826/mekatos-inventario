<?php
declare(strict_types=1);

final class PlanillasController
{
    private function periodoPedido(): array
    {
        $inicio = (string)input('periodo', '');
        return preg_match('/^\d{4}-\d{2}-(01|16)$/', $inicio)
            ? PlanillaService::periodoDe($inicio)
            : PlanillaService::periodoActual();
    }

    public function index(): void
    {
        Auth::requireAdmin();
        PlanillaService::sincronizar();
        $periodo = $this->periodoPedido();
        $filas = [];
        $totales = ['minutos' => 0, 'total_entregar' => 0, 'pendientes' => 0];
        foreach (PlanillaService::delPeriodo($periodo['inicio']) as $p) {
            $c = PlanillaService::calcular($p);
            $filas[] = ['p' => $p, 'c' => $c];
            $totales['minutos'] += $c['minutos'];
            $totales['total_entregar'] += $c['total_entregar'];
            $totales['pendientes'] += $c['pendientes'];
        }
        view('admin/planillas', [
            'periodo'   => $periodo,
            'periodos'  => PlanillaService::periodos(),
            'actual'    => PlanillaService::periodoActual(),
            'filas'     => $filas,
            'totales'   => $totales,
            'seccion'   => 'planillas',
        ]);
    }

    public function ver(): void
    {
        Auth::requireAdmin();
        PlanillaService::sincronizar();
        $p = PlanillaService::buscar((int)input('id')) ?? abort(404, 'La planilla no existe.');
        $jornadas = PlanillaService::jornadas($p);
        $hermanas = Database::all(
            'SELECT p.id, t.nombre FROM planillas p JOIN trabajadores t ON t.id = p.trabajador_id
             WHERE p.periodo_inicio = ? ORDER BY t.orden, t.nombre',
            [$p['periodo_inicio']]
        );
        view('admin/planilla', [
            'p'        => $p,
            'c'        => PlanillaService::calcular($p, $jornadas),
            'jornadas' => $jornadas,
            'periodo'  => PlanillaService::periodoDe($p['periodo_inicio']),
            'hermanas' => $hermanas,
            'historial'=> Database::all("SELECT * FROM auditoria WHERE entidad = 'planilla' AND entidad_id = ? ORDER BY creado DESC", [$p['id']]),
            'seccion'  => 'planillas',
        ]);
    }

    public function guardar(): void
    {
        Auth::requireAdmin();
        $id = (int)input('id');
        try {
            PlanillaService::guardar($id, $_POST);
            flash('ok', 'Planilla guardada.');
        } catch (InvalidArgumentException $e) {
            flash('error', $e->getMessage());
        }
        redirect('admin/planillas/ver', ['id' => $id]);
    }

    public function pagar(): void
    {
        Auth::requireAdmin();
        $id = (int)input('id');
        try {
            PlanillaService::pagar($id);
            flash('ok', 'Planilla marcada como pagada. Sus jornadas quedan bloqueadas.');
        } catch (InvalidArgumentException $e) {
            flash('error', $e->getMessage());
        }
        redirect('admin/planillas/ver', ['id' => $id]);
    }

    public function reabrir(): void
    {
        Auth::requireAdmin();
        $id = (int)input('id');
        try {
            PlanillaService::reabrir($id, (string)input('motivo', ''));
            flash('ok', 'Planilla reabierta.');
        } catch (InvalidArgumentException $e) {
            flash('error', $e->getMessage());
        }
        redirect('admin/planillas/ver', ['id' => $id]);
    }

    /** Hojas para imprimir: una planilla (id) o todas las de la quincena (periodo) */
    public function imprimir(): void
    {
        Auth::requireAdmin();
        PlanillaService::sincronizar();
        $id = (int)input('id', 0);
        if ($id) {
            $p = PlanillaService::buscar($id) ?? abort(404, 'La planilla no existe.');
            $lista = [$p];
            $periodo = PlanillaService::periodoDe($p['periodo_inicio']);
        } else {
            $periodo = $this->periodoPedido();
            $lista = PlanillaService::delPeriodo($periodo['inicio']);
            // en "todas" se omiten trabajadores inactivos sin horas
            $lista = array_values(array_filter($lista, fn($p) => (int)$p['trabajador_activo'] === 1 || PlanillaService::calcular($p)['jornadas_total'] > 0));
        }
        $hojas = [];
        foreach ($lista as $p) {
            $js = PlanillaService::jornadas($p);
            $hojas[] = ['p' => $p, 'c' => PlanillaService::calcular($p, $js), 'jornadas' => $js];
        }
        Audit::log('planillas_impresas', 'planilla', $id ?: null, null, null, ['periodo' => $periodo['nombre'], 'hojas' => count($hojas)]);
        view('planilla_impresion', [
            'hojas'   => $hojas,
            'periodo' => $periodo,
            'title'   => 'Planillas · ' . $periodo['nombre'],
        ], 'impresion');
    }
}
