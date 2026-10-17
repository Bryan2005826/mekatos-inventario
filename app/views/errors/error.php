<?php $title = $title; ?>
<div class="tarjeta-acceso">
  <p class="acceso-local">Error <?= (int)$code ?></p>
  <h1><?= e($title) ?></h1>
  <?php if ($message): ?><p><?= e($message) ?></p><?php endif; ?>
  <a class="btn btn-primario" href="<?= e(url('')) ?>">Ir al inicio</a>
</div>
