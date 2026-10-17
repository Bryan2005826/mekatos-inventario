<?php
$secciones = [
    ['resumen', 'admin', 'Resumen del día'],
    ['trabajadores', 'admin/trabajadores', 'Trabajadores'],
    ['asistencia', 'admin/asistencia', 'Asistencia'],
    ['planillas', 'admin/planillas', 'Planillas'],
    ['stock', 'admin/stock', 'Control de loza'],
    ['inventarios', 'admin/inventarios', 'Historial de inventarios'],
    ['inventario', 'admin/inventario', 'Inventario por áreas'],
    ['qr', 'admin/qr', 'Códigos QR'],
    ['rechazos', 'admin/rechazos', 'Intentos rechazados'],
    ['dispositivos', 'admin/dispositivos', 'Kiosco'],
    ['correo', 'admin/correo', 'Correo y envíos'],
    ['configuracion', 'admin/configuracion', 'Configuración'],
    ['auditoria', 'admin/auditoria', 'Auditoría'],
    ['ayuda', 'admin/ayuda', 'Guía de uso'],
];
$proximas = [];
$adm = Auth::admin();
$seccion = $seccion ?? '';
?>
<!doctype html>
<html lang="es">
<head>
<?php require APP_PATH . '/views/partials/head.php'; ?>
<title>Panel · <?= e(Settings::get('nombre_establecimiento')) ?></title>
</head>
<body class="admin">
<header class="admin-top">
  <a class="marca" href="<?= e(url('admin')) ?>">
    <span class="marca-sello" aria-hidden="true"><?= e(initials(Settings::get('nombre_establecimiento'))) ?></span>
    <span><?= e(Settings::get('nombre_establecimiento')) ?></span>
  </a>
  <div class="admin-top-acciones">
    <a class="enlace-quieto" href="<?= e(url('admin/cuenta')) ?>"><?= e($adm['nombre'] ?? '') ?></a>
    <form method="post" action="<?= e(url('admin/salir')) ?>">
      <?= Csrf::field() ?>
      <button class="btn btn-quieto btn-sm" type="submit">Cerrar sesión</button>
    </form>
  </div>
</header>
<div class="admin-cuerpo">
  <nav class="admin-nav" aria-label="Secciones del panel">
    <?php foreach ($secciones as [$key, $ruta, $label]): ?>
      <a href="<?= e(url($ruta)) ?>" <?= $seccion === $key ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
    <?php if ($proximas): ?>
    <div class="nav-proximas">
      <span class="nav-proximas-titulo">Próximas etapas</span>
      <?php foreach ($proximas as $p): ?><span class="nav-deshabilitada"><?= e($p) ?></span><?php endforeach; ?>
    </div>
    <?php endif; ?>
    <a class="nav-kiosco" href="<?= e(url('')) ?>">Ir al kiosco</a>
  </nav>
  <main class="admin-main" id="contenido">
    <?php require APP_PATH . '/views/partials/flashes.php'; ?>
    <?= $content ?>
  </main>
</div>
<script src="<?= e(asset('assets/js/admin.js')) ?>"></script>
</body>
</html>
