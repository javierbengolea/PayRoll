<?php
$title = 'Categorías y escalas';
$today = date('Y-m-d');
$future = array_values(array_filter($scales, fn ($d) => $d > $today));
?>
<div class="page-header">
    <div>
        <h1>Categorías y escalas salariales</h1>
        <div class="subtitle">Convenios colectivos, categorías y sueldos básicos con fecha de vigencia</div>
    </div>
    <?php if (can('rrhh')): ?>
        <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#agreementModal" data-fill='{"active":1}' data-title="Nuevo convenio">
            <i class="bi bi-plus-lg me-1"></i>Nuevo convenio
        </button>
    <?php endif; ?>
</div>

<?php if (!$agreements): ?>
    <div class="card"><div class="empty-state">
        <i class="bi bi-journal-bookmark"></i>
        Todavía no hay convenios cargados.<br>Creá uno (por ejemplo, «CCT 130/75 — Empleados de Comercio») y después sus categorías.
    </div></div>
<?php else: ?>

<ul class="nav nav-pills mb-3 gap-1">
    <?php foreach ($agreements as $a): ?>
        <li class="nav-item">
            <a class="nav-link<?= $agreement && (int) $a['id'] === (int) $agreement['id'] ? ' active' : '' ?><?= $a['active'] ? '' : ' text-decoration-line-through' ?>"
               href="<?= url('categories', ['agreement' => $a['id']]) ?>">
                <?= e($a['code']) ?> <span class="badge rounded-pill text-bg-light border ms-1"><?= (int) $a['employees'] ?></span>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<?php if ($agreement): ?>
<div class="card mb-3">
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h2 class="h5 mb-0"><?= e($agreement['name']) ?> <span class="text-body-secondary fw-normal">· <?= e($agreement['code']) ?></span>
                <?php if (!$agreement['active']): ?><span class="badge text-bg-secondary">Inactivo</span><?php endif; ?></h2>
            <?php if ($agreement['description']): ?><div class="small text-body-secondary"><?= e($agreement['description']) ?></div><?php endif; ?>
            <?php if ($future): ?>
                <div class="small text-info-emphasis mt-1"><i class="bi bi-calendar-event me-1"></i>Hay escalas programadas a futuro: <?= e(implode(', ', array_map('fmt_date', array_reverse($future)))) ?></div>
            <?php endif; ?>
        </div>
        <?php if (can('rrhh')): ?>
            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#agreementModal" data-title="Editar convenio"
                        data-fill='<?= e(json_encode(['id' => $agreement['id'], 'code' => $agreement['code'], 'name' => $agreement['name'], 'description' => $agreement['description'], 'active' => $agreement['active']])) ?>'>
                    <i class="bi bi-pencil"></i> Convenio
                </button>
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#categoryModal" data-title="Nueva categoría"
                        data-fill='<?= e(json_encode(['agreement_id' => $agreement['id'], 'active' => 1, 'sort_order' => (count($categories) + 1) * 10, 'valid_from' => date('Y-m-01')])) ?>'>
                    <i class="bi bi-plus-lg"></i> Categoría
                </button>
                <?php if ($categories): ?>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#scaleModal">
                        <i class="bi bi-graph-up-arrow"></i> Nueva escala (paritaria)
                    </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">Categorías · básicos vigentes hoy</div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Código</th><th>Categoría</th><th class="num">Básico vigente</th><th>Desde</th><th class="num">Empleados</th><th>Estado</th><?php if (can('rrhh')): ?><th></th><?php endif; ?></tr></thead>
            <tbody>
            <?php foreach ($categories as $c): ?>
                <tr class="<?= $c['active'] ? '' : 'opacity-50' ?>">
                    <td class="fw-semibold"><?= e($c['code']) ?></td>
                    <td><?= e($c['name']) ?></td>
                    <td class="num fw-semibold"><?= $c['current_salary'] !== null ? money($c['current_salary']) : '<span class="text-body-secondary fw-normal">Sin escala vigente</span>' ?></td>
                    <td class="small text-body-secondary"><?= e(fmt_date($c['current_from'])) ?></td>
                    <td class="num"><?php if ($c['employees']): ?><a href="<?= url('employees', ['category' => $c['id']]) ?>"><?= (int) $c['employees'] ?></a><?php else: ?>0<?php endif; ?></td>
                    <td><?= $c['active'] ? '<span class="badge text-bg-success">Activa</span>' : '<span class="badge text-bg-secondary">Inactiva</span>' ?></td>
                    <?php if (can('rrhh')): ?>
                        <td class="text-end text-nowrap">
                            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#categoryModal" data-title="Editar categoría"
                                    data-fill='<?= e(json_encode(['id' => $c['id'], 'agreement_id' => $c['agreement_id'], 'code' => $c['code'], 'name' => $c['name'], 'sort_order' => $c['sort_order'], 'active' => $c['active']])) ?>'><i class="bi bi-pencil"></i></button>
                            <form class="d-inline" method="post" action="<?= url('categories/delete') ?>" data-confirm="¿Eliminar la categoría <?= e($c['name']) ?> y su historial de básicos?">
                                <?= csrf_field() ?><input type="hidden" name="id" value="<?= $c['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (!$categories): ?>
            <div class="empty-state"><i class="bi bi-layers"></i>Este convenio todavía no tiene categorías.</div>
        <?php endif; ?>
    </div>
