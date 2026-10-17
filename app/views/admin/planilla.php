<?php
$etiquetas = ['abierta' => 'En curso', 'cerrada' => 'Cerrada', 'pagada' => 'Pagada'];
$bloqueada = $p['estado'] === 'pagada';
$num = fn($v) => (float)$v == 0 ? '' : (string)(0 + (float)$v);
?>
<div class="encabezado encabezado-acciones">
  <div>
    <a class="volver" href="<?= e(url('admin/planillas', ['periodo' => $p['periodo_inicio']])) ?>">Planillas · <?= e($periodo['nombre']) ?></a>
    <h1><?= e($p['nombre']) ?></h1>
    <p class="sub"><?= e($periodo['rango']) ?> · <span class="estado estado-p-<?= e($p['estado']) ?>"><?= e($etiquetas[$p['estado']]) ?></span></p>
  </div>
  <a class="btn btn-primario" href="<?= e(url('admin/planillas/imprimir', ['id' => $p['id'], 'auto' => 1])) ?>">Imprimir esta planilla</a>
</div>

<?php if (count($hermanas) > 1): ?>
<nav class="hermanas" aria-label="Otros trabajadores">
  <?php foreach ($hermanas as $h): ?>
    <a href="<?= e(url('admin/planillas/ver', ['id' => $h['id']])) ?>" <?= (int)$h['id'] === (int)$p['id'] ? 'aria-current="page"' : '' ?>><?= e($h['nombre']) ?></a>
  <?php endforeach; ?>
</nav>
<?php endif; ?>

<dl class="cifras">
  <div><dt>Tiempo trabajado</dt><dd class="cifra-texto"><?= e(fmt_minutos($c['minutos'])) ?></dd></div>
  <div><dt>Horas</dt><dd><?= e($c['horas_dec']) ?></dd></div>
  <div><dt data-etiqueta-pago><?= $c['pago_fijo'] ? 'Pago fijo' : 'Pago por horas' ?></dt><dd class="cifra-texto" data-salida="pago"><?= e(fmt_pesos($c['pago_horas'])) ?></dd></div>
  <div><dt>Total a entregar</dt><dd class="cifra-texto" data-salida="entregar"><?= e(fmt_pesos($c['total_entregar'])) ?></dd></div>
</dl>

<?php if ($c['pendientes']): ?>
  <p class="aviso aviso-error"><?= (int)$c['pendientes'] ?> jornada(s) sin salida no se están sumando. <a href="<?= e(url('admin/asistencia', ['trabajador' => $p['trabajador_id'], 'desde' => $p['periodo_inicio'], 'hasta' => $p['periodo_fin']])) ?>">Corregir en Asistencia</a>.</p>
<?php endif; ?>

