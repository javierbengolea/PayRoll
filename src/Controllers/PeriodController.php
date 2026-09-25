<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Controller;
use App\Core\Validator;
use App\Services\CsvExporter;
use App\Services\PayrollService;
use RuntimeException;

final class PeriodController extends Controller
{
    public function index(): void
    {
        $periods = $this->db->all(
            "SELECT pp.*, COUNT(ps.id) AS payslips, COALESCE(SUM(ps.net_pay), 0) AS net,
                    COALESCE(SUM(ps.gross_rem + ps.gross_no_rem), 0) AS gross,
                    COALESCE(SUM(ps.employer_contrib), 0) AS contrib,
                    (SELECT COUNT(*) FROM novelties n WHERE n.period_id = pp.id) AS novelties
               FROM payroll_periods pp LEFT JOIN payslips ps ON ps.period_id = pp.id
           GROUP BY pp.id ORDER BY pp.year DESC, pp.month DESC, pp.type DESC"
        );
        $suggested = $this->suggestNextPeriod();
        $this->render('periods/index', compact('periods', 'suggested'));
    }

    public function store(): void
    {
        $v = Validator::make($_POST, [
            'year'         => 'required|integer|min:2000|max:2100',
            'month'        => 'required|integer|min:1|max:12',
            'type'         => 'required|in:mensual,sac',
            'payment_date' => 'date',
        ], ['year' => 'Año', 'month' => 'Mes', 'type' => 'Tipo', 'payment_date' => 'Fecha de pago']);
        if ($v->fails()) {
            $this->flash('danger', implode(' ', $v->errors()));
            $this->redirect('periods');
        }

        $year = (int) $_POST['year'];
        $type = $_POST['type'];
        $month = $type === 'sac' ? ((int) $_POST['month'] <= 6 ? 6 : 12) : (int) $_POST['month'];

        if ($this->db->value('SELECT id FROM payroll_periods WHERE year = ? AND month = ? AND type = ?', [$year, $month, $type])) {
            $this->flash('warning', 'Ya existe esa liquidación.');
            $this->redirect('periods');
        }

        $period = ['year' => $year, 'month' => $month, 'type' => $type];
        $description = trim((string) $this->input('description', '')) ?: ($type === 'sac' ? period_label($period) : 'Sueldos ' . period_label($period));
        $id = $this->db->insert('payroll_periods', $period + [
            'description'  => mb_substr($description, 0, 160),
            'payment_date' => $this->input('payment_date') ?: null,
            'created_by'   => \App\Core\Auth::id(),
        ]);
        Audit::log('crear', 'period', $id, $description);
        $this->flash('success', 'Liquidación creada. Cargá las novedades y luego calculá.');
        $this->redirect('periods/show', ['id' => $id]);
    }

    public function show(): void
    {
        $period = $this->findOrFail($this->intParam('id'));
        $tab = $_GET['tab'] ?? 'payslips';

        $payslips = $this->db->all(
            'SELECT ps.* FROM payslips ps WHERE ps.period_id = ? ORDER BY ps.employee_name',
            [$period['id']]
        );
        $totals = [
            'gross_rem'        => array_sum(array_column($payslips, 'gross_rem')),
            'gross_no_rem'     => array_sum(array_column($payslips, 'gross_no_rem')),
            'deductions'       => array_sum(array_column($payslips, 'deductions')),
            'net_pay'          => array_sum(array_column($payslips, 'net_pay')),
            'employer_contrib' => array_sum(array_column($payslips, 'employer_contrib')),
        ];

        $novelties = $this->db->all(
            'SELECT n.*, e.file_number, e.last_name, e.first_name, c.code, c.name AS concept_name, c.calc_mode, c.type
               FROM novelties n
               JOIN employees e ON e.id = n.employee_id
               JOIN concepts c ON c.id = n.concept_id
              WHERE n.period_id = ? ORDER BY e.last_name, e.first_name, c.sort_order',
            [$period['id']]
        );

        $byConcept = $this->db->all(
            "SELECT pi.code, pi.name, pi.type, COUNT(*) AS employees, SUM(pi.amount) AS total
               FROM payslip_items pi JOIN payslips ps ON ps.id = pi.payslip_id
              WHERE ps.period_id = ?
           GROUP BY pi.code, pi.name, pi.type
           ORDER BY FIELD(pi.type, 'haber_rem', 'haber_no_rem', 'descuento', 'contribucion'), MIN(pi.sort_order)",
            [$period['id']]
        );

        $service = new PayrollService($this->db);
        $employees = $service->eligibleEmployees($period);
        $concepts = $this->db->all(
            "SELECT id, code, name, type, calc_mode FROM concepts WHERE active = 1 AND calc_mode NOT IN ('basico','sac')
           ORDER BY sort_order"
        );

        $this->render('periods/show', compact('period', 'tab', 'payslips', 'totals', 'novelties', 'byConcept', 'employees', 'concepts'));
    }

