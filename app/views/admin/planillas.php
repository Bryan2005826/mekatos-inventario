<?php
$estadoPeriodo = $periodo['fin'] < hoy_local() ? 'Cerrada' : 'En curso';
$ant = PlanillaService::periodoAnterior($periodo['inicio']);
$sig = PlanillaService::periodoSiguiente($periodo['inicio']);
$etiquetas = ['abierta' => 'En curso', 'cerrada' => 'Cerrada', 'pagada' => 'Pagada'];
?>
<div class="encabezado encabezado-acciones">
  <div>
    <h1>Planillas · <?= e(LocalService::actual()['nombre']) ?></h1>
    <p class="sub"><?= e($periodo['nombre']) ?> (<?= e($periodo['rango']) ?>) · <?= e($estadoPeriodo) ?></p>
  </div>
  <a class="btn btn-primario btn-grande" href="<?= e(url('admin/planillas/imprimir', ['periodo' => $periodo['inicio'], 'auto' => 1])) ?>">Imprimir todas</a>
</div>

<nav class="periodos" aria-label="Cambiar quincena">
  <a class="btn btn-quieto btn-sm" href="<?= e(url('admin/planillas', ['periodo' => $ant['inicio']])) ?>">‹ <?= e($ant['nombre']) ?></a>
  <form method="get" action="<?= e(url('admin/planillas')) ?>">
    <label class="visualmente-oculto" for="sel-periodo">Quincena</label>
    <select id="sel-periodo" name="periodo" data-autoenviar>
      <?php $lista = $periodos; if (!in_array($periodo['inicio'], array_column($lista, 'inicio'), true)) { array_unshift($lista, $periodo); } ?>
      <?php foreach ($lista as $op): ?>
        <option value="<?= e($op['inicio']) ?>" <?= $op['inicio'] === $periodo['inicio'] ? 'selected' : '' ?>><?= e($op['nombre']) ?><?= $op['inicio'] === $actual['inicio'] ? ' (actual)' : '' ?></option>
      <?php endforeach; ?>
    </select>
  </form>
  <?php if ($sig['inicio'] <= $actual['inicio']): ?>
    <a class="btn btn-quieto btn-sm" href="<?= e(url('admin/planillas', ['periodo' => $sig['inicio']])) ?>"><?= e($sig['nombre']) ?> ›</a>
  <?php endif; ?>
</nav>

<?php if ($periodo['inicio'] === $actual['inicio']): ?>
  <p class="aviso aviso-info">Esta quincena se cierra sola el <?= e(fmt_fecha($periodo['fin'])) ?> a la medianoche. Después empieza una nueva y esta queda guardada aquí.</p>
<?php endif; ?>
<?php if ($totales['pendientes']): ?>
  <p class="aviso aviso-error">Hay <?= (int)$totales['pendientes'] ?> jornada(s) sin salida en esta quincena. No se suman hasta que registres la salida en Asistencia.</p>
<?php endif; ?>

<section class="bloque">
  <?php if (!$filas): ?>
    <p class="vacio">No hay planillas en esta quincena.</p>
  <?php else: ?>
  <div class="tabla-envoltura"><table class="tabla">
    <thead><tr><th>Trabajador</th><th>Jornadas</th><th>Tiempo</th><th>Horas</th><th>Forma de pago</th><th>A entregar</th><th>Estado</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($filas as ['p' => $p, 'c' => $c]): ?>
        <tr>
          <td><span class="punto" style="--c: <?= e($p['color']) ?>"></span><a href="<?= e(url('admin/planillas/ver', ['id' => $p['id']])) ?>"><?= e($p['nombre']) ?></a></td>
          <td><?= (int)$c['jornadas_total'] ?><?= $c['pendientes'] ? ' <span class="estado estado-incompleta">' . (int)$c['pendientes'] . ' sin salida</span>' : '' ?></td>
          <td class="num"><?= e(fmt_minutos($c['minutos'])) ?></td>
          <td class="num"><strong><?= e($c['horas_dec']) ?></strong></td>
          <td class="num"><?= $c['pago_fijo'] ? '<span class="estado estado-p-cerrada">Fijo</span> ' . e(fmt_pesos($c['valor_fijo'])) : ($c['valor_hora'] > 0 ? e(fmt_pesos($c['valor_hora'])) . '/h' : '<span class="meta">Sin valor</span>') ?></td>
          <td class="num"><strong><?= $c['liquidable'] ? e(fmt_pesos($c['total_entregar'])) : '—' ?></strong></td>
          <td><span class="estado estado-p-<?= e($p['estado']) ?>"><?= e($etiquetas[$p['estado']]) ?></span></td>
          <td class="acciones-fila">
            <a class="btn btn-secundario btn-sm" href="<?= e(url('admin/planillas/ver', ['id' => $p['id']])) ?>">Abrir</a>
            <a class="btn btn-quieto btn-sm" href="<?= e(url('admin/planillas/imprimir', ['id' => $p['id'], 'auto' => 1])) ?>">Imprimir</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr><th>Total</th><th></th><th class="num"><?= e(fmt_minutos($totales['minutos'])) ?></th><th class="num"><?= e(fmt_horas_dec($totales['minutos'])) ?></th><th></th><th class="num"><?= e(fmt_pesos($totales['total_entregar'])) ?></th><th></th><th></th></tr>
    </tfoot>
  </table></div>
  <?php endif; ?>
</section>
