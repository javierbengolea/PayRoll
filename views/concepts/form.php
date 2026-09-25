<?php
use App\Services\Formula\Formula;

$isNew = empty($concept['id']);
$title = $isNew ? 'Nuevo concepto' : 'Editar concepto';
$v = fn (string $k) => old($k, $concept[$k] ?? '');
$hints = [
    'basico'     => 'Toma el sueldo básico del empleado (de la escala de su categoría, o su básico propio). Si ingresó o egresó en el mes, se calcula proporcional a los días.',
    'fijo'       => 'Importe fijo en pesos. Con valor 0 funciona como "a informar": se carga el importe como novedad en cada liquidación.',
    'porcentaje' => 'Porcentaje sobre la base elegida. En haberes, "Total remunerativo" es lo acumulado hasta este concepto (según el orden).',
    'cantidad'   => 'Cantidad (de la novedad o asignación) multiplicada por el valor unitario.',
    'horas'      => 'Horas × (básico ÷ divisor de horas) × valor %. Ej: 150 para horas al 50%.',
    'dias'       => 'Días × (básico ÷ 30) × valor %. Usá un valor negativo (-100) para descontar inasistencias.',
    'antiguedad' => 'Valor % del básico por cada año completo de antigüedad.',
    'sac'        => 'Valor % (normalmente 50) de la mejor remuneración mensual del semestre, proporcional a los días trabajados.',
    'formula'    => 'El importe es el resultado de la fórmula. Podés usar VALOR para referirte al campo "Valor" (y a los valores propios asignados a cada empleado).',
];
$examples = [
    'Presentismo (se pierde con faltas)' => 'SI(C("140") = 0; REMUNERATIVO * 8,33 / 100; 0)',
    'Adicional por título (% en Valor)'  => 'BASICO * VALOR / 100',
    'Antigüedad con tope de 20 años'     => 'BASICO * MIN(ANTIGUEDAD; 20) / 100',
    'Horas extras al 50%'                => 'CANTIDAD * VALOR_HORA * 1,5',
    'Bono solo en diciembre'             => 'SI(MES = 12; VALOR; 0)',
    'Aporte con tope sobre la base'      => 'MIN(REMUNERATIVO; 3500000) * 11 / 100',
];
$typeColors = ['haber_rem' => 'success', 'haber_no_rem' => 'info', 'descuento' => 'danger', 'contribucion' => 'warning'];
?>
<div class="page-header">
    <h1><?= e($title) ?></h1>
    <a class="btn btn-outline-secondary" href="<?= url('concepts') ?>"><i class="bi bi-arrow-left me-1"></i>Volver</a>
</div>

