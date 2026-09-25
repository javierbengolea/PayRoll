<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Controller;
use App\Core\Validator;
use App\Repositories\Categories;
use App\Services\CsvExporter;

final class EmployeeController extends Controller
{
    private const PER_PAGE = 20;

    public function index(): void
    {
        [$where, $params] = $this->filters();
        $page = max(1, $this->intParam('page'));
        $total = (int) $this->db->value("SELECT COUNT(*) FROM employees e $where", $params);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $pages);
        $offset = ($page - 1) * self::PER_PAGE;

        $sortable = [
            'legajo'  => 'e.file_number',
            'nombre'  => 'e.last_name, e.first_name',
            'ingreso' => 'e.hire_date',
            'sueldo'  => 'effective_salary',
        ];
        $sort = $sortable[$_GET['sort'] ?? ''] ?? $sortable['nombre'];
        $dir = ($_GET['dir'] ?? '') === 'desc' ? 'DESC' : 'ASC';

        $employees = $this->db->all(
            "SELECT e.*, d.name AS department_name, p.name AS position_name, c.name AS category_name,
                    " . Categories::effectiveSalary('CURDATE()') . " AS effective_salary
               FROM employees e
          LEFT JOIN departments d ON d.id = e.department_id
          LEFT JOIN positions p ON p.id = e.position_id
          LEFT JOIN categories c ON c.id = e.category_id
             $where ORDER BY $sort $dir LIMIT " . self::PER_PAGE . " OFFSET $offset",
            $params
        );

