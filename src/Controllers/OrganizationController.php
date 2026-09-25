<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Controller;
use App\Core\Validator;

/** Departamentos y puestos (con su sueldo básico). */
final class OrganizationController extends Controller
{
    public function index(): void
    {
        $departments = $this->db->all(
            "SELECT d.*, (SELECT COUNT(*) FROM employees e WHERE e.department_id = d.id AND e.status <> 'baja') AS employees
               FROM departments d ORDER BY d.name"
        );
        $positions = $this->db->all(
            "SELECT p.*, d.name AS department_name,
                    (SELECT COUNT(*) FROM employees e WHERE e.position_id = p.id AND e.status <> 'baja') AS employees
               FROM positions p LEFT JOIN departments d ON d.id = p.department_id
           ORDER BY d.name, p.name"
        );
        $this->render('organization/index', compact('departments', 'positions'));
    }

    public function saveDepartment(): void
    {
        $id = $this->intParam('id');
        $name = (string) $this->input('name', '');
        $v = Validator::make($_POST, ['name' => 'required|maxlen:120', 'description' => 'maxlen:255'], ['name' => 'Nombre', 'description' => 'Descripción']);
        if ($this->db->value('SELECT id FROM departments WHERE name = ? AND id <> ?', [$name, $id])) {
            $v->addError('name', 'Ya existe un departamento con ese nombre.');
        }
        if ($v->fails()) {
            $this->flash('danger', implode(' ', $v->errors()));
            $this->redirect('organization');
        }
        $data = ['name' => $name, 'description' => $this->input('description') ?: null, 'active' => isset($_POST['active']) ? 1 : 0];
        if ($id) {
            $this->db->update('departments', $data, 'id = :id', ['id' => $id]);
        } else {
            $id = $this->db->insert('departments', $data);
        }
        Audit::log('guardar', 'department', $id, $name);
        $this->flash('success', 'Departamento guardado.');
        $this->redirect('organization');
    }

    public function deleteDepartment(): void
    {
        $id = $this->intParam('id');
        if ($this->db->value('SELECT COUNT(*) FROM employees WHERE department_id = ?', [$id])) {
            $this->flash('warning', 'No se puede eliminar: hay empleados en ese departamento. Podés desactivarlo.');
        } else {
            $this->db->run('DELETE FROM departments WHERE id = ?', [$id]);
            Audit::log('eliminar', 'department', $id);
            $this->flash('success', 'Departamento eliminado.');
        }
        $this->redirect('organization');
    }

    public function savePosition(): void
    {
        $id = $this->intParam('id');
        $v = Validator::make($_POST, ['name' => 'required|maxlen:120', 'base_salary' => 'required|numeric|min:0'],
            ['name' => 'Nombre', 'base_salary' => 'Sueldo básico']);
        if ($v->fails()) {
            $this->flash('danger', implode(' ', $v->errors()));
            $this->redirect('organization', ['tab' => 'positions']);
        }
        $data = [
            'name'          => (string) $this->input('name'),
            'department_id' => (int) $this->input('department_id') ?: null,
            'base_salary'   => Validator::normalizeNumber((string) $this->input('base_salary')),
            'active'        => isset($_POST['active']) ? 1 : 0,
        ];
        if ($id) {
            $this->db->update('positions', $data, 'id = :id', ['id' => $id]);
        } else {
            $id = $this->db->insert('positions', $data);
        }
        Audit::log('guardar', 'position', $id, $data['name'] . ' - básico ' . $data['base_salary']);
        $this->flash('success', 'Puesto guardado.');
        $this->redirect('organization', ['tab' => 'positions']);
    }

    public function deletePosition(): void
    {
        $id = $this->intParam('id');
        if ($this->db->value('SELECT COUNT(*) FROM employees WHERE position_id = ?', [$id])) {
            $this->flash('warning', 'No se puede eliminar: hay empleados con ese puesto. Podés desactivarlo.');
        } else {
            $this->db->run('DELETE FROM positions WHERE id = ?', [$id]);
            Audit::log('eliminar', 'position', $id);
            $this->flash('success', 'Puesto eliminado.');
        }
        $this->redirect('organization', ['tab' => 'positions']);
    }

    /** Aumento general: aplica un % a los básicos de puestos (y opcionalmente a los básicos individuales). */
    public function raise(): void
    {
        $v = Validator::make($_POST, ['percent' => 'required|numeric|min:-50|max:500'], ['percent' => 'Porcentaje']);
        if ($v->fails()) {
            $this->flash('danger', implode(' ', $v->errors()));
            $this->redirect('organization', ['tab' => 'positions']);
        }
        $factor = 1 + (float) Validator::normalizeNumber((string) $this->input('percent')) / 100;
        $includeEmployees = isset($_POST['include_employees']);

        $this->db->transaction(function ($db) use ($factor, $includeEmployees) {
            $db->run('UPDATE positions SET base_salary = ROUND(base_salary * ?, 2) WHERE active = 1', [$factor]);
            if ($includeEmployees) {
                $db->run("UPDATE employees SET base_salary = ROUND(base_salary * ?, 2) WHERE base_salary IS NOT NULL AND status <> 'baja'", [$factor]);
            }
        });
        $pct = num(($factor - 1) * 100);
        Audit::log('aumento_general', 'position', null, "{$pct}%" . ($includeEmployees ? ' (incluye básicos individuales)' : ''));
        $this->flash('success', "Se aplicó un ajuste del {$pct}% a los sueldos básicos.");
        $this->redirect('organization', ['tab' => 'positions']);
    }
}
