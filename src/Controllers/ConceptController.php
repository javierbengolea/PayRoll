<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Controller;
use App\Core\Validator;

final class ConceptController extends Controller
{
    public function index(): void
    {
        $concepts = $this->db->all(
            "SELECT c.*, (SELECT COUNT(*) FROM employee_concepts ec WHERE ec.concept_id = c.id) AS assigned
               FROM concepts c ORDER BY FIELD(c.type, 'haber_rem', 'haber_no_rem', 'descuento', 'contribucion'), c.sort_order, c.code"
        );
        $this->render('concepts/index', compact('concepts'));
    }

    public function create(): void
    {
        $this->render('concepts/form', ['concept' => [
            'type' => 'haber_rem', 'calc_mode' => 'fijo', 'value' => '0', 'scope' => 'mensual', 'sort_order' => 100, 'active' => 1,
        ]]);
    }

    public function store(): void
    {
        $data = $this->validated(null);
        $id = $this->db->insert('concepts', $data);
        Audit::log('crear', 'concept', $id, $data['code'] . ' ' . $data['name']);
        $this->flash('success', 'Concepto creado.');
        $this->redirect('concepts');
    }

    public function edit(): void
    {
        $concept = $this->db->one('SELECT * FROM concepts WHERE id = ?', [$this->intParam('id')]) ?? $this->notFound();
        $this->render('concepts/form', compact('concept'));
    }

    public function update(): void
    {
        $id = $this->intParam('id');
        $this->db->one('SELECT id FROM concepts WHERE id = ?', [$id]) ?? $this->notFound();
        $data = $this->validated($id);
        $this->db->update('concepts', $data, 'id = :id', ['id' => $id]);
        Audit::log('editar', 'concept', $id, $data['code'] . ' ' . $data['name'] . ' valor=' . $data['value']);
        $this->flash('success', 'Concepto actualizado. Los cambios se aplican a las próximas liquidaciones.');
        $this->redirect('concepts');
    }

    public function toggle(): void
    {
        $id = $this->intParam('id');
        $this->db->run('UPDATE concepts SET active = 1 - active WHERE id = ?', [$id]);
        Audit::log('activar_desactivar', 'concept', $id);
        $this->flash('success', 'Estado del concepto actualizado.');
        $this->redirect('concepts');
    }

    private function validated(?int $id): array
    {
        $v = Validator::make($_POST, [
            'code'       => 'required|maxlen:10',
            'name'       => 'required|maxlen:120',
            'type'       => 'required|in:' . implode(',', array_keys(CONCEPT_TYPES)),
            'calc_mode'  => 'required|in:' . implode(',', array_keys(CALC_MODES)),
            'base'       => 'in:' . implode(',', array_keys(CONCEPT_BASES)),
            'value'      => 'required|numeric',
            'scope'      => 'required|in:mensual,sac,ambos',
            'sort_order' => 'required|integer',
        ], ['code' => 'Código', 'name' => 'Nombre', 'type' => 'Tipo', 'calc_mode' => 'Modo de cálculo',
            'base' => 'Base', 'value' => 'Valor', 'scope' => 'Alcance', 'sort_order' => 'Orden']);

        $code = (string) $this->input('code');
        if ($this->db->value('SELECT id FROM concepts WHERE code = ? AND id <> ?', [$code, $id ?? 0])) {
            $v->addError('code', 'Ya existe un concepto con ese código.');
        }
        if (($_POST['calc_mode'] ?? '') === 'porcentaje' && empty($_POST['base'])) {
            $v->addError('base', 'Elegí sobre qué base se calcula el porcentaje.');
        }
        if ($v->fails()) {
            $id === null
                ? $this->failValidation($v->errors(), 'concepts/create')
                : $this->failValidation($v->errors(), 'concepts/edit', ['id' => $id]);
        }

        return [
            'code'           => $code,
            'name'           => (string) $this->input('name'),
            'type'           => $_POST['type'],
            'calc_mode'      => $_POST['calc_mode'],
            'base'           => $_POST['calc_mode'] === 'porcentaje' ? $_POST['base'] : null,
            'value'          => Validator::normalizeNumber((string) $_POST['value']),
            'applies_to_all' => isset($_POST['applies_to_all']) ? 1 : 0,
            'scope'          => $_POST['scope'],
            'sort_order'     => (int) $_POST['sort_order'],
            'active'         => isset($_POST['active']) ? 1 : 0,
            'description'    => $this->input('description') ?: null,
        ];
    }
}
