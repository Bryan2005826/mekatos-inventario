<!doctype html>
<html lang="es">
<head>
<?php require APP_PATH . '/views/partials/head.php'; ?>
<title><?= e($title ?? 'Asistencia') ?></title>
</head>
<body class="plano">
<main class="plano-main">
<?php require APP_PATH . '/views/partials/flashes.php'; ?>
<?= $content ?>
</main>
<script src="<?= e(asset('assets/js/admin.js')) ?>"></script>
</body>
</html>
