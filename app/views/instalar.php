<?php $title = 'Instalación'; ?>
<div class="tarjeta-acceso tarjeta-ancha">
  <p class="acceso-local">Instalación inicial</p>
  <h1>Configura el sistema de asistencia</h1>

  <ul class="revision">
    <?php foreach ($checks as [$nombre, $ok, $detalle]): ?>
      <li class="<?= $ok ? 'ok' : 'falla' ?>"><strong><?= $ok ? '✓' : '✗' ?> <?= e($nombre) ?></strong><span><?= e($detalle) ?></span></li>
    <?php endforeach; ?>
  </ul>

  <?php if (!$listo): ?>
    <p class="aviso aviso-error">Corrige los puntos marcados con ✗ y recarga esta página.</p>
  <?php else: ?>
    <form method="post" action="<?= e(url('instalar')) ?>" class="form">
      <?= Csrf::field() ?>
      <label class="campo"><span>Nombre del establecimiento</span><input name="establecimiento" required maxlength="120"></label>
      <div class="form-par">
        <label class="campo"><span>Tu nombre</span><input name="nombre" required maxlength="120"></label>
        <label class="campo"><span>Usuario administrador</span><input name="usuario" required pattern="[a-zA-Z0-9._\-]{3,40}" autocomplete="username"></label>
      </div>
      <div class="form-par">
        <label class="campo"><span>Contraseña</span><input type="password" name="password" required minlength="10" autocomplete="new-password"><small>Mínimo 10 caracteres.</small></label>
        <label class="campo"><span>Repite la contraseña</span><input type="password" name="password2" required minlength="10" autocomplete="new-password"></label>
      </div>
      <label class="campo"><span>Trabajadores iniciales (separados por coma)</span><input name="trabajadores" value="Ronaldo, Brian, David, Julián, William"><small>Se genera un PIN aleatorio distinto para cada uno.</small></label>
      <button class="btn btn-primario btn-ancho" type="submit">Instalar</button>
    </form>
  <?php endif; ?>
</div>
