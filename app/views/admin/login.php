<?php $title = 'Ingreso del administrador'; ?>
<div class="tarjeta-acceso">
  <p class="acceso-local"><?= e($establecimiento) ?></p>
  <h1>Panel del administrador</h1>
  <form method="post" action="<?= e(url('admin/login')) ?>" class="form">
    <?= Csrf::field() ?>
    <label class="campo"><span>Usuario</span>
      <input name="usuario" required autocomplete="username" autofocus>
    </label>
    <label class="campo"><span>Contraseña</span>
      <input name="password" type="password" required autocomplete="current-password">
    </label>
    <button class="btn btn-primario btn-ancho" type="submit">Ingresar</button>
  </form>
  <a class="enlace-quieto" href="<?= e(url('')) ?>">Volver al kiosco</a>
</div>
