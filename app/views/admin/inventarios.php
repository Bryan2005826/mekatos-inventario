<?php $turnos = InventarioService::TURNOS; $envio = ['compartido' => 'Compartido desde el celular', 'enviado_correo' => 'Enviado por correo', 'reintentando' => 'Correo pendiente (reintentando)', 'error_correo' => 'Correo falló', 'pendiente' => 'Solo en el panel']; ?>
<div class="encabezado encabezado-acciones">
  <div>
    <h1>Historial de inventarios</h1>
    <p class="sub">Todo inventario enviado queda guardado aquí, aunque se borren los mensajes de WhatsApp.</p>
  </div>
  <a class="btn btn-quieto" href="<?= e(url('admin/respaldo')) ?>">Descargar copia de seguridad</a>
</div>

<form method="get" action="<?= e(url('admin/inventarios')) ?>" class="form form-linea filtros">
  <label class="campo"><span>Desde</span><input type="date" name="desde" value="<?= e($f['desde']) ?>"></label>
  <label class="campo"><span>Hasta</span><input type="date" name="hasta" value="<?= e($f['hasta']) ?>"></label>
  <label class="campo"><span>Área</span>
    <select name="area"><option value="">Todas</option>
      <?php foreach ($areas as $a): ?><option value="<?= (int)$a['id'] ?>" <?= (int)$f['area_id'] === (int)$a['id'] ? 'selected' : '' ?>><?= e($a['nombre']) ?></option><?php endforeach; ?>
    </select>
  </label>
  <label class="campo"><span>Turno</span>
    <select name="turno"><option value="">Ambos</option>
      <?php foreach ($turnos as $k => $v): ?><option value="<?= $k ?>" <?= $f['turno'] === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?>
    </select>
  </label>
  <label class="campo"><span>Trabajador</span>
    <select name="trabajador"><option value="">Todos</option>
      <?php foreach ($trabajadores as $t): ?><option value="<?= (int)$t['id'] ?>" <?= (int)$f['trabajador_id'] === (int)$t['id'] ? 'selected' : '' ?>><?= e($t['nombre']) ?></option><?php endforeach; ?>
    </select>
  </label>
  <button class="btn btn-primario" type="submit">Buscar</button>
</form>

<section class="bloque">
  <?php if (!$filas): ?>
    <p class="vacio">No hay inventarios con esos filtros.</p>
  <?php else: ?>
  <div class="tabla-envoltura"><table class="tabla">
    <thead><tr><th>Fecha</th><th>Área</th><th>Turno</th><th>Trabajador</th><th>Hora</th><th>Novedades</th><th>Envío</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($filas as $i): ?>
      <tr>
        <td><a href="<?= e(url('admin/inventarios/ver', ['id' => $i['id']])) ?>"><?= e(fmt_fecha($i['fecha'])) ?></a></td>
        <td><?= e($i['area']) ?></td>
        <td><?= e($turnos[$i['turno']]) ?></td>
        <td><?= e($i['trabajador']) ?></td>
        <td class="num"><?= e(fmt_hora($i['enviado'])) ?></td>
        <td><?= (int)$i['diferencias'] ? '<span class="estado estado-incompleta">' . (int)$i['diferencias'] . '</span>' : '<span class="estado estado-completa">Sin novedad</span>' ?><?= $i['corregido'] ? ' <span class="estado estado-corregida">Corregido</span>' : '' ?><?= $i['es_base'] ? ' <span class="estado estado-abierta">Fijó stock</span>' : '' ?><?= $i['motivos_pendientes'] ? ' <span class="estado estado-incompleta">Sin explicar</span>' : '' ?></td>
        <td class="meta"><?= e($envio[$i['envio_estado']] ?? $i['envio_estado']) ?></td>
        <td class="acciones-fila"><a class="btn btn-quieto btn-sm" href="<?= e(url('inventario/pdf', ['id' => $i['id']])) ?>" target="_blank" rel="noopener">PDF</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>
