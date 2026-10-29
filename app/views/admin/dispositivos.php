<div class="encabezado">
  <h1>Kiosco · <?= e($local['nombre']) ?></h1>
  <p class="sub">Solo el celular autorizado de <?= e($local['nombre']) ?> registra entradas y salidas, y solo muestra al personal de este local. Para el otro local, cámbialo arriba y autoriza su celular allá.</p>
</div>

<?php if ($codigo): ?>
  <section class="bloque bloque-pin" role="status">
    <h2>Código para "<?= e($codigo['nombre']) ?>"</h2>
    <p class="pin-grande"><?= e($codigo['codigo']) ?></p>
    <p>En el dispositivo del local abre <strong><?= e(url('activar')) ?></strong> y escribe este código. Vence en <?= (int)$codigo['minutos'] ?> minutos y solo sirve una vez.</p>
  </section>
<?php endif; ?>

<?php if ($actual): ?>
  <div class="aviso aviso-ok">El navegador que estás usando ahora está autorizado como <strong><?= e($actual['nombre']) ?></strong> (<?= e(LocalService::nombre((int)$actual['local_id'])) ?>).</div>
<?php endif; ?>

<section class="bloque bloque-estrecho">
  <h2>Autorizar el celular de <?= e($local['nombre']) ?></h2>
  <form method="post" action="<?= e(url('admin/dispositivos/codigo')) ?>" class="form form-linea">
    <?= Csrf::field() ?>
    <label class="campo"><span>Nombre</span><input name="nombre" required maxlength="60" placeholder="Celular de <?= e($local['nombre']) ?>"></label>
    <button class="btn btn-primario" type="submit">Generar código</button>
  </form>
  <ol class="pasos">
    <li>Genera el código aquí, desde tu celular o computador.</li>
    <li>En el dispositivo del local abre la página <strong>/activar</strong> e ingresa el código.</li>
    <li>Instálalo como aplicación (menú del navegador › Agregar a la pantalla de inicio) para que abra a pantalla completa.</li>
  </ol>
</section>

<section class="bloque">
  <h2>Dispositivos</h2>
  <?php if (!$dispositivos): ?>
    <p class="vacio">No hay dispositivos registrados.</p>
  <?php else: ?>
  <div class="tabla-envoltura"><table class="tabla">
    <thead><tr><th>Nombre</th><th>Estado</th><th>Activado</th><th>Último uso</th><th>Última IP</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($dispositivos as $d):
        $estado = !$d['activo'] ? 'Revocado' : ($d['token_hash'] ? 'Autorizado' : 'Esperando código'); ?>
      <tr class="<?= $d['activo'] ? '' : 'fila-inactiva' ?>">
        <td><?= e($d['nombre']) ?></td>
        <td><?= e($estado) ?></td>
        <td><?= e(fmt_fecha_hora($d['activado'])) ?></td>
        <td><?= e(fmt_fecha_hora($d['ultimo_uso'])) ?></td>
        <td class="meta"><?= e($d['ultima_ip'] ?? '—') ?></td>
        <td class="acciones-fila">
          <?php if ($d['activo']): ?>
            <?php if (!$d['token_hash']): ?>
              <form method="post" action="<?= e(url('admin/dispositivos/codigo')) ?>">
                <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                <button class="btn btn-quieto btn-sm" type="submit">Nuevo código</button>
              </form>
            <?php endif; ?>
            <form method="post" action="<?= e(url('admin/dispositivos/revocar')) ?>" data-confirmar="¿Revocar <?= e($d['nombre']) ?>? Ya no podrá registrar asistencia.">
              <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
              <button class="btn btn-peligro btn-sm" type="submit">Revocar</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>
