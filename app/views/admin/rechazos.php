<?php
$acciones = ['login' => 'PIN', 'entrada' => 'Entrada', 'salida' => 'Salida', 'admin_login' => 'Acceso admin', 'activar_kiosco' => 'Activar kiosco'];
?>
<div class="encabezado">
  <h1>Intentos rechazados</h1>
  <p class="sub">PIN incorrectos, marcaciones desde dispositivos no autorizados o fuera de la red del local, y secuencias no válidas. Últimos 300.</p>
</div>
<section class="bloque">
  <?php if (!$rechazos): ?>
    <p class="vacio">No hay intentos rechazados.</p>
  <?php else: ?>
  <div class="tabla-envoltura"><table class="tabla">
    <thead><tr><th>Fecha</th><th>Trabajador</th><th>Acción</th><th>Motivo</th><th>Dispositivo</th><th>IP</th></tr></thead>
    <tbody>
    <?php foreach ($rechazos as $r): ?>
      <tr>
        <td><?= e(fmt_fecha_hora($r['creado'])) ?></td>
        <td><?= e($r['nombre'] ?? '—') ?></td>
        <td><?= e($acciones[$r['accion']] ?? $r['accion']) ?></td>
        <td><?= e($r['motivo']) ?></td>
        <td><?= e($r['dispositivo'] ?? 'No autorizado') ?></td>
        <td class="meta"><?= e($r['ip']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>
