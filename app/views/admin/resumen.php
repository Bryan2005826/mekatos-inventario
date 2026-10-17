<div class="encabezado">
  <h1>Resumen del día</h1>
  <p class="sub"><?= e(fmt_fecha($hoy)) ?></p>
</div>

<?php if (!$dispositivos): ?>
  <div class="aviso aviso-info">Aún no hay un kiosco autorizado, así que nadie puede registrar asistencia. <a href="<?= e(url('admin/dispositivos')) ?>">Autorizar el dispositivo del local</a>.</div>
<?php endif; ?>

<dl class="cifras">
  <div><dt>Con entrada hoy</dt><dd><?= count($con) ?></dd></div>
  <div><dt>Sin entrada</dt><dd><?= count($sin) ?></dd></div>
  <div><dt>Jornadas abiertas</dt><dd><?= count($abiertas) ?></dd></div>
  <div><dt>Tiempo trabajado hoy</dt><dd class="cifra-texto"><?= e(fmt_minutos($minutosHoy)) ?></dd></div>
</dl>

<div class="columnas">
  <section class="bloque">
    <h2>Trabajando ahora</h2>
    <?php if (!$abiertas): ?>
      <p class="vacio">Nadie tiene una jornada abierta.</p>
    <?php else: ?>
      <ul class="lista-personas">
        <?php foreach ($abiertas as $j): ?>
          <li>
            <span class="punto" style="--c: <?= e($j['color']) ?>"></span>
            <a href="<?= e(url('admin/asistencia/jornada', ['id' => $j['id']])) ?>"><?= e($j['nombre']) ?></a>
            <span class="meta">desde <?= e(fmt_hora($j['entrada'])) ?><?= $j['fecha'] !== $hoy ? ' (' . e(fmt_fecha($j['fecha'])) . ')' : '' ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <section class="bloque">
    <h2>No han registrado entrada</h2>
    <?php if (!$sin): ?>
      <p class="vacio">Todos registraron entrada hoy.</p>
    <?php else: ?>
      <ul class="lista-personas">
        <?php foreach ($sin as $t): ?>
          <li><span class="punto" style="--c: <?= e($t['color']) ?>"></span><?= e($t['nombre']) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</div>

<section class="bloque">
  <h2>Jornadas de hoy</h2>
  <?php $filas = []; foreach ($con as $t) { foreach ($t['jornadas'] as $j) { if ($j['fecha'] === $hoy) { $filas[] = $j + ['nombre' => $t['nombre']]; } } } ?>
  <?php if (!$filas): ?>
    <p class="vacio">Todavía no hay registros hoy.</p>
  <?php else: ?>
    <div class="tabla-envoltura"><table class="tabla">
      <thead><tr><th>Trabajador</th><th>Entrada</th><th>Salida</th><th>Tiempo</th><th>Estado</th></tr></thead>
      <tbody>
        <?php foreach ($filas as $j): ?>
          <tr>
            <td><a href="<?= e(url('admin/asistencia/jornada', ['id' => $j['id']])) ?>"><?= e($j['nombre']) ?></a></td>
            <td><?= e(fmt_hora($j['entrada'])) ?></td>
            <td><?= e(fmt_hora($j['salida'])) ?></td>
            <td><?= $j['estado'] === 'completa' ? e(fmt_minutos((int)$j['minutos_trabajados'])) : '—' ?></td>
            <td><?php require APP_PATH . '/views/partials/estado.php'; ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</section>

<?php if ($incompletas): ?>
<section class="bloque bloque-alerta">
  <h2>Jornadas incompletas por revisar</h2>
  <p class="sub">No se calculan hasta que registres la salida real.</p>
  <ul class="lista-personas">
    <?php foreach ($incompletas as $j): ?>
      <li>
        <span class="punto" style="--c: <?= e($j['color']) ?>"></span>
        <a href="<?= e(url('admin/asistencia/jornada', ['id' => $j['id']])) ?>"><?= e($j['nombre']) ?></a>
        <span class="meta">entrada <?= e(fmt_fecha_hora($j['entrada'])) ?>, sin salida</span>
      </li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<?php if ($alertasStock): ?>
<section class="bloque bloque-alerta">
  <h2>No cuadra la loza</h2>
  <p class="sub">Según el último inventario de cada área. Revisa el motivo y ajusta el stock en Control de loza.</p>
  <ul class="lista-personas">
    <?php foreach ($alertasStock as $a): ?>
      <li><a href="<?= e(url('admin/stock', ['area' => $a['area_id']])) ?>"><strong><?= e($a['item']['nombre']) ?></strong> · <?= e($a['area']) ?></a>
        <span class="meta"><?= $a['dif'] < 0 ? 'faltan ' . -(int)$a['dif'] : 'sobran ' . (int)$a['dif'] ?> (debe haber <?= (int)$a['item']['cantidad_esperada'] ?>)<?= $a['ultimo']['motivo_faltante'] ? ' · ' . e(InventarioService::MOTIVOS[$a['ultimo']['motivo_faltante']] ?? '') : ' · <strong>sin explicar</strong>' ?> · reportó <?= e($a['ultimo']['trabajador']) ?></span></li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<section class="bloque">
  <h2>Inventarios de hoy</h2>
  <?php if (!$tablero): ?>
    <p class="vacio">No hay áreas configuradas.</p>
  <?php else: ?>
  <div class="tabla-envoltura"><table class="tabla">
    <thead><tr><th>Área</th><th>Mediodía</th><th>Noche</th></tr></thead>
    <tbody>
      <?php foreach ($tablero as $fila): ?>
        <tr>
          <td><strong><?= e($fila['area']) ?></strong></td>
          <?php foreach (['mediodia', 'noche'] as $t): $i = $fila[$t]; ?>
            <td><?php if ($i): ?><a href="<?= e(url('admin/inventarios/ver', ['id' => $i['id']])) ?>">Enviado <?= e(fmt_hora($i['enviado'])) ?></a> · <?= e($i['trabajador']) ?><?= (int)$i['diferencias'] ? ' <span class="estado estado-incompleta">' . (int)$i['diferencias'] . ' novedad(es)</span>' : '' ?><?php else: ?><span class="estado estado-abierta">Pendiente</span><?php endif; ?></td>
          <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
  <?php if ($ultimosInv): ?><p><a href="<?= e(url('admin/inventarios')) ?>">Últimos inventarios recibidos (<?= count($ultimosInv) ?> en 3 días)</a></p><?php endif; ?>
</section>

<section class="bloque">
  <h2>Intentos rechazados hoy</h2>
  <?php if (!$rechazos): ?>
    <p class="vacio">Ninguno.</p>
  <?php else: ?>
    <ul class="lista-simple">
      <?php foreach ($rechazos as $r): ?>
        <li><span class="meta"><?= e(fmt_hora($r['creado'])) ?></span> <?= e($r['nombre'] ?? 'Desconocido') ?>: <?= e($r['motivo']) ?></li>
      <?php endforeach; ?>
    </ul>
    <a href="<?= e(url('admin/rechazos')) ?>">Ver todos los intentos rechazados</a>
  <?php endif; ?>
</section>