        $departments = $this->db->all('SELECT id, name FROM departments ORDER BY name');
        $categories = Categories::grouped();
        $this->render('employees/index', compact('employees', 'departments', 'categories', 'total', 'page', 'pages'));
    }

    public function export(): void
    {
        [$where, $params] = $this->filters();
        $rows = $this->db->all(
            "SELECT e.file_number, e.last_name, e.first_name, e.cuil, e.email, e.phone, e.hire_date,
                    e.termination_date, d.name AS department, p.name AS position, e.contract_type,
                    CONCAT(a.code, ' - ', c.name) AS category,
                    " . Categories::effectiveSalary('CURDATE()') . " AS salary, e.bank_name, e.cbu, e.status
               FROM employees e
          LEFT JOIN departments d ON d.id = e.department_id
          LEFT JOIN positions p ON p.id = e.position_id
          LEFT JOIN categories c ON c.id = e.category_id
          LEFT JOIN agreements a ON a.id = c.agreement_id
             $where ORDER BY e.last_name, e.first_name",
            $params
        );
        CsvExporter::download('empleados_' . date('Ymd') . '.csv', [
            'Legajo', 'Apellido', 'Nombre', 'CUIL', 'Email', 'Teléfono', 'Ingreso', 'Egreso',
            'Departamento', 'Puesto', 'Categoría', 'Contratación', 'Básico', 'Banco', 'CBU', 'Estado',
        ], array_map(fn ($r) => [
            $r['file_number'], $r['last_name'], $r['first_name'], fmt_cuil($r['cuil']), $r['email'], $r['phone'],
            fmt_date($r['hire_date']), fmt_date($r['termination_date']), $r['department'], $r['position'], $r['category'],
            CONTRACT_TYPES[$r['contract_type']] ?? $r['contract_type'], money($r['salary'], false),
            $r['bank_name'], $r['cbu'] ? "'" . $r['cbu'] : '', EMPLOYEE_STATUS[$r['status']][0] ?? $r['status'],
        ], $rows));
    }

    public function show(): void
    {
        $employee = $this->findOrFail($this->intParam('id'));
        $concepts = $this->db->all(
            'SELECT c.*, ec.value_override, ec.quantity AS assigned_quantity
               FROM employee_concepts ec JOIN concepts c ON c.id = ec.concept_id
              WHERE ec.employee_id = ? ORDER BY c.sort_order',
            [$employee['id']]
        );
        $available = $this->db->all(
            'SELECT id, code, name, type, calc_mode FROM concepts
              WHERE active = 1 AND applies_to_all = 0
                AND id NOT IN (SELECT concept_id FROM employee_concepts WHERE employee_id = ?)
           ORDER BY sort_order',
            [$employee['id']]
        );
        $payslips = $this->db->all(
            'SELECT ps.*, pp.year, pp.month, pp.type, pp.status
               FROM payslips ps JOIN payroll_periods pp ON pp.id = ps.period_id
              WHERE ps.employee_id = ? ORDER BY pp.year DESC, pp.month DESC, pp.type DESC',
            [$employee['id']]
        );
        $this->render('employees/show', compact('employee', 'concepts', 'available', 'payslips'));
    }

    public function create(): void
    {
        $next = (int) $this->db->value("SELECT COALESCE(MAX(CAST(file_number AS UNSIGNED)), 0) + 1 FROM employees WHERE file_number REGEXP '^[0-9]+$'");
        $employee = ['file_number' => (string) $next, 'hire_date' => date('Y-m-d'), 'status' => 'activo', 'contract_type' => 'permanente', 'nationality' => 'Argentina'];
        $this->render('employees/form', $this->formData($employee));
    }

    public function store(): void
    {
        $data = $this->validated(null);
        $id = $this->db->insert('employees', $data);
        Audit::log('crear', 'employee', $id, $data['last_name'] . ', ' . $data['first_name']);
        $this->flash('success', 'Empleado creado correctamente.');
        $this->redirect('employees/show', ['id' => $id]);
    }

    public function edit(): void
    {
        $employee = $this->findOrFail($this->intParam('id'));
        $this->render('employees/form', $this->formData($employee));
    }

    public function update(): void
    {
        $id = $this->intParam('id');
        $before = $this->findOrFail($id);
        $data = $this->validated($id);
        $this->db->update('employees', $data, 'id = :id', ['id' => $id]);

        $changes = [];
        foreach ($data as $k => $v) {
            if ((string) $before[$k] !== (string) $v) {
                $changes[] = $k;
            }
        }
        Audit::log('editar', 'employee', $id, $changes ? 'Campos: ' . implode(', ', $changes) : 'Sin cambios');
        $this->flash('success', 'Datos del empleado actualizados.');
        $this->redirect('employees/show', ['id' => $id]);
    }

    public function addConcept(): void
    {
        $id = $this->intParam('id');
        $this->findOrFail($id);
        $conceptId = (int) $this->input('concept_id');
        $value = $this->input('value_override');
        $qty = $this->input('quantity');

        $v = Validator::make($_POST, ['concept_id' => 'required|integer', 'value_override' => 'numeric', 'quantity' => 'numeric'],
            ['concept_id' => 'Concepto', 'value_override' => 'Valor', 'quantity' => 'Cantidad']);
        if ($v->fails()) {
            $this->flash('danger', implode(' ', $v->errors()));
            $this->redirect('employees/show', ['id' => $id]);
        }

        $this->db->run(
            'INSERT INTO employee_concepts (employee_id, concept_id, value_override, quantity) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE value_override = VALUES(value_override), quantity = VALUES(quantity)',
            [$id, $conceptId,
             $value === '' || $value === null ? null : Validator::normalizeNumber($value),
             $qty === '' || $qty === null ? null : Validator::normalizeNumber($qty)]
        );
        Audit::log('asignar_concepto', 'employee', $id, 'Concepto #' . $conceptId);
        $this->flash('success', 'Concepto asignado.');
        $this->redirect('employees/show', ['id' => $id]);
    }

    public function removeConcept(): void
    {
        $id = $this->intParam('id');
        $conceptId = (int) $this->input('concept_id');
        $this->db->run('DELETE FROM employee_concepts WHERE employee_id = ? AND concept_id = ?', [$id, $conceptId]);
        Audit::log('quitar_concepto', 'employee', $id, 'Concepto #' . $conceptId);
        $this->flash('success', 'Concepto quitado.');
        $this->redirect('employees/show', ['id' => $id]);
    }

    // ------------------------------------------------------------------

    private function filters(): array
    {
        $where = [];
        $params = [];
        $q = trim((string) ($_GET['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(e.first_name LIKE :q OR e.last_name LIKE :q2 OR e.file_number LIKE :q3 OR e.cuil LIKE :q4)';
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
            $params += ['q' => $like, 'q2' => $like, 'q3' => $like, 'q4' => '%' . preg_replace('/\D/', '', $q) . '%'];
            if (preg_replace('/\D/', '', $q) === '') {
                $params['q4'] = $like;
            }
        }
        $status = $_GET['status'] ?? 'vigentes';
        if ($status === 'vigentes') {
            $where[] = "e.status <> 'baja'";
        } elseif (isset(EMPLOYEE_STATUS[$status])) {
            $where[] = 'e.status = :status';
            $params['status'] = $status;
        }
        if (!empty($_GET['department'])) {
            $where[] = 'e.department_id = :dep';
            $params['dep'] = (int) $_GET['department'];
        }
        if (!empty($_GET['category'])) {
            $where[] = 'e.category_id = :cat';
            $params['cat'] = (int) $_GET['category'];
        }
        return [$where ? 'WHERE ' . implode(' AND ', $where) : '', $params];
    }

    private function findOrFail(int $id): array
    {
        $employee = $this->db->one(
            'SELECT e.*, d.name AS department_name, p.name AS position_name,
                    c.name AS category_name, c.code AS category_code, c.agreement_id, a.code AS agreement_code, a.name AS agreement_name,
                    ' . Categories::salaryAt('CURDATE()') . ' AS category_salary,
                    ' . Categories::effectiveSalary('CURDATE()') . ' AS effective_salary
               FROM employees e
          LEFT JOIN departments d ON d.id = e.department_id
          LEFT JOIN positions p ON p.id = e.position_id
          LEFT JOIN categories c ON c.id = e.category_id
          LEFT JOIN agreements a ON a.id = c.agreement_id
              WHERE e.id = ?',
            [$id]
        );
        if ($employee === null) {
            $this->notFound();
        }
        return $employee;
    }

    private function formData(array $employee): array
    {
        return [
            'employee'    => $employee,
            'departments' => $this->db->all('SELECT id, name FROM departments WHERE active = 1 ORDER BY name'),
            'positions'   => $this->db->all('SELECT id, name, department_id FROM positions WHERE active = 1 ORDER BY name'),
            'categories'  => Categories::grouped(),
        ];
    }

    private function validated(?int $id): array
    {
        $labels = [
            'file_number' => 'Legajo', 'first_name' => 'Nombre', 'last_name' => 'Apellido', 'cuil' => 'CUIL',
            'birth_date' => 'Fecha de nacimiento', 'email' => 'Email', 'hire_date' => 'Fecha de ingreso',
            'termination_date' => 'Fecha de egreso', 'base_salary' => 'Básico propio', 'cbu' => 'CBU',
            'contract_type' => 'Tipo de contratación', 'status' => 'Estado', 'gender' => 'Género',
        ];
        $v = Validator::make($_POST, [
            'file_number'      => 'required|maxlen:20',
            'first_name'       => 'required|maxlen:100',
            'last_name'        => 'required|maxlen:100',
            'cuil'             => 'required|cuil',
            'birth_date'       => 'date',
            'gender'           => 'in:F,M,X',
            'email'            => 'email|maxlen:190',
            'hire_date'        => 'required|date',
            'termination_date' => 'date',
            'base_salary'      => 'numeric|min:0',
            'cbu'              => 'cbu',
            'contract_type'    => 'required|in:' . implode(',', array_keys(CONTRACT_TYPES)),
            'status'           => 'required|in:' . implode(',', array_keys(EMPLOYEE_STATUS)),
        ], $labels);

        $cuil = preg_replace('/\D/', '', (string) ($_POST['cuil'] ?? ''));
        $fileNumber = trim((string) ($_POST['file_number'] ?? ''));

        if ($this->db->value('SELECT id FROM employees WHERE cuil = ? AND id <> ?', [$cuil, $id ?? 0])) {
            $v->addError('cuil', 'Ya existe un empleado con ese CUIL.');
        }
        if ($this->db->value('SELECT id FROM employees WHERE file_number = ? AND id <> ?', [$fileNumber, $id ?? 0])) {
            $v->addError('file_number', 'Ese legajo ya está en uso.');
        }
        $hire = $_POST['hire_date'] ?? '';
        $term = $_POST['termination_date'] ?? '';
        if ($term !== '' && $hire !== '' && $term < $hire) {
            $v->addError('termination_date', 'La fecha de egreso no puede ser anterior al ingreso.');
        }
        $categoryId = (int) ($_POST['category_id'] ?? 0);
        if ($categoryId && !$this->db->value('SELECT id FROM categories WHERE id = ?', [$categoryId])) {
            $v->addError('category_id', 'La categoría no existe.');
        }
        if (!$categoryId && trim((string) ($_POST['base_salary'] ?? '')) === '') {
            $v->addError('category_id', 'Asigná una categoría o cargá un básico propio.');
        }
        if (($_POST['status'] ?? '') === 'baja' && $term === '') {
            $v->addError('termination_date', 'Para dar de baja indicá la fecha de egreso.');
        }

        if ($v->fails()) {
            $id === null
                ? $this->failValidation($v->errors(), 'employees/create')
                : $this->failValidation($v->errors(), 'employees/edit', ['id' => $id]);
        }

        $nullable = static fn ($key) => ($val = trim((string) ($_POST[$key] ?? ''))) === '' ? null : $val;
        $salary = $nullable('base_salary');

        return [
            'file_number'      => $fileNumber,
            'first_name'       => trim($_POST['first_name']),
            'last_name'        => trim($_POST['last_name']),
            'cuil'             => $cuil,
            'birth_date'       => $nullable('birth_date'),
            'gender'           => $nullable('gender'),
            'nationality'      => $nullable('nationality'),
            'email'            => $nullable('email') !== null ? mb_strtolower($nullable('email')) : null,
            'phone'            => $nullable('phone'),
            'address'          => $nullable('address'),
            'hire_date'        => $hire,
            'termination_date' => $term !== '' ? $term : null,
            'department_id'    => (int) ($_POST['department_id'] ?? 0) ?: null,
            'position_id'      => (int) ($_POST['position_id'] ?? 0) ?: null,
            'category_id'      => $categoryId ?: null,
            'contract_type'    => $_POST['contract_type'],
            'base_salary'      => $salary !== null ? Validator::normalizeNumber($salary) : null,
            'bank_name'        => $nullable('bank_name'),
            'cbu'              => ($cbu = preg_replace('/\D/', '', (string) ($_POST['cbu'] ?? ''))) !== '' ? $cbu : null,
            'status'           => $_POST['status'],
            'notes'            => $nullable('notes'),
        ];
    }
}
