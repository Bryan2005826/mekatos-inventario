<?php
$turnos = InventarioService::TURNOS;
$motivos = InventarioService::MOTIVOS;
$tiposMov = ['base' => 'Primer conteo', 'baja' => 'Baja', 'ingreso' => 'Ingreso', 'ajuste' => 'Ajuste', 'reinicio' => 'Reinicio', 'conteo' => 'Conteo'];
$sinStock = $area ? count(array_filter($control, fn($c) => $c['item']['tipo'] === 'cantidad' && $c['item']['cantidad_esperada'] === null)) : 0;
?>
<div class="encabezado encabezado-acciones">
  <div>
    <h1>Control de loza</h1>
    <p class="sub">Cuánta loza hay en cada área según el último conteo, qué cambió frente al anterior y por qué.</p>
  </div>
  <?php if ($area): ?><a class="btn btn-primario" href="<?= e(url('admin/stock/contar', ['area' => $area['id']])) ?>">Hacer conteo como administrador</a><?php endif; ?>
</div>

<nav class="hermanas" aria-label="Áreas">
  <?php foreach ($areas as $a): ?>
    <a href="<?= e(url('admin/stock', ['area' => $a['id']])) ?>" <?= $area && (int)$a['id'] === (int)$area['id'] ? 'aria-current="page"' : '' ?>><?= e($a['nombre']) ?></a>
  <?php endforeach; ?>
</nav>

<?php if ($area): ?>
<?php if ($sinStock): ?>
  <p class="aviso aviso-info"><strong><?= $sinStock ?> elemento(s) todavía no se han contado.</strong> El primer inventario que envíe el trabajador del turno los deja como las unidades que hay. Desde ahí cada conteo se compara con el anterior.</p>
<?php endif; ?>

<section class="bloque">
  <h2>Loza de <?= e($area['nombre']) ?> · <?= e(LocalService::actual()['nombre']) ?></h2>
  <p class="sub">Cada conteo queda como las unidades que hay en el local, falte o sobre. Si no coincide con el conteo anterior, el trabajador dice por qué: se rompió (y quién lo partió), el dueño compró loza o el turno anterior contó mal.</p>
  <div class="tabla-envoltura"><table class="tabla">
    <thead><tr><th>Elemento</th><th class="num">Hay ahora</th><th class="num">Conteo anterior</th><th class="num">Diferencia</th><th>Por qué</th></tr></thead>
    <tbody>
    <?php foreach ($control as ['item' => $it, 'ultimo' => $u, 'dif' => $dif]): if ($it['tipo'] !== 'cantidad') { continue; }
      $quien = $u && $u['responsable_id'] ? (string)Database::value('SELECT nombre FROM trabajadores WHERE id = ?', [$u['responsable_id']]) : ($u['responsable_otro'] ?? ''); ?>
      <tr class="<?= $dif !== null && $dif !== 0 ? 'fila-dif' : '' ?>">
        <td><strong><?= e($it['nombre']) ?></strong></td>
        <td class="num"><?= $it['cantidad_esperada'] !== null ? '<strong>' . (int)$it['cantidad_esperada'] . '</strong>' : '<span class="estado estado-abierta">Sin contar</span>' ?><?php if ($u): ?><br><a class="meta" href="<?= e(url('admin/inventarios/ver', ['id' => $u['inventario_id']])) ?>"><?= e(fmt_fecha($u['fecha'])) ?> · <?= e($turnos[$u['turno']]) ?> · <?= e($u['trabajador']) ?></a><?php endif; ?></td>
        <td class="num"><?= $u && $u['antes'] !== null && !$u['es_base'] ? (int)$u['antes'] : '—' ?></td>
        <td class="num"><?= $dif === null ? '—' : ($dif === 0 ? '<span class="estado estado-completa">Igual</span>' : '<strong>' . ($dif > 0 ? '+' : '') . $dif . '</strong>') ?></td>
        <td><?= $u && $u['motivo_faltante'] ? e($motivos[$u['motivo_faltante']] ?? '') : ($dif ? '<span class="estado estado-incompleta">Sin explicar</span>' : '') ?><?= $quien !== '' ? '<br><strong>Lo partió: ' . e($quien) . '</strong>' : '' ?><?= $u && $u['observacion'] ? '<br><span class="meta">' . e($u['observacion']) . '</span>' : '' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <p class="sub">Lo partido por un trabajador aparece en su planilla para que le pongas el precio. Lo que partió un cliente no se le cobra a nadie.</p>
