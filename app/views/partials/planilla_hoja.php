<?php
/** Hoja de planilla con el formato del establecimiento. Variables: $p, $c, $jornadas */
$filas = [];
foreach ($jornadas as $j) {
    $e = utc_to_local($j['entrada']);
    $s = utc_to_local($j['salida']);
    $completa = $j['estado'] === 'completa';
    $m = $completa ? (int)$j['minutos_trabajados'] : null;
    $filas[] = [
        'mes'   => $e ? nombre_mes((int)$e->format('n')) : '',
        'dia'   => $e ? $e->format('j') : '',
        'eh'    => $e ? $e->format('H') : '',
        'em'    => $e ? $e->format('i') : '',
        'sh'    => $s ? $s->format('H') : '',
        'sm'    => $s ? $s->format('i') : '',
        'h'     => $m !== null ? intdiv($m, 60) : '',
        'm'     => $m !== null ? str_pad((string)($m % 60), 2, '0', STR_PAD_LEFT) : '',
        'nota'  => $completa ? '' : 'sin salida',
    ];
}
$minimo = 30;
while (count($filas) < $minimo) {
    $filas[] = null;
}
$logo = is_file(ROOT_PATH . '/public/assets/img/logo.png') || is_file(ROOT_PATH . '/assets/img/logo.png');
$per = PlanillaService::periodoDe($p['periodo_inicio']);
$dinero = fn($v) => (float)$v != 0 ? fmt_pesos($v) : '';
?>
<article class="hoja">
  <header class="hoja-cabecera">
    <?php if ($logo): ?><img class="hoja-logo" src="<?= e(asset('assets/img/logo.png')) ?>" alt="<?= e(Settings::get('nombre_establecimiento')) ?>"><?php else: ?><span class="hoja-logo-texto"><?= e(Settings::get('nombre_establecimiento')) ?></span><?php endif; ?>
    <h1 class="hoja-titulo">Planilla Horario</h1>
    <p class="hoja-numero">N.º <?= (int)$p['id'] ?></p>
  </header>
  <div class="hoja-datos">
    <p><span>Nombre o firma:</span> <strong><?= e($p['nombre']) ?></strong></p>
    <p><span>Período:</span> <?= e($per['rango']) ?></p>
  </div>

  <table class="hoja-tabla">
    <colgroup><col class="c-mes"><col class="c-dia"><col><col><col><col><col class="c-horas"><col class="c-min"><col class="c-firma"></colgroup>
    <thead>
      <tr><th rowspan="2">Mes</th><th rowspan="2">Día</th><th colspan="2" class="grupo">Llegada</th><th colspan="2" class="grupo">Salida</th><th rowspan="2">Horas</th><th rowspan="2">Minutos</th><th rowspan="2">Firma</th></tr>
      <tr><th class="sub">Hora</th><th class="sub">Min</th><th class="sub">Hora</th><th class="sub">Min</th></tr>
    </thead>
    <tbody>
      <?php foreach ($filas as $f): ?>
        <?php if ($f === null): ?>
          <tr><td></td><td></td><td class="sep"></td><td></td><td class="sep"></td><td></td><td class="sep"></td><td></td><td></td></tr>
        <?php else: ?>
          <tr>
            <td><?= e($f['mes']) ?></td><td><?= e($f['dia']) ?></td>
            <td class="sep"><?= e($f['eh']) ?></td><td><?= e($f['em']) ?></td>
            <td class="sep"><?= e($f['sh']) ?></td><td><?= e($f['sm']) ?></td>
            <td class="sep"><?= e((string)$f['h']) ?></td><td><?= e($f['m']) ?></td>
            <td class="nota"><?= e($f['nota']) ?></td>
          </tr>
        <?php endif; ?>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr>
        <td colspan="6" class="total-etiqueta">Tiempo total trabajado</td>
        <td class="sep total"><?= (int)$c['horas'] ?></td><td class="total"><?= str_pad((string)$c['min_resto'], 2, '0', STR_PAD_LEFT) ?></td>
        <td class="total-horas"><?= e($c['horas_dec']) ?> horas</td>
      </tr>
    </tfoot>
  </table>

  <?php if ($c['pendientes']): ?>
    <p class="hoja-alerta"><?= (int)$c['pendientes'] ?> jornada(s) sin salida no se suman. Corrígelas antes de liquidar.</p>
  <?php endif; ?>

  <div class="hoja-pie">
    <table class="hoja-cuentas">
      <tr><th>Bono extra</th><td class="desc"><?= e($p['bono_descripcion'] ?? '') ?></td><td class="valor"><?= e($dinero($p['bono_valor'])) ?></td></tr>
      <tr><th>Comida −</th><td class="desc"></td><td class="valor"><?= e($dinero($p['desc_comida'])) ?></td></tr>
      <tr><th>Adelanto efectivo −</th><td class="desc"></td><td class="valor"><?= e($dinero($p['desc_adelanto'])) ?></td></tr>
      <tr><th>Otros −</th><td class="desc"></td><td class="valor"><?= e($dinero($p['desc_otros'])) ?></td></tr>
      <tr><th>Total descuentos</th><td class="desc"></td><td class="valor"><?= e(fmt_pesos($c['descuentos'])) ?></td></tr>
      <tr><th>Total quincena</th><td class="desc"><?= $c['pago_fijo'] ? 'pago fijo + bono' : 'horas + bono' ?></td><td class="valor"><?= e(fmt_pesos($c['total_quincena'])) ?></td></tr>
      <tr class="entregar"><th>Total a entregar</th><td class="desc"></td><td class="valor"><?= e(fmt_pesos($c['total_entregar'])) ?></td></tr>
    </table>

    <div class="hoja-derecha">
      <table class="hoja-liquidacion">
        <?php if ($c['pago_fijo']): ?>
        <tr><th>Horas</th><th>Pago fijo</th><th>Total</th></tr>
        <tr><td><?= e($c['horas_dec']) ?></td><td>Quincena</td><td><?= e(fmt_pesos($c['pago_horas'])) ?></td></tr>
        <?php else: ?>
        <tr><th>Horas</th><th>Valor hora</th><th>Total</th></tr>
        <tr><td><?= e($c['horas_dec']) ?></td><td><?= e($c['valor_hora'] > 0 ? fmt_pesos($c['valor_hora']) : '') ?></td><td><?= e($c['valor_hora'] > 0 ? fmt_pesos($c['pago_horas']) : '') ?></td></tr>
        <?php endif; ?>
      </table>
      <dl class="hoja-firmas">
        <div><dt>Valor total</dt><dd><?= e($c['liquidable'] ? fmt_pesos($c['total_entregar']) : '') ?></dd></div>
        <div><dt>Firma</dt><dd></dd></div>
        <div><dt>N.º identificación</dt><dd><?= e($p['documento'] ?? '') ?></dd></div>
        <div><dt>Fecha de recibido</dt><dd></dd></div>
      </dl>
      <p class="hoja-mensaje"><?= e(Settings::get('mensaje_planilla')) ?></p>
    </div>
  </div>
  <?php if ($p['observaciones']): ?><p class="hoja-obs"><strong>Observaciones:</strong> <?= e($p['observaciones']) ?></p><?php endif; ?>
</article>
