<?php $estados = InventarioService::ESTADOS; $envio = ['compartido' => 'Compartido desde el celular', 'enviado_correo' => 'Enviado por correo', 'reintentando' => 'Correo pendiente (reintentando)', 'error_correo' => 'Correo falló', 'pendiente' => 'Solo en el panel']; ?>
<div class="encabezado encabezado-acciones">
  <div>
    <a class="volver" href="<?= e(url('admin/inventarios')) ?>">Historial de inventarios</a>
    <h1><?= e($inv['area']) ?> · <?= e(InventarioService::TURNOS[$inv['turno']]) ?></h1>
    <p class="sub"><?= e(fmt_fecha($inv['fecha'])) ?> · <?= e($inv['trabajador']) ?> · enviado a las <?= e(utc_to_local($inv['enviado'])->format('H:i:s')) ?><?= $inv['corregido'] ? ' · <span class="estado estado-corregida">Corregido</span>' : '' ?><?= $inv['es_base'] ? ' · <span class="estado estado-abierta">Fijó stock</span>' : '' ?><?= $inv['origen'] === 'admin' ? ' · <span class="estado estado-p-cerrada">Conteo del administrador</span>' : '' ?><?= $inv['motivos_pendientes'] ? ' · <span class="estado estado-incompleta">El trabajador no explicó las diferencias</span>' : '' ?></p>
  </div>
  <div class="acciones-fila">
    <a class="btn btn-primario" href="<?= e(url('inventario/pdf', ['id' => $inv['id']])) ?>" target="_blank" rel="noopener">Abrir PDF</a>
    <a class="btn btn-quieto" href="<?= e(url('inventario/pdf', ['id' => $inv['id'], 'descargar' => 1])) ?>">Descargar</a>
  </div>
</div>

<form method="post" action="<?= e(url('admin/inventarios/corregir')) ?>" class="form">
  <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int)$inv['id'] ?>">
  <section class="bloque">
    <h2>Elementos reportados</h2>
    <div class="tabla-envoltura"><table class="tabla">
      <thead><tr><th>Elemento</th><th class="num">Esperado</th><th>Reportado</th><th class="num">Diferencia</th><th>Estado</th><th>Por qué</th><th>Observación</th></tr></thead>
      <tbody>
      <?php foreach ($lineas as $l):
          $dif = ($l['tipo'] === 'cantidad' && $l['cantidad_esperada'] !== null && $l['cantidad'] !== null) ? (int)$l['cantidad'] - (int)$l['cantidad_esperada'] : null;
          $alerta = ($dif !== null && $dif !== 0) || in_array($l['estado'], ['novedad', 'danado'], true); ?>
        <tr class="<?= $alerta ? 'fila-dif' : '' ?>">
          <td><strong><?= e($l['item_nombre']) ?></strong></td>
          <td class="num"><?= $l['cantidad_esperada'] !== null ? (int)$l['cantidad_esperada'] : '—' ?></td>
          <td><?php if ($l['tipo'] === 'cantidad'): ?><input class="input-corto" type="number" min="0" name="l[<?= (int)$l['id'] ?>][cantidad]" value="<?= e($l['cantidad']) ?>" aria-label="Cantidad de <?= e($l['item_nombre']) ?>"><?php else: ?><span class="meta">n/a</span><?php endif; ?></td>
          <td class="num"><?= $dif === null ? '—' : ($dif > 0 ? '+' . $dif : $dif) ?></td>
          <td><select name="l[<?= (int)$l['id'] ?>][estado]" aria-label="Estado de <?= e($l['item_nombre']) ?>"><?php foreach ($estados as $k => $v): ?><option value="<?= $k ?>" <?= $l['estado'] === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></td>
          <td><?php if ($l['es_base']): ?><span class="estado estado-abierta">Stock inicial</span><?php elseif ($dif !== null && $dif !== 0): ?><select name="l[<?= (int)$l['id'] ?>][motivo]" aria-label="Motivo de <?= e($l['item_nombre']) ?>"><option value="">Sin motivo</option><?php foreach ($dif < 0 ? InventarioService::MOTIVOS_FALTA : InventarioService::MOTIVOS_SOBRA as $k => $v): ?><option value="<?= $k ?>" <?= $l['motivo_faltante'] === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select><?php else: ?><span class="meta">—</span><?php endif; ?></td>
          <td><input name="l[<?= (int)$l['id'] ?>][observacion]" maxlength="255" value="<?= e($l['observacion']) ?>" aria-label="Observación de <?= e($l['item_nombre']) ?>"></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <label class="campo"><span>Observaciones generales</span><textarea name="observaciones" rows="2" maxlength="1000"><?= e($inv['observaciones']) ?></textarea></label>
  </section>
  <section class="bloque bloque-estrecho">
    <h2>Corregir</h2>
    <p class="sub">Cambia los valores de arriba, escribe el motivo y guarda. El PDF se vuelve a generar y queda el registro de antes y después.</p>
    <label class="campo"><span>Motivo de la corrección</span><input name="motivo" required minlength="5" maxlength="200" placeholder="Contó mal los vasos; verificado con el encargado"></label>
    <button class="btn btn-secundario" type="submit">Guardar corrección</button>
  </section>
</form>

<section class="bloque">
  <h2>Envío y cambios</h2>
  <p>Estado del envío: <strong><?= e($envio[$inv['envio_estado']] ?? $inv['envio_estado']) ?></strong><?= $inv['envio_estado'] === 'compartido' ? ' <span class="meta">(WhatsApp no confirma la entrega)</span>' : '' ?> · <a href="<?= e(url('admin/correo')) ?>">Registro de envíos</a></p>
  <?php if ($inv['envio_detalle']): ?><pre class="registro-envio"><?= e($inv['envio_detalle']) ?></pre><?php endif; ?>
  <?php if ($historial): ?>
    <ul class="lista-simple">
      <?php foreach ($historial as $h): ?>
        <li><span class="meta"><?= e(fmt_fecha_hora($h['creado'])) ?></span> <?= e($h['actor_nombre']) ?>: <?= e(str_replace('_', ' ', $h['accion'])) ?><?= $h['motivo'] ? ' — ' . e($h['motivo']) : '' ?><?php require APP_PATH . '/views/partials/diff.php'; ?></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
