<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Repositories\Categories;
use App\Repositories\Settings;
use App\Services\Formula\FormulaException;
use RuntimeException;

/**
 * Orquesta la liquidación de un período: reúne empleados, conceptos y novedades,
 * delega el cálculo en PayrollCalculator y guarda los recibos.
 */
final class PayrollService
{
    public function __construct(private Database $db)
    {
    }

    public function findPeriod(int $periodId): array
    {
        $period = $this->db->one('SELECT * FROM payroll_periods WHERE id = ?', [$periodId]);
        if ($period === null) {
            throw new RuntimeException('La liquidación no existe.');
        }
        return $period;
    }

    /**
     * Empleados alcanzados por el período: ingresados antes del fin de mes y sin baja previa al inicio.
     * El básico se toma de la escala de su categoría vigente al fin del período.
     */
    public function eligibleEmployees(array $period, ?int $employeeId = null): array
    {
        [$from, $to] = $this->periodRange($period);
        $params = ['from' => $from, 'to' => $to, 'salary_at' => $to];
        $onlyOne = '';
        if ($employeeId !== null) {
            $onlyOne = 'AND e.id = :emp';
            $params['emp'] = $employeeId;
        }
        return $this->db->all(
            'SELECT e.*, ' . Categories::effectiveSalary(':salary_at') . " AS effective_salary,
                    p.name AS position_name, d.name AS department_name,
                    CONCAT(c.name, ' (', a.code, ')') AS category_name
               FROM employees e
          LEFT JOIN positions p   ON p.id = e.position_id
          LEFT JOIN departments d ON d.id = e.department_id
          LEFT JOIN categories c  ON c.id = e.category_id
          LEFT JOIN agreements a  ON a.id = c.agreement_id
              WHERE e.hire_date <= :to
                AND (e.termination_date IS NULL OR e.termination_date >= :from)
                AND (e.status <> 'baja' OR e.termination_date IS NOT NULL)
                $onlyOne
           ORDER BY e.last_name, e.first_name",
            $params
        );
    }

    /**
     * Calcula (o recalcula) todos los recibos de la liquidación.
     * @return int cantidad de recibos generados
     */
    public function calculate(int $periodId): int
    {
        $period = $this->findPeriod($periodId);
        if ($period['status'] === 'cerrada') {
            throw new RuntimeException('La liquidación está cerrada y no puede recalcularse.');
        }

        $data = $this->loadConcepts($period, $periodId);
        $employees = $this->eligibleEmployees($period);

        return $this->db->transaction(function (Database $db) use ($period, $periodId, $data, $employees) {
            $db->run('DELETE FROM payslips WHERE period_id = ?', [$periodId]);
            $count = 0;

            foreach ($employees as $emp) {
                $result = $this->calculateEmployee($emp, $period, $data);
                if ($result['items'] === []) {
                    continue;
                }

                $payslipId = $db->insert('payslips', [
                    'period_id'        => $periodId,
                    'employee_id'      => (int) $emp['id'],
                    'file_number'      => $emp['file_number'],
                    'employee_name'    => $emp['last_name'] . ', ' . $emp['first_name'],
                    'cuil'             => $emp['cuil'],
                    'department_name'  => $emp['department_name'],
                    'position_name'    => $emp['position_name'],
                    'category_name'    => $emp['category_name'],
                    'hire_date'        => $emp['hire_date'],
                    'base_salary'      => $emp['effective_salary'],
                    'seniority_years'  => $result['seniority_years'],
                    'bank_name'        => $emp['bank_name'],
                    'cbu'              => $emp['cbu'],
                    'gross_rem'        => $result['gross_rem'],
                    'gross_no_rem'     => $result['gross_no_rem'],
                    'deductions'       => $result['deductions'],
                    'net_pay'          => $result['net_pay'],
                    'employer_contrib' => $result['employer_contrib'],
                ]);
                foreach ($result['items'] as $item) {
                    $db->insert('payslip_items', ['payslip_id' => $payslipId] + $item);
                }
                $count++;
            }

            $db->update('payroll_periods', [
                'status'        => 'calculada',
                'calculated_at' => date('Y-m-d H:i:s'),
            ], 'id = :id', ['id' => $periodId]);

            return $count;
        });
    }

