<?php
use App\Core\Session;
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ingresar · PayRoll</title>
    <link rel="stylesheet" href="<?= asset('vendor/bootstrap/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('app.css') ?>">
</head>
<body>
<div class="login-page">
    <div class="card login-card shadow-lg">
        <div class="card-body p-4 p-sm-5">
            <div class="text-center mb-4">
                <i class="bi bi-cash-coin login-logo"></i>
                <h1 class="h4 fw-bold mt-2 mb-1">PayRoll</h1>
                <p class="text-body-secondary small mb-0">Sistema de liquidación de sueldos</p>
            </div>
            <?php foreach (Session::pullFlashes() as $flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?> py-2 small"><?= e($flash['message']) ?></div>
            <?php endforeach; ?>
            <form method="post" action="<?= url('login/submit') ?>" novalidate>
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label" for="email">Email</label>
                    <input class="form-control" type="email" id="email" name="email" value="<?= e($_GET['email'] ?? '') ?>" required autofocus autocomplete="username">
                </div>
                <div class="mb-4">
                    <label class="form-label" for="password">Contraseña</label>
                    <input class="form-control" type="password" id="password" name="password" required autocomplete="current-password">
                </div>
                <button class="btn btn-primary w-100 py-2">Ingresar</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
