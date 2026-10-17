<div class="barra-impresion">
  <a class="btn btn-quieto" href="<?= e(url('admin/planillas', ['periodo' => $periodo['inicio']])) ?>">Volver a planillas</a>
  <p><strong><?= e($periodo['nombre']) ?></strong> · <?= count($hojas) ?> hoja(s), una por trabajador</p>
  <button type="button" class="btn btn-primario" data-imprimir>Imprimir</button>
</div>
<?php if (!$hojas): ?>
  <p class="vacio" style="text-align:center">No hay planillas en esta quincena.</p>
<?php endif; ?>
<?php foreach ($hojas as $h): ?>
  <?php ['p' => $p, 'c' => $c, 'jornadas' => $jornadas] = $h; require APP_PATH . '/views/partials/planilla_hoja.php'; ?>
<?php endforeach; ?>
<?php if ($hojas && input('auto') === '1'): ?><span data-auto-imprimir hidden></span><?php endif; ?>
