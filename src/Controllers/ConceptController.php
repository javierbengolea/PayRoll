<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Controller;
use App\Core\Validator;
use App\Services\Formula\Formula;
use App\Services\Formula\FormulaException;
use App\Services\PayrollService;
use RuntimeException;

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
        $this->render('concepts/form', $this->formData([
            'type' => 'haber_rem', 'calc_mode' => 'fijo', 'value' => '0', 'scope' => 'mensual', 'sort_order' => 100, 'active' => 1,
        ]));
    }

    public function store(): void
    {
        $data = $this->validated(null);
        $id = $this->db->insert('concepts', $data);
        Audit::log('crear', 'concept', $id, $data['code'] . ' ' . $data['name']);
        $this->flash('success', 'Concepto creado.');
        $this->warnAboutReferences($data);
        $this->redirect('concepts');
    }

    public function edit(): void
    {
        $concept = $this->db->one('SELECT * FROM concepts WHERE id = ?', [$this->intParam('id')]) ?? $this->notFound();
        $this->render('concepts/form', $this->formData($concept));
    }

    public function update(): void
    {
        $id = $this->intParam('id');
        $this->db->one('SELECT id FROM concepts WHERE id = ?', [$id]) ?? $this->notFound();
        $data = $this->validated($id);
        $this->db->update('concepts', $data, 'id = :id', ['id' => $id]);
        Audit::log('editar', 'concept', $id, $data['code'] . ' ' . $data['name'] . ' valor=' . $data['value']
            . ($data['formula'] !== null ? ' fórmula=' . $data['formula'] : ''));
        $this->flash('success', 'Concepto actualizado. Los cambios se aplican a las próximas liquidaciones.');
        $this->warnAboutReferences($data);
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
        $formula = trim((string) ($_POST['formula'] ?? ''));
        if (($_POST['calc_mode'] ?? '') === 'formula') {
            if ($formula === '') {
                $v->addError('formula', 'Escribí la fórmula del concepto.');
            } elseif (($error = Formula::validate($formula)) !== null) {
                $v->addError('formula', $error);
            }
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
            'formula'        => $_POST['calc_mode'] === 'formula' ? $formula : null,
            'value'          => Validator::normalizeNumber((string) $_POST['value']),
            'applies_to_all' => isset($_POST['applies_to_all']) ? 1 : 0,
            'scope'          => $_POST['scope'],
            'sort_order'     => (int) $_POST['sort_order'],
            'active'         => isset($_POST['active']) ? 1 : 0,
            'description'    => $this->input('description') ?: null,
        ];
    }

    /**
     * Vista previa (AJAX): calcula el recibo completo de un empleado con el concepto tal
     * como está en el formulario, sin guardar nada.
     */
    public function preview(): void
    {
        $mode = (string) ($_POST['calc_mode'] ?? '');
        $formula = trim((string) ($_POST['formula'] ?? ''));
        if ($mode === 'formula' && ($error = ($formula === '' ? 'Escribí una fórmula.' : Formula::validate($formula))) !== null) {
            $this->json(['ok' => false, 'error' => $error]);
        }
        $value = Validator::normalizeNumber((string) ($_POST['value'] ?? '0'));
        $concept = [
            'id'         => (int) ($_POST['id'] ?? 0) ?: null,
            'code'       => trim((string) ($_POST['code'] ?? '')) ?: '(nuevo)',
            'name'       => trim((string) ($_POST['name'] ?? '')) ?: 'Concepto en edición',
            'type'       => array_key_exists($_POST['type'] ?? '', CONCEPT_TYPES) ? $_POST['type'] : 'haber_rem',
            'calc_mode'  => array_key_exists($mode, CALC_MODES) ? $mode : 'fijo',
            'base'       => array_key_exists($_POST['base'] ?? '', CONCEPT_BASES) ? $_POST['base'] : 'remunerativo',
            'value'      => is_numeric($value) ? (float) $value : 0,
            'formula'    => $formula,
            'sort_order' => (int) ($_POST['sort_order'] ?? 100),
        ];

        $service = new PayrollService($this->db);
        $periodId = (int) ($_POST['period_id'] ?? 0);
        try {
            $period = $periodId
                ? $service->findPeriod($periodId)
                : ['year' => (int) date('Y'), 'month' => (int) date('n'), 'type' => ($_POST['scope'] ?? '') === 'sac' ? 'sac' : 'mensual'];
            // Con un código de concepto informado como cantidad en la vista previa
            $qty = trim((string) ($_POST['preview_quantity'] ?? ''));
            if ($qty !== '' && is_numeric(Validator::normalizeNumber($qty))) {
                $concept['quantity'] = (float) Validator::normalizeNumber($qty);
            }
            $result = $service->simulate($period, (int) ($_POST['employee_id'] ?? 0), $concept);
        } catch (RuntimeException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }

        $amount = 0.0;
        $items = [];
        foreach ($result['items'] as $item) {
            $isThis = $item['code'] === $concept['code'];
            if ($isThis) {
                $amount += $item['amount'];
            }
            $items[] = [
                'code' => $item['code'], 'name' => $item['name'], 'type' => $item['type'],
                'quantity' => $item['quantity'] !== null ? num($item['quantity']) : '',
                'amount' => money($item['amount'], false), 'current' => $isThis,
            ];
        }
        $emp = $result['employee'];
        $this->json([
            'ok'       => true,
            'amount'   => money($amount),
            'included' => (bool) array_filter($items, fn ($i) => $i['current']),
            'employee' => $emp['last_name'] . ', ' . $emp['first_name'],
            'period'   => period_label($period),
            'basic'    => money($emp['effective_salary']),
            'items'    => $items,
            'totals'   => [
                'rem' => money($result['gross_rem']), 'no_rem' => money($result['gross_no_rem']),
                'deductions' => money($result['deductions']), 'net' => money($result['net_pay']),
            ],
        ]);
    }

    private function formData(array $concept): array
    {
        return [
            'concept'   => $concept,
            'others'    => $this->db->all(
                "SELECT code, name, type, sort_order FROM concepts WHERE active = 1
               ORDER BY FIELD(type, 'haber_rem', 'haber_no_rem', 'descuento', 'contribucion'), sort_order, code"
            ),
            'employees' => $this->db->all(
                "SELECT id, file_number, first_name, last_name FROM employees WHERE status <> 'baja' ORDER BY last_name, first_name"
            ),
            'periods'   => $this->db->all('SELECT id, year, month, type, description FROM payroll_periods ORDER BY year DESC, month DESC, type DESC LIMIT 12'),
        ];
    }

    /**
     * C("x") devuelve 0 si "x" todavía no se calculó. Avisa si la fórmula referencia
     * conceptos inexistentes o que se evalúan después (por tipo u orden).
     */
    private function warnAboutReferences(array $data): void
    {
        if ($data['formula'] === null) {
            return;
        }
        try {
            $codes = Formula::referencedConcepts(Formula::compile($data['formula']));
        } catch (FormulaException) {
            return;
        }
        $rank = ['haber_rem' => 1, 'haber_no_rem' => 1, 'descuento' => 2, 'contribucion' => 3];
        $problems = [];
        foreach ($codes as $code) {
            $ref = $this->db->one('SELECT code, name, type, sort_order FROM concepts WHERE code = ?', [$code]);
            if ($ref === null) {
                $problems[] = "el concepto «{$code}» no existe";
            } elseif ([$rank[$ref['type']], (int) $ref['sort_order']] >= [$rank[$data['type']], (int) $data['sort_order']] && $ref['code'] !== $data['code']) {
                $problems[] = "«{$code} {$ref['name']}» se calcula después (orden {$ref['sort_order']}), así que valdrá 0";
            }
        }
        if ($problems) {
            $this->flash('warning', 'Revisá la fórmula: ' . implode('; ', $problems) . '.');
        }
    }
}