    /**
     * Calcula el recibo de un empleado sin guardar nada. Si se pasa $override, ese concepto
     * (por ejemplo, uno que se está editando) reemplaza al guardado y se incluye siempre.
     * Sirve para la vista previa de fórmulas.
     */
    public function simulate(array $period, int $employeeId, ?array $override = null): array
    {
        $employee = $this->eligibleEmployees($period, $employeeId)[0] ?? null;
        if ($employee === null) {
            throw new RuntimeException('El empleado no está alcanzado por ese período (fecha de ingreso o egreso).');
        }
        $data = $this->loadConcepts($period, isset($period['id']) ? (int) $period['id'] : null, $employeeId);
        return $this->calculateEmployee($employee, $period, $data, $override) + ['employee' => $employee];
    }

    private function calculateEmployee(array $emp, array $period, array $data, ?array $override = null): array
    {
        $empId = (int) $emp['id'];
        $lines = $this->buildLines($data['general'], $data['assigned'][$empId] ?? [], $data['novelties'][$empId] ?? []);

        if ($override !== null) {
            $ov = $this->conceptLine($override);
            $found = false;
            foreach ($lines as &$line) {
                if (($ov['id'] && $line['id'] === $ov['id']) || $line['code'] === $ov['code']) {
                    // se conservan cantidad e importe de novedades/asignaciones del empleado
                    $line = array_merge($ov, ['quantity' => $line['quantity'], 'amount' => $line['amount']]);
                    $found = true;
                }
            }
            unset($line);
            if (!$found) {
                $lines[] = $ov;
            }
            if (isset($override['quantity'])) {
                foreach ($lines as &$line) {
                    if ($line['code'] === $ov['code']) {
                        $line['quantity'] = $override['quantity'];
                    }
                }
                unset($line);
            }
        }

        $sacBest = $period['type'] === 'sac' ? $this->bestSemesterSalary($empId, $period) : 0.0;
        $calculator = new PayrollCalculator((int) Settings::get('hours_divisor', '200'));
        try {
            return $calculator->calculate([
                'base_salary'      => (float) $emp['effective_salary'],
                'hire_date'        => $emp['hire_date'],
                'termination_date' => $emp['termination_date'],
                'birth_date'       => $emp['birth_date'],
            ], $period, $lines, $sacBest);
        } catch (FormulaException $e) {
            throw new RuntimeException("Error al liquidar a {$emp['last_name']}, {$emp['first_name']} — {$e->getMessage()}", 0, $e);
        }
    }

    /** Conceptos generales, asignados y novedades que intervienen en el período. */
    private function loadConcepts(array $period, ?int $periodId, ?int $employeeId = null): array
    {
        $scopes = [$period['type'], 'ambos'];
        $empFilter = $employeeId !== null ? ' AND ec.employee_id = ' . (int) $employeeId : '';
        $novFilter = $employeeId !== null ? ' AND n.employee_id = ' . (int) $employeeId : '';

        return [
            'general' => $this->db->all(
                'SELECT * FROM concepts WHERE active = 1 AND applies_to_all = 1 AND scope IN (?, ?)',
                $scopes
            ),
            'assigned' => $this->groupBy($this->db->all(
                "SELECT c.*, ec.employee_id, ec.value_override, ec.quantity AS assigned_quantity
                   FROM employee_concepts ec JOIN concepts c ON c.id = ec.concept_id
                  WHERE c.active = 1 AND c.scope IN (?, ?) $empFilter",
                $scopes
            ), 'employee_id'),
            'novelties' => $periodId === null ? [] : $this->groupBy($this->db->all(
                "SELECT n.employee_id, n.quantity AS nov_quantity, n.amount AS nov_amount, c.*
                   FROM novelties n JOIN concepts c ON c.id = n.concept_id
                  WHERE n.period_id = ? $novFilter",
                [$periodId]
            ), 'employee_id'),
        ];
    }

