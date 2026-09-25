<?php
$title = 'Reportes';
$sum = fn (array $rows, string $k) => array_sum(array_map(fn ($r) => (float) $r[$k], $rows));
$typeColors = ['haber_rem' => 'success', 'haber_no_rem' => 'info', 'descuento' => 'danger', 'contribucion' => 'warning'];
$chart = [
    'labels'  => array_map(fn ($m) => ($m['type'] === 'sac' ? 'SAC ' : '') . mb_substr(month_name((int) $m['month']), 0, 3), $monthly),
    'rem'     => array_map(fn ($m) => (float) $m['rem'], $monthly),
    'noRem'   => array_map(fn ($m) => (float) $m['no_rem'], $monthly),
    'contrib' => array_map(fn ($m) => (float) $m['contrib'], $monthly),
];
?>
<div class="page-header">
    <div>
        <h1>Reportes</h1>
        <div class="subtitle">Acumulados anuales de liquidaciones calculadas y cerradas</div>
    </div>
    <form method="get" action="<?= url('') ?>" class="d-flex gap-2">
        <input type="hidden" name="r" value="reports">
        <select class="form-select" name="year" onchange="this.form.submit()" aria-label="Año">
            <?php foreach ($years ?: [$year] as $y): ?>
                <option value="<?= $y ?>"<?= selected($year, $y) ?>><?= $y ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-outline-secondary" type="button" onclick="window.print()" title="Imprimir"><i class="bi bi-printer"></i></button>
    </form>
</div>

<?php if (!$monthly): ?>
    <div class="card"><div class="empty-state"><i class="bi bi-bar-chart-line"></i>No hay liquidaciones calculadas en <?= $year ?>.</div></div>
<?php else: ?>

<div class="row g-3 mb-3">
    <?php foreach ([
        ['Total bruto', $sum($monthly, 'rem') + $sum($monthly, 'no_rem'), 'bi-cash-stack', 'success'],
        ['Total neto pagado', $sum($monthly, 'net'), 'bi-wallet2', 'primary'],
        ['Contribuciones', $sum($monthly, 'contrib'), 'bi-bank', 'warning'],
        ['Costo laboral', $sum($monthly, 'rem') + $sum($monthly, 'no_rem') + $sum($monthly, 'contrib'), 'bi-building', 'danger'],
    ] as [$label, $amount, $icon, $color]): ?>
        <div class="col-md-6">
            <div class="card h-100"><div class="card-body kpi">
                <div class="kpi-icon bg-<?= $color ?>-subtle text-<?= $color ?>-emphasis"><i class="bi <?= $icon ?>"></i></div>
                <div><div class="kpi-label"><?= e($label) ?> <?= $year ?></div><div class="kpi-value"><?= money($amount) ?></div></div>
            </div></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card mb-3">
    <div class="card-header">Composición del costo laboral por mes</div>
    <div class="card-body"><canvas id="costChart" height="90" role="img" aria-label="Costo laboral mensual"></canvas></div>
</div>

