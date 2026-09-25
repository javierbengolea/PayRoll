<?php
$title = 'Configuración';
$v = fn (string $k) => old($k, $settings[$k] ?? '');
?>
<div class="page-header">
    <div>
        <h1>Configuración</h1>
        <div class="subtitle">Datos de la empresa que aparecen en los recibos y parámetros de cálculo</div>
    </div>
</div>

<form method="post" action="<?= url('settings/save') ?>" novalidate>
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-building me-2"></i>Empresa</div>
                <div class="card-body row g-3">
                    <div class="col-md-8">
                        <label class="form-label" for="company_name">Razón social *</label>
                        <input class="form-control<?= invalid('company_name') ?>" id="company_name" name="company_name" value="<?= e($v('company_name')) ?>">
                        <?= field_error('company_name') ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="company_cuit">CUIT *</label>
                        <input class="form-control<?= invalid('company_cuit') ?>" id="company_cuit" name="company_cuit" value="<?= e(fmt_cuil($v('company_cuit'))) ?>">
                        <?= field_error('company_cuit') ?>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="company_address">Domicilio</label>
                        <input class="form-control" id="company_address" name="company_address" value="<?= e($v('company_address')) ?>">
                    </div>
                    <div class="col-md-7">
                        <label class="form-label" for="company_activity">Actividad</label>
                        <input class="form-control" id="company_activity" name="company_activity" value="<?= e($v('company_activity')) ?>">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label" for="payment_place">Lugar de pago</label>
                        <input class="form-control" id="payment_place" name="payment_place" value="<?= e($v('payment_place')) ?>">
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-sliders me-2"></i>Parámetros de cálculo</div>
                <div class="card-body">
                    <label class="form-label" for="hours_divisor">Divisor para el valor hora *</label>
                    <input class="form-control<?= invalid('hours_divisor') ?>" type="number" id="hours_divisor" name="hours_divisor" value="<?= e($v('hours_divisor')) ?>" min="1" max="400">
                    <?= field_error('hours_divisor') ?>
                    <div class="form-text">Valor hora = básico ÷ divisor. Habitualmente 200 (jornada de 48 hs semanales).</div>
                    <hr>
                    <p class="small text-body-secondary mb-0">
                        Las alícuotas de aportes, contribuciones, presentismo y antigüedad se configuran en
                        <a href="<?= url('concepts') ?>">Conceptos</a>.
                    </p>
                </div>
            </div>
        </div>
    </div>
    <div class="d-flex justify-content-end mt-3">
        <button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Guardar configuración</button>
    </div>
</form>