    public function calculate(): void
    {
        $id = $this->intParam('id');
        try {
            $count = (new PayrollService($this->db))->calculate($id);
            Audit::log('calcular', 'period', $id, "$count recibos");
            $this->flash('success', "Liquidación calculada: se generaron $count recibos.");
        } catch (RuntimeException $e) {
            $this->flash('danger', $e->getMessage());
        }
        $this->redirect('periods/show', ['id' => $id]);
    }

    public function close(): void
    {
        $id = $this->intParam('id');
        try {
            (new PayrollService($this->db))->close($id);
            Audit::log('cerrar', 'period', $id);
            $this->flash('success', 'Liquidación cerrada. Los recibos quedaron definitivos.');
        } catch (RuntimeException $e) {
            $this->flash('danger', $e->getMessage());
        }
        $this->redirect('periods/show', ['id' => $id]);
    }

    public function reopen(): void
    {
        $id = $this->intParam('id');
        try {
            (new PayrollService($this->db))->reopen($id);
            Audit::log('reabrir', 'period', $id);
            $this->flash('warning', 'Liquidación reabierta. Recordá volver a cerrarla.');
        } catch (RuntimeException $e) {
            $this->flash('danger', $e->getMessage());
        }
        $this->redirect('periods/show', ['id' => $id]);
    }

    public function delete(): void
    {
        $period = $this->findOrFail($this->intParam('id'));
        if ($period['status'] === 'cerrada') {
            $this->flash('danger', 'No se puede eliminar una liquidación cerrada.');
            $this->redirect('periods/show', ['id' => $period['id']]);
        }
        $this->db->run('DELETE FROM payroll_periods WHERE id = ?', [$period['id']]);
        Audit::log('eliminar', 'period', (int) $period['id'], $period['description']);
        $this->flash('success', 'Liquidación eliminada.');
        $this->redirect('periods');
    }

    public function addNovelty(): void
    {
        $period = $this->findOrFail($this->intParam('id'));
        $this->assertEditable($period);

        $v = Validator::make($_POST, [
            'employee_id' => 'required|integer',
            'concept_id'  => 'required|integer',
            'quantity'    => 'numeric',
            'amount'      => 'numeric',
            'note'        => 'maxlen:255',
        ], ['employee_id' => 'Empleado', 'concept_id' => 'Concepto', 'quantity' => 'Cantidad', 'amount' => 'Importe', 'note' => 'Observación']);

        $qty = trim((string) ($_POST['quantity'] ?? ''));
        $amount = trim((string) ($_POST['amount'] ?? ''));
        if ($qty === '' && $amount === '') {
            $v->addError('quantity', 'Indicá una cantidad o un importe.');
        }
        if ($v->fails()) {
            $this->flash('danger', implode(' ', $v->errors()));
            $this->redirect('periods/show', ['id' => $period['id'], 'tab' => 'novelties']);
        }

        $this->db->insert('novelties', [
            'period_id'   => $period['id'],
            'employee_id' => (int) $_POST['employee_id'],
            'concept_id'  => (int) $_POST['concept_id'],
            'quantity'    => $qty !== '' ? Validator::normalizeNumber($qty) : null,
            'amount'      => $amount !== '' ? Validator::normalizeNumber($amount) : null,
            'note'        => $this->input('note') ?: null,
        ]);
        $this->markDirty($period);
        Audit::log('agregar_novedad', 'period', (int) $period['id']);
        $this->flash('success', 'Novedad registrada.');
        $this->redirect('periods/show', ['id' => $period['id'], 'tab' => 'novelties']);
    }

    public function deleteNovelty(): void
    {
        $period = $this->findOrFail($this->intParam('id'));
        $this->assertEditable($period);
        $this->db->run('DELETE FROM novelties WHERE id = ? AND period_id = ?', [(int) $this->input('novelty_id'), $period['id']]);
        $this->markDirty($period);
        Audit::log('eliminar_novedad', 'period', (int) $period['id']);
        $this->flash('success', 'Novedad eliminada.');
        $this->redirect('periods/show', ['id' => $period['id'], 'tab' => 'novelties']);
    }

