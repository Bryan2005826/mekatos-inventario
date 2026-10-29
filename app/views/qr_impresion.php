<div class="barra-impresion">
  <a class="btn btn-quieto" href="<?= e(url('admin/qr')) ?>">Volver</a>
  <p>Una hoja por área. Para PDF elige "Guardar como PDF" al imprimir.</p>
  <button type="button" class="btn btn-primario" data-imprimir>Imprimir</button>
</div>
<?php foreach ($areas as $a): $enlace = url('inventario', ['a' => $a['token']]); ?>
  <article class="hoja hoja-qr">
    <p class="hoja-qr-local"><?= e(LocalService::nombre((int)$a['local_id'])) ?></p>
    <h1 class="hoja-qr-area"><?= e($a['nombre']) ?></h1>
    <p class="hoja-qr-instruccion">Escanea para hacer el inventario de loza y utensilios</p>
    <canvas class="qr-lienzo qr-grande" width="900" height="900" data-qr="<?= e($enlace) ?>" role="img" aria-label="Código QR de <?= e($a['nombre']) ?>"></canvas>
    <ol class="hoja-qr-pasos">
      <li>Abre la cámara del celular y apunta al código.</li>
      <li>Toca tu nombre y escribe tu PIN.</li>
      <li>Elige el turno, cuenta y envía.</li>
    </ol>
  </article>
<?php endforeach; ?>
<?php if (input('auto') === '1'): ?><span data-auto-imprimir hidden></span><?php endif; ?>
<script src="<?= e(asset('assets/js/vendor/qrcode.js')) ?>"></script>
