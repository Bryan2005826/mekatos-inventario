<?php $title = 'Instalación completa'; ?>
<div class="tarjeta-acceso tarjeta-ancha">
  <p class="acceso-local">Instalación completa</p>
  <h1>El sistema está listo</h1>
  <p>Ingresa al panel con el usuario <strong><?= e($usuario) ?></strong>.</p>
  <?php if ($pins): ?>
    <h2>PIN iniciales</h2>
    <p class="aviso aviso-info"><strong>Esta es la única vez que se muestran.</strong> Imprime o copia esta lista y entrega cada PIN en persona. Si se pierde alguno, genera uno nuevo desde el panel.</p>
    <table class="tabla">
      <thead><tr><th>Trabajador</th><th>PIN</th></tr></thead>
      <tbody>
        <?php foreach ($pins as $p): ?>
          <tr><td><?= e($p['nombre']) ?></td><td class="num pin-celda"><?= e($p['pin']) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <button type="button" class="btn btn-quieto" data-imprimir>Imprimir</button>
  <?php endif; ?>
  <p>Siguiente paso: en el panel, entra a <strong>Kiosco</strong> y autoriza el dispositivo del local.</p>
  <a class="btn btn-primario" href="<?= e(url('admin/login')) ?>">Ir al panel</a>
</div>
