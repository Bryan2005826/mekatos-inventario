<div class="encabezado">
  <a class="volver" href="<?= e(url('admin/trabajadores')) ?>">Trabajadores</a>
  <h1><?= e($t['nombre']) ?></h1>
</div>

<div class="columnas">
<section class="bloque">
  <h2>Datos</h2>
  <form method="post" action="<?= e(url('admin/trabajadores/guardar')) ?>" class="form">
    <?= Csrf::field() ?>
    <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
    <label class="campo"><span>Nombre</span><input name="nombre" required maxlength="60" value="<?= e($t['nombre']) ?>"></label>
    <label class="campo"><span>Documento</span><input name="documento" maxlength="30" value="<?= e($t['documento']) ?>"></label>
    <label class="campo"><span>Local donde trabaja</span>
      <select name="local_id"><?php foreach (LocalService::todos() as $l): ?><option value="<?= (int)$l['id'] ?>" <?= (int)$l['id'] === (int)$t['local_id'] ? 'selected' : '' ?>><?= e($l['nombre']) ?></option><?php endforeach; ?></select>
      <small>Solo puede marcar en el celular de su local.</small>
    </label>
    <label class="campo"><span>Orden en el kiosco</span><input name="orden" type="number" min="0" max="999" value="<?= (int)$t['orden'] ?>"></label>
    <fieldset class="campo">
      <legend>Color de la ficha</legend>
      <div class="colores">
        <?php foreach ($colores as $c): ?>
          <label class="color-opcion" style="--c: <?= e($c) ?>">
            <input type="radio" name="color" value="<?= e($c) ?>" <?= strtoupper($t['color']) === $c ? 'checked' : '' ?>>
            <span class="visualmente-oculto"><?= e($c) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </fieldset>
    <label class="campo"><span>Motivo del cambio (opcional)</span><input name="motivo" maxlength="200"></label>
    <button class="btn btn-primario" type="submit">Guardar cambios</button>
  </form>
</section>

<div>
<section class="bloque">
  <h2>PIN de acceso</h2>
  <p>Generado el <?= e(fmt_fecha_hora($t['pin_generado'])) ?>. Restablecerlo invalida el anterior y desbloquea al trabajador si tenía demasiados intentos fallidos.</p>
  <form method="post" action="<?= e(url('admin/trabajadores/pin')) ?>" class="form" data-confirmar="¿Generar un PIN nuevo para <?= e($t['nombre']) ?>? El actual dejará de funcionar.">
    <?= Csrf::field() ?>
    <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
    <label class="campo"><span>Motivo</span><input name="motivo" maxlength="200" placeholder="Olvidó su PIN"></label>
    <button class="btn btn-secundario" type="submit">Generar PIN nuevo</button>
  </form>
</section>

<section class="bloque">
  <h2><?= $t['activo'] ? 'Desactivar' : 'Activar' ?> trabajador</h2>
  <?php if ($t['activo']): ?>
    <p>Deja de aparecer en el kiosco. Su historial y sus jornadas se conservan.</p>
  <?php else: ?>
    <p>Volverá a aparecer en el kiosco con su PIN actual.</p>
  <?php endif; ?>
  <form method="post" action="<?= e(url('admin/trabajadores/estado')) ?>" class="form">
    <?= Csrf::field() ?>
    <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
    <input type="hidden" name="activo" value="<?= $t['activo'] ? '0' : '1' ?>">
    <label class="campo"><span>Motivo<?= $t['activo'] ? '' : ' (opcional)' ?></span><input name="motivo" maxlength="200" <?= $t['activo'] ? 'required' : '' ?>></label>
    <button class="btn <?= $t['activo'] ? 'btn-peligro' : 'btn-secundario' ?>" type="submit"><?= $t['activo'] ? 'Desactivar' : 'Activar' ?></button>
  </form>
</section>

<section class="bloque">
  <h2>Eliminar trabajador</h2>
  <?php if ($registros): ?>
    <p><?= e($t['nombre']) ?> ya tiene <?= (int)$registros ?> registro(s) de horas, planillas pagadas o inventarios. No se puede borrar porque son el soporte de lo que se le pagó. Si se retiró, usa <strong>Desactivar</strong>.</p>
  <?php else: ?>
    <p>No tiene ningún registro, así que se puede borrar por completo (por ejemplo, si lo creaste por error).</p>
    <form method="post" action="<?= e(url('admin/trabajadores/eliminar')) ?>" class="form" data-confirmar="¿Eliminar a <?= e($t['nombre']) ?> por completo? No se puede deshacer.">
      <?= Csrf::field() ?>
      <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
      <label class="campo"><span>Motivo (opcional)</span><input name="motivo" maxlength="200"></label>
      <button class="btn btn-peligro" type="submit">Eliminar trabajador</button>
    </form>
  <?php endif; ?>
</section>
</div>
</div>
