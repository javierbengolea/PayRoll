<?php
use App\Services\NumberToWords;

$copies = ['ORIGINAL', 'DUPLICADO'];
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · PayRoll</title>
    <link rel="stylesheet" href="<?= asset('vendor/bootstrap/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">
    <style>
        body { background: #e5e7eb; font-size: 12px; color: #111; }
        .toolbar { position: sticky; top: 0; z-index: 10; background: #111827; padding: .6rem 1rem; }
        .sheet { background: #fff; width: 210mm; margin: 1rem auto; padding: 10mm 12mm; box-shadow: 0 2px 8px rgba(0,0,0,.15); }
        .slip { border: 1.5px solid #111; margin-bottom: 8mm; }
        .slip:last-child { margin-bottom: 0; }
        .slip-head { display: grid; grid-template-columns: 1fr auto; border-bottom: 1.5px solid #111; }
        .slip-head > div { padding: 6px 10px; }
        .copy { font-weight: 700; letter-spacing: .1em; border-left: 1.5px solid #111; display: flex; align-items: center; justify-content: center; writing-mode: vertical-rl; transform: rotate(180deg); font-size: 10px; }
        .company { font-size: 15px; font-weight: 700; }
        .grid-info { display: grid; grid-template-columns: repeat(4, 1fr); border-bottom: 1.5px solid #111; }
        .grid-info > div { padding: 4px 8px; border-right: 1px solid #999; border-bottom: 1px solid #ccc; }
        .grid-info > div:nth-child(4n) { border-right: 0; }
        .lbl { display: block; font-size: 9px; text-transform: uppercase; color: #555; letter-spacing: .04em; }
        .val { font-weight: 600; }
        table.items { width: 100%; border-collapse: collapse; }
        table.items th { font-size: 9px; text-transform: uppercase; background: #f3f4f6; border-bottom: 1px solid #111; padding: 4px 6px; }
        table.items td { padding: 2px 6px; }
        table.items tbody tr:last-child td { padding-bottom: 8px; }
        table.items .n { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        table.items tfoot td { border-top: 1px solid #111; font-weight: 700; padding: 4px 6px; background: #f9fafb; }
        .net { display: grid; grid-template-columns: 1fr auto; border-top: 1.5px solid #111; }
        .net > div { padding: 6px 10px; }
        .net .amount { font-size: 16px; font-weight: 800; border-left: 1.5px solid #111; min-width: 45mm; text-align: right; }
        .foot { display: grid; grid-template-columns: 1fr 1fr; border-top: 1px solid #111; min-height: 22mm; }
        .foot > div { padding: 6px 10px; }
        .sign { border-left: 1px solid #111; display: flex; flex-direction: column; justify-content: flex-end; text-align: center; }
        .sign span { border-top: 1px solid #111; padding-top: 2px; font-size: 10px; }
        .draft { color: #b91c1c; font-weight: 700; }
        @media print {
            @page { size: A4; margin: 8mm; }
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { box-shadow: none; margin: 0; padding: 0; width: auto; page-break-after: always; }
            .sheet:last-child { page-break-after: auto; }
        }
        @media (max-width: 220mm) { .sheet { width: auto; margin: .5rem; padding: 4mm; } .grid-info { grid-template-columns: repeat(2, 1fr); } }
    </style>
</head>
<body>
<div class="toolbar d-flex gap-2">
    <a class="btn btn-sm btn-outline-light" href="<?= e($backUrl) ?>"><i class="bi bi-arrow-left"></i> Volver</a>
    <button class="btn btn-sm btn-light" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir / Guardar PDF</button>
    <span class="text-white-50 small align-self-center ms-2"><?= count($payslips) ?> recibo(s)</span>
</div>

<?php foreach ($payslips as $ps):
    $rem = array_filter($ps['items'], fn ($i) => $i['type'] === 'haber_rem');
    $noRem = array_filter($ps['items'], fn ($i) => $i['type'] === 'haber_no_rem');
    $ded = array_filter($ps['items'], fn ($i) => $i['type'] === 'descuento');
?>
<div class="sheet">
    <?php foreach ($copies as $copy): ?>
    <div class="slip">
        <div class="slip-head">
            <div>
                <div class="company"><?= e($company['company_name'] ?? '') ?></div>
                <div><?= e(implode(' · ', array_filter(['CUIT ' . fmt_cuil($company['company_cuit'] ?? ''), $company['company_address'] ?? '']))) ?></div>
                <div><?= e($company['company_activity'] ?? '') ?></div>
                <div class="mt-1"><strong>RECIBO DE HABERES</strong> — Ley 20.744 art. 140 · Período: <strong><?= e(period_label($ps)) ?></strong>
                    <?php if ($ps['status'] !== 'cerrada'): ?><span class="draft ms-2">[BORRADOR — liquidación no cerrada]</span><?php endif; ?></div>
            </div>
            <div class="copy"><?= $copy ?></div>
        </div>
        <div class="grid-info">
            <div><span class="lbl">Legajo</span><span class="val"><?= e($ps['file_number']) ?></span></div>
            <div style="grid-column: span 2"><span class="lbl">Apellido y nombre</span><span class="val"><?= e($ps['employee_name']) ?></span></div>
            <div><span class="lbl">CUIL</span><span class="val"><?= e(fmt_cuil($ps['cuil'])) ?></span></div>
            <div><span class="lbl">Fecha de ingreso</span><span class="val"><?= e(fmt_date($ps['hire_date'])) ?></span></div>
            <div><span class="lbl">Antigüedad</span><span class="val"><?= (int) $ps['seniority_years'] ?> años</span></div>
            <div><span class="lbl">Puesto / categoría</span><span class="val"><?= e($ps['position_name'] ?? '—') ?></span></div>
            <div><span class="lbl">Sueldo básico</span><span class="val"><?= money($ps['base_salary']) ?></span></div>
            <div><span class="lbl">Departamento</span><span class="val"><?= e($ps['department_name'] ?? '—') ?></span></div>
            <div><span class="lbl">Fecha de pago</span><span class="val"><?= e(fmt_date($ps['payment_date'])) ?: '—' ?></span></div>
            <div style="grid-column: span 2"><span class="lbl">Banco / CBU</span><span class="val"><?= e(trim(($ps['bank_name'] ?? '') . ' ' . ($ps['cbu'] ?? ''))) ?: 'Efectivo' ?></span></div>
        </div>
        <table class="items">
            <thead><tr><th style="width:9%">Cód.</th><th>Concepto</th><th class="n" style="width:10%">Cant.</th><th class="n" style="width:15%">Rem.</th><th class="n" style="width:15%">No rem.</th><th class="n" style="width:15%">Descuentos</th></tr></thead>
            <tbody>
            <?php foreach ([$rem, $noRem, $ded] as $group): foreach ($group as $it): ?>
                <tr>
                    <td><?= e($it['code']) ?></td>
                    <td><?= e($it['name']) ?></td>
                    <td class="n"><?= e(num($it['quantity'] ?? ($it['rate'] ?? null))) ?><?= $it['quantity'] === null && $it['rate'] !== null ? '%' : '' ?></td>
                    <td class="n"><?= $it['type'] === 'haber_rem' ? money($it['amount'], false) : '' ?></td>
                    <td class="n"><?= $it['type'] === 'haber_no_rem' ? money($it['amount'], false) : '' ?></td>
                    <td class="n"><?= $it['type'] === 'descuento' ? money($it['amount'], false) : '' ?></td>
                </tr>
            <?php endforeach; endforeach; ?>
            </tbody>
            <tfoot>
                <tr><td colspan="3">Totales</td><td class="n"><?= money($ps['gross_rem'], false) ?></td><td class="n"><?= money($ps['gross_no_rem'], false) ?></td><td class="n"><?= money($ps['deductions'], false) ?></td></tr>
            </tfoot>
        </table>
        <div class="net">
            <div><span class="lbl">Son</span><?= e(NumberToWords::money((float) $ps['net_pay'])) ?></div>
            <div class="amount"><span class="lbl text-start">Neto a cobrar</span><?= money($ps['net_pay']) ?></div>
        </div>
        <div class="foot">
            <div>
                <span class="lbl">Lugar y fecha de pago</span>
                <?= e(implode(', ', array_filter([$company['payment_place'] ?? '', fmt_date($ps['payment_date'])]))) ?: '—' ?>
                <div class="mt-2" style="font-size:10px">Recibí el importe neto de esta liquidación en pago de mis haberes correspondientes al período indicado y copia de este recibo.</div>
            </div>
            <div class="sign"><span><?= $copy === 'ORIGINAL' ? 'Firma del empleado' : 'Firma del empleador' ?></span></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endforeach; ?>
</body>
</html>
