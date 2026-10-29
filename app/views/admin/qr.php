<div class="encabezado encabezado-acciones">
  <div>
    <h1>Códigos QR · <?= e($local['nombre']) ?></h1>
    <p class="sub">Un código fijo por área. Imprímelo y pégalo en el lugar. El código solo abre el formulario; para registrar se necesita el PIN.</p>
  </div>
  <a class="btn btn-primario" href="<?= e(url('admin/qr/imprimir', ['auto' => 1])) ?>">Imprimir todos</a>
</div>
<?php if (!is_https()): ?>
  <p class="aviso aviso-info">El QR apunta a <strong><?= e(base_url()) ?></strong>. Cuando actives el SSL, vuelve a descargarlos para que abran con https://.</p>
<?php endif; ?>
<div class="qr-grilla">
  <?php foreach ($areas as $a): $enlace = url('inventario', ['a' => $a['token']]); ?>
    <section class="bloque qr-tarjeta">
      <h2><?= e($a['nombre']) ?></h2>
      <p class="meta"><?= e($local['nombre']) ?> · <?= $a['tipo'] === 'pedido' ? 'Lista de pedido' : 'Inventario' ?></p>
      <canvas class="qr-lienzo" width="300" height="300" data-qr="<?= e($enlace) ?>" data-nombre="<?= e($a['nombre']) ?>" role="img" aria-label="Código QR de <?= e($a['nombre']) ?>"></canvas>
      <p class="qr-enlace"><a href="<?= e($enlace) ?>" target="_blank" rel="noopener">Abrir el formulario</a></p>
      <div class="qr-acciones">
        <button type="button" class="btn btn-secundario btn-sm" data-qr-png="<?= e($a['nombre']) ?>">Descargar PNG</button>
        <a class="btn btn-quieto btn-sm" href="<?= e(url('admin/qr/imprimir', ['id' => $a['id'], 'auto' => 1])) ?>">Imprimir / PDF</a>
      </div>
      <details>
        <summary>Cambiar este código</summary>
        <form method="post" action="<?= e(url('admin/qr/regenerar')) ?>" class="form" data-confirmar="¿Cambiar el QR de <?= e($a['nombre']) ?>? El que está pegado dejará de funcionar.">
          <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
          <p class="sub">Úsalo si el QR se dañó o alguien lo copió. El impreso anterior dejará de funcionar.</p>
          <label class="campo"><span>Motivo</span><input name="motivo" maxlength="200"></label>
          <button class="btn btn-peligro btn-sm" type="submit">Generar QR nuevo</button>
        </form>
      </details>
    </section>
  <?php endforeach; ?>
</div>
<script src="<?= e(asset('assets/js/vendor/qrcode.js')) ?>"></script>
