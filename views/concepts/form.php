<?php
$isNew = empty($concept['id']);
$title = $isNew ? 'Nuevo concepto' : 'Editar concepto';
$v = fn (string $k) => old($k, $concept[$k] ?? '');
$hints = [
    'basico'     => 'Toma el sueldo básico del empleado (o de su puesto). Si ingresó o egresó en el mes, se calcula proporcional a los días.',
    'fijo'       => 'Importe fijo en pesos. Con valor 0 funciona como "a informar": se carga el importe como novedad en cada liquidación.',
    'porcentaje' => 'Porcentaje sobre la base elegida. En haberes, "Total remunerativo" es lo acumulado hasta este concepto (según el orden).',
    'cantidad'   => 'Cantidad (de la novedad o asignación) multiplicada por el valor unitario.',
    'horas'      => 'Horas × (básico ÷ divisor de horas) × valor %. Ej: 150 para horas al 50%.',
    'dias'       => 'Días × (básico ÷ 30) × valor %. Usá un valor negativo (-100) para descontar inasistencias.',
    'antiguedad' => 'Valor % del básico por cada año completo de antigüedad.',
    'sac'        => 'Valor % (normalmente 50) de la mejor remuneración mensual del semestre, proporcional a los días trabajados.',
];
?>
<div class="page-header">
    <h1><?= e($title) ?></h1>
    <a class="btn btn-outline-secondary" href="<?= url('concepts') ?>"><i class="bi bi-arrow-left me-1"></i>Volver</a>
</div>

<form method="post" action="<?= $isNew ? url('concepts/store') : url('concepts/update', ['id' => $concept['id']]) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="card" style="max-width: 860px">
        <div class="card-body row g-3">
            <div class="col-md-3">
                <label class="form-label" for="code">Código *</label>
                <input class="form-control<?= invalid('code') ?>" id="code" name="code" value="<?= e($v('code')) ?>" maxlength="10" required>
                <?= field_error('code') ?>
            </div>
            <div class="col-md-9">
                <label class="form-label" for="name">Nombre *</label>
                <input class="form-control<?= invalid('name') ?>" id="name" name="name" value="<?= e($v('name')) ?>" maxlength="120" required>
                <?= field_error('name') ?>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="type">Tipo *</label>
                <select class="form-select" id="type" name="type">
                    <?php foreach (CONCEPT_TYPES as $k => $label): ?>
                        <option value="<?= $k ?>"<?= selected($v('type'), $k) ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="calc_mode">Modo de cálculo *</label>
                <select class="form-select" id="calc_mode" name="calc_mode">
                    <?php foreach (CALC_MODES as $k => $label): ?>
                        <option value="<?= $k ?>"<?= selected($v('calc_mode'), $k) ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <?php foreach ($hints as $mode => $hint): ?>
                    <div class="alert alert-light border small py-2 mb-0 d-none" data-mode-hint="<?= $mode ?>"><i class="bi bi-info-circle me-1"></i><?= e($hint) ?></div>
                <?php endforeach; ?>
            </div>
            <div class="col-md-6" id="baseGroup">
                <label class="form-label" for="base">Base del porcentaje</label>
                <select class="form-select<?= invalid('base') ?>" id="base" name="base">
                    <option value="">—</option>
                    <?php foreach (CONCEPT_BASES as $k => $label): ?>
                        <option value="<?= $k ?>"<?= selected($v('base'), $k) ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= field_error('base') ?>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="value">Valor *</label>
                <input class="form-control<?= invalid('value') ?>" id="value" name="value" value="<?= e(is_numeric($v('value')) ? num($v('value'), 4) : $v('value')) ?>" inputmode="decimal" required>
                <?= field_error('value') ?>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="sort_order">Orden *</label>
                <input class="form-control<?= invalid('sort_order') ?>" type="number" id="sort_order" name="sort_order" value="<?= e($v('sort_order')) ?>" required>
                <?= field_error('sort_order') ?>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="scope">Se aplica en</label>
                <select class="form-select" id="scope" name="scope">
                    <option value="mensual"<?= selected($v('scope'), 'mensual') ?>>Liquidaciones mensuales</option>
                    <option value="sac"<?= selected($v('scope'), 'sac') ?>>Liquidaciones de SAC</option>
                    <option value="ambos"<?= selected($v('scope'), 'ambos') ?>>Ambas</option>
                </select>
            </div>
            <div class="col-md-6 d-flex flex-column justify-content-end gap-2">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="applies_to_all" name="applies_to_all" value="1"<?= checked($v('applies_to_all')) ?>>
                    <label class="form-check-label" for="applies_to_all">Aplicar a todos los empleados</label>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="active" name="active" value="1"<?= checked($v('active')) ?>>
                    <label class="form-check-label" for="active">Activo</label>
                </div>
            </div>
            <div class="col-12">
                <label class="form-label" for="description">Descripción</label>
                <input class="form-control" id="description" name="description" value="<?= e($v('description')) ?>" maxlength="255">
            </div>
        </div>
        <div class="card-footer d-flex justify-content-end gap-2">
            <a class="btn btn-outline-secondary" href="<?= url('concepts') ?>">Cancelar</a>
            <button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Guardar</button>
        </div>
    </div>
</form>
