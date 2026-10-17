<?php
$tipos = ['inventario' => 'Inventario', 'cierre_quincena' => 'Cierre de quincena', 'prueba' => 'Prueba'];
$estados = ['pendiente' => ['Pendiente', 'estado-abierta'], 'enviado' => ['Enviado', 'estado-completa'], 'error' => ['Falló', 'estado-incompleta']];
$tieneClave = Secreto::descifrar($s['smtp_clave']) !== '';
$tieneApi = Secreto::descifrar($s['brevo_api_key']) !== '';
?>
<div class="encabezado">
  <h1>Correo y envíos</h1>
  <p class="sub">Cada inventario enviado llega al correo con el PDF adjunto, y al cerrar la quincena llega el resumen de horas. InfinityFree no envía correos por sí mismo, así que se usa una cuenta tuya.</p>
</div>

<?php if (!$listo): ?>
  <p class="aviso aviso-info">El envío de correos está apagado. Los PDF igual quedan guardados en el panel y los trabajadores pueden compartirlos por WhatsApp.</p>
<?php endif; ?>

<form method="post" action="<?= e(url('admin/correo')) ?>" class="form">
  <?= Csrf::field() ?>
  <div class="columnas">
    <section class="bloque">
      <h2>Envío</h2>
      <label class="interruptor"><input type="checkbox" name="correo_activo" value="1" <?= $s['correo_activo'] === '1' ? 'checked' : '' ?>><span>Enviar correos automáticos</span></label>
      <label class="interruptor"><input type="checkbox" name="notificar_inventario" value="1" <?= $s['notificar_inventario'] === '1' ? 'checked' : '' ?>><span>Cada inventario enviado, con el PDF adjunto</span></label>
      <label class="interruptor"><input type="checkbox" name="notificar_faltantes" value="1" <?= $s['notificar_faltantes'] === '1' ? 'checked' : '' ?>><span>Avisar siempre que falte loza, aunque lo anterior esté apagado</span></label>
      <label class="interruptor"><input type="checkbox" name="notificar_cierre" value="1" <?= $s['notificar_cierre'] === '1' ? 'checked' : '' ?>><span>Resumen al cerrar cada quincena</span></label>
      <label class="campo"><span>Destinatarios</span><input name="correo_destinos" value="<?= e($s['correo_destinos']) ?>" placeholder="dueno@gmail.com, encargado@gmail.com"><small>Separa varios con coma. Máximo 10.</small></label>
      <div class="form-par">
        <label class="campo"><span>Correo remitente</span><input name="correo_remitente" type="email" value="<?= e($s['correo_remitente']) ?>" placeholder="tucuenta@gmail.com"><small>Con Gmail debe ser la misma cuenta del usuario.</small></label>
        <label class="campo"><span>Nombre del remitente</span><input name="correo_nombre" maxlength="80" value="<?= e($s['correo_nombre']) ?>" placeholder="<?= e(Settings::get('nombre_establecimiento')) ?>"></label>
      </div>
    </section>

    <section class="bloque">
      <h2>Servidor</h2>
      <fieldset class="campo">
        <legend>Método</legend>
        <label class="interruptor"><input type="radio" name="correo_metodo" value="smtp" <?= $s['correo_metodo'] !== 'brevo' ? 'checked' : '' ?>><span>SMTP (Gmail, Outlook u otro)</span></label>
        <label class="interruptor"><input type="radio" name="correo_metodo" value="brevo" <?= $s['correo_metodo'] === 'brevo' ? 'checked' : '' ?>><span>Brevo (API gratuita, sirve si el hosting bloquea SMTP)</span></label>
      </fieldset>
      <details <?= $s['correo_metodo'] !== 'brevo' ? 'open' : '' ?>>
        <summary>Datos SMTP</summary>
        <div class="form">
          <div class="form-par">
            <label class="campo"><span>Servidor</span><input name="smtp_host" value="<?= e($s['smtp_host']) ?>" placeholder="smtp.gmail.com"></label>
            <label class="campo"><span>Puerto</span><input name="smtp_puerto" type="number" min="1" max="65535" value="<?= e($s['smtp_puerto']) ?>"></label>
          </div>
          <label class="campo"><span>Seguridad</span>
            <select name="smtp_seguridad">
              <?php foreach (['tls' => 'STARTTLS (puerto 587)', 'ssl' => 'SSL (puerto 465)', 'ninguna' => 'Ninguna (no recomendado)'] as $k => $v): ?>
                <option value="<?= $k ?>" <?= $s['smtp_seguridad'] === $k ? 'selected' : '' ?>><?= $v ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="campo"><span>Usuario</span><input name="smtp_usuario" value="<?= e($s['smtp_usuario']) ?>" autocomplete="off" placeholder="tucuenta@gmail.com"></label>
          <label class="campo"><span>Contraseña de aplicación</span><input name="smtp_clave" type="password" autocomplete="new-password" placeholder="<?= $tieneClave ? 'Guardada · escribe para cambiarla' : 'abcd efgh ijkl mnop' ?>"><small>En Gmail: Cuenta de Google › Seguridad › Verificación en dos pasos › Contraseñas de aplicaciones. No uses tu contraseña normal.</small></label>
        </div>
      </details>
      <details <?= $s['correo_metodo'] === 'brevo' ? 'open' : '' ?>>
        <summary>Datos de Brevo</summary>
        <div class="form">
          <label class="campo"><span>Clave API</span><input name="brevo_api_key" type="password" autocomplete="off" placeholder="<?= $tieneApi ? 'Guardada · escribe para cambiarla' : 'xkeysib-...' ?>"><small>En brevo.com: crea una cuenta gratis, verifica el correo remitente y genera la clave en SMTP y API › Claves API.</small></label>
        </div>
      </details>
      <p class="sub">Las contraseñas se guardan cifradas.</p>
    </section>
  </div>
  <section class="bloque"><button class="btn btn-primario" type="submit">Guardar configuración de correo</button></section>
