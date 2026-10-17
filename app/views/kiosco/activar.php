<div class="k-pantalla k-pantalla-centro">
  <div class="k-panel k-panel-texto">
    <h1>Autorizar este dispositivo</h1>
    <?php require APP_PATH . '/views/partials/flashes.php'; ?>
    <?php if ($dispositivo): ?>
      <p>Este dispositivo ya está autorizado como <strong><?= e($dispositivo['nombre']) ?></strong>.</p>
      <a class="btn btn-primario" href="<?= e(url('')) ?>">Volver al kiosco</a>
    <?php else: ?>
      <p>Solo el kiosco del local puede registrar entradas y salidas. Para autorizarlo, el administrador genera un código en <strong>Panel › Kiosco</strong> y lo escribe aquí. El código vence a los 15 minutos.</p>
      <form method="post" action="<?= e(url('activar')) ?>" class="form">
        <?= Csrf::field() ?>
        <label class="campo">
          <span>Código de activación</span>
          <input name="codigo" required autocomplete="off" autocapitalize="characters" inputmode="text" maxlength="9" placeholder="ABCD-1234" class="input-codigo">
        </label>
        <div class="k-fila-botones">
          <a class="btn btn-quieto" href="<?= e(url('')) ?>">Cancelar</a>
          <button class="btn btn-primario" type="submit">Autorizar dispositivo</button>
        </div>
      </form>
    <?php endif; ?>
  </div>
</div>
