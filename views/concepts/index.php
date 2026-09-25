<?php
$title = 'Conceptos';
$describe = function (array $c): string {
    $v = num($c['value']);
    return match ($c['calc_mode']) {
        'basico'     => 'Básico del empleado',
        'fijo'       => (float) $c['value'] ? money($c['value']) : 'Importe informado',
        'porcentaje' => $v . '% s/ ' . mb_strtolower(CONCEPT_BASES[$c['base']] ?? ''),
        'cantidad'   => 'Cantidad × ' . money($c['value']),
        'horas'      => 'Horas × valor hora × ' . $v . '%',
        'dias'       => 'Días × básico/30 × ' . $v . '%',
        'antiguedad' => $v . '% del básico por año',
        'sac'        => $v . '% mejor remuneración',
        default      => '',
    };
};
$typeColors = ['haber_rem' => 'success', 'haber_no_rem' => 'info', 'descuento' => 'danger', 'contribucion' => 'warning'];
$scopes = ['mensual' => 'Mensual', 'sac' => 'SAC', 'ambos' => 'Mensual y SAC'];
?>
<div class="page-header">
    <div>
        <h1>Conceptos de liquidación</h1>
        <div class="subtitle">Haberes, descuentos y contribuciones que componen cada recibo</div>
    </div>
    <?php if (can('rrhh')): ?>
        <a class="btn btn-primary" href="<?= url('concepts/create') ?>"><i class="bi bi-plus-lg me-1"></i>Nuevo concepto</a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Código</th><th>Concepto</th><th>Tipo</th><th>Cálculo</th><th>Aplicación</th><th>Estado</th><?php if (can('rrhh')): ?><th></th><?php endif; ?></tr></thead>
            <tbody>
            <?php foreach ($concepts as $c): ?>
                <tr class="<?= $c['active'] ? '' : 'opacity-50' ?>">
                    <td class="fw-semibold"><?= e($c['code']) ?></td>
                    <td>
                        <?= e($c['name']) ?>
                        <?php if ($c['description']): ?><div class="small text-body-secondary"><?= e($c['description']) ?></div><?php endif; ?>
                    </td>
                    <td><span class="badge bg-<?= $typeColors[$c['type']] ?>-subtle text-<?= $typeColors[$c['type']] ?>-emphasis"><?= e(CONCEPT_TYPES[$c['type']]) ?></span></td>
                    <td class="small"><?= e($describe($c)) ?></td>
                    <td class="small">
                        <?= $c['applies_to_all'] ? '<i class="bi bi-people-fill text-primary"></i> Todos' : '<i class="bi bi-person"></i> Asignado / novedad' ?>
                        <?php if (!$c['applies_to_all'] && $c['assigned']): ?><span class="text-body-secondary">(<?= (int) $c['assigned'] ?>)</span><?php endif; ?>
                        <div class="text-body-secondary"><?= e($scopes[$c['scope']]) ?></div>
                    </td>
                    <td><?= $c['active'] ? '<span class="badge text-bg-success">Activo</span>' : '<span class="badge text-bg-secondary">Inactivo</span>' ?></td>
                    <?php if (can('rrhh')): ?>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-secondary" href="<?= url('concepts/edit', ['id' => $c['id']]) ?>" title="Editar"><i class="bi bi-pencil"></i></a>
                            <form class="d-inline" method="post" action="<?= url('concepts/toggle') ?>">
                                <?= csrf_field() ?><input type="hidden" name="id" value="<?= $c['id'] ?>">
                                <button class="btn btn-sm btn-outline-<?= $c['active'] ? 'warning' : 'success' ?>" title="<?= $c['active'] ? 'Desactivar' : 'Activar' ?>"><i class="bi bi-power"></i></button>
                            </form>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
