<?php
$title = 'Organización';
$tab = ($_GET['tab'] ?? '') === 'positions' ? 'positions' : 'departments';
?>
<div class="page-header">
    <div>
        <h1>Organización</h1>
        <div class="subtitle">Departamentos, puestos y sueldos básicos</div>
    </div>
</div>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link<?= $tab === 'departments' ? ' active' : '' ?>" href="<?= url('organization') ?>">Departamentos</a></li>
    <li class="nav-item"><a class="nav-link<?= $tab === 'positions' ? ' active' : '' ?>" href="<?= url('organization', ['tab' => 'positions']) ?>">Puestos y básicos</a></li>
</ul>

<?php if ($tab === 'departments'): ?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><?= count($departments) ?> departamentos</span>
        <?php if (can('rrhh')): ?>
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#depModal" data-fill='{"active":1}' data-title="Nuevo departamento"><i class="bi bi-plus-lg"></i> Nuevo</button>
        <?php endif; ?>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Nombre</th><th>Descripción</th><th class="num">Empleados</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($departments as $d): ?>
                <tr>
                    <td class="fw-medium"><a class="text-reset" href="<?= url('employees', ['department' => $d['id']]) ?>"><?= e($d['name']) ?></a></td>
                    <td class="text-body-secondary"><?= e($d['description']) ?></td>
                    <td class="num"><?= (int) $d['employees'] ?></td>
                    <td><?= $d['active'] ? '<span class="badge text-bg-success">Activo</span>' : '<span class="badge text-bg-secondary">Inactivo</span>' ?></td>
                    <td class="text-end text-nowrap">
                        <?php if (can('rrhh')): ?>
                            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#depModal" data-title="Editar departamento"
                                    data-fill='<?= e(json_encode(['id' => $d['id'], 'name' => $d['name'], 'description' => $d['description'], 'active' => $d['active']])) ?>'><i class="bi bi-pencil"></i></button>
                            <form class="d-inline" method="post" action="<?= url('departments/delete') ?>" data-confirm="¿Eliminar el departamento <?= e($d['name']) ?>?">
                                <?= csrf_field() ?><input type="hidden" name="id" value="<?= $d['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="depModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="post" action="<?= url('departments/save') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="id">
            <div class="modal-header"><h5 class="modal-title">Departamento</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Nombre *</label><input class="form-control" name="name" required maxlength="120"></div>
                <div class="mb-3"><label class="form-label">Descripción</label><input class="form-control" name="description" maxlength="255"></div>
                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="active" value="1" id="depActive" checked><label class="form-check-label" for="depActive">Activo</label></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">Guardar</button></div>
        </form>
    </div>
</div>

<?php else: ?>
<div class="card">
    <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
        <span><?= count($positions) ?> puestos</span>
        <?php if (can('rrhh')): ?>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#raiseModal"><i class="bi bi-graph-up-arrow"></i> Aumento general</button>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#posModal" data-fill='{"active":1}' data-title="Nuevo puesto"><i class="bi bi-plus-lg"></i> Nuevo</button>
            </div>
        <?php endif; ?>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Puesto</th><th>Departamento</th><th class="num">Sueldo básico</th><th class="num">Empleados</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($positions as $p): ?>
                <tr>
                    <td class="fw-medium"><?= e($p['name']) ?></td>
                    <td><?= e($p['department_name'] ?? '—') ?></td>
                    <td class="num"><?= money($p['base_salary']) ?></td>
                    <td class="num"><?= (int) $p['employees'] ?></td>
                    <td><?= $p['active'] ? '<span class="badge text-bg-success">Activo</span>' : '<span class="badge text-bg-secondary">Inactivo</span>' ?></td>
                    <td class="text-end text-nowrap">
                        <?php if (can('rrhh')): ?>
                            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#posModal" data-title="Editar puesto"
                                    data-fill='<?= e(json_encode(['id' => $p['id'], 'name' => $p['name'], 'department_id' => $p['department_id'], 'base_salary' => number_format((float) $p['base_salary'], 2, ',', '.'), 'active' => $p['active']])) ?>'><i class="bi bi-pencil"></i></button>
                            <form class="d-inline" method="post" action="<?= url('positions/delete') ?>" data-confirm="¿Eliminar el puesto <?= e($p['name']) ?>?">
                                <?= csrf_field() ?><input type="hidden" name="id" value="<?= $p['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="posModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="post" action="<?= url('positions/save') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="id">
            <div class="modal-header"><h5 class="modal-title">Puesto</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Nombre *</label><input class="form-control" name="name" required maxlength="120"></div>
                <div class="mb-3"><label class="form-label">Departamento</label>
                    <select class="form-select" name="department_id"><option value="">—</option>
                        <?php foreach ($departments as $d): ?><option value="<?= $d['id'] ?>"><?= e($d['name']) ?></option><?php endforeach; ?>
                    </select></div>
                <div class="mb-3"><label class="form-label">Sueldo básico *</label>
                    <div class="input-group"><span class="input-group-text">$</span><input class="form-control" name="base_salary" required inputmode="decimal"></div></div>
                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="active" value="1" id="posActive" checked><label class="form-check-label" for="posActive">Activo</label></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">Guardar</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="raiseModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="post" action="<?= url('positions/raise') ?>" data-confirm="Se van a modificar los sueldos básicos. ¿Continuar?">
            <?= csrf_field() ?>
            <div class="modal-header"><h5 class="modal-title">Aumento general de básicos</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <p class="text-body-secondary small">Aplica un porcentaje a los básicos de todos los puestos activos (por ejemplo, un acuerdo paritario).</p>
                <div class="mb-3"><label class="form-label">Porcentaje *</label>
                    <div class="input-group"><input class="form-control" name="percent" required inputmode="decimal" placeholder="Ej: 5,5"><span class="input-group-text">%</span></div></div>
                <div class="form-check"><input class="form-check-input" type="checkbox" name="include_employees" value="1" id="incEmp" checked>
                    <label class="form-check-label" for="incEmp">Aplicar también a los empleados con básico propio</label></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">Aplicar</button></div>
        </form>
    </div>
</div>
<?php endif; ?>
