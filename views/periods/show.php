<?php
$title = period_label($period);
$editable = $period['status'] !== 'cerrada';
$tabs = ['payslips' => ['Recibos', count($payslips)], 'novelties' => ['Novedades', count($novelties)], 'summary' => ['Resumen por concepto', null]];
$tab = isset($tabs[$tab]) ? $tab : 'payslips';
$typeColors = ['haber_rem' => 'success', 'haber_no_rem' => 'info', 'descuento' => 'danger', 'contribucion' => 'warning'];
?>
<div class="page-header">
    <div>
        <h1><?= e($period['description']) ?> <?php $map = PERIOD_STATUS; $value = $period['status']; require BASE_PATH . '/views/partials/status.php'; ?></h1>
        <div class="subtitle">
            <?= e(period_label($period)) ?>
            <?php if ($period['payment_date']): ?> · Pago: <?= e(fmt_date($period['payment_date'])) ?><?php endif; ?>
            <?php if ($period['calculated_at']): ?> · Calculada: <?= e(fmt_datetime($period['calculated_at'])) ?><?php endif; ?>
        </div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-secondary" href="<?= url('periods') ?>"><i class="bi bi-arrow-left"></i></a>
        <?php if ($payslips): ?>
            <div class="btn-group">
                <a class="btn btn-outline-secondary" href="<?= url('payslips/print', ['id' => $period['id']]) ?>" target="_blank"><i class="bi bi-printer me-1"></i>Imprimir recibos</a>
                <button class="btn btn-outline-secondary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-label="Más opciones"></button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="<?= url('periods/export', ['id' => $period['id']]) ?>"><i class="bi bi-filetype-csv me-2"></i>Libro de sueldos (CSV)</a></li>
                    <?php if (can('rrhh')): ?>
                        <li><a class="dropdown-item" href="<?= url('periods/bank', ['id' => $period['id']]) ?>"><i class="bi bi-bank me-2"></i>Archivo de transferencias</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        <?php endif; ?>
        <?php if (can('rrhh') && $editable): ?>
            <form method="post" action="<?= url('periods/calculate', ['id' => $period['id']]) ?>"<?= $payslips ? ' data-confirm="Se van a regenerar todos los recibos. ¿Continuar?"' : '' ?>>
                <?= csrf_field() ?>
                <button class="btn btn-primary"><i class="bi bi-lightning-charge me-1"></i><?= $period['status'] === 'borrador' && !$payslips ? 'Calcular' : 'Recalcular' ?></button>
            </form>
            <?php if ($period['status'] === 'calculada'): ?>
                <form method="post" action="<?= url('periods/close', ['id' => $period['id']]) ?>" data-confirm="Al cerrar la liquidación los recibos quedan definitivos. ¿Cerrar?">
                    <?= csrf_field() ?>
                    <button class="btn btn-success"><i class="bi bi-lock me-1"></i>Cerrar</button>
                </form>
            <?php endif; ?>
            <form method="post" action="<?= url('periods/delete', ['id' => $period['id']]) ?>" data-confirm="¿Eliminar la liquidación con sus novedades y recibos?">
                <?= csrf_field() ?>
                <button class="btn btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
            </form>
        <?php endif; ?>
        <?php if (!$editable && can('admin')): ?>
            <form method="post" action="<?= url('periods/reopen', ['id' => $period['id']]) ?>" data-confirm="¿Reabrir la liquidación cerrada?">
                <?= csrf_field() ?>
                <button class="btn btn-outline-warning"><i class="bi bi-unlock me-1"></i>Reabrir</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if ($period['status'] === 'borrador' && $payslips): ?>
    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i>Hubo cambios en las novedades después del último cálculo. Recalculá para actualizar los recibos.</div>
<?php endif; ?>

