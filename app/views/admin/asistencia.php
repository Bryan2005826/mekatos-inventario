<div class="encabezado encabezado-acciones">
  <h1>Asistencia · <?= e(LocalService::actual()['nombre']) ?></h1>
  <a class="btn btn-secundario" href="<?= e(url('admin/asistencia/nueva', $trab ? ['trabajador' => $trab] : [])) ?>">Registrar jornada manual</a>
</div>

<form method="get" action="<?= e(url('admin/asistencia')) ?>" class="form form-linea filtros">
  <label class="campo"><span>Desde</span><input type="date" name="desde" value="<?= e($desde) ?>"></label>
  <label class="campo"><span>Hasta</span><input type="date" name="hasta" value="<?= e($hasta) ?>"></label>
  <label class="campo"><span>Trabajador</span>
    <select name="trabajador">
      <option value="">Todos</option>
      <?php foreach ($trabajadores as $t): ?>
        <option value="<?= (int)$t['id'] ?>" <?= $trab === (int)$t['id'] ? 'selected' : '' ?>><?= e($t['nombre']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label class="campo"><span>Estado</span>
    <select name="estado">
      <option value="">Todos</option>
      <?php foreach (['abierta' => 'Abiertas', 'completa' => 'Completas', 'incompleta' => 'Incompletas', 'anulada' => 'Anuladas'] as $k => $v): ?>
        <option value="<?= $k ?>" <?= $estado === $k ? 'selected' : '' ?>><?= $v ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <button class="btn btn-primario" type="submit">Filtrar</button>
</form>

<?php if ($totales): ?>
<section class="bloque">
  <h2>Totales del rango</h2>
  <div class="tabla-envoltura"><table class="tabla">
    <thead><tr><th>Trabajador</th><th>Jornadas completas</th><th>Tiempo trabajado</th><th>Pendientes</th></tr></thead>
    <tbody>
      <?php foreach ($totales as $tt): ?>
        <tr>
          <td><span class="punto" style="--c: <?= e($tt['color']) ?>"></span><?= e($tt['nombre']) ?></td>
          <td><?= (int)$tt['jornadas'] ?></td>
          <td class="num"><?= e(fmt_minutos($tt['min'])) ?></td>
          <td><?= $tt['pendientes'] ? '<span class="estado estado-incompleta">' . (int)$tt['pendientes'] . ' sin cerrar</span>' : '—' ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
  <p class="sub">Las jornadas abiertas o incompletas no suman tiempo hasta que tengan salida.</p>
</section>
<?php endif; ?>

<section class="bloque">
  <h2>Jornadas</h2>
  <?php if (!$jornadas): ?>
    <p class="vacio">No hay jornadas en este rango.</p>
  <?php else: ?>
  <div class="tabla-envoltura"><table class="tabla">
    <thead><tr><th>Fecha</th><th>Trabajador</th><th>Entrada</th><th>Salida</th><th>Descanso</th><th>Trabajado</th><th>Nocturno</th><th>Estado</th></tr></thead>
    <tbody>
    <?php foreach ($jornadas as $j): ?>
      <tr class="<?= $j['estado'] === 'anulada' ? 'fila-inactiva' : '' ?>">
        <td><a href="<?= e(url('admin/asistencia/jornada', ['id' => $j['id']])) ?>"><?= e(fmt_fecha($j['fecha'])) ?></a></td>
        <td><span class="punto" style="--c: <?= e($j['color']) ?>"></span><?= e($j['nombre']) ?></td>
        <td><?= e(fmt_hora($j['entrada'])) ?></td>
        <td><?= e(fmt_hora($j['salida'])) ?></td>
        <td><?= $j['minutos_descanso'] !== null ? e(fmt_minutos((int)$j['minutos_descanso'])) : '—' ?></td>
        <td class="num"><?= $j['minutos_trabajados'] !== null ? e(fmt_minutos((int)$j['minutos_trabajados'])) : '—' ?></td>
        <td><?= $j['minutos_nocturnos'] ? e(fmt_minutos((int)$j['minutos_nocturnos'])) : '—' ?></td>
        <td><?php require APP_PATH . '/views/partials/estado.php'; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>
