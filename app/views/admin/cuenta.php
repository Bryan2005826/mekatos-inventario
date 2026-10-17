<div class="encabezado"><h1>Mi cuenta</h1><p class="sub">Usuario: <?= e($admin['usuario']) ?></p></div>
<section class="bloque bloque-estrecho">
  <h2>Cambiar contraseña</h2>
  <form method="post" action="<?= e(url('admin/cuenta')) ?>" class="form">
    <?= Csrf::field() ?>
    <label class="campo"><span>Contraseña actual</span><input type="password" name="actual" required autocomplete="current-password"></label>
    <label class="campo"><span>Nueva contraseña</span><input type="password" name="nueva" required minlength="10" autocomplete="new-password"><small>Mínimo 10 caracteres.</small></label>
    <label class="campo"><span>Repite la nueva contraseña</span><input type="password" name="repetir" required minlength="10" autocomplete="new-password"></label>
    <button class="btn btn-primario" type="submit">Cambiar contraseña</button>
  </form>
</section>
