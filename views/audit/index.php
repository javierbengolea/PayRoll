<?php
$title = 'Auditoría';
$entities = ['employee' => 'Empleado', 'period' => 'Liquidación', 'concept' => 'Concepto', 'user' => 'Usuario',
    'department' => 'Departamento', 'position' => 'Puesto', 'settings' => 'Configuración'];
$links = ['employee' => 'employees/show', 'period' => 'periods/show', 'concept' => 'concepts/edit'];
?>
<div class="page-header">
    <div>
        <h1>Auditoría</h1>
        <div class="subtitle"><?= $total ?> eventos registrados</div>
    </div>
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Entidad</th><th>Detalle</th><th>IP</th></tr></thead>
            <tbody>
            <?php foreach ($entries as $a): ?>
                <tr>
                    <td class="text-nowrap"><?= e(fmt_datetime($a['created_at'])) ?></td>
                    <td><?= e($a['user_name'] ?? '—') ?></td>
                    <td><span class="badge text-bg-light border"><?= e(str_replace('_', ' ', $a['action'])) ?></span></td>
                    <td class="text-nowrap">
                        <?= e($entities[$a['entity']] ?? $a['entity']) ?>
                        <?php if ($a['entity_id'] && isset($links[$a['entity']])): ?>
                            <a href="<?= url($links[$a['entity']], ['id' => $a['entity_id']]) ?>">#<?= (int) $a['entity_id'] ?></a>
                        <?php elseif ($a['entity_id']): ?>#<?= (int) $a['entity_id'] ?><?php endif; ?>
                    </td>
                    <td class="small"><?= e($a['details']) ?></td>
                    <td class="small text-body-secondary"><?= e($a['ip']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pages > 1): ?>
        <div class="card-footer"><?php $route = 'audit'; require BASE_PATH . '/views/partials/pagination.php'; ?></div>
    <?php endif; ?>
</div>