</section>

<section class="bloque">
  <h2>Reportes de faltantes, sobrantes y daños (30 días)</h2>
  <?php if (!$faltantes): ?>
    <p class="vacio">No se han reportado diferencias en esta área.</p>
  <?php else: ?>
  <div class="tabla-envoltura"><table class="tabla">
    <thead><tr><th>Fecha</th><th>Turno</th><th>Reportó</th><th>Elemento</th><th class="num">Diferencia</th><th>Motivo</th><th>Observación</th></tr></thead>
    <tbody>
    <?php foreach ($faltantes as $f): $n = ($f['cantidad_esperada'] !== null && $f['cantidad'] !== null) ? (int)$f['cantidad'] - (int)$f['cantidad_esperada'] : 0; ?>
      <tr>
        <td><a href="<?= e(url('admin/inventarios/ver', ['id' => $f['inventario_id']])) ?>"><?= e(fmt_fecha($f['fecha'])) ?></a></td>
        <td><?= e($turnos[$f['turno']]) ?> <span class="meta"><?= e(fmt_hora($f['enviado'])) ?></span></td>
        <td><?= e($f['trabajador']) ?></td>
        <td><?= e($f['item_nombre']) ?></td>
        <td class="num"><?= $n === 0 ? '—' : ($n < 0 ? 'Faltan ' . (-$n) : 'Sobran ' . $n) ?></td>
        <td><?= $f['motivo_faltante'] ? e($motivos[$f['motivo_faltante']] ?? '') : ($n !== 0 ? '<span class="estado estado-incompleta">Sin explicar</span>' : ($f['estado'] === 'danado' ? 'Dañado' : '')) ?><?= $f['responsable'] && $f['motivo_faltante'] === 'roto' ? '<br><strong>Lo partió: ' . e($f['responsable']) . '</strong>' : '' ?></td>
        <td><?= e($f['observacion']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>

<div class="columnas">
<section class="bloque">
  <h2>Movimientos del stock</h2>
  <?php if (!$movimientos): ?>
    <p class="vacio">Sin movimientos todavía.</p>
  <?php else: ?>
    <ul class="lista-simple">
      <?php foreach ($movimientos as $m): ?>
        <li><span class="meta"><?= e(fmt_fecha_hora($m['creado'])) ?></span> <strong><?= e($m['nombre']) ?></strong>: <?= e($tiposMov[$m['tipo']] ?? $m['tipo']) ?>
          <?= $m['antes'] !== null ? (int)$m['antes'] : 'sin stock' ?> → <?= $m['despues'] !== null ? (int)$m['despues'] : 'sin stock' ?>
          <span class="meta">· <?= e($m['actor']) ?><?= $m['motivo'] ? ' · ' . e($m['motivo']) : '' ?></span></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
<section class="bloque">
  <h2>Empezar de cero</h2>
  <p>Borra las cantidades de <?= e($area['nombre']) ?>. El próximo inventario quedará como conteo inicial, sin preguntar diferencias.</p>
  <form method="post" action="<?= e(url('admin/stock/reiniciar')) ?>" class="form" data-confirmar="¿Reiniciar el stock de <?= e($area['nombre']) ?>? El próximo inventario lo volverá a fijar.">
    <?= Csrf::field() ?><input type="hidden" name="area_id" value="<?= (int)$area['id'] ?>">
    <label class="campo"><span>Motivo</span><input name="motivo" maxlength="200" placeholder="El primer conteo quedó mal"></label>
    <button class="btn btn-quieto" type="submit">Reiniciar stock</button>
  </form>
</section>
</div>
<?php endif; ?>
