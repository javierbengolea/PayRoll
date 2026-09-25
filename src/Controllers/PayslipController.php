<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\View;
use App\Repositories\Settings;

final class PayslipController extends Controller
{
    /** Recibo individual (vista imprimible). */
    public function show(): void
    {
        $payslip = $this->db->one(
            'SELECT ps.*, pp.year, pp.month, pp.type, pp.payment_date, pp.status, pp.description AS period_description
               FROM payslips ps JOIN payroll_periods pp ON pp.id = ps.period_id WHERE ps.id = ?',
            [$this->intParam('id')]
        ) ?? $this->notFound();

        View::render('payslips/print', [
            'payslips' => [$this->withItems($payslip)],
            'company'  => Settings::all(),
            'title'    => 'Recibo ' . $payslip['employee_name'],
            'backUrl'  => url('periods/show', ['id' => $payslip['period_id']]),
        ], null);
    }

    /** Todos los recibos de una liquidación, para imprimir en lote. */
    public function printPeriod(): void
    {
        $period = $this->db->one('SELECT * FROM payroll_periods WHERE id = ?', [$this->intParam('id')]) ?? $this->notFound();
        $payslips = $this->db->all(
            'SELECT ps.*, pp.year, pp.month, pp.type, pp.payment_date, pp.status, pp.description AS period_description
               FROM payslips ps JOIN payroll_periods pp ON pp.id = ps.period_id
              WHERE ps.period_id = ? ORDER BY ps.employee_name',
            [$period['id']]
        );
        View::render('payslips/print', [
            'payslips' => array_map([$this, 'withItems'], $payslips),
            'company'  => Settings::all(),
            'title'    => 'Recibos ' . period_label($period),
            'backUrl'  => url('periods/show', ['id' => $period['id']]),
        ], null);
    }

    private function withItems(array $payslip): array
    {
        $payslip['items'] = $this->db->all(
            "SELECT * FROM payslip_items WHERE payslip_id = ? AND type <> 'contribucion'
           ORDER BY FIELD(type, 'haber_rem', 'haber_no_rem', 'descuento'), sort_order, code",
            [$payslip['id']]
        );
        return $payslip;
    }
}
