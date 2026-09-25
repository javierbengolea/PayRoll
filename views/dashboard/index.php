<?php
$title = 'Tablero';
$active = (int) ($counts['activo'] ?? 0);
$leave = (int) ($counts['licencia'] ?? 0);
$labels = array_map(fn ($h) => ($h['type'] === 'sac' ? 'SAC ' : mb_substr(month_name((int) $h['month']), 0, 3) . ' ') . $h['year'], $history);
$chartData = [
    'labels'  => $labels,
    'net'     => array_map(fn ($h) => round((float) $h['net'], 2), $history),
    'cost'    => array_map(fn ($h) => round((float) $h['gross'] + (float) $h['contrib'], 2), $history),
    'depLabels' => array_column($byDepartment, 'name'),
    'depData'   => array_map('intval', array_column($byDepartment, 'total')),
];
?>
<div class="page-header">
    <div>
        <h1>Tablero</h1>
        <div class="subtitle"><?= e(fmt_date(date('Y-m-d'))) ?> · Resumen general de la nómina</div>
    </div>
    <?php if (can('rrhh')): ?>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary" href="<?= url('employees/create') ?>"><i class="bi bi-person-plus me-1"></i>Nuevo empleado</a>
            <a class="btn btn-primary" href="<?= url('periods') ?>"><i class="bi bi-calculator me-1"></i>Liquidar</a>
        </div>
    <?php endif; ?>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card h-100"><div class="card-body kpi">
            <div class="kpi-icon bg-primary-subtle text-primary-emphasis"><i class="bi bi-people"></i></div>
            <div><div class="kpi-label">Empleados activos</div><div class="kpi-value"><?= $active ?></div>
                <?php if ($leave): ?><div class="small text-body-secondary"><?= $leave ?> en licencia</div><?php endif; ?></div>
        </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card h-100"><div class="card-body kpi">
            <div class="kpi-icon bg-success-subtle text-success-emphasis"><i class="bi bi-wallet2"></i></div>
            <div><div class="kpi-label">Neto a pagar<?= $lastPeriod ? ' · ' . e(period_label($lastPeriod)) : '' ?></div>
                <div class="kpi-value"><?= money($lastPeriod['net'] ?? 0) ?></div></div>
        </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card h-100"><div class="card-body kpi">
            <div class="kpi-icon bg-warning-subtle text-warning-emphasis"><i class="bi bi-building"></i></div>
            <div><div class="kpi-label">Costo laboral total</div>
                <div class="kpi-value"><?= money(($lastPeriod['gross'] ?? 0) + ($lastPeriod['contrib'] ?? 0)) ?></div>
                <div class="small text-body-secondary">Bruto + contribuciones</div></div>
        </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card h-100"><div class="card-body kpi">
            <div class="kpi-icon bg-info-subtle text-info-emphasis"><i class="bi bi-graph-up"></i></div>
            <div><div class="kpi-label">Neto promedio</div>
                <div class="kpi-value"><?= money(!empty($lastPeriod['payslips']) ? $lastPeriod['net'] / $lastPeriod['payslips'] : 0) ?></div>
                <div class="small text-body-secondary">por recibo</div></div>
        </div></div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header">Evolución de la nómina (últimas 12 liquidaciones)</div>
            <div class="card-body">
                <?php if ($history): ?>
                    <canvas id="historyChart" height="120" role="img" aria-label="Evolución del neto y del costo laboral"></canvas>
                <?php else: ?>
                    <div class="empty-state"><i class="bi bi-bar-chart"></i>Todavía no hay liquidaciones calculadas.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">Dotación por departamento</div>
            <div class="card-body">
                <?php if ($byDepartment): ?>
                    <canvas id="depChart" height="220" role="img" aria-label="Empleados por departamento"></canvas>
                <?php else: ?>
                    <div class="empty-state"><i class="bi bi-diagram-3"></i>Sin datos.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">Liquidaciones abiertas</div>
            <div class="list-group list-group-flush">
                <?php foreach ($openPeriods as $p): ?>
                    <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" href="<?= url('periods/show', ['id' => $p['id']]) ?>">
                        <span><i class="bi bi-folder2-open me-2 text-body-secondary"></i><?= e($p['description']) ?></span>
                        <?php $map = PERIOD_STATUS; $value = $p['status']; require BASE_PATH . '/views/partials/status.php'; ?>
                    </a>
                <?php endforeach; ?>
                <?php if (!$openPeriods): ?>
                    <div class="list-group-item text-body-secondary">No hay liquidaciones pendientes. <i class="bi bi-check2-circle text-success"></i></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">Este mes</div>
            <div class="card-body">
                <h6 class="text-body-secondary small text-uppercase"><i class="bi bi-cake2 me-1"></i>Cumpleaños</h6>
                <ul class="list-unstyled mb-3">
                    <?php foreach ($birthdays as $b): ?>
                        <li><a href="<?= url('employees/show', ['id' => $b['id']]) ?>"><?= e($b['last_name'] . ', ' . $b['first_name']) ?></a>
                            <span class="text-body-secondary">· <?= e(date('d/m', strtotime($b['birth_date']))) ?></span></li>
                    <?php endforeach; ?>
                    <?php if (!$birthdays): ?><li class="text-body-secondary">Ninguno este mes.</li><?php endif; ?>
                </ul>
                <h6 class="text-body-secondary small text-uppercase"><i class="bi bi-award me-1"></i>Aniversarios en la empresa</h6>
                <ul class="list-unstyled mb-0">
                    <?php foreach ($anniversaries as $a): ?>
                        <li><a href="<?= url('employees/show', ['id' => $a['id']]) ?>"><?= e($a['last_name'] . ', ' . $a['first_name']) ?></a>
                            <span class="text-body-secondary">· <?= (int) $a['years'] ?> <?= (int) $a['years'] === 1 ? 'año' : 'años' ?> (<?= e(date('d/m', strtotime($a['hire_date']))) ?>)</span></li>
                    <?php endforeach; ?>
                    <?php if (!$anniversaries): ?><li class="text-body-secondary">Ninguno este mes.</li><?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php ob_start(); ?>