</div>

<?php if ($categories && $shownScales): ?>
<div class="card">
    <div class="card-header d-flex justify-content-between">
        <span>Historial de escalas</span>
        <span class="small text-body-secondary fw-normal"><?= count($scales) > count($shownScales) ? 'Últimas ' . count($shownScales) . ' de ' . count($scales) . ' vigencias' : count($scales) . ' vigencia(s)' ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead>
            <tr>
                <th>Categoría</th>
                <?php foreach ($shownScales as $date): ?>
                    <th class="num">
                        <?= e(fmt_date($date)) ?><?= $date > $today ? ' <span class="badge text-bg-info">futura</span>' : '' ?>
                        <?php if (can('rrhh')): ?>
                            <form class="d-inline" method="post" action="<?= url('categories/scale-del') ?>" data-confirm="¿Eliminar todos los básicos con vigencia <?= e(fmt_date($date)) ?>?">
                                <?= csrf_field() ?><input type="hidden" name="agreement_id" value="<?= $agreement['id'] ?>"><input type="hidden" name="valid_from" value="<?= e($date) ?>">
                                <button class="btn btn-link btn-sm p-0 text-danger" title="Eliminar esta escala"><i class="bi bi-x-circle"></i></button>
                            </form>
                        <?php endif; ?>
                    </th>
                <?php endforeach; ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($categories as $c): $prev = null; ?>
                <tr>
                    <td><span class="text-body-secondary"><?= e($c['code']) ?></span> <?= e($c['name']) ?></td>
                    <?php foreach ($shownScales as $date):
                        $value = $history[$c['id']][$date] ?? null;
                        // valor anterior aunque no esté entre las columnas mostradas
                        if ($prev === null) {
                            foreach (array_reverse($history[$c['id']] ?? [], true) as $d => $v) {
                                if ($d < $date) { $prev = $v; break; }
                            }
                        } ?>
                        <td class="num">
                            <?php if ($value !== null): ?>
                                <?= money($value, false) ?>
                                <?php if ($prev): $pct = ($value / $prev - 1) * 100; ?>
                                    <div class="small <?= $pct >= 0 ? 'text-success' : 'text-danger' ?>"><?= $pct >= 0 ? '+' : '' ?><?= num($pct, 1) ?>%</div>
                                <?php endif; $prev = $value; ?>
                            <?php else: ?>
                                <span class="text-body-tertiary">—</span>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>
<?php endif; ?>

<?php if (can('rrhh')): ?>
<div class="modal fade" id="agreementModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="post" action="<?= url('agreements/save') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="id">
            <div class="modal-header"><h5 class="modal-title">Convenio</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body row g-3">
                <div class="col-4"><label class="form-label">Código *</label><input class="form-control" name="code" required maxlength="20" placeholder="130/75"></div>
                <div class="col-8"><label class="form-label">Nombre *</label><input class="form-control" name="name" required maxlength="160" placeholder="Empleados de Comercio"></div>
                <div class="col-12"><label class="form-label">Descripción</label><input class="form-control" name="description" maxlength="255"></div>
                <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="active" value="1" id="agrActive" checked><label class="form-check-label" for="agrActive">Activo</label></div></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">Guardar</button></div>
        </form>
    </div>
</div>

