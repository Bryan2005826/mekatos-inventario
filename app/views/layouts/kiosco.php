<!doctype html>
<html lang="es">
<head>
<?php require APP_PATH . '/views/partials/head.php'; ?>
<title><?= e(Settings::get('nombre_establecimiento')) ?> · Asistencia</title>
</head>
<body class="kiosco">
<?= $content ?>
<script src="<?= e(asset('assets/js/kiosco.js')) ?>"></script>
</body>
</html>
