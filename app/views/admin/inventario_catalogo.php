<?php
$tipos = InventarioService::TIPOS_ITEM;
$esPedido = $area && $area['tipo'] === 'pedido';
?>
<div class="encabezado">
  <h1>Categorías y productos · <?= e($local['nombre']) ?></h1>
  <p class="sub">Cada categoría tiene su propio código QR. Puede ser un inventario (loza, utensilios, caja…) o una lista de pedido (gaseosa, plásticos…).</p>
</div>

<nav class="hermanas" aria-label="Categorías">
  <?php foreach ($areas as $a): if (!$a['activo']) { continue; } ?>
    <a href="<?= e(url('admin/inventario', ['area' => $a['id']])) ?>" <?= $area && (int)$a['id'] === (int)$area['id'] ? 'aria-current="page"' : '' ?>><?= e($a['nombre']) ?><?= $a['tipo'] === 'pedido' ? ' · pedido' : '' ?></a>
  <?php endforeach; ?>
</nav>

<?php if ($area): ?>
<div class="columnas">
  <section class="bloque">
    <h2>Agregar a <?= e($area['nombre']) ?></h2>
    <form method="post" action="<?= e(url('admin/inventario/item/crear')) ?>" enctype="multipart/form-data" class="form" data-form-item>
      <?= Csrf::field() ?>
      <input type="hidden" name="area_id" value="<?= (int)$area['id'] ?>">
      <label class="campo"><span><?= $esPedido ? 'Producto' : 'Nombre o pregunta' ?></span><input name="nombre" required maxlength="80" placeholder="<?= $esPedido ? 'Coca-Cola 1.5 L' : 'Plato hondo' ?>"></label>
      <?php if (!$esPedido): ?>
      <label class="campo"><span>Cómo se registra</span>
        <select name="tipo" data-tipo-item><?php foreach ($tipos as $k => $v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select>
      </label>
      <?php endif; ?>
      <div class="form-par" data-solo-cantidad>
        <?php if ($esPedido): ?>
        <label class="campo"><span>Mínimo</span><input name="minimo" inputmode="decimal" maxlength="8" placeholder="2"><small>Si hay esto o menos, se pide. Admite 0.5 o 1/2.</small></label>
        <label class="campo"><span>Debe haber (óptimo)</span><input name="optimo" inputmode="decimal" maxlength="8" required placeholder="4"><small>Se pide lo que falte para llegar aquí.</small></label>
        <?php else: ?>
        <label class="campo"><span>Cantidad que hay</span><input name="cantidad_esperada" type="number" min="0" inputmode="numeric" placeholder="Opcional"><small>Déjala vacía y el primer inventario la fijará.</small></label>
        <?php endif; ?>
        <label class="campo"><span>Unidad</span><input name="unidad" maxlength="20" placeholder="<?= $esPedido ? 'pacas' : 'unidades' ?>"></label>
        <?php if (!$esPedido): ?><label class="campo"><span>Precio de reposición</span><input name="precio" type="number" min="0" step="100" inputmode="numeric" placeholder="Opcional"><small>Se usa para cobrar lo partido.</small></label><?php endif; ?>
      </div>
      <label class="campo"><span>Foto de referencia</span><input name="foto" type="file" accept="image/*" capture="environment"></label>
      <button class="btn btn-primario" type="submit">Agregar</button>
    </form>
  </section>
  <section class="bloque">
    <h2>Categoría</h2>
    <p class="sub">Tipo: <strong><?= e(InventarioService::TIPOS_AREA[$area['tipo']]) ?></strong></p>
    <form method="post" action="<?= e(url('admin/inventario/area/guardar')) ?>" class="form">
      <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int)$area['id'] ?>">
      <label class="campo"><span>Nombre</span><input name="nombre" required maxlength="60" value="<?= e($area['nombre']) ?>"></label>
      <label class="campo"><span>Texto de la casilla de novedades</span><input name="ayuda_obs" maxlength="160" value="<?= e($area['ayuda_obs']) ?>" placeholder="Novedades: carne, pollo, empanadas…"></label>
      <button class="btn btn-secundario" type="submit">Guardar</button>
    </form>
    <p><a href="<?= e(url('admin/qr')) ?>">Ver y descargar el código QR</a></p>
    <details>
      <summary>Quitar esta categoría</summary>
      <form method="post" action="<?= e(url('admin/inventario/area/quitar')) ?>" class="form" data-confirmar="¿Quitar <?= e($area['nombre']) ?>? Su QR dejará de funcionar. El historial se conserva.">
        <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int)$area['id'] ?>">
        <p class="sub">Su QR deja de funcionar y no aparece más. Los inventarios anteriores se conservan.</p>
        <button class="btn btn-peligro btn-sm" type="submit">Quitar categoría</button>
      </form>
    </details>
  </section>
</div>

