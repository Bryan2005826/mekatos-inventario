<?php
$etiquetas = ['abierta' => 'Abierta', 'completa' => 'Completa', 'incompleta' => 'Incompleta', 'anulada' => 'Anulada'];
?>
<span class="estado estado-<?= e($j['estado']) ?>"><?= e($etiquetas[$j['estado']] ?? $j['estado']) ?></span><?php if (!empty($j['corregida'])): ?> <span class="estado estado-corregida" title="Modificada por el administrador">Corregida</span><?php endif; ?>
