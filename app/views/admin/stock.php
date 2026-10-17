<?php
$turnos = InventarioService::TURNOS;
$motivos = InventarioService::MOTIVOS;
$tiposMov = ['base' => 'Stock inicial', 'baja' => 'Baja', 'ingreso' => 'Ingreso', 'ajuste' => 'Ajuste', 'reinicio' => 'Reinicio'];
$sinStock = $area ? count(array_filter($control, fn($c) => $c['item']['tipo'] === 'cantidad' && $c['item']['cantidad_esperada'] === null)) : 0;
?>
<div class="encabezado encabezado-acciones">
  <div>
    <h1>Control de loza</h1>
    <p class="sub">Cuánta loza debe haber en cada área, qué falta según el último inventario y por qué.</p>
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
  <p class="aviso aviso-info"><strong><?= $sinStock ?> elemento(s) todavía no tienen stock.</strong> El primer inventario que envíe el trabajador del turno fijará cuánto debe haber. Desde ahí, si falta o sobra algo, el sistema le pregunta por qué y te avisa; si todo cuadra, no pregunta nada.</p>
<?php endif; ?>

<section class="bloque">
  <h2>Stock de <?= e($area['nombre']) ?></h2>
  <div class="tabla-envoltura"><table class="tabla">
    <thead><tr><th>Elemento</th><th class="num">Debe haber</th><th class="num">Último conteo</th><th class="num">Diferencia</th><th>Por qué</th><th>Ajustar stock</th></tr></thead>
    <tbody>
    <?php foreach ($control as ['item' => $it, 'ultimo' => $u, 'dif' => $dif]): if ($it['tipo'] !== 'cantidad') { continue; } ?>
      <tr class="<?= $dif !== null && $dif !== 0 ? 'fila-dif' : '' ?>">
        <td><strong><?= e($it['nombre']) ?></strong></td>
        <td class="num"><?= $it['cantidad_esperada'] !== null ? (int)$it['cantidad_esperada'] : '<span class="estado estado-abierta">Sin definir</span>' ?></td>
        <td class="num"><?php if ($u): ?><a href="<?= e(url('admin/inventarios/ver', ['id' => $u['inventario_id']])) ?>"><?= (int)$u['cantidad'] ?></a><br><span class="meta"><?= e(fmt_fecha($u['fecha'])) ?> · <?= e($turnos[$u['turno']]) ?> · <?= e($u['trabajador']) ?></span><?php else: ?>—<?php endif; ?></td>
        <td class="num"><?= $dif === null ? '—' : ($dif === 0 ? '<span class="estado estado-completa">Completo</span>' : '<strong>' . ($dif > 0 ? '+' : '') . $dif . '</strong>') ?></td>
        <td><?= $u && $u['motivo_faltante'] ? e($motivos[$u['motivo_faltante']] ?? '') : ($dif ? '<span class="estado estado-incompleta">Sin explicar</span>' : '') ?><?= $u && $u['observacion'] ? '<br><span class="meta">' . e($u['observacion']) . '</span>' : '' ?></td>
        <td>
          <?php if ($it['cantidad_esperada'] !== null): ?>
          <div class="stock-acciones">
            <form method="post" action="<?= e(url('admin/stock/mover')) ?>" data-confirmar="¿Descontar del stock de <?= e($it['nombre']) ?>?">
              <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int)$it['id'] ?>"><input type="hidden" name="tipo" value="baja">
              <input type="number" name="cantidad" min="1" required value="<?= $dif !== null && $dif < 0 ? -$dif : '' ?>" aria-label="Cantidad a descontar">
              <input name="motivo" required maxlength="200" placeholder="Se rompieron" value="<?= e($dif !== null && $dif < 0 && $u['motivo_faltante'] ? ($motivos[$u['motivo_faltante']] ?? '') : '') ?>" aria-label="Motivo de la baja">
              <button class="btn btn-peligro btn-sm" type="submit">Dar de baja</button>
            </form>
            <form method="post" action="<?= e(url('admin/stock/mover')) ?>">
              <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int)$it['id'] ?>"><input type="hidden" name="tipo" value="ingreso">
              <input type="number" name="cantidad" min="1" required value="<?= $dif !== null && $dif > 0 ? $dif : '' ?>" aria-label="Cantidad nueva">
              <input name="motivo" required maxlength="200" placeholder="Compra nueva" value="<?= e($dif !== null && $dif > 0 && $u['motivo_faltante'] ? ($motivos[$u['motivo_faltante']] ?? '') : '') ?>" aria-label="Motivo del ingreso">
              <button class="btn btn-secundario btn-sm" type="submit">Sumar</button>
            </form>
          </div>
          <?php else: ?><span class="meta">Se fija con el próximo conteo</span><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <p class="sub">"Dar de baja" es para lo que se rompió o se perdió y no va a volver. "Sumar" es para lo nuevo que llegó. Así el stock queda al día y los siguientes inventarios no siguen marcando la misma diferencia.</p>
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
        <td><?= $f['motivo_faltante'] ? e($motivos[$f['motivo_faltante']] ?? '') : ($n !== 0 ? '<span class="estado estado-incompleta">Sin explicar</span>' : ($f['estado'] === 'danado' ? 'Dañado' : '')) ?></td>
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
  <p>Borra el stock de <?= e($area['nombre']) ?>. El próximo inventario que se envíe volverá a fijar cuánto debe haber de cada elemento. Úsalo si el primer conteo quedó mal.</p>
  <form method="post" action="<?= e(url('admin/stock/reiniciar')) ?>" class="form" data-confirmar="¿Reiniciar el stock de <?= e($area['nombre']) ?>? El próximo inventario lo volverá a fijar.">
    <?= Csrf::field() ?><input type="hidden" name="area_id" value="<?= (int)$area['id'] ?>">
    <label class="campo"><span>Motivo</span><input name="motivo" maxlength="200" placeholder="El primer conteo quedó mal"></label>
    <button class="btn btn-quieto" type="submit">Reiniciar stock</button>
  </form>
</section>
</div>
<?php endif; ?>
