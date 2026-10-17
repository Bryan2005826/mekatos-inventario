<div class="encabezado">
  <a class="volver" href="<?= e(url('admin/asistencia')) ?>">Asistencia</a>
  <h1>Registrar jornada manual</h1>
  <p class="sub">Para casos en que el trabajador no pudo marcar en el kiosco. Queda identificada como registro manual, con tu nombre y el motivo.</p>
</div>
<section class="bloque bloque-estrecho">
  <form method="post" action="<?= e(url('admin/asistencia/manual')) ?>" class="form">
    <?= Csrf::field() ?>
    <label class="campo"><span>Trabajador</span>
      <select name="trabajador" required>
        <option value="">Selecciona…</option>
        <?php foreach ($trabajadores as $t): ?>
          <option value="<?= (int)$t['id'] ?>" <?= $trab === (int)$t['id'] ? 'selected' : '' ?>><?= e($t['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="campo"><span>Entrada</span><input type="datetime-local" name="entrada" required></label>
    <label class="campo"><span>Salida</span><input type="datetime-local" name="salida"><small>Vacía si sigue trabajando.</small></label>
    <label class="campo"><span>Motivo</span><textarea name="motivo" required minlength="5" maxlength="500" rows="2" placeholder="El kiosco estaba sin internet."></textarea></label>
    <button class="btn btn-primario" type="submit">Registrar jornada</button>
  </form>
</section>
