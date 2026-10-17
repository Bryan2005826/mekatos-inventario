<!doctype html>
<html lang="es">
<head>
<?php require APP_PATH . '/views/partials/head.php'; ?>
<title><?= e($area['nombre']) ?> · Inventario</title>
</head>
<body class="kiosco inv">
<?= $content ?>
<script src="<?= e(asset('assets/js/inventario.js')) ?>"></script>
</body>
</html>