    /** Libro de sueldos: un renglón por empleado con cada concepto en columnas. */
    public function export(): void
    {
        $period = $this->findOrFail($this->intParam('id'));
        $payslips = $this->db->all('SELECT * FROM payslips WHERE period_id = ? ORDER BY employee_name', [$period['id']]);
        $items = $this->db->all(
            'SELECT pi.payslip_id, pi.code, pi.name, pi.amount, pi.type, pi.sort_order
               FROM payslip_items pi JOIN payslips ps ON ps.id = pi.payslip_id WHERE ps.period_id = ?',
            [$period['id']]
        );

        $columns = [];
        $matrix = [];
        foreach ($items as $it) {
            $columns[$it['code']] ??= ['name' => $it['code'] . ' ' . $it['name'], 'order' => [$it['type'] === 'contribucion' ? 2 : ($it['type'] === 'descuento' ? 1 : 0), (int) $it['sort_order']]];
            $matrix[$it['payslip_id']][$it['code']] = ($matrix[$it['payslip_id']][$it['code']] ?? 0) + (float) $it['amount'];
        }
        uasort($columns, fn ($a, $b) => $a['order'] <=> $b['order']);

        $headers = array_merge(['Legajo', 'Empleado', 'CUIL', 'Departamento', 'Puesto'], array_column($columns, 'name'),
            ['Total remunerativo', 'Total no remunerativo', 'Descuentos', 'Neto', 'Contribuciones']);
        $rows = [];
        foreach ($payslips as $ps) {
            $row = [$ps['file_number'], $ps['employee_name'], fmt_cuil($ps['cuil']), $ps['department_name'], $ps['position_name']];
            foreach (array_keys($columns) as $code) {
                $row[] = isset($matrix[$ps['id']][$code]) ? money($matrix[$ps['id']][$code], false) : '';
            }
            $rows[] = array_merge($row, [money($ps['gross_rem'], false), money($ps['gross_no_rem'], false),
                money($ps['deductions'], false), money($ps['net_pay'], false), money($ps['employer_contrib'], false)]);
        }
        Audit::log('exportar', 'period', (int) $period['id'], 'Libro de sueldos');
        CsvExporter::download(sprintf('libro_sueldos_%04d_%02d_%s.csv', $period['year'], $period['month'], $period['type']), $headers, $rows);
    }

    /** Archivo para acreditar sueldos por transferencia bancaria. */
    public function bankFile(): void
    {
        $period = $this->findOrFail($this->intParam('id'));
        $rows = $this->db->all(
            'SELECT cbu, cuil, employee_name, net_pay, file_number FROM payslips WHERE period_id = ? AND net_pay > 0 ORDER BY employee_name',
            [$period['id']]
        );
        Audit::log('exportar', 'period', (int) $period['id'], 'Archivo bancario');
        CsvExporter::download(sprintf('transferencias_%04d_%02d_%s.csv', $period['year'], $period['month'], $period['type']),
            ['CBU', 'CUIL', 'Beneficiario', 'Importe', 'Referencia'],
            array_map(fn ($r) => [
                $r['cbu'] ? "'" . $r['cbu'] : 'SIN CBU',
                $r['cuil'],
                $r['employee_name'],
                number_format((float) $r['net_pay'], 2, '.', ''),
                'HABERES ' . mb_strtoupper(period_label($period)) . ' LEG ' . $r['file_number'],
            ], $rows));
    }

    // ------------------------------------------------------------------

    private function findOrFail(int $id): array
    {
        return $this->db->one('SELECT * FROM payroll_periods WHERE id = ?', [$id]) ?? $this->notFound();
    }

    private function assertEditable(array $period): void
    {
        if ($period['status'] === 'cerrada') {
            $this->flash('danger', 'La liquidación está cerrada: no se pueden modificar novedades.');
            $this->redirect('periods/show', ['id' => $period['id']]);
        }
    }

    /** Si cambian las novedades de una liquidación calculada, vuelve a borrador para forzar el recálculo. */
    private function markDirty(array $period): void
    {
        if ($period['status'] === 'calculada') {
            $this->db->update('payroll_periods', ['status' => 'borrador'], 'id = :id', ['id' => $period['id']]);
            $this->flash('info', 'La liquidación volvió a borrador: recalculá para aplicar los cambios.');
        }
    }

    private function suggestNextPeriod(): array
    {
        $last = $this->db->one("SELECT year, month FROM payroll_periods WHERE type = 'mensual' ORDER BY year DESC, month DESC LIMIT 1");
        if ($last === null) {
            return ['year' => (int) date('Y'), 'month' => (int) date('n')];
        }
        $month = (int) $last['month'] + 1;
        $year = (int) $last['year'];
        if ($month > 12) {
            $month = 1;
            $year++;
        }
        return ['year' => $year, 'month' => $month];
    }
}