<script src="<?= asset('vendor/chartjs/chart.umd.min.js') ?>"></script>
<script>
(function () {
    var d = <?= json_encode($chartData, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    var charts = [];
    var money = function (v) { return '$ ' + Number(v).toLocaleString('es-AR', { maximumFractionDigits: 0 }); };
    function draw() {
        charts.forEach(function (c) { c.destroy(); });
        charts = [];
        var s = getComputedStyle(document.documentElement);
        var fg = s.getPropertyValue('--bs-secondary-color').trim();
        var grid = s.getPropertyValue('--bs-border-color-translucent').trim();
        Chart.defaults.color = fg;
        var h = document.getElementById('historyChart');
        if (h) charts.push(new Chart(h, {
            data: {
                labels: d.labels,
                datasets: [
                    { type: 'bar', label: 'Neto pagado', data: d.net, backgroundColor: '#6366f1', borderRadius: 4 },
                    { type: 'line', label: 'Costo laboral', data: d.cost, borderColor: '#f59e0b', backgroundColor: '#f59e0b', tension: .3, pointRadius: 3 }
                ]
            },
            options: {
                interaction: { mode: 'index', intersect: false },
                plugins: { tooltip: { callbacks: { label: function (c) { return c.dataset.label + ': ' + money(c.parsed.y); } } } },
                scales: { y: { ticks: { callback: money }, grid: { color: grid } }, x: { grid: { display: false } } }
            }
        }));
        var p = document.getElementById('depChart');
        if (p) charts.push(new Chart(p, {
            type: 'doughnut',
            data: { labels: d.depLabels, datasets: [{ data: d.depData, borderWidth: 0,
                backgroundColor: ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#06b6d4', '#8b5cf6', '#ec4899', '#84cc16'] }] },
            options: { plugins: { legend: { position: 'bottom' } }, cutout: '62%' }
        }));
    }
    draw();
    document.addEventListener('themechange', draw);
})();
</script>
<?php $scripts = ob_get_clean(); ?>