<?php if ($payslips): ?>
<div class="row g-3 mb-3">
    <?php foreach ([
        ['Remunerativo', $totals['gross_rem'], 'success'],
        ['No remunerativo', $totals['gross_no_rem'], 'info'],
        ['Descuentos', $totals['deductions'], 'danger'],
        ['Neto a pagar', $totals['net_pay'], 'primary'],
        ['Contribuciones', $totals['employer_contrib'], 'warning'],
    ] as [$label, $amount, $color]): ?>
        <div class="col-6 col-md-4 col-xl">
            <div class="card h-100 border-start border-4 border-<?= $color ?>"><div class="card-body py-3">
                <div class="kpi-label"><?= e($label) ?></div>
                <div class="kpi-value kpi-value-sm"><?= money($amount) ?></div>
            </div></div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<ul class="nav nav-tabs mb-3">
    <?php foreach ($tabs as $key => [$label, $count]): ?>
        <li class="nav-item">
            <a class="nav-link<?= $tab === $key ? ' active' : '' ?>" href="<?= url('periods/show', ['id' => $period['id'], 'tab' => $key]) ?>">
                <?= e($label) ?><?php if ($count !== null): ?> <span class="badge rounded-pill text-bg-light border"><?= $count ?></span><?php endif; ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<?php if ($tab === 'payslips'): ?>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Legajo</th><th>Empleado</th><th class="d-none d-lg-table-cell">Puesto</th><th class="num">Remunerativo</th><th class="num d-none d-md-table-cell">No rem.</th><th class="num">Descuentos</th><th class="num">Neto</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($payslips as $ps): ?>
                    <tr data-href="<?= url('payslips/show', ['id' => $ps['id']]) ?>">
                        <td><?= e($ps['file_number']) ?></td>
                        <td class="fw-medium"><?= e($ps['employee_name']) ?></td>
                        <td class="d-none d-lg-table-cell small text-body-secondary"><?= e($ps['position_name']) ?></td>
                        <td class="num"><?= money($ps['gross_rem']) ?></td>
                        <td class="num d-none d-md-table-cell"><?= money($ps['gross_no_rem']) ?></td>
                        <td class="num text-danger"><?= money($ps['deductions']) ?></td>
                        <td class="num fw-semibold"><?= money($ps['net_pay']) ?></td>
                        <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="<?= url('payslips/show', ['id' => $ps['id']]) ?>" title="Ver recibo"><i class="bi bi-receipt"></i></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <?php if ($payslips): ?>
                <tfoot class="fw-semibold">
                    <tr><td colspan="2">Totales</td><td class="d-none d-lg-table-cell"></td><td class="num"><?= money($totals['gross_rem']) ?></td><td class="num d-none d-md-table-cell"><?= money($totals['gross_no_rem']) ?></td><td class="num text-danger"><?= money($totals['deductions']) ?></td><td class="num"><?= money($totals['net_pay']) ?></td><td></td></tr>
                </tfoot>
                <?php endif; ?>
            </table>
            <?php if (!$payslips): ?>
                <div class="empty-state">
                    <i class="bi bi-lightning-charge"></i>
                    Todavía no se calculó esta liquidación.
                    <?php if (can('rrhh')): ?><br>Cargá las novedades del mes (horas extras, inasistencias, bonos) y presioná <strong>Calcular</strong>.<?php endif; ?>
                    <div class="small mt-2"><?= count($employees) ?> empleados alcanzados por este período.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php elseif ($tab === 'novelties'): ?>
    <?php if (can('rrhh') && $editable): ?>
        <div class="card mb-3">
            <div class="card-header">Registrar novedad</div>
            <div class="card-body">
                <form class="row g-2 align-items-end" method="post" action="<?= url('periods/novelty-add', ['id' => $period['id']]) ?>">
                    <?= csrf_field() ?>
                    <div class="col-md-4">
                        <label class="form-label small" for="nov_employee">Empleado</label>
                        <select class="form-select" id="nov_employee" name="employee_id" required>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?= $emp['id'] ?>"><?= e($emp['last_name'] . ', ' . $emp['first_name'] . ' (' . $emp['file_number'] . ')') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small" for="nov_concept">Concepto</label>
                        <select class="form-select" id="nov_concept" name="concept_id" required>
                            <?php foreach ($concepts as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= e($c['code'] . ' · ' . $c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-1">
                        <label class="form-label small" for="nov_qty">Cantidad</label>
                        <input class="form-control" id="nov_qty" name="quantity" inputmode="decimal" placeholder="hs/días">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small" for="nov_amount">Importe</label>
                        <input class="form-control" id="nov_amount" name="amount" inputmode="decimal" placeholder="$">
                    </div>
                    <div class="col-md-2 d-grid">
                        <button class="btn btn-primary"><i class="bi bi-plus-lg"></i> Agregar</button>
                    </div>
                    <div class="col-12">
                        <input class="form-control form-control-sm" name="note" placeholder="Observación (opcional)" maxlength="255" aria-label="Observación">
                        <div class="form-text">Informá <strong>cantidad</strong> para horas, días o unidades; o un <strong>importe</strong> para montos fijos (bonos, adelantos). El importe reemplaza al cálculo automático.</div>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Empleado</th><th>Concepto</th><th class="num">Cantidad</th><th class="num">Importe</th><th>Observación</th><?php if (can('rrhh') && $editable): ?><th></th><?php endif; ?></tr></thead>
                <tbody>
                <?php foreach ($novelties as $n): ?>
                    <tr>
                        <td><?= e($n['last_name'] . ', ' . $n['first_name']) ?> <span class="text-body-secondary small">(<?= e($n['file_number']) ?>)</span></td>
                        <td><span class="badge bg-<?= $typeColors[$n['type']] ?>-subtle text-<?= $typeColors[$n['type']] ?>-emphasis me-1"><?= e($n['code']) ?></span><?= e($n['concept_name']) ?></td>
                        <td class="num"><?= e(num($n['quantity'])) ?></td>
                        <td class="num"><?= $n['amount'] !== null ? money($n['amount']) : '' ?></td>
                        <td class="small text-body-secondary"><?= e($n['note']) ?></td>
                        <?php if (can('rrhh') && $editable): ?>
                            <td class="text-end">
                                <form method="post" action="<?= url('periods/novelty-del', ['id' => $period['id']]) ?>" data-confirm="¿Eliminar la novedad?">
                                    <?= csrf_field() ?><input type="hidden" name="novelty_id" value="<?= $n['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (!$novelties): ?>
                <div class="empty-state"><i class="bi bi-inbox"></i>No hay novedades cargadas para este período.</div>
            <?php endif; ?>
        </div>
    </div>

<?php else: ?>
    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Código</th><th>Concepto</th><th>Tipo</th><th class="num">Empleados</th><th class="num">Total</th></tr></thead>
                <tbody>
                <?php foreach ($byConcept as $row): ?>
                    <tr>
                        <td><?= e($row['code']) ?></td>
                        <td><?= e($row['name']) ?></td>
                        <td><span class="badge bg-<?= $typeColors[$row['type']] ?>-subtle text-<?= $typeColors[$row['type']] ?>-emphasis"><?= e(CONCEPT_TYPES[$row['type']]) ?></span></td>
                        <td class="num"><?= (int) $row['employees'] ?></td>
                        <td class="num fw-semibold"><?= money($row['total']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (!$byConcept): ?>
                <div class="empty-state"><i class="bi bi-table"></i>Calculá la liquidación para ver el resumen.</div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
