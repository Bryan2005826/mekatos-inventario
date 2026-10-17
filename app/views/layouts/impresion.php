<!doctype html>
<html lang="es">
<head>
<?php require APP_PATH . '/views/partials/head.php'; ?>
<title><?= e($title ?? 'Imprimir') ?></title>
</head>
<body class="impresion">
<?= $content ?>
<script src="<?= e(asset('assets/js/admin.js')) ?>"></script>
</body>
</html>