<?php if ($agreement): ?>
<div class="modal fade" id="categoryModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="post" action="<?= url('categories/save') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="id">
            <input type="hidden" name="agreement_id" value="<?= $agreement['id'] ?>">
            <div class="modal-header"><h5 class="modal-title">Categoría</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body row g-3">
                <div class="col-4"><label class="form-label">Código *</label><input class="form-control" name="code" required maxlength="20" placeholder="ADM-A"></div>
                <div class="col-8"><label class="form-label">Nombre *</label><input class="form-control" name="name" required maxlength="120" placeholder="Administrativo A"></div>
                <div class="col-4"><label class="form-label">Orden</label><input class="form-control" type="number" name="sort_order"></div>
                <div class="col-8 d-flex align-items-end"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="active" value="1" id="catActive" checked><label class="form-check-label" for="catActive">Activa</label></div></div>
                <div class="col-12 new-only">
                    <div class="border rounded p-3 bg-body-tertiary">
                        <div class="small fw-semibold mb-2">Básico inicial</div>
                        <div class="row g-2">
                            <div class="col-7"><div class="input-group"><span class="input-group-text">$</span><input class="form-control" name="base_salary" inputmode="decimal" aria-label="Sueldo básico"></div></div>
                            <div class="col-5"><input class="form-control" type="date" name="valid_from" aria-label="Vigencia desde"></div>
                        </div>
                        <div class="form-text">Los cambios posteriores se cargan con «Nueva escala».</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">Guardar</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="scaleModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form class="modal-content" method="post" action="<?= url('categories/scale') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="agreement_id" value="<?= $agreement['id'] ?>">
            <div class="modal-header"><h5 class="modal-title">Nueva escala salarial · <?= e($agreement['code']) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row g-3 align-items-end mb-3">
                    <div class="col-sm-4">
                        <label class="form-label" for="scaleDate">Vigencia desde *</label>
                        <input class="form-control" type="date" id="scaleDate" name="valid_from" value="<?= date('Y-m-01', strtotime('first day of next month')) ?>" required>
                    </div>
                    <div class="col-sm-5">
                        <label class="form-label" for="scalePct">Aumento sobre el básico vigente</label>
                        <div class="input-group">
                            <input class="form-control" id="scalePct" inputmode="decimal" placeholder="Ej: 4,5">
                            <span class="input-group-text">%</span>
                            <button class="btn btn-outline-primary" type="button" id="applyPct">Aplicar a todas</button>
                        </div>
                    </div>
                    <div class="col-sm-3 form-text">Podés ajustar cada importe a mano. Las liquidaciones de meses anteriores a la vigencia no cambian.</div>
                </div>
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Categoría</th><th class="num">Vigente</th><th style="width: 40%">Nuevo básico</th></tr></thead>
                    <tbody>
                    <?php foreach ($categories as $c): if (!$c['active']) continue; ?>
                        <tr>
                            <td><span class="text-body-secondary"><?= e($c['code']) ?></span> <?= e($c['name']) ?></td>
                            <td class="num"><?= $c['current_salary'] !== null ? money($c['current_salary']) : '—' ?></td>
                            <td>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">$</span>
                                    <input class="form-control text-end scale-input" name="salaries[<?= $c['id'] ?>]" inputmode="decimal"
                                           data-current="<?= e((string) ($c['current_salary'] ?? '')) ?>" aria-label="Nuevo básico <?= e($c['name']) ?>">
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">Guardar escala</button></div>
        </form>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<?php ob_start(); ?>
<script>
(function () {
    // En el modal de categoría, el básico inicial solo se pide al crear
    var catModal = document.getElementById('categoryModal');
    if (catModal) {
        catModal.addEventListener('show.bs.modal', function () {
            setTimeout(function () {
                var isNew = !catModal.querySelector('input[name=id]').value;
                catModal.querySelectorAll('.new-only').forEach(function (el) { el.classList.toggle('d-none', !isNew); });
            }, 0);
        });
    }
    // Aplicar un % de aumento a todos los básicos vigentes
    var btn = document.getElementById('applyPct');
    if (btn) {
        btn.addEventListener('click', function () {
            var pct = parseFloat((document.getElementById('scalePct').value || '0').replace(',', '.'));
            if (isNaN(pct)) return;
            document.querySelectorAll('.scale-input').forEach(function (input) {
                var current = parseFloat(input.getAttribute('data-current'));
                if (isNaN(current)) return;
                var value = Math.round(current * (1 + pct / 100) * 100) / 100;
                input.value = value.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            });
        });
    }
})();
</script>
<?php $scripts = ob_get_clean(); ?>