</form>

<section class="bloque bloque-estrecho">
  <h2>Probar</h2>
  <form method="post" action="<?= e(url('admin/correo/probar')) ?>" class="form form-linea">
    <?= Csrf::field() ?>
    <label class="campo"><span>Enviar prueba a</span><input name="destino" type="email" required value="<?= e(CorreoService::destinos()[0] ?? '') ?>"></label>
    <button class="btn btn-secundario" type="submit" <?= $listo ? '' : 'disabled' ?>>Enviar correo de prueba</button>
  </form>
</section>

<section class="bloque">
  <h2>Registro de envíos</h2>
  <p class="sub">"Enviado" significa que el servidor de correo aceptó el mensaje. Los pendientes se reintentan solos hasta 5 veces cuando se usa el panel.</p>
  <?php if (!$envios): ?>
    <p class="vacio">Todavía no hay envíos.</p>
  <?php else: ?>
  <div class="tabla-envoltura"><table class="tabla">
    <thead><tr><th>Generado</th><th>Tipo</th><th>Asunto</th><th>Destino</th><th>Estado</th><th>Intentos</th><th>Detalle</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($envios as $v): [$et, $cl] = $estados[$v['estado']]; ?>
      <tr>
        <td class="num"><?= e(fmt_fecha_hora($v['creado'])) ?></td>
        <td><?= e($tipos[$v['tipo']] ?? $v['tipo']) ?><?php if ($v['tipo'] === 'inventario' && $v['referencia_id']): ?> · <a href="<?= e(url('admin/inventarios/ver', ['id' => $v['referencia_id']])) ?>">ver</a><?php endif; ?></td>
        <td><?= e($v['asunto']) ?></td>
        <td class="meta"><?= e($v['destinos']) ?></td>
        <td><span class="estado <?= $cl ?>"><?= $et ?></span><?= $v['enviado'] ? '<br><span class="meta">' . e(fmt_fecha_hora($v['enviado'])) . '</span>' : '' ?></td>
        <td><?= (int)$v['intentos'] ?></td>
        <td class="meta"><?= $v['estado'] === 'enviado' ? 'Id: ' . e($v['mensaje_id'] ?? '—') . ' · ' . e($v['metodo']) : e($v['ultimo_error'] ?? '') ?><?= $v['estado'] === 'pendiente' && $v['proximo_intento'] ? '<br>Próximo intento: ' . e(fmt_hora($v['proximo_intento'])) : '' ?></td>
        <td><?php if ($v['estado'] !== 'enviado'): ?>
          <form method="post" action="<?= e(url('admin/correo/reintentar')) ?>"><?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int)$v['id'] ?>"><button class="btn btn-quieto btn-sm" type="submit">Reintentar</button></form>
        <?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>