<div class="card mb-3">
    <div class="card-header">Detalle mensual</div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Período</th><th class="num">Recibos</th><th class="num">Remunerativo</th><th class="num">No rem.</th><th class="num">Descuentos</th><th class="num">Neto</th><th class="num">Contribuciones</th><th class="num">Costo total</th></tr></thead>
            <tbody>
            <?php foreach ($monthly as $m): ?>
                <tr>
                    <td class="text-nowrap"><?= e(period_label(['year' => $year] + $m)) ?></td>
                    <td class="num"><?= (int) $m['employees'] ?></td>
                    <td class="num"><?= money($m['rem']) ?></td>
                    <td class="num"><?= money($m['no_rem']) ?></td>
                    <td class="num"><?= money($m['deductions']) ?></td>
                    <td class="num fw-semibold"><?= money($m['net']) ?></td>
                    <td class="num"><?= money($m['contrib']) ?></td>
                    <td class="num"><?= money($m['rem'] + $m['no_rem'] + $m['contrib']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot class="fw-semibold">
                <tr><td>Total</td><td></td><td class="num"><?= money($sum($monthly, 'rem')) ?></td><td class="num"><?= money($sum($monthly, 'no_rem')) ?></td><td class="num"><?= money($sum($monthly, 'deductions')) ?></td><td class="num"><?= money($sum($monthly, 'net')) ?></td><td class="num"><?= money($sum($monthly, 'contrib')) ?></td><td class="num"><?= money($sum($monthly, 'rem') + $sum($monthly, 'no_rem') + $sum($monthly, 'contrib')) ?></td></tr>
            </tfoot>
        </table>
    </div>
</div>

<div class="row g-3">
    <div class="col-12">
        <div class="card h-100">
            <div class="card-header">Costo por departamento</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Departamento</th><th class="num">Empleados</th><th class="num">Neto</th><th class="num">Costo total</th><th style="width:25%"></th></tr></thead>
                    <tbody>
                    <?php $maxCost = max(array_map(fn ($d) => (float) $d['cost'], $byDepartment) ?: [1]); ?>
                    <?php foreach ($byDepartment as $d): ?>
                        <tr>
                            <td><?= e($d['name']) ?></td>
                            <td class="num"><?= (int) $d['employees'] ?></td>
                            <td class="num"><?= money($d['net']) ?></td>
                            <td class="num fw-semibold"><?= money($d['cost']) ?></td>
                            <td><div class="progress" style="height:6px"><div class="progress-bar" style="width: <?= round((float) $d['cost'] / $maxCost * 100) ?>%"></div></div></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="card h-100">
            <div class="card-header">Acumulado por concepto</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Concepto</th><th>Tipo</th><th class="num">Total</th></tr></thead>
                    <tbody>
                    <?php foreach ($byConcept as $c): ?>
                        <tr>
                            <td><span class="text-body-secondary"><?= e($c['code']) ?></span> <?= e($c['name']) ?></td>
                            <td><span class="badge bg-<?= $typeColors[$c['type']] ?>-subtle text-<?= $typeColors[$c['type']] ?>-emphasis"><?= e(CONCEPT_TYPES[$c['type']]) ?></span></td>
                            <td class="num"><?= money($c['total']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="card">
            <div class="card-header">Mayor costo laboral por empleado (top 10)</div>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead><tr><th>#</th><th>Empleado</th><th>Departamento</th><th class="num">Neto anual</th><th class="num">Costo anual</th></tr></thead>
                    <tbody>
                    <?php foreach ($topEarners as $i => $t): ?>
                        <tr data-href="<?= url('employees/show', ['id' => $t['employee_id']]) ?>">
                            <td class="text-body-secondary"><?= $i + 1 ?></td>
                            <td><?= e($t['employee_name']) ?></td>
                            <td><?= e($t['department_name'] ?? '—') ?></td>
                            <td class="num"><?= money($t['net']) ?></td>
                            <td class="num fw-semibold"><?= money($t['cost']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php ob_start(); ?>
<script src="<?= asset('vendor/chartjs/chart.umd.min.js') ?>"></script>
<script>
(function () {
    var d = <?= json_encode($chart, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    var chart;
    var money = function (v) { return '$ ' + Number(v).toLocaleString('es-AR', { maximumFractionDigits: 0 }); };
    function draw() {
        if (chart) chart.destroy();
        var s = getComputedStyle(document.documentElement);
        Chart.defaults.color = s.getPropertyValue('--bs-secondary-color').trim();
        chart = new Chart(document.getElementById('costChart'), {
            type: 'bar',
            data: { labels: d.labels, datasets: [
                { label: 'Remunerativo', data: d.rem, backgroundColor: '#10b981' },
                { label: 'No remunerativo', data: d.noRem, backgroundColor: '#06b6d4' },
                { label: 'Contribuciones', data: d.contrib, backgroundColor: '#f59e0b' }
            ] },
            options: {
                interaction: { mode: 'index', intersect: false },
                plugins: { tooltip: { callbacks: { label: function (c) { return c.dataset.label + ': ' + money(c.parsed.y); } } } },
                scales: { x: { stacked: true, grid: { display: false } }, y: { stacked: true, ticks: { callback: money }, grid: { color: s.getPropertyValue('--bs-border-color-translucent').trim() } } }
            }
        });
    }
    draw();
    document.addEventListener('themechange', draw);
})();
</script>
<?php $scripts = ob_get_clean(); ?>
<?php endif; ?>