<section class="bloque">
  <h2>Crear una categoría nueva</h2>
  <form method="post" action="<?= e(url('admin/inventario/area/crear')) ?>" class="form form-linea">
    <?= Csrf::field() ?>
    <label class="campo"><span>Nombre</span><input name="nombre" required maxlength="60" placeholder="Bar"></label>
    <label class="campo"><span>Tipo</span>
      <select name="tipo"><?php foreach (InventarioService::TIPOS_AREA as $k => $v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select>
    </label>
    <label class="campo"><span>Texto de novedades (opcional)</span><input name="ayuda_obs" maxlength="160" placeholder="Novedades del bar"></label>
    <button class="btn btn-secundario" type="submit">Crear categoría</button>
  </form>
</section>

<section class="bloque">
  <h2><?= $esPedido ? 'Productos de la lista' : 'Elementos' ?> de <?= e($area['nombre']) ?></h2>
  <?php if (!$items): ?>
    <p class="vacio"><?= $esPedido ? 'Esta lista todavía no tiene productos. Agrégalos con lo que debe haber de cada uno.' : 'Esta categoría no tiene elementos. Agrega el primero.' ?></p>
  <?php endif; ?>
  <ul class="catalogo" role="list">
    <?php foreach ($items as $it): ?>
      <li class="catalogo-item <?= $it['activo'] ? '' : 'fila-inactiva' ?>">
        <div class="catalogo-foto">
          <?php if ($it['foto']): ?>
            <img src="<?= e(url('inventario/foto', ['i' => $it['id'], 'v' => substr(md5($it['foto']), 0, 6)])) ?>" alt="Foto de <?= e($it['nombre']) ?>" width="96" height="96" loading="lazy">
          <?php else: ?><span>Sin foto</span><?php endif; ?>
        </div>
        <form method="post" action="<?= e(url('admin/inventario/item/guardar')) ?>" enctype="multipart/form-data" class="form catalogo-form" data-form-item>
          <?= Csrf::field() ?>
          <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
          <label class="campo campo-ancho"><span>Nombre</span><input name="nombre" required maxlength="80" value="<?= e($it['nombre']) ?>"></label>
          <?php if (!$esPedido): ?>
          <label class="campo"><span>Tipo</span>
            <select name="tipo" data-tipo-item><?php foreach ($tipos as $k => $v): ?><option value="<?= $k ?>" <?= $it['tipo'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
          </label>
          <?php endif; ?>
          <?php if ($esPedido): ?>
          <label class="campo"><span>Mínimo</span><input name="minimo" inputmode="decimal" maxlength="8" value="<?= $it['minimo'] !== null ? e(rtrim(rtrim((string)$it['minimo'], '0'), '.')) : '' ?>"></label>
          <label class="campo"><span>Debe haber</span><input name="optimo" inputmode="decimal" maxlength="8" value="<?= $it['optimo'] !== null ? e(rtrim(rtrim((string)$it['optimo'], '0'), '.')) : '' ?>"></label>
          <?php else: ?>
          <label class="campo" data-solo-cantidad><span>Hay</span><input name="cantidad_esperada" type="number" min="0" inputmode="numeric" value="<?= e($it['cantidad_esperada']) ?>"></label>
          <?php endif; ?>
          <label class="campo" data-solo-cantidad><span>Unidad</span><input name="unidad" maxlength="20" value="<?= e($it['unidad']) ?>"></label>
          <?php if (!$esPedido): ?><label class="campo" data-solo-cantidad><span>Precio</span><input name="precio" type="number" min="0" step="100" inputmode="numeric" value="<?= $it['precio'] !== null ? (int)$it['precio'] : '' ?>"></label><?php endif; ?>
          <label class="campo"><span>Categoría</span>
            <select name="area_id">
              <?php foreach ($areas as $a): if (!$a['activo'] || $a['tipo'] !== $area['tipo']) { continue; } ?><option value="<?= (int)$a['id'] ?>" <?= (int)$a['id'] === (int)$it['area_id'] ? 'selected' : '' ?>><?= e($a['nombre']) ?></option><?php endforeach; ?>
            </select>
          </label>
          <label class="campo"><span>Orden</span><input name="orden" type="number" min="0" max="999" value="<?= (int)$it['orden'] ?>"></label>
          <label class="campo"><span><?= $it['foto'] ? 'Cambiar foto' : 'Foto' ?></span><input name="foto" type="file" accept="image/*"></label>
          <div class="catalogo-acciones">
            <button class="btn btn-secundario btn-sm" type="submit">Guardar</button>
          </div>
        </form>
        <div class="catalogo-estado">
          <form method="post" action="<?= e(url('admin/inventario/item/estado')) ?>">
            <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int)$it['id'] ?>"><input type="hidden" name="activo" value="<?= $it['activo'] ? '0' : '1' ?>">
            <button class="btn btn-quieto btn-sm" type="submit"><?= $it['activo'] ? 'Desactivar' : 'Activar' ?></button>
          </form>
          <form method="post" action="<?= e(url('admin/inventario/item/eliminar')) ?>" data-confirmar="¿Eliminar <?= e($it['nombre']) ?>? Si ya tiene inventarios solo se desactiva.">
            <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
            <button class="btn btn-peligro btn-sm" type="submit">Eliminar</button>
          </form>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>