    /**
     * Une conceptos generales, asignados al empleado y novedades del período.
     * Prioridad del valor: novedad > asignación individual > valor del concepto.
     */
    public function buildLines(array $general, array $assigned, array $novelties): array
    {
        $lines = [];
        foreach ($general as $c) {
            $lines[$c['id']] = $this->conceptLine($c);
        }
        foreach ($assigned as $c) {
            $line = $lines[$c['id']] ?? $this->conceptLine($c);
            if ($c['value_override'] !== null) {
                $line['value'] = $c['value_override'];
            }
            if ($c['assigned_quantity'] !== null) {
                $line['quantity'] = $c['assigned_quantity'];
            }
            $lines[$c['id']] = $line;
        }
        $fromNovelty = [];
        foreach ($novelties as $n) {
            $id = $n['id'];
            $line = $lines[$id] ?? $this->conceptLine($n);
            // Varias novedades del mismo concepto se acumulan
            if (!isset($fromNovelty[$id])) {
                $line['quantity'] = null;
                $line['amount'] = null;
                $fromNovelty[$id] = true;
            }
            if ($n['nov_quantity'] !== null) {
                $line['quantity'] = (float) ($line['quantity'] ?? 0) + (float) $n['nov_quantity'];
            }
            if ($n['nov_amount'] !== null) {
                $line['amount'] = (float) ($line['amount'] ?? 0) + (float) $n['nov_amount'];
            }
            $lines[$id] = $line;
        }
        return array_values($lines);
    }

    private function conceptLine(array $c): array
    {
        return [
            'id'         => (int) $c['id'],
            'code'       => $c['code'],
            'name'       => $c['name'],
            'type'       => $c['type'],
            'calc_mode'  => $c['calc_mode'],
            'base'       => $c['base'],
            'value'      => $c['value'],
            'formula'    => $c['formula'] ?? null,
            'sort_order' => $c['sort_order'],
            'quantity'   => null,
            'amount'     => null,
        ];
    }

    /** Mejor remuneración (total remunerativo) mensual del semestre, para el SAC. */
    public function bestSemesterSalary(int $employeeId, array $period): float
    {
        $month = (int) $period['month'];
        [$m1, $m2] = $month <= 6 ? [1, 6] : [7, 12];
        return (float) $this->db->value(
            "SELECT COALESCE(MAX(ps.gross_rem), 0)
               FROM payslips ps JOIN payroll_periods pp ON pp.id = ps.period_id
              WHERE ps.employee_id = ? AND pp.type = 'mensual' AND pp.year = ?
                AND pp.month BETWEEN ? AND ? AND pp.status IN ('calculada','cerrada')",
            [$employeeId, $period['year'], $m1, $m2]
        );
    }

    public function close(int $periodId): void
    {
        $period = $this->findPeriod($periodId);
        if ($period['status'] !== 'calculada') {
            throw new RuntimeException('Solo se puede cerrar una liquidación calculada.');
        }
        $this->db->update('payroll_periods', ['status' => 'cerrada', 'closed_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $periodId]);
    }

    public function reopen(int $periodId): void
    {
        $period = $this->findPeriod($periodId);
        if ($period['status'] !== 'cerrada') {
            throw new RuntimeException('La liquidación no está cerrada.');
        }
        $this->db->update('payroll_periods', ['status' => 'calculada', 'closed_at' => null], 'id = :id', ['id' => $periodId]);
    }

    /** @return string[] [desde, hasta] en formato Y-m-d */
    private function periodRange(array $period): array
    {
        if ($period['type'] === 'sac') {
            [$s, $e] = PayrollCalculator::semester((int) $period['year'], (int) $period['month']);
            return [$s->format('Y-m-d'), $e->format('Y-m-d')];
        }
        $from = sprintf('%04d-%02d-01', $period['year'], $period['month']);
        return [$from, date('Y-m-t', strtotime($from))];
    }

    private function groupBy(array $rows, string $key): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row[$key]][] = $row;
        }
        return $out;
    }
}