<div class="planilla-trabajo">
  <section class="bloque">
    <h2>Liquidación</h2>
    <?php if ($bloqueada): ?>
      <p class="aviso aviso-ok">Pagada el <?= e(fmt_fecha_hora($p['pagada'])) ?> por <?= e(fmt_pesos($p['total_pagado'])) ?>. Las jornadas de esta quincena quedaron bloqueadas.</p>
    <?php endif; ?>
    <form method="post" action="<?= e(url('admin/planillas/guardar')) ?>" class="form" id="form-planilla" data-minutos="<?= (int)$c['minutos'] ?>">
      <?= Csrf::field() ?>
      <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
      <fieldset <?= $bloqueada ? 'disabled' : '' ?> class="form sin-borde">
        <label class="interruptor interruptor-grande"><input type="checkbox" name="pago_fijo" value="1" id="pago-fijo" <?= $c['pago_fijo'] ? 'checked' : '' ?>><span><strong>Trabajador de pago fijo</strong><br><small>Se le paga un valor fijo en la quincena, no por horas.</small></span></label>
        <label class="campo campo-destacado" id="campo-hora" <?= $c['pago_fijo'] ? 'hidden' : '' ?>><span>Valor de la hora</span>
          <input name="valor_hora" type="number" min="0" step="50" inputmode="numeric" value="<?= e($num($p['valor_hora'])) ?>" placeholder="5700" data-calc>
          <small><?= e($c['horas_dec']) ?> horas × valor de la hora</small>
        </label>
        <label class="campo campo-destacado" id="campo-fijo" <?= $c['pago_fijo'] ? '' : 'hidden' ?>><span>Valor a pagar en la quincena</span>
          <input name="valor_fijo" type="number" min="0" step="1000" inputmode="numeric" value="<?= e($num($p['valor_fijo'])) ?>" placeholder="650000" data-calc>
          <small>Trabajó <?= e($c['horas_dec']) ?> horas. Se paga este valor sin importar las horas.</small>
        </label>
        <div class="form-par">
          <label class="campo"><span>Bono extra: descripción</span><input name="bono_descripcion" maxlength="120" value="<?= e($p['bono_descripcion']) ?>"></label>
          <label class="campo"><span>Bono extra: valor</span><input name="bono_valor" type="number" min="0" step="100" inputmode="numeric" value="<?= e($num($p['bono_valor'])) ?>" data-calc></label>
        </div>
        <div class="form-par">
          <label class="campo"><span>Comida (descuento)</span><input name="desc_comida" type="number" min="0" step="100" inputmode="numeric" value="<?= e($num($p['desc_comida'])) ?>" data-calc></label>
          <label class="campo"><span>Adelanto en efectivo</span><input name="desc_adelanto" type="number" min="0" step="100" inputmode="numeric" value="<?= e($num($p['desc_adelanto'])) ?>" data-calc></label>
          <label class="campo"><span>Otros descuentos</span><input name="desc_otros" type="number" min="0" step="100" inputmode="numeric" value="<?= e($num($p['desc_otros'])) ?>" data-calc></label>
        </div>
        <label class="campo"><span>Observaciones</span><textarea name="observaciones" rows="2" maxlength="1000"><?= e($p['observaciones']) ?></textarea></label>
        <table class="tabla tabla-cuentas" aria-live="polite">
          <tr><td data-etiqueta-pago><?= $c['pago_fijo'] ? 'Pago fijo' : 'Pago por horas' ?></td><td class="num" data-salida="pago"><?= e(fmt_pesos($c['pago_horas'])) ?></td></tr>
          <tr><td>+ Bono</td><td class="num" data-salida="bono"><?= e(fmt_pesos($c['bono'])) ?></td></tr>
          <tr><td>− Descuentos</td><td class="num" data-salida="descuentos"><?= e(fmt_pesos($c['descuentos'])) ?></td></tr>
          <tr class="fila-total"><td>Total a entregar</td><td class="num" data-salida="entregar"><?= e(fmt_pesos($c['total_entregar'])) ?></td></tr>
        </table>
        <button class="btn btn-primario" type="submit">Guardar planilla</button>
      </fieldset>
    </form>
  </section>

  <section class="bloque">
    <h2>Estado</h2>
    <?php if ($p['estado'] === 'abierta'): ?>
      <p>La quincena sigue en curso. Se cierra sola el <?= e(fmt_fecha($p['periodo_fin'])) ?> a la medianoche. Puedes poner el valor de la hora desde ya.</p>
    <?php elseif ($p['estado'] === 'cerrada'): ?>
      <p>Quincena cerrada. Cuando entregues el pago y el trabajador firme, márcala como pagada para bloquear sus jornadas.</p>
      <form method="post" action="<?= e(url('admin/planillas/pagar')) ?>" data-confirmar="¿Marcar como pagada? Las jornadas de esta quincena quedarán bloqueadas.">
        <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
        <button class="btn btn-secundario" type="submit">Marcar como pagada</button>
      </form>
    <?php else: ?>
      <form method="post" action="<?= e(url('admin/planillas/reabrir')) ?>" class="form">
        <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
        <label class="campo"><span>Motivo para reabrir</span><input name="motivo" required minlength="5" maxlength="200"></label>
        <button class="btn btn-peligro" type="submit">Reabrir planilla</button>
      </form>
    <?php endif; ?>
    <?php if ($historial): ?>
      <h2>Cambios</h2>
      <ul class="lista-simple">
        <?php foreach ($historial as $h): ?>
          <li><span class="meta"><?= e(fmt_fecha_hora($h['creado'])) ?></span> <?= e($h['actor_nombre']) ?>: <?= e(str_replace('_', ' ', $h['accion'])) ?><?= $h['motivo'] ? ' — ' . e($h['motivo']) : '' ?><?php require APP_PATH . '/views/partials/diff.php'; ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</div>

<section class="bloque vista-hoja">
  <h2>Así se imprime</h2>
  <div class="hoja-marco"><?php require APP_PATH . '/views/partials/planilla_hoja.php'; ?></div>
</section>
