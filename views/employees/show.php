<?php
use App\Services\PayrollCalculator;

$title = $employee['last_name'] . ', ' . $employee['first_name'];
$seniority = PayrollCalculator::seniorityYears(new DateTimeImmutable($employee['hire_date']), new DateTimeImmutable($employee['termination_date'] ?? 'today'));
$age = $employee['birth_date'] ? (new DateTimeImmutable($employee['birth_date']))->diff(new DateTimeImmutable('today'))->y : null;
?>
<div class="page-header">
    <div class="d-flex align-items-center gap-3">
        <span class="avatar" style="width:56px;height:56px;font-size:1.4rem"><?= e(mb_strtoupper(mb_substr($employee['first_name'], 0, 1) . mb_substr($employee['last_name'], 0, 1))) ?></span>
        <div>
            <h1><?= e($title) ?></h1>
            <div class="subtitle">
                Legajo <?= e($employee['file_number']) ?> · <?= e($employee['position_name'] ?? 'Sin puesto') ?>
                · <?php $map = EMPLOYEE_STATUS; $value = $employee['status']; require BASE_PATH . '/views/partials/status.php'; ?>
            </div>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="<?= url('employees') ?>"><i class="bi bi-arrow-left me-1"></i>Volver</a>
        <?php if (can('rrhh')): ?>
            <a class="btn btn-primary" href="<?= url('employees/edit', ['id' => $employee['id']]) ?>"><i class="bi bi-pencil me-1"></i>Editar</a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header">Datos personales</div>
            <div class="card-body">
                <dl class="dl-grid">
                    <dt>CUIL</dt><dd><?= e(fmt_cuil($employee['cuil'])) ?></dd>
                    <dt>Nacimiento</dt><dd><?= e(fmt_date($employee['birth_date'])) ?: '—' ?><?= $age !== null ? ' (' . $age . ' años)' : '' ?></dd>
                    <dt>Nacionalidad</dt><dd><?= e($employee['nationality'] ?: '—') ?></dd>
                    <dt>Email</dt><dd><?= $employee['email'] ? '<a href="mailto:' . e($employee['email']) . '">' . e($employee['email']) . '</a>' : '—' ?></dd>
                    <dt>Teléfono</dt><dd><?= e($employee['phone'] ?: '—') ?></dd>
                    <dt>Domicilio</dt><dd><?= e($employee['address'] ?: '—') ?></dd>
                </dl>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-header">Datos laborales</div>
            <div class="card-body">
                <dl class="dl-grid">
                    <dt>Departamento</dt><dd><?= e($employee['department_name'] ?? '—') ?></dd>
                    <dt>Puesto</dt><dd><?= e($employee['position_name'] ?? '—') ?></dd>
                    <dt>Categoría</dt><dd>
                        <?php if ($employee['category_id']): ?>
                            <a href="<?= url('categories', ['agreement' => $employee['agreement_id'] ?? '']) ?>"><?= e($employee['category_name']) ?></a>
                            <div class="small text-body-secondary"><?= e($employee['agreement_code'] . ' · ' . $employee['agreement_name']) ?></div>
                        <?php else: ?>Fuera de convenio<?php endif; ?>
                    </dd>
                    <dt>Contratación</dt><dd><?= e(CONTRACT_TYPES[$employee['contract_type']] ?? '') ?></dd>
                    <dt>Ingreso</dt><dd><?= e(fmt_date($employee['hire_date'])) ?> (<?= $seniority ?> <?= $seniority === 1 ? 'año' : 'años' ?>)</dd>
                    <?php if ($employee['termination_date']): ?><dt>Egreso</dt><dd><?= e(fmt_date($employee['termination_date'])) ?></dd><?php endif; ?>
                    <dt>Básico</dt><dd class="fw-semibold"><?= money($employee['effective_salary']) ?>
                        <div class="small text-body-secondary"><?= $employee['base_salary'] === null ? 'Según escala de la categoría' : 'Básico propio' . ($employee['category_salary'] !== null ? ' (escala: ' . money($employee['category_salary']) . ')' : '') ?></div></dd>
                    <dt>Banco</dt><dd><?= e($employee['bank_name'] ?: '—') ?></dd>
                    <dt>CBU</dt><dd class="text-break"><?= e($employee['cbu'] ?: '—') ?></dd>
                </dl>
                <?php if ($employee['notes']): ?>
                    <hr><div class="small" style="white-space: pre-line"><?= e($employee['notes']) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Conceptos fijos asignados</span>
                <span class="small text-body-secondary fw-normal">Se suman a los conceptos generales en cada liquidación</span>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Código</th><th>Concepto</th><th>Tipo</th><th class="num">Valor</th><th class="num">Cantidad</th><?php if (can('rrhh')): ?><th></th><?php endif; ?></tr></thead>
                    <tbody>
                    <?php foreach ($concepts as $c): ?>
                        <tr>
                            <td><?= e($c['code']) ?></td>
                            <td><?= e($c['name']) ?></td>
                            <td class="small"><?= e(CONCEPT_TYPES[$c['type']]) ?></td>
                            <td class="num"><?= $c['value_override'] !== null ? num($c['value_override']) : '<span class="text-body-secondary">' . num($c['value']) . '</span>' ?></td>
                            <td class="num"><?= e(num($c['assigned_quantity'])) ?></td>
                            <?php if (can('rrhh')): ?>
                                <td class="text-end">
                                    <form method="post" action="<?= url('employees/concept-del', ['id' => $employee['id']]) ?>" data-confirm="¿Quitar este concepto?">
                                        <?= csrf_field() ?><input type="hidden" name="concept_id" value="<?= $c['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger" title="Quitar"><i class="bi bi-x-lg"></i></button>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$concepts): ?>
                        <tr><td colspan="6" class="text-body-secondary">Sin conceptos particulares. Se liquidan solo los conceptos generales.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if (can('rrhh') && $available): ?>
                <div class="card-body border-top">
                    <form class="row g-2 align-items-end" method="post" action="<?= url('employees/concept-add', ['id' => $employee['id']]) ?>">
                        <?= csrf_field() ?>
                        <div class="col-md-5">
                            <label class="form-label small" for="concept_id">Asignar concepto</label>
                            <select class="form-select form-select-sm" id="concept_id" name="concept_id" required>
                                <?php foreach ($available as $a): ?>
                                    <option value="<?= $a['id'] ?>"><?= e($a['code'] . ' · ' . $a['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small" for="value_override">Valor propio</label>
                            <input class="form-control form-control-sm" id="value_override" name="value_override" placeholder="Del concepto" inputmode="decimal">
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label small" for="quantity">Cantidad</label>
                            <input class="form-control form-control-sm" id="quantity" name="quantity" inputmode="decimal">
                        </div>
                        <div class="col-md-2 d-grid">
                            <button class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> Asignar</button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-header">Historial de recibos</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Período</th><th class="num">Remunerativo</th><th class="num">No rem.</th><th class="num">Descuentos</th><th class="num">Neto</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($payslips as $ps): ?>
                        <tr>
                            <td class="text-nowrap"><?= e(period_label($ps)) ?> <?php $map = PERIOD_STATUS; $value = $ps['status']; if ($value !== 'cerrada') require BASE_PATH . '/views/partials/status.php'; ?></td>
                            <td class="num"><?= money($ps['gross_rem']) ?></td>
                            <td class="num"><?= money($ps['gross_no_rem']) ?></td>
                            <td class="num text-danger"><?= money($ps['deductions']) ?></td>
                            <td class="num fw-semibold"><?= money($ps['net_pay']) ?></td>
                            <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="<?= url('payslips/show', ['id' => $ps['id']]) ?>" title="Ver recibo"><i class="bi bi-receipt"></i></a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$payslips): ?>
                        <tr><td colspan="6" class="text-body-secondary">Todavía no tiene recibos liquidados.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
