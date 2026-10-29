<?php
$inv = $_SESSION['_invalidos'] ?? [];
unset($_SESSION['_invalidos']);
$err = fn($k) => in_array($k, $inv, true) ? ' campo-error' : '';
$umbralHoras = rtrim(rtrim(number_format(((int)$s['descanso_umbral_minutos']) / 60, 2, '.', ''), '0'), '.');
?>
<div class="encabezado"><h1>Configuración</h1></div>

<form method="post" action="<?= e(url('admin/configuracion')) ?>" class="form">
  <?= Csrf::field() ?>
  <div class="columnas">
    <section class="bloque">
      <h2>Establecimiento</h2>
      <label class="campo<?= $err('nombre_establecimiento') ?>"><span>Nombre</span><input name="nombre_establecimiento" required maxlength="120" value="<?= e($s['nombre_establecimiento']) ?>"></label>

      <h2>Cálculo de horas</h2>
      <div class="form-par">
        <label class="campo<?= $err('descanso_minutos') ?>"><span>Descanso a descontar (min)</span><input type="number" name="descanso_minutos" min="0" max="240" value="<?= e($s['descanso_minutos']) ?>"></label>
        <label class="campo<?= $err('descanso_umbral_minutos') ?>"><span>Solo si la jornada dura al menos (horas)</span><input type="number" step="0.5" name="descanso_umbral_horas" min="0" max="24" value="<?= e($umbralHoras) ?>"></label>
      </div>
      <p class="sub">Ejemplo: 60 min y 6 h. Entrada 8:00 a. m., salida 5:00 p. m. = 9 h brutas − 1 h de descanso = 8 h trabajadas. Una jornada de 5 h no descuenta descanso.</p>
      <div class="form-par">
        <label class="campo<?= $err('nocturna_inicio') ?>"><span>Franja nocturna desde</span><input type="time" name="nocturna_inicio" value="<?= e($s['nocturna_inicio']) ?>"></label>
        <label class="campo<?= $err('nocturna_fin') ?>"><span>hasta</span><input type="time" name="nocturna_fin" value="<?= e($s['nocturna_fin']) ?>"></label>
      </div>
      <p class="sub">Se muestran los minutos nocturnos de cada jornada. Los recargos se liquidarán en la etapa de planillas; confirma la franja vigente con tu contador.</p>
    </section>

    <section class="bloque">
      <h2>Control de marcación</h2>
      <label class="interruptor"><input type="checkbox" name="foto_obligatoria" value="1" <?= $s['foto_obligatoria'] === '1' ? 'checked' : '' ?>><span>Exigir foto de evidencia en cada registro</span></label>
      <label class="interruptor"><input type="checkbox" name="validar_red" value="1" <?= $s['validar_red'] === '1' ? 'checked' : '' ?>><span>Exigir que el kiosco esté conectado al internet del local</span></label>
      <label class="interruptor"><input type="checkbox" name="mensaje_informal" value="1" <?= $s['mensaje_informal'] === '1' ? 'checked' : '' ?>><span>Usar el mensaje informal "¡No seas deshonesto!" cuando se bloquee un registro</span></label>
      <div class="form-par">
        <label class="campo<?= $err('max_horas_jornada') ?>"><span>Horas máximas de una jornada</span><input type="number" name="max_horas_jornada" min="4" max="24" value="<?= e($s['max_horas_jornada']) ?>"><small>Si se supera sin salida, queda incompleta para revisión.</small></label>
        <label class="campo<?= $err('min_minutos_entre_marcas') ?>"><span>Minutos mínimos entre marcaciones</span><input type="number" name="min_minutos_entre_marcas" min="0" max="60" value="<?= e($s['min_minutos_entre_marcas']) ?>"><small>Evita registros dobles por error.</small></label>
      </div>

      <h2>Acceso con PIN</h2>
      <div class="form-par">
        <label class="campo<?= $err('pin_intentos_max') ?>"><span>Intentos antes de bloquear</span><input type="number" name="pin_intentos_max" min="3" max="10" value="<?= e($s['pin_intentos_max']) ?>"></label>
        <label class="campo<?= $err('pin_bloqueo_minutos') ?>"><span>Minutos de bloqueo</span><input type="number" name="pin_bloqueo_minutos" min="1" max="120" value="<?= e($s['pin_bloqueo_minutos']) ?>"></label>
      </div>

      <h2>WhatsApp del administrador</h2>
      <label class="campo<?= $err('whatsapp_admin') ?>"><span>Número que recibe los reportes y pedidos</span><input name="whatsapp_admin" inputmode="tel" maxlength="20" value="<?= e($s['whatsapp_admin']) ?>" placeholder="3101234567"><small>Al terminar un inventario o una lista de pedido, el trabajador toca "Enviar por WhatsApp" y se abre el chat con este número y el mensaje listo.</small></label>

      <h2>Planillas</h2>
      <label class="campo<?= $err('valor_hora_defecto') ?>"><span>Valor de la hora para trabajadores nuevos</span><input type="number" name="valor_hora_defecto" min="0" step="50" value="<?= e($s['valor_hora_defecto']) ?>"><small>Cada trabajador conserva el último valor que le pongas en su planilla.</small></label>
      <label class="campo"><span>Mensaje al pie de la planilla</span><input name="mensaje_planilla" maxlength="160" value="<?= e($s['mensaje_planilla']) ?>"></label>

      <h2>Conservación de datos</h2>
      <label class="campo<?= $err('retencion_fotos_dias') ?>"><span>Conservar fotos de evidencia (días)</span><input type="number" name="retencion_fotos_dias" min="0" max="3650" value="<?= e($s['retencion_fotos_dias']) ?>"><small>0 = no eliminar. Los registros de hora se conservan siempre.</small></label>
    </section>
  </div>
  <section class="bloque">
    <label class="campo"><span>Motivo del cambio (queda en la auditoría)</span><input name="motivo" maxlength="200"></label>
    <button class="btn btn-primario" type="submit">Guardar configuración</button>
  </section>
