<?php $estados = InventarioService::ESTADOS; ?>
<div class="encabezado">
  <a class="volver" href="<?= e(url('admin/stock', ['area' => $area['id']])) ?>">Control de loza</a>
  <h1>Conteo del administrador · <?= e($area['nombre']) ?></h1>
  <p class="sub">Cuenta todo lo que hay en el área. Si dejas marcada la opción de abajo, estas cantidades quedan como el stock: desde ese momento cada inventario de los trabajadores se compara contra ellas.</p>
</div>

<nav class="hermanas" aria-label="Áreas">
  <?php foreach ($areas as $a): ?>
    <a href="<?= e(url('admin/stock/contar', ['area' => $a['id']])) ?>" <?= (int)$a['id'] === (int)$area['id'] ? 'aria-current="page"' : '' ?>><?= e($a['nombre']) ?></a>
  <?php endforeach; ?>
</nav>

<form method="post" action="<?= e(url('admin/stock/contar')) ?>" class="form">
  <?= Csrf::field() ?>
  <input type="hidden" name="area_id" value="<?= (int)$area['id'] ?>">
  <section class="bloque">
    <?php if (!$items): ?>
      <p class="vacio">Esta área no tiene elementos. Agrégalos en <a href="<?= e(url('admin/inventario', ['area' => $area['id']])) ?>">Inventario por áreas</a>.</p>
    <?php else: ?>
    <div class="tabla-envoltura"><table class="tabla conteo-tabla">
      <thead><tr><th>Elemento</th><th class="num">Stock actual</th><th>Cantidad contada</th><th>Estado</th><th>Observación</th></tr></thead>
      <tbody>
      <?php foreach ($items as $it): ?>
        <tr>
          <td><strong><?= e($it['nombre']) ?></strong></td>
          <td class="num"><?= $it['cantidad_esperada'] !== null ? (int)$it['cantidad_esperada'] : '<span class="meta">Sin definir</span>' ?></td>
          <td><?php if ($it['tipo'] === 'cantidad'): ?><input type="number" min="0" inputmode="numeric" required name="l[<?= (int)$it['id'] ?>][cantidad]" aria-label="Cantidad de <?= e($it['nombre']) ?>"><?php else: ?><span class="meta">Solo estado</span><?php endif; ?></td>
          <td><select name="l[<?= (int)$it['id'] ?>][estado]" aria-label="Estado de <?= e($it['nombre']) ?>"><?php foreach ($estados as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?></select></td>
          <td><input name="l[<?= (int)$it['id'] ?>][observacion]" maxlength="255" aria-label="Observación de <?= e($it['nombre']) ?>"></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </section>
  <section class="bloque bloque-estrecho">
    <label class="campo"><span>Turno</span>
      <select name="turno"><?php foreach (InventarioService::TURNOS as $k => $v): ?><option value="<?= $k ?>" <?= $turno === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select>
    </label>
    <label class="campo"><span>Observaciones</span><textarea name="observaciones" rows="2" maxlength="1000" placeholder="Conteo inicial antes de empezar con el sistema"></textarea></label>
    <label class="interruptor"><input type="checkbox" name="fijar_stock" value="1" checked><span><strong>Usar estas cantidades como el stock del área</strong> (lo que debe haber)</span></label>
    <p class="sub">Este conteo no ocupa el turno de los trabajadores: ellos igual deben enviar su inventario de mediodía y noche.</p>
    <button class="btn btn-primario" type="submit" <?= $items ? '' : 'disabled' ?>>Guardar conteo</button>
  </section>
</form>
