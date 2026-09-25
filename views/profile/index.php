<?php $title = 'Mi perfil'; ?>
<div class="page-header">
    <h1>Mi perfil</h1>
</div>
<div class="row g-3">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-body">
                <dl class="dl-grid">
                    <dt>Nombre</dt><dd><?= e($user['name']) ?></dd>
                    <dt>Email</dt><dd><?= e($user['email']) ?></dd>
                    <dt>Rol</dt><dd><?= e(ROLES[$user['role']] ?? $user['role']) ?></dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">Cambiar contraseña</div>
            <div class="card-body">
                <form method="post" action="<?= url('profile/password') ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3"><label class="form-label" for="cp">Contraseña actual</label><input class="form-control" type="password" id="cp" name="current_password" required autocomplete="current-password"></div>
                    <div class="mb-3"><label class="form-label" for="np">Nueva contraseña</label><input class="form-control" type="password" id="np" name="new_password" minlength="8" required autocomplete="new-password"></div>
                    <div class="mb-3"><label class="form-label" for="cf">Repetir nueva contraseña</label><input class="form-control" type="password" id="cf" name="confirm_password" minlength="8" required autocomplete="new-password"></div>
                    <button class="btn btn-primary">Actualizar contraseña</button>
                </form>
            </div>
        </div>
    </div>
</div>
