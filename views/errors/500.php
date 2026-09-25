<?php $dev = \App\Core\Config::get('app.env') === 'development'; ?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Error · PayRoll</title>
    <link rel="stylesheet" href="<?= asset('vendor/bootstrap/bootstrap.min.css') ?>">
</head>
<body class="bg-body-tertiary">
<div class="container py-5" style="max-width: 720px">
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <h1 class="h4 text-danger">Algo salió mal</h1>
            <p><?= e($message ?? 'Ocurrió un error inesperado.') ?></p>
            <?php if ($dev && isset($exception)): ?>
                <pre class="small bg-body-secondary p-3 rounded" style="white-space: pre-wrap"><?= e($exception->getMessage() . "\n\n" . $exception->getTraceAsString()) ?></pre>
            <?php endif; ?>
            <a class="btn btn-outline-primary" href="<?= url('') ?>">Ir al inicio</a>
        </div>
    </div>
</div>
</body>
</html>
