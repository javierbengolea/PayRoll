<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class ReportController extends Controller
{
    public function index(): void
    {
        $years = array_map('intval', array_column(
            $this->db->all('SELECT DISTINCT year FROM payroll_periods ORDER BY year DESC'), 'year'
        ));
        $year = (int) ($_GET['year'] ?? ($years[0] ?? date('Y')));

        $monthly = $this->db->all(
            "SELECT pp.month, pp.type, COUNT(ps.id) AS employees,
                    SUM(ps.gross_rem) AS rem, SUM(ps.gross_no_rem) AS no_rem, SUM(ps.deductions) AS deductions,
                    SUM(ps.net_pay) AS net, SUM(ps.employer_contrib) AS contrib
               FROM payroll_periods pp JOIN payslips ps ON ps.period_id = pp.id
              WHERE pp.year = ? AND pp.status IN ('calculada','cerrada')
           GROUP BY pp.id ORDER BY pp.month, pp.type",
            [$year]
        );

        $byDepartment = $this->db->all(
            "SELECT COALESCE(ps.department_name, 'Sin departamento') AS name, COUNT(DISTINCT ps.employee_id) AS employees,
                    SUM(ps.gross_rem + ps.gross_no_rem) AS gross, SUM(ps.net_pay) AS net, SUM(ps.employer_contrib) AS contrib,
                    SUM(ps.gross_rem + ps.gross_no_rem + ps.employer_contrib) AS cost
               FROM payroll_periods pp JOIN payslips ps ON ps.period_id = pp.id
              WHERE pp.year = ? AND pp.status IN ('calculada','cerrada')
           GROUP BY ps.department_name ORDER BY cost DESC",
            [$year]
        );

        $byConcept = $this->db->all(
            "SELECT pi.code, pi.name, pi.type, SUM(pi.amount) AS total
               FROM payroll_periods pp
               JOIN payslips ps ON ps.period_id = pp.id
               JOIN payslip_items pi ON pi.payslip_id = ps.id
              WHERE pp.year = ? AND pp.status IN ('calculada','cerrada')
           GROUP BY pi.code, pi.name, pi.type
           ORDER BY FIELD(pi.type, 'haber_rem', 'haber_no_rem', 'descuento', 'contribucion'), pi.code",
            [$year]
        );

        $topEarners = $this->db->all(
            "SELECT ps.employee_id, ps.employee_name, ps.department_name, SUM(ps.net_pay) AS net,
                    SUM(ps.gross_rem + ps.gross_no_rem + ps.employer_contrib) AS cost
               FROM payroll_periods pp JOIN payslips ps ON ps.period_id = pp.id
              WHERE pp.year = ? AND pp.status IN ('calculada','cerrada')
           GROUP BY ps.employee_id, ps.employee_name, ps.department_name ORDER BY cost DESC LIMIT 10",
            [$year]
        );

        $this->render('reports/index', compact('years', 'year', 'monthly', 'byDepartment', 'byConcept', 'topEarners'));
    }
}