</form>

<div class="columnas">
  <section class="bloque">
    <h2>Internet de <?= e($local['nombre']) ?></h2>
    <p>Tu IP pública en este momento es <strong class="num"><?= e($ipActual) ?></strong>. Si estás conectado al Wi-Fi del local, agrégala. Si el proveedor de internet la cambia, actualízala aquí.</p>
    <form method="post" action="<?= e(url('admin/redes/agregar')) ?>" class="form form-linea">
      <?= Csrf::field() ?>
      <label class="campo"><span>IP</span><input name="ip" required value="<?= e($ipActual) ?>"></label>
      <label class="campo"><span>Descripción</span><input name="descripcion" maxlength="120" placeholder="Wi-Fi del local"></label>
      <button class="btn btn-secundario" type="submit">Autorizar IP</button>
    </form>
    <?php if ($redes): ?>
      <ul class="lista-simple">
        <?php foreach ($redes as $r): ?>
          <li class="fila-flex">
            <span><strong class="num"><?= e($r['ip']) ?></strong> <?= e($r['descripcion'] ?? '') ?></span>
            <form method="post" action="<?= e(url('admin/redes/eliminar')) ?>" data-confirmar="¿Quitar la IP <?= e($r['ip']) ?>?">
              <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn btn-quieto btn-sm" type="submit">Quitar</button>
            </form>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="vacio">No hay IP autorizadas.</p>
    <?php endif; ?>
  </section>

  <section class="bloque">
    <h2>Copia de seguridad</h2>
    <p>Descarga un ZIP con toda la base de datos y los archivos (fotos y PDF de inventarios). Guárdalo en tu computador o en Google Drive al menos una vez por semana.</p>
    <a class="btn btn-secundario" href="<?= e(url('admin/respaldo')) ?>">Descargar copia de seguridad</a>
    <p class="sub">Para restaurar: importa respaldo.sql en phpMyAdmin y sube la carpeta storage al hosting.</p>
  </section>

  <section class="bloque">
    <h2>Fotos de evidencia</h2>
    <p>Elimina ahora las fotos con más de <?= (int)$s['retencion_fotos_dias'] ?> días. Las horas registradas no se borran.</p>
    <form method="post" action="<?= e(url('admin/fotos/purgar')) ?>" data-confirmar="¿Eliminar las fotos antiguas? No se pueden recuperar.">
      <?= Csrf::field() ?>
      <button class="btn btn-quieto" type="submit" <?= (int)$s['retencion_fotos_dias'] === 0 ? 'disabled' : '' ?>>Eliminar fotos antiguas</button>
    </form>
  </section>
</div>
