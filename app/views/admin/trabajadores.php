<div class="encabezado">
  <h1>Trabajadores · <?= e($local['nombre']) ?></h1>
  <p class="sub">Agrega a cada persona nueva aquí: se le genera su PIN. Para retirar a alguien, entra a Editar y desactívalo (o elimínalo si no tiene registros).</p>
</div>

<?php if ($pin): ?>
  <section class="bloque bloque-pin" role="status">
    <h2><?= $pin['nuevo'] ? 'PIN inicial de ' : 'Nuevo PIN de ' ?><?= e($pin['nombre']) ?></h2>
    <p class="pin-grande"><?= e(chunk_split($pin['pin'], 3, ' ')) ?></p>
    <p>Entrégalo en persona. Este PIN <strong>no se vuelve a mostrar</strong>: en la base de datos solo queda cifrado. Si se pierde, genera uno nuevo.</p>
    <button type="button" class="btn btn-quieto btn-sm" data-imprimir>Imprimir</button>
  </section>
<?php endif; ?>

<section class="bloque">
  <h2>Agregar trabajador</h2>
  <form method="post" action="<?= e(url('admin/trabajadores/crear')) ?>" class="form form-linea">
    <?= Csrf::field() ?>
    <label class="campo"><span>Nombre</span><input name="nombre" required maxlength="60"></label>
    <label class="campo"><span>Documento (opcional)</span><input name="documento" maxlength="30" inputmode="numeric"></label>
    <button class="btn btn-primario" type="submit">Agregar a <?= e($local['nombre']) ?> y generar PIN</button>
  </form>
</section>

<section class="bloque">
  <div class="tabla-envoltura"><table class="tabla">
    <thead><tr><th>Nombre</th><th>Estado</th><th>Última marcación</th><th>PIN generado</th><th>Datos</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($trabajadores as $t): ?>
      <tr class="<?= $t['activo'] ? '' : 'fila-inactiva' ?>">
        <td><span class="punto" style="--c: <?= e($t['color']) ?>"></span><?= e($t['nombre']) ?><?= (int)$t['abiertas'] ? ' <span class="estado estado-abierta">Trabajando</span>' : '' ?></td>
        <td><?= $t['activo'] ? 'Activo' : 'Inactivo' ?></td>
        <td><?= e(fmt_fecha_hora($t['ultima_marca'])) ?></td>
        <td><?= e(fmt_fecha_hora($t['pin_generado'])) ?></td>
        <td><?= $t['consentimiento_datos'] ? 'Autorizó el ' . e(fmt_fecha(utc_to_local($t['consentimiento_datos'])->format('Y-m-d'))) : '<span class="meta">Pendiente</span>' ?></td>
        <td class="acciones-fila">
          <a class="btn btn-quieto btn-sm" href="<?= e(url('admin/trabajadores/editar', ['id' => $t['id']])) ?>">Editar</a>
          <a class="btn btn-quieto btn-sm" href="<?= e(url('admin/asistencia', ['trabajador' => $t['id']])) ?>">Asistencia</a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
