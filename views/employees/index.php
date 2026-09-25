<?php
$title = 'Empleados';
$q = $_GET['q'] ?? '';
$status = $_GET['status'] ?? 'vigentes';
$dep = $_GET['department'] ?? '';
$sortLink = function (string $key, string $label) {
    $params = $_GET;
    unset($params['r'], $params['page']);
    $isCurrent = ($params['sort'] ?? 'nombre') === $key;
    $dir = $isCurrent && ($params['dir'] ?? 'asc') === 'asc' ? 'desc' : 'asc';
    $icon = $isCurrent ? (($params['dir'] ?? 'asc') === 'asc' ? ' <i class="bi bi-caret-up-fill"></i>' : ' <i class="bi bi-caret-down-fill"></i>') : '';
    return '<a class="text-reset text-decoration-none" href="' . e(url('employees', ['sort' => $key, 'dir' => $dir] + $params)) . '">' . e($label) . $icon . '</a>';
};
$exportParams = array_filter(['q' => $q, 'status' => $status, 'department' => $dep, 'category' => $_GET['category'] ?? '']);
$categoryFilter = null;
foreach ($categories as $group => $cats) {
    foreach ($cats as $c) {
        if ((string) $c['id'] === (string) ($_GET['category'] ?? '')) {
            $categoryFilter = $c['name'] . ' (' . $c['agreement_code'] . ')';
        }
    }
}
?>
<div class="page-header">
    <div>
        <h1>Empleados</h1>
        <div class="subtitle"><?= $total ?> <?= $total === 1 ? 'resultado' : 'resultados' ?>
            <?php if ($categoryFilter): ?>· Categoría <strong><?= e($categoryFilter) ?></strong>
                <a href="<?= url('employees', array_filter(['q' => $q, 'status' => $status, 'department' => $dep])) ?>" class="ms-1" title="Quitar filtro"><i class="bi bi-x-circle"></i></a><?php endif; ?></div>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="<?= url('employees/export', $exportParams) ?>"><i class="bi bi-filetype-csv me-1"></i>Exportar</a>
        <?php if (can('rrhh')): ?>
            <a class="btn btn-primary" href="<?= url('employees/create') ?>"><i class="bi bi-person-plus me-1"></i>Nuevo empleado</a>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-body border-bottom">
        <form class="row g-2" method="get" action="<?= url('') ?>">
            <input type="hidden" name="r" value="employees">
            <?php if (!empty($_GET['category'])): ?><input type="hidden" name="category" value="<?= (int) $_GET['category'] ?>"><?php endif; ?>
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="Buscar por nombre, legajo o CUIL">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <select class="form-select" name="department" aria-label="Departamento">
                    <option value="">Todos los departamentos</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= $d['id'] ?>"<?= selected($dep, $d['id']) ?>><?= e($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select class="form-select" name="status" aria-label="Estado">
                    <option value="vigentes"<?= selected($status, 'vigentes') ?>>Vigentes</option>
                    <?php foreach (EMPLOYEE_STATUS as $k => [$label]): ?>
                        <option value="<?= $k ?>"<?= selected($status, $k) ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                    <option value="todos"<?= selected($status, 'todos') ?>>Todos</option>
                </select>
            </div>
            <div class="col-md-2 d-grid">
                <button class="btn btn-outline-primary">Filtrar</button>
            </div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
            <tr>
                <th><?= $sortLink('legajo', 'Legajo') ?></th>
                <th><?= $sortLink('nombre', 'Empleado') ?></th>
                <th class="d-none d-md-table-cell">CUIL</th>
                <th class="d-none d-lg-table-cell">Departamento / Puesto</th>
                <th class="d-none d-xl-table-cell">Categoría</th>
                <th class="d-none d-md-table-cell"><?= $sortLink('ingreso', 'Ingreso') ?></th>
                <th class="num"><?= $sortLink('sueldo', 'Básico') ?></th>
                <th>Estado</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($employees as $emp): ?>
                <tr data-href="<?= url('employees/show', ['id' => $emp['id']]) ?>">
                    <td class="fw-semibold"><?= e($emp['file_number']) ?></td>
                    <td>
                        <a class="text-reset text-decoration-none fw-medium" href="<?= url('employees/show', ['id' => $emp['id']]) ?>"><?= e($emp['last_name'] . ', ' . $emp['first_name']) ?></a>
                        <?php if ($emp['email']): ?><div class="small text-body-secondary"><?= e($emp['email']) ?></div><?php endif; ?>
                    </td>
                    <td class="d-none d-md-table-cell text-nowrap"><?= e(fmt_cuil($emp['cuil'])) ?></td>
                    <td class="d-none d-lg-table-cell">
                        <?= e($emp['department_name'] ?? '—') ?>
                        <div class="small text-body-secondary"><?= e($emp['position_name'] ?? '') ?></div>
                    </td>
                    <td class="d-none d-xl-table-cell small"><?= e($emp['category_name'] ?? 'Fuera de convenio') ?></td>
                    <td class="d-none d-md-table-cell"><?= e(fmt_date($emp['hire_date'])) ?></td>
                    <td class="num"><?= money($emp['effective_salary']) ?></td>
                    <td><?php $map = EMPLOYEE_STATUS; $value = $emp['status']; require BASE_PATH . '/views/partials/status.php'; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (!$employees): ?>
            <div class="empty-state"><i class="bi bi-people"></i>No se encontraron empleados con esos filtros.</div>
        <?php endif; ?>
    </div>
    <?php if ($pages > 1): ?>
        <div class="card-footer"><?php $route = 'employees'; require BASE_PATH . '/views/partials/pagination.php'; ?></div>
    <?php endif; ?>
</div>
