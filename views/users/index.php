<?php $title = 'Usuarios'; ?>
<div class="page-header">
    <div>
        <h1>Usuarios</h1>
        <div class="subtitle">Quién puede acceder al sistema y con qué permisos</div>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal" data-fill='{"active":1,"role":"rrhh"}' data-title="Nuevo usuario"><i class="bi bi-person-plus me-1"></i>Nuevo usuario</button>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Nombre</th><th>Email</th><th>Rol</th><th>Último ingreso</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td class="fw-medium"><?= e($u['name']) ?></td>
                    <td><?= e($u['email']) ?></td>
                    <td><?= e(ROLES[$u['role']] ?? $u['role']) ?></td>
                    <td><?= e(fmt_datetime($u['last_login_at'])) ?: '<span class="text-body-secondary">Nunca</span>' ?></td>
                    <td>
                        <?= $u['active'] ? '<span class="badge text-bg-success">Activo</span>' : '<span class="badge text-bg-secondary">Inactivo</span>' ?>
                        <?php if ($u['locked_until'] && strtotime($u['locked_until']) > time()): ?><span class="badge text-bg-danger">Bloqueado</span><?php endif; ?>
                    </td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#userModal" data-title="Editar usuario"
                                data-fill='<?= e(json_encode(['id' => $u['id'], 'name' => $u['name'], 'email' => $u['email'], 'role' => $u['role'], 'active' => $u['active']])) ?>'><i class="bi bi-pencil"></i></button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card mt-3">
    <div class="card-body small text-body-secondary">
        <strong>Roles:</strong>
        <em>Administrador</em>: acceso total, incluidos usuarios, configuración, auditoría y reapertura de liquidaciones.
        <em>Recursos Humanos</em>: gestiona empleados, conceptos y liquidaciones.
        <em>Solo consulta</em>: puede ver todo y descargar reportes, sin modificar.
    </div>
</div>

<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="post" action="<?= url('users/save') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="id">
            <div class="modal-header"><h5 class="modal-title">Usuario</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Nombre *</label><input class="form-control" name="name" required maxlength="120"></div>
                <div class="mb-3"><label class="form-label">Email *</label><input class="form-control" type="email" name="email" required maxlength="190" autocomplete="off"></div>
                <div class="mb-3"><label class="form-label">Rol *</label>
                    <select class="form-select" name="role">
                        <?php foreach (ROLES as $k => $label): ?><option value="<?= $k ?>"><?= e($label) ?></option><?php endforeach; ?>
                    </select></div>
                <div class="mb-3"><label class="form-label">Contraseña</label><input class="form-control" type="password" name="password" minlength="8" autocomplete="new-password">
                    <div class="form-text">Mínimo 8 caracteres. Al editar, dejala vacía para no cambiarla.</div></div>
                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="active" value="1" id="userActive" checked><label class="form-check-label" for="userActive">Activo</label></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">Guardar</button></div>
        </form>
    </div>
</div>
