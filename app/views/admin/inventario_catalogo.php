<div class="encabezado">
  <h1>Inventario por áreas</h1>
  <p class="sub">Lo que aparece en el formulario de cada QR. Las fotos ayudan a que nadie confunda los utensilios.</p>
</div>

<nav class="hermanas" aria-label="Áreas">
  <?php foreach ($areas as $a): ?>
    <a href="<?= e(url('admin/inventario', ['area' => $a['id']])) ?>" <?= $area && (int)$a['id'] === (int)$area['id'] ? 'aria-current="page"' : '' ?>><?= e($a['nombre']) ?></a>
  <?php endforeach; ?>
</nav>

<?php if ($area): ?>
<div class="columnas">
  <section class="bloque">
    <h2>Agregar elemento a <?= e($area['nombre']) ?></h2>
    <form method="post" action="<?= e(url('admin/inventario/item/crear')) ?>" enctype="multipart/form-data" class="form">
      <?= Csrf::field() ?>
      <input type="hidden" name="area_id" value="<?= (int)$area['id'] ?>">
      <label class="campo"><span>Nombre</span><input name="nombre" required maxlength="80" placeholder="Plato hondo"></label>
      <div class="form-par">
        <label class="campo"><span>Cantidad esperada</span><input name="cantidad_esperada" type="number" min="0" inputmode="numeric" placeholder="Opcional"><small>Déjala vacía y el primer inventario la fijará.</small></label>
        <label class="campo"><span>Se registra por</span>
          <select name="tipo"><option value="cantidad">Cantidad</option><option value="estado">Solo estado (bien / novedad / dañado)</option></select>
        </label>
      </div>
      <label class="campo"><span>Foto de referencia</span><input name="foto" type="file" accept="image/*" capture="environment"></label>
      <button class="btn btn-primario" type="submit">Agregar elemento</button>
    </form>
  </section>
  <section class="bloque">
    <h2>Área</h2>
    <form method="post" action="<?= e(url('admin/inventario/area/guardar')) ?>" class="form form-linea">
      <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int)$area['id'] ?>">
      <label class="campo"><span>Nombre del área</span><input name="nombre" required maxlength="60" value="<?= e($area['nombre']) ?>"></label>
      <button class="btn btn-secundario" type="submit">Renombrar</button>
    </form>
    <p><a href="<?= e(url('admin/qr')) ?>">Ver y descargar el código QR de esta área</a></p>
    <details>
      <summary>Crear un área nueva</summary>
      <form method="post" action="<?= e(url('admin/inventario/area/crear')) ?>" class="form form-linea">
        <?= Csrf::field() ?>
        <label class="campo"><span>Nombre</span><input name="nombre" required maxlength="60" placeholder="Bar"></label>
        <button class="btn btn-quieto" type="submit">Crear área</button>
      </form>
    </details>
  </section>
</div>

<section class="bloque">
  <h2>Elementos de <?= e($area['nombre']) ?></h2>
  <?php if (!$items): ?>
    <p class="vacio">Esta área no tiene elementos. Agrega el primero.</p>
  <?php endif; ?>
  <ul class="catalogo" role="list">
    <?php foreach ($items as $it): ?>
      <li class="catalogo-item <?= $it['activo'] ? '' : 'fila-inactiva' ?>">
        <div class="catalogo-foto">
          <?php if ($it['foto']): ?>
            <img src="<?= e(url('inventario/foto', ['i' => $it['id'], 'v' => substr(md5($it['foto']), 0, 6)])) ?>" alt="Foto de <?= e($it['nombre']) ?>" width="96" height="96" loading="lazy">
          <?php else: ?><span>Sin foto</span><?php endif; ?>
        </div>
        <form method="post" action="<?= e(url('admin/inventario/item/guardar')) ?>" enctype="multipart/form-data" class="form catalogo-form">
          <?= Csrf::field() ?>
          <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
          <label class="campo"><span>Nombre</span><input name="nombre" required maxlength="80" value="<?= e($it['nombre']) ?>"></label>
          <label class="campo"><span>Esperado</span><input name="cantidad_esperada" type="number" min="0" inputmode="numeric" value="<?= e($it['cantidad_esperada']) ?>" <?= $it['tipo'] === 'estado' ? 'disabled' : '' ?>></label>
          <label class="campo"><span>Tipo</span>
            <select name="tipo"><option value="cantidad" <?= $it['tipo'] === 'cantidad' ? 'selected' : '' ?>>Cantidad</option><option value="estado" <?= $it['tipo'] === 'estado' ? 'selected' : '' ?>>Solo estado</option></select>
          </label>
          <label class="campo"><span>Área</span>
            <select name="area_id">
              <?php foreach ($areas as $a): ?><option value="<?= (int)$a['id'] ?>" <?= (int)$a['id'] === (int)$it['area_id'] ? 'selected' : '' ?>><?= e($a['nombre']) ?></option><?php endforeach; ?>
            </select>
          </label>
          <label class="campo"><span>Orden</span><input name="orden" type="number" min="0" max="999" value="<?= (int)$it['orden'] ?>"></label>
          <label class="campo"><span><?= $it['foto'] ? 'Cambiar foto' : 'Foto' ?></span><input name="foto" type="file" accept="image/*"></label>
          <div class="catalogo-acciones">
            <button class="btn btn-secundario btn-sm" type="submit">Guardar</button>
          </div>
        </form>
        <form method="post" action="<?= e(url('admin/inventario/item/estado')) ?>" class="catalogo-estado">
          <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int)$it['id'] ?>"><input type="hidden" name="activo" value="<?= $it['activo'] ? '0' : '1' ?>">
          <button class="btn btn-quieto btn-sm" type="submit"><?= $it['activo'] ? 'Desactivar' : 'Activar' ?></button>
        </form>
      </li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>
