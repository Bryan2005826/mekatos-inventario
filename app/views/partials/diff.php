<?php
$a = $h['antes'] ? json_decode($h['antes'], true) : null;
$d = $h['despues'] ? json_decode($h['despues'], true) : null;
if ($a || $d):
    $claves = array_unique(array_merge(array_keys($a ?? []), array_keys($d ?? [])));
?>
<details class="diff"><summary>Ver detalle</summary>
  <table class="tabla tabla-mini">
    <thead><tr><th>Campo</th><th>Antes</th><th>Después</th></tr></thead>
    <tbody>
    <?php foreach ($claves as $k): ?>
      <tr><td><?= e($k) ?></td><td><?= e(is_scalar($a[$k] ?? null) ? $a[$k] : json_encode($a[$k] ?? null)) ?></td><td><?= e(is_scalar($d[$k] ?? null) ? $d[$k] : json_encode($d[$k] ?? null)) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</details>
<?php endif; ?>
