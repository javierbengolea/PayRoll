<?php $title = 'Liquidaciones'; ?>
<div class="page-header">
    <div>
        <h1>Liquidaciones</h1>
        <div class="subtitle">Crear, calcular y cerrar los períodos de pago</div>
    </div>
    <?php if (can('rrhh')): ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#periodModal"><i class="bi bi-plus-lg me-1"></i>Nueva liquidación</button>
    <?php endif; ?>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
            <tr><th>Período</th><th>Descripción</th><th>Estado</th><th class="num">Recibos</th><th class="num">Bruto</th><th class="num">Neto</th><th class="num d-none d-lg-table-cell">Costo total</th><th class="d-none d-md-table-cell">Pago</th></tr>
            </thead>
            <tbody>
            <?php foreach ($periods as $p): ?>
                <tr data-href="<?= url('periods/show', ['id' => $p['id']]) ?>">
                    <td class="fw-semibold text-nowrap"><a class="text-reset text-decoration-none" href="<?= url('periods/show', ['id' => $p['id']]) ?>"><?= e(period_label($p)) ?></a></td>
                    <td><?= e($p['description']) ?><?php if ($p['novelties']): ?> <span class="badge text-bg-light border"><?= (int) $p['novelties'] ?> novedades</span><?php endif; ?></td>
                    <td><?php $map = PERIOD_STATUS; $value = $p['status']; require BASE_PATH . '/views/partials/status.php'; ?></td>
                    <td class="num"><?= (int) $p['payslips'] ?></td>
                    <td class="num"><?= money($p['gross']) ?></td>
                    <td class="num fw-semibold"><?= money($p['net']) ?></td>
                    <td class="num d-none d-lg-table-cell"><?= money($p['gross'] + $p['contrib']) ?></td>
                    <td class="d-none d-md-table-cell"><?= e(fmt_date($p['payment_date'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (!$periods): ?>
            <div class="empty-state"><i class="bi bi-calculator"></i>Todavía no hay liquidaciones. Creá la primera con el botón "Nueva liquidación".</div>
        <?php endif; ?>
    </div>
</div>

<?php if (can('rrhh')): ?>
<div class="modal fade" id="periodModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="post" action="<?= url('periods/store') ?>">
            <?= csrf_field() ?>
            <div class="modal-header"><h5 class="modal-title">Nueva liquidación</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body row g-3">
                <div class="col-12">
                    <label class="form-label">Tipo</label>
                    <select class="form-select" name="type">
                        <option value="mensual">Sueldo mensual</option>
                        <option value="sac">SAC (aguinaldo)</option>
                    </select>
                    <div class="form-text">Para el SAC se usa el mes 6 (1er semestre) o 12 (2do semestre).</div>
                </div>
                <div class="col-7">
                    <label class="form-label">Mes</label>
                    <select class="form-select" name="month">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>"<?= selected($suggested['month'], $m) ?>><?= month_name($m) ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-5">
                    <label class="form-label">Año</label>
                    <input class="form-control" type="number" name="year" value="<?= (int) $suggested['year'] ?>" min="2000" max="2100" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Fecha de pago</label>
                    <input class="form-control" type="date" name="payment_date">
                </div>
                <div class="col-12">
                    <label class="form-label">Descripción</label>
                    <input class="form-control" name="description" placeholder="Automática si queda vacía" maxlength="160">
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">Crear</button></div>
        </form>
    </div>
</div>
<?php endif; ?>
