<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class DashboardController extends Controller
{
    public function index(): void
    {
        $db = $this->db;

        $headcount = $db->all("SELECT status, COUNT(*) AS total FROM employees GROUP BY status");
        $counts = array_column($headcount, 'total', 'status');

        $lastPeriod = $db->one(
            "SELECT pp.*, COUNT(ps.id) AS payslips, COALESCE(SUM(ps.net_pay),0) AS net,
                    COALESCE(SUM(ps.gross_rem + ps.gross_no_rem),0) AS gross,
                    COALESCE(SUM(ps.employer_contrib),0) AS contrib
               FROM payroll_periods pp LEFT JOIN payslips ps ON ps.period_id = pp.id
              WHERE pp.status IN ('calculada','cerrada') AND pp.type = 'mensual'
           GROUP BY pp.id ORDER BY pp.year DESC, pp.month DESC LIMIT 1"
        );

        $history = array_reverse($db->all(
            "SELECT pp.year, pp.month, pp.type, SUM(ps.net_pay) AS net, SUM(ps.gross_rem + ps.gross_no_rem) AS gross,
                    SUM(ps.employer_contrib) AS contrib
               FROM payroll_periods pp JOIN payslips ps ON ps.period_id = pp.id
              WHERE pp.status IN ('calculada','cerrada')
           GROUP BY pp.id ORDER BY pp.year DESC, pp.month DESC, pp.type DESC LIMIT 12"
        ));

        $byDepartment = $db->all(
            "SELECT COALESCE(d.name, 'Sin departamento') AS name, COUNT(*) AS total
               FROM employees e LEFT JOIN departments d ON d.id = e.department_id
              WHERE e.status <> 'baja' GROUP BY d.name ORDER BY total DESC"
        );

        $openPeriods = $db->all(
            "SELECT * FROM payroll_periods WHERE status <> 'cerrada' ORDER BY year DESC, month DESC LIMIT 5"
        );

        $birthdays = $db->all(
            "SELECT id, first_name, last_name, birth_date FROM employees
              WHERE status <> 'baja' AND birth_date IS NOT NULL AND MONTH(birth_date) = MONTH(CURDATE())
           ORDER BY DAY(birth_date)"
        );

        $anniversaries = $db->all(
            "SELECT id, first_name, last_name, hire_date, TIMESTAMPDIFF(YEAR, hire_date, LAST_DAY(CURDATE())) AS years
               FROM employees
              WHERE status <> 'baja' AND MONTH(hire_date) = MONTH(CURDATE()) AND YEAR(hire_date) < YEAR(CURDATE())
           ORDER BY DAY(hire_date)"
        );

        $this->render('dashboard/index', compact(
            'counts', 'lastPeriod', 'history', 'byDepartment', 'openPeriods', 'birthdays', 'anniversaries'
        ));
    }
}
