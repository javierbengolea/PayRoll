<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Controller;
use App\Core\Validator;

/** Departamentos y puestos (funciones). El básico lo define la categoría del convenio. */
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
        $v = Validator::make($_POST, ['name' => 'required|maxlen:120'], ['name' => 'Nombre']);
        if ($v->fails()) {
            $this->flash('danger', implode(' ', $v->errors()));
            $this->redirect('organization', ['tab' => 'positions']);
        }
        $data = [
            'name'          => (string) $this->input('name'),
            'department_id' => (int) $this->input('department_id') ?: null,
            'active'        => isset($_POST['active']) ? 1 : 0,
        ];
        if ($id) {
            $this->db->update('positions', $data, 'id = :id', ['id' => $id]);
        } else {
            $id = $this->db->insert('positions', $data);
        }
        Audit::log('guardar', 'position', $id, $data['name']);
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
}
