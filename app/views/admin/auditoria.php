<div class="encabezado">
  <h1>Auditoría</h1>
  <p class="sub">Toda acción administrativa queda registrada con fecha, hora, responsable y motivo.</p>
</div>
<section class="bloque">
  <?php if (!$rows): ?>
    <p class="vacio">Sin registros.</p>
  <?php else: ?>
  <div class="tabla-envoltura"><table class="tabla">
    <thead><tr><th>Fecha</th><th>Responsable</th><th>Acción</th><th>Elemento</th><th>Motivo y detalle</th><th>IP</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $h): ?>
      <tr>
        <td><?= e(fmt_fecha_hora($h['creado'])) ?></td>
        <td><?= e($h['actor_nombre'] ?? $h['actor_tipo']) ?></td>
        <td><?= e(str_replace('_', ' ', $h['accion'])) ?></td>
        <td><?php if ($h['entidad'] === 'jornada' && $h['entidad_id']): ?><a href="<?= e(url('admin/asistencia/jornada', ['id' => $h['entidad_id']])) ?>">Jornada #<?= (int)$h['entidad_id'] ?></a><?php else: ?><?= e($h['entidad'] ? $h['entidad'] . ($h['entidad_id'] ? ' #' . $h['entidad_id'] : '') : '—') ?><?php endif; ?></td>
        <td><?= e($h['motivo'] ?? '') ?><?php require APP_PATH . '/views/partials/diff.php'; ?></td>
        <td class="meta"><?= e($h['ip']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php if ($paginas > 1): ?>
    <nav class="paginacion" aria-label="Páginas">
      <?php if ($pagina > 1): ?><a class="btn btn-quieto btn-sm" href="<?= e(url('admin/auditoria', ['p' => $pagina - 1])) ?>">Anterior</a><?php endif; ?>
      <span>Página <?= $pagina ?> de <?= $paginas ?></span>
      <?php if ($pagina < $paginas): ?><a class="btn btn-quieto btn-sm" href="<?= e(url('admin/auditoria', ['p' => $pagina + 1])) ?>">Siguiente</a><?php endif; ?>
    </nav>
  <?php endif; ?>
  <?php endif; ?>
</section>