<form method="post" id="conceptForm" action="<?= $isNew ? url('concepts/store') : url('concepts/update', ['id' => $concept['id']]) ?>" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= e($concept['id'] ?? '') ?>">
    <div class="row g-3">
        <div class="col-xl-7">
            <div class="card">
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

                    <div class="col-12 d-none" id="formulaGroup">
                        <label class="form-label" for="formula">Fórmula *</label>
                        <textarea class="form-control font-monospace<?= invalid('formula') ?>" id="formula" name="formula" rows="3" spellcheck="false"
                                  placeholder='Ej: SI(ANTIGUEDAD >= 5; BASICO * 0,05; 0)'><?= e($v('formula')) ?></textarea>
                        <?= field_error('formula') ?>
                        <div class="form-text">
                            Decimales con coma o punto · argumentos separados con <code>;</code> ·
                            <code>C("120")</code> trae el importe de otro concepto ya calculado.
                        </div>
                        <div class="dropdown mt-2">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown"><i class="bi bi-lightbulb me-1"></i>Ejemplos</button>
                            <ul class="dropdown-menu">
                                <?php foreach ($examples as $label => $example): ?>
                                    <li><button class="dropdown-item small" type="button" data-example="<?= e($example) ?>">
                                        <?= e($label) ?><div class="font-monospace text-body-secondary"><?= e($example) ?></div></button></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
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
        </div>

        <div class="col-xl-5">
            <div class="card mb-3" id="previewCard">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-eye me-1"></i>Vista previa en un recibo</span>
                    <span class="small text-body-secondary fw-normal">No guarda nada</span>
                </div>
                <div class="card-body">
                    <div class="row g-2 mb-2">
                        <div class="col-sm-7">
                            <select class="form-select form-select-sm" name="employee_id" id="previewEmployee" aria-label="Empleado">
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?= $emp['id'] ?>"><?= e($emp['last_name'] . ', ' . $emp['first_name'] . ' (' . $emp['file_number'] . ')') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-5">
                            <select class="form-select form-select-sm" name="period_id" id="previewPeriod" aria-label="Período">
                                <option value="">Mes actual (sin novedades)</option>
                                <?php foreach ($periods as $p): ?>
                                    <option value="<?= $p['id'] ?>"><?= e(period_label($p)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-7">
                            <input class="form-control form-control-sm" name="preview_quantity" id="previewQty" inputmode="decimal" placeholder="Cantidad de prueba (horas, días…)" aria-label="Cantidad de prueba">
                        </div>
                        <div class="col-sm-5 d-grid">
                            <button class="btn btn-sm btn-primary" type="button" id="previewBtn"><i class="bi bi-play-fill"></i> Probar</button>
                        </div>
                    </div>
                    <div id="previewResult" class="small text-body-secondary">
                        Elegí un empleado y un período y presioná <strong>Probar</strong> para ver cómo queda el recibo con este concepto.
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><i class="bi bi-book me-1"></i>Referencia para fórmulas</div>
                <div class="card-body small" style="max-height: 460px; overflow-y: auto">
                    <p class="text-body-secondary mb-2">Hacé clic para insertar en la fórmula.</p>
                    <h6 class="small text-uppercase text-body-secondary">Variables</h6>
                    <dl class="mb-3">
                        <?php foreach (Formula::VARIABLES as $name => $desc): ?>
                            <dt><button type="button" class="btn btn-link btn-sm p-0 font-monospace" data-insert="<?= $name ?>"><?= $name ?></button></dt>
                            <dd class="text-body-secondary mb-1"><?= e($desc) ?></dd>
                        <?php endforeach; ?>
                    </dl>
                    <h6 class="small text-uppercase text-body-secondary">Funciones</h6>
                    <dl class="mb-3">
                        <?php foreach (Formula::FUNCTIONS as $name => [, , $desc]): ?>
                            <dt><button type="button" class="btn btn-link btn-sm p-0 font-monospace" data-insert="<?= $name ?>("><?= $name ?>()</button></dt>
                            <dd class="text-body-secondary mb-1"><?= e($desc) ?></dd>
                        <?php endforeach; ?>
                        <dt class="font-monospace">+ - * / ^ &nbsp; = &lt;&gt; &lt; &lt;= &gt; &gt;= &nbsp; Y O NO</dt>
                        <dd class="text-body-secondary mb-1">Operadores. Las condiciones valen 1 (verdadero) o 0 (falso).</dd>
                    </dl>
                    <h6 class="small text-uppercase text-body-secondary">Conceptos (para C y CANT)</h6>
                    <p class="text-body-secondary mb-1">Se calculan en este orden; un concepto posterior vale 0.</p>
                    <?php foreach ($others as $o): ?>
                        <button type="button" class="btn btn-sm btn-light border mb-1 text-start" data-insert='C("<?= e($o['code']) ?>")' title="Orden <?= (int) $o['sort_order'] ?>">
                            <span class="badge bg-<?= $typeColors[$o['type']] ?>-subtle text-<?= $typeColors[$o['type']] ?>-emphasis"><?= e($o['code']) ?></span> <?= e($o['name']) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</form>

<?php ob_start(); ?>
<script>
(function () {
    var form = document.getElementById('conceptForm');
    var mode = document.getElementById('calc_mode');
    var formula = document.getElementById('formula');
    var group = document.getElementById('formulaGroup');

    function syncFormula() { group.classList.toggle('d-none', mode.value !== 'formula'); }
    mode.addEventListener('change', syncFormula);
    syncFormula();

    // Insertar en la posición del cursor
    function insert(text) {
        if (mode.value !== 'formula') { mode.value = 'formula'; mode.dispatchEvent(new Event('change')); }
        var start = formula.selectionStart, end = formula.selectionEnd, val = formula.value;
        var before = val.slice(0, start), after = val.slice(end);
        var pad = before && !/[\s(;]$/.test(before) ? ' ' : '';
        formula.value = before + pad + text + after;
        var pos = (before + pad + text).length;
        formula.focus();
        formula.setSelectionRange(pos, pos);
    }
    document.querySelectorAll('[data-insert]').forEach(function (b) {
        b.addEventListener('click', function () { insert(b.getAttribute('data-insert')); });
    });
    document.querySelectorAll('[data-example]').forEach(function (b) {
        b.addEventListener('click', function () { formula.value = b.getAttribute('data-example'); formula.focus(); });
    });

    // Vista previa
    var out = document.getElementById('previewResult');
    var btn = document.getElementById('previewBtn');
    function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
    function run() {
        btn.disabled = true;
        out.innerHTML = '<div class="text-body-secondary"><span class="spinner-border spinner-border-sm me-1"></span>Calculando…</div>';
        fetch(<?= json_encode(url('concepts/preview')) ?>, { method: 'POST', body: new FormData(form), headers: { 'Accept': 'application/json' } })
            .then(function (r) {
                if (!(r.headers.get('content-type') || '').includes('json')) throw new Error('La sesión expiró: recargá la página.');
                return r.json();
            })
            .then(function (d) {
                if (!d.ok) {
                    out.innerHTML = '<div class="alert alert-danger py-2 mb-0"><i class="bi bi-exclamation-triangle me-1"></i>' + esc(d.error) + '</div>';
                    return;
                }
                var rows = d.items.map(function (i) {
                    var employer = i.type === 'contribucion';
                    return '<tr class="' + (i.current ? 'table-primary fw-semibold' : (employer ? 'text-body-secondary' : '')) + '"><td>' + esc(i.code) + '</td><td>' + esc(i.name) +
                        (employer ? ' <span class="badge text-bg-light border">patronal</span>' : '') +
                        '</td><td class="num">' + esc(i.quantity) + '</td><td class="num">' + (i.type === 'descuento' ? '-' : '') + esc(i.amount) + '</td></tr>';
                }).join('');
                out.innerHTML =
                    '<div class="d-flex justify-content-between align-items-baseline mb-2">' +
                        '<div>' + esc(d.employee) + '<div class="text-body-secondary">' + esc(d.period) + ' · básico ' + esc(d.basic) + '</div></div>' +
                        '<div class="text-end"><div class="text-body-secondary">Este concepto</div><div class="fs-5 fw-bold text-body">' + esc(d.amount) + '</div></div>' +
                    '</div>' +
                    (d.included ? '' : '<div class="alert alert-warning py-1 px-2 mb-2">El resultado es 0, así que el concepto no aparece en el recibo.</div>') +
                    '<div class="table-responsive"><table class="table table-sm mb-2"><tbody>' + rows + '</tbody></table></div>' +
                    '<div class="d-flex justify-content-between text-body"><span>Descuentos ' + esc(d.totals.deductions) + '</span><strong>Neto ' + esc(d.totals.net) + '</strong></div>';
            })
            .catch(function (e) { out.innerHTML = '<div class="alert alert-danger py-2 mb-0">' + esc(e.message) + '</div>'; })
            .finally(function () { btn.disabled = false; });
    }
    btn.addEventListener('click', run);
    formula.addEventListener('keydown', function (e) { if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); run(); } });
})();
</script>
<?php $scripts = ob_get_clean(); ?>
