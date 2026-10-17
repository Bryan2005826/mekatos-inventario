<?php
$local = fn($utc) => $utc ? utc_to_local($utc)->format('Y-m-d\TH:i') : '';
?>
<div class="encabezado">
  <a class="volver" href="<?= e(url('admin/asistencia', ['trabajador' => $j['trabajador_id']])) ?>">Asistencia de <?= e($j['nombre']) ?></a>
  <h1><?= e($j['nombre']) ?>, <?= e(fmt_fecha($j['fecha'])) ?></h1>
  <p><?php require APP_PATH . '/views/partials/estado.php'; ?></p>
</div>

<dl class="cifras">
  <div><dt>Entrada</dt><dd class="cifra-texto"><?= e(fmt_hora($j['entrada'])) ?></dd></div>
  <div><dt>Salida</dt><dd class="cifra-texto"><?= e(fmt_hora($j['salida'])) ?></dd></div>
  <div><dt>Descanso descontado</dt><dd class="cifra-texto"><?= $j['minutos_descanso'] !== null ? e(fmt_minutos((int)$j['minutos_descanso'])) : '—' ?></dd></div>
  <div><dt>Tiempo trabajado</dt><dd class="cifra-texto"><?= $j['minutos_trabajados'] !== null ? e(fmt_minutos((int)$j['minutos_trabajados'])) : 'Sin calcular' ?></dd></div>
</dl>
<?php if ($j['observaciones']): ?><p class="aviso aviso-info"><?= nl2br(e($j['observaciones'])) ?></p><?php endif; ?>

<section class="bloque">
  <h2>Marcaciones originales</h2>
  <p class="sub">Estos registros no se modifican. Las correcciones se aplican a la jornada y quedan en el historial.</p>
  <?php if (!$marcaciones): ?>
    <p class="vacio">Sin marcaciones.</p>
  <?php else: ?>
  <div class="marcaciones">
    <?php foreach ($marcaciones as $m): ?>
      <figure class="marcacion">
        <?php if ($m['foto']): ?>
          <a href="<?= e(url('admin/foto', ['m' => $m['id']])) ?>" target="_blank" rel="noopener"><img src="<?= e(url('admin/foto', ['m' => $m['id']])) ?>" alt="Foto de evidencia de la <?= e($m['tipo']) ?>" loading="lazy" width="160" height="120"></a>
        <?php else: ?>
          <div class="sin-foto"><?= $m['origen'] === 'admin' ? 'Registro manual' : 'Sin foto' ?></div>
        <?php endif; ?>
        <figcaption>
          <strong><?= $m['tipo'] === 'entrada' ? 'Entrada' : 'Salida' ?></strong> <?= e(fmt_fecha_hora($m['fecha_hora'])) ?><br>
          <span class="meta"><?= e($m['dispositivo'] ?? ($m['origen'] === 'admin' ? 'Panel' : '—')) ?> · IP <?= e($m['ip']) ?></span><br>
          <span class="meta">Identidad: <?= e($m['verif_identidad']) ?> · Ubicación: <?= e($m['verif_ubicacion']) ?></span>
          <?php if ($m['observaciones']): ?><br><span class="meta"><?= e($m['observaciones']) ?></span><?php endif; ?>
        </figcaption>
      </figure>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>

<?php if ($j['estado'] !== 'anulada'): ?>
<div class="columnas">
<section class="bloque">
  <h2>Corregir horas</h2>
  <form method="post" action="<?= e(url('admin/asistencia/corregir')) ?>" class="form">
    <?= Csrf::field() ?>
    <input type="hidden" name="id" value="<?= (int)$j['id'] ?>">
    <label class="campo"><span>Entrada</span><input type="datetime-local" name="entrada" required value="<?= e($local($j['entrada'])) ?>"></label>
    <label class="campo"><span>Salida</span><input type="datetime-local" name="salida" value="<?= e($local($j['salida'])) ?>"><small>Déjala vacía si la jornada sigue abierta.</small></label>
    <label class="campo"><span>Motivo de la corrección</span><textarea name="motivo" required minlength="5" maxlength="500" rows="2" placeholder="Olvidó marcar la salida; confirmado con el encargado."></textarea></label>
    <button class="btn btn-primario" type="submit">Guardar corrección</button>
  </form>
</section>
<section class="bloque">
  <h2>Anular jornada</h2>
  <p>Úsalo si el registro fue un error. No se borra: queda como anulada y no suma tiempo.</p>
  <form method="post" action="<?= e(url('admin/asistencia/anular')) ?>" class="form" data-confirmar="¿Anular esta jornada?">
    <?= Csrf::field() ?>
    <input type="hidden" name="id" value="<?= (int)$j['id'] ?>">
    <label class="campo"><span>Motivo</span><textarea name="motivo" required minlength="5" maxlength="500" rows="2"></textarea></label>
    <button class="btn btn-peligro" type="submit">Anular jornada</button>
  </form>
</section>
</div>
<?php endif; ?>

<section class="bloque">
  <h2>Historial de cambios</h2>
  <?php if (!$historial): ?>
    <p class="vacio">Esta jornada no ha sido modificada.</p>
  <?php else: ?>
    <ul class="lista-simple">
      <?php foreach ($historial as $h): ?>
        <li><span class="meta"><?= e(fmt_fecha_hora($h['creado'])) ?></span> <strong><?= e($h['actor_nombre']) ?></strong>: <?= e(str_replace('_', ' ', $h['accion'])) ?><?= $h['motivo'] ? ' — ' . e($h['motivo']) : '' ?>
          <?php require APP_PATH . '/views/partials/diff.php'; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
