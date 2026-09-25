<?php
$isNew = empty($employee['id']);
$title = $isNew ? 'Nuevo empleado' : 'Editar empleado';
$v = fn (string $k) => old($k, $employee[$k] ?? '');
$salary = $v('base_salary');
if ($salary !== '' && $salary !== null && is_numeric($salary)) {
    $salary = number_format((float) $salary, 2, ',', '.');
}
?>
<div class="page-header">
    <div>
        <h1><?= e($title) ?></h1>
        <?php if (!$isNew): ?><div class="subtitle">Legajo <?= e($employee['file_number']) ?> · <?= e($employee['last_name'] . ', ' . $employee['first_name']) ?></div><?php endif; ?>
    </div>
    <a class="btn btn-outline-secondary" href="<?= $isNew ? url('employees') : url('employees/show', ['id' => $employee['id']]) ?>"><i class="bi bi-arrow-left me-1"></i>Volver</a>
</div>

<form method="post" action="<?= $isNew ? url('employees/store') : url('employees/update', ['id' => $employee['id']]) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-xl-8">
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-person-vcard me-2"></i>Datos personales</div>
                <div class="card-body row g-3">
                    <div class="col-md-3">
                        <label class="form-label" for="file_number">Legajo *</label>
                        <input class="form-control<?= invalid('file_number') ?>" id="file_number" name="file_number" value="<?= e($v('file_number')) ?>" required>
                        <?= field_error('file_number') ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="last_name">Apellido *</label>
                        <input class="form-control<?= invalid('last_name') ?>" id="last_name" name="last_name" value="<?= e($v('last_name')) ?>" required>
                        <?= field_error('last_name') ?>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label" for="first_name">Nombres *</label>
                        <input class="form-control<?= invalid('first_name') ?>" id="first_name" name="first_name" value="<?= e($v('first_name')) ?>" required>
                        <?= field_error('first_name') ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="cuil">CUIL *</label>
                        <input class="form-control<?= invalid('cuil') ?>" id="cuil" name="cuil" value="<?= e(fmt_cuil($v('cuil'))) ?>" placeholder="20-12345678-9" required>
                        <?= field_error('cuil') ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="birth_date">Fecha de nacimiento</label>
                        <input class="form-control<?= invalid('birth_date') ?>" type="date" id="birth_date" name="birth_date" value="<?= e($v('birth_date')) ?>">
                        <?= field_error('birth_date') ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="gender">Género</label>
                        <select class="form-select" id="gender" name="gender">
                            <option value="">—</option>
                            <option value="F"<?= selected($v('gender'), 'F') ?>>Femenino</option>
                            <option value="M"<?= selected($v('gender'), 'M') ?>>Masculino</option>
                            <option value="X"<?= selected($v('gender'), 'X') ?>>No binario</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="nationality">Nacionalidad</label>
                        <input class="form-control" id="nationality" name="nationality" value="<?= e($v('nationality')) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="email">Email</label>
                        <input class="form-control<?= invalid('email') ?>" type="email" id="email" name="email" value="<?= e($v('email')) ?>">
                        <?= field_error('email') ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="phone">Teléfono</label>
                        <input class="form-control" id="phone" name="phone" value="<?= e($v('phone')) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="address">Domicilio</label>
                        <input class="form-control" id="address" name="address" value="<?= e($v('address')) ?>">
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-briefcase me-2"></i>Datos laborales</div>
                <div class="card-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="department_id">Departamento</label>
                        <select class="form-select" id="department_id" name="department_id">
                            <option value="">—</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= $d['id'] ?>"<?= selected($v('department_id'), $d['id']) ?>><?= e($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="position_id">Puesto</label>
                        <select class="form-select" id="position_id" name="position_id">
                            <option value="">—</option>
                            <?php foreach ($positions as $p): ?>
                                <option value="<?= $p['id'] ?>" data-department="<?= e($p['department_id']) ?>"<?= selected($v('position_id'), $p['id']) ?>><?= e($p['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label" for="category_id">Convenio y categoría</label>
                        <select class="form-select<?= invalid('category_id') ?>" id="category_id" name="category_id">
                            <option value="" data-salary="">Sin categoría (fuera de convenio)</option>
                            <?php foreach ($categories as $group => $cats): ?>
                                <optgroup label="<?= e($group) ?>">
                                    <?php foreach ($cats as $c): ?>
                                        <option value="<?= $c['id'] ?>" data-salary="<?= e($c['salary'] !== null ? money($c['salary']) : 'sin escala vigente') ?>"<?= selected($v('category_id'), $c['id']) ?>><?= e($c['code'] . ' · ' . $c['name']) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                        <?= field_error('category_id') ?>
                        <div class="form-text" id="categorySalaryHint">El sueldo básico sale de la escala vigente de la categoría.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="hire_date">Fecha de ingreso *</label>
                        <input class="form-control<?= invalid('hire_date') ?>" type="date" id="hire_date" name="hire_date" value="<?= e($v('hire_date')) ?>" required>
                        <?= field_error('hire_date') ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="contract_type">Contratación *</label>
                        <select class="form-select" id="contract_type" name="contract_type">
                            <?php foreach (CONTRACT_TYPES as $k => $label): ?>
                                <option value="<?= $k ?>"<?= selected($v('contract_type'), $k) ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="base_salary">Básico propio (opcional)</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input class="form-control<?= invalid('base_salary') ?>" id="base_salary" name="base_salary" value="<?= e($salary) ?>" inputmode="decimal">
                        </div>
                        <div class="form-text">Solo para quien cobra distinto a la escala o está fuera de convenio. Reemplaza al básico de la categoría.</div>
                        <?= field_error('base_salary') ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-toggles me-2"></i>Situación</div>
                <div class="card-body row g-3">
                    <div class="col-12">
                        <label class="form-label" for="status">Estado *</label>
                        <select class="form-select<?= invalid('status') ?>" id="status" name="status">
                            <?php foreach (EMPLOYEE_STATUS as $k => [$label]): ?>
                                <option value="<?= $k ?>"<?= selected($v('status'), $k) ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="termination_date">Fecha de egreso</label>
                        <input class="form-control<?= invalid('termination_date') ?>" type="date" id="termination_date" name="termination_date" value="<?= e($v('termination_date')) ?>">
                        <?= field_error('termination_date') ?>
                        <div class="form-text">Se liquida proporcional hasta esta fecha.</div>
                    </div>
                </div>
            </div>
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-bank me-2"></i>Datos bancarios</div>
                <div class="card-body row g-3">
                    <div class="col-12">
                        <label class="form-label" for="bank_name">Banco</label>
                        <input class="form-control" id="bank_name" name="bank_name" value="<?= e($v('bank_name')) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="cbu">CBU</label>
                        <input class="form-control<?= invalid('cbu') ?>" id="cbu" name="cbu" value="<?= e($v('cbu')) ?>" maxlength="26" inputmode="numeric">
                        <?= field_error('cbu') ?>
                    </div>
                </div>
            </div>
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-sticky me-2"></i>Observaciones</div>
                <div class="card-body">
                    <textarea class="form-control" name="notes" rows="4" aria-label="Observaciones"><?= e($v('notes')) ?></textarea>
                </div>
            </div>
        </div>
    </div>
    <div class="d-flex justify-content-end gap-2">
        <a class="btn btn-outline-secondary" href="<?= $isNew ? url('employees') : url('employees/show', ['id' => $employee['id']]) ?>">Cancelar</a>
        <button class="btn btn-primary"><i class="bi bi-check2 me-1"></i><?= $isNew ? 'Crear empleado' : 'Guardar cambios' ?></button>
    </div>
</form>
