<?php foreach (take_flashes() as $f): ?>
  <div class="aviso aviso-<?= e($f['type']) ?>" role="<?= $f['type'] === 'error' ? 'alert' : 'status' ?>"><?= e($f['msg']) ?></div>
<?php endforeach; ?>
