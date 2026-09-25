<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Controller;
use App\Core\Validator;
use App\Repositories\Categories;

/** Convenios colectivos, sus categorías y las escalas salariales (básicos con vigencia). */
final class CategoryController extends Controller
{
    private const HISTORY_COLUMNS = 6;

    public function index(): void
    {
        $agreements = $this->db->all(
            "SELECT a.*, (SELECT COUNT(*) FROM categories c WHERE c.agreement_id = a.id) AS categories,
                    (SELECT COUNT(*) FROM employees e JOIN categories c ON c.id = e.category_id
                      WHERE c.agreement_id = a.id AND e.status <> 'baja') AS employees
               FROM agreements a ORDER BY a.active DESC, a.name"
        );
        $agreementId = $this->intParam('agreement') ?: (int) ($agreements[0]['id'] ?? 0);
        $agreement = null;
        foreach ($agreements as $a) {
            if ((int) $a['id'] === $agreementId) {
                $agreement = $a;
            }
        }

        $categories = [];
        $scales = [];
        $history = [];
        if ($agreement !== null) {
            $categories = $this->db->all(
                'SELECT c.*, ' . Categories::salaryAt('CURDATE()', 'c.id') . " AS current_salary,
                        (SELECT MAX(cs.valid_from) FROM category_salaries cs WHERE cs.category_id = c.id AND cs.valid_from <= CURDATE()) AS current_from,
                        (SELECT COUNT(*) FROM employees e WHERE e.category_id = c.id AND e.status <> 'baja') AS employees
                   FROM categories c WHERE c.agreement_id = ? ORDER BY c.sort_order, c.code",
                [$agreementId]
            );
            // Fechas de vigencia del convenio (las más recientes primero)
            $scales = array_column($this->db->all(
                'SELECT DISTINCT cs.valid_from FROM category_salaries cs JOIN categories c ON c.id = cs.category_id
                  WHERE c.agreement_id = ? ORDER BY cs.valid_from DESC',
                [$agreementId]
            ), 'valid_from');
            $rows = $this->db->all(
                'SELECT cs.category_id, cs.valid_from, cs.base_salary FROM category_salaries cs
                   JOIN categories c ON c.id = cs.category_id WHERE c.agreement_id = ?',
                [$agreementId]
            );
            foreach ($rows as $r) {
                $history[$r['category_id']][$r['valid_from']] = (float) $r['base_salary'];
            }
        }
        $shownScales = array_reverse(array_slice($scales, 0, self::HISTORY_COLUMNS));

        $this->render('categories/index', compact('agreements', 'agreement', 'categories', 'scales', 'shownScales', 'history'));
    }

    public function saveAgreement(): void
    {
        $id = $this->intParam('id');
        $v = Validator::make($_POST, ['code' => 'required|maxlen:20', 'name' => 'required|maxlen:160', 'description' => 'maxlen:255'],
            ['code' => 'Código', 'name' => 'Nombre', 'description' => 'Descripción']);
        $code = (string) $this->input('code');
        if ($this->db->value('SELECT id FROM agreements WHERE code = ? AND id <> ?', [$code, $id])) {
            $v->addError('code', 'Ya existe un convenio con ese código.');
        }
        if ($v->fails()) {
            $this->flash('danger', implode(' ', $v->errors()));
            $this->redirect('categories', $id ? ['agreement' => $id] : []);
        }
        $data = [
            'code'        => $code,
            'name'        => (string) $this->input('name'),
            'description' => $this->input('description') ?: null,
            'active'      => isset($_POST['active']) ? 1 : 0,
        ];
        if ($id) {
            $this->db->update('agreements', $data, 'id = :id', ['id' => $id]);
        } else {
            $id = $this->db->insert('agreements', $data);
        }
        Audit::log('guardar', 'agreement', $id, $code . ' ' . $data['name']);
        $this->flash('success', 'Convenio guardado.');
        $this->redirect('categories', ['agreement' => $id]);
    }

    public function saveCategory(): void
    {
        $id = $this->intParam('id');
        $agreementId = (int) $this->input('agreement_id');
        $isNew = !$id;
        $rules = ['code' => 'required|maxlen:20', 'name' => 'required|maxlen:120', 'sort_order' => 'integer'];
        if ($isNew) {
            $rules += ['base_salary' => 'required|numeric|min:0', 'valid_from' => 'required|date'];
        }
        $v = Validator::make($_POST, $rules, ['code' => 'Código', 'name' => 'Nombre', 'sort_order' => 'Orden',
            'base_salary' => 'Sueldo básico', 'valid_from' => 'Vigencia']);
        $code = (string) $this->input('code');
        if (!$this->db->value('SELECT id FROM agreements WHERE id = ?', [$agreementId])) {
            $v->addError('agreement_id', 'Elegí un convenio.');
        }
        if ($this->db->value('SELECT id FROM categories WHERE agreement_id = ? AND code = ? AND id <> ?', [$agreementId, $code, $id])) {
            $v->addError('code', 'Ya existe una categoría con ese código en el convenio.');
        }
        if ($v->fails()) {
            $this->flash('danger', implode(' ', $v->errors()));
            $this->redirect('categories', ['agreement' => $agreementId]);
        }

        $data = [
            'agreement_id' => $agreementId,
            'code'         => $code,
            'name'         => (string) $this->input('name'),
            'sort_order'   => (int) ($this->input('sort_order') ?: 100),
            'active'       => isset($_POST['active']) ? 1 : 0,
        ];
        $this->db->transaction(function ($db) use (&$id, $data, $isNew) {
            if ($isNew) {
                $id = $db->insert('categories', $data);
                $db->insert('category_salaries', [
                    'category_id' => $id,
                    'valid_from'  => $_POST['valid_from'],
                    'base_salary' => Validator::normalizeNumber((string) $_POST['base_salary']),
                ]);
            } else {
                $db->update('categories', $data, 'id = :id', ['id' => $id]);
            }
        });
        Audit::log('guardar', 'category', $id, $code . ' ' . $data['name']);
        $this->flash('success', 'Categoría guardada.');
        $this->redirect('categories', ['agreement' => $agreementId]);
    }

    public function deleteCategory(): void
    {
        $id = $this->intParam('id');
        $category = $this->db->one('SELECT * FROM categories WHERE id = ?', [$id]) ?? $this->notFound();
        if ($this->db->value('SELECT COUNT(*) FROM employees WHERE category_id = ?', [$id])) {
            $this->flash('warning', 'No se puede eliminar: hay empleados con esa categoría. Podés desactivarla.');
        } else {
            $this->db->run('DELETE FROM categories WHERE id = ?', [$id]);
            Audit::log('eliminar', 'category', $id, $category['code'] . ' ' . $category['name']);
            $this->flash('success', 'Categoría eliminada.');
        }
        $this->redirect('categories', ['agreement' => $category['agreement_id']]);
    }

    /**
     * Carga una escala salarial (por ejemplo, una paritaria): un básico por categoría
     * con la misma fecha de vigencia. Si ya había valores para esa fecha, se reemplazan.
     */
    public function saveScale(): void
    {
        $agreementId = (int) $this->input('agreement_id');
        $validFrom = (string) $this->input('valid_from');
        $salaries = is_array($_POST['salaries'] ?? null) ? $_POST['salaries'] : [];

        $errors = [];
        if (!Validator::isDate($validFrom)) {
            $errors[] = 'Indicá una fecha de vigencia válida.';
        }
        $categoryIds = array_map('intval', array_column(
            $this->db->all('SELECT id FROM categories WHERE agreement_id = ?', [$agreementId]), 'id'
        ));
        $values = [];
        foreach ($salaries as $categoryId => $raw) {
            $raw = trim((string) $raw);
            if ($raw === '' || !in_array((int) $categoryId, $categoryIds, true)) {
                continue;
            }
            $num = Validator::normalizeNumber($raw);
            if (!is_numeric($num) || (float) $num < 0) {
                $errors[] = "El básico «{$raw}» no es un importe válido.";
                continue;
            }
            $values[(int) $categoryId] = round((float) $num, 2);
        }
        if (!$values) {
            $errors[] = 'Cargá al menos un básico.';
        }
        if ($errors) {
            $this->flash('danger', implode(' ', array_unique($errors)));
            $this->redirect('categories', ['agreement' => $agreementId]);
        }

        $this->db->transaction(function ($db) use ($values, $validFrom) {
            foreach ($values as $categoryId => $salary) {
                $db->run(
                    'INSERT INTO category_salaries (category_id, valid_from, base_salary) VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE base_salary = VALUES(base_salary)',
                    [$categoryId, $validFrom, $salary]
                );
            }
        });
        Audit::log('escala_salarial', 'agreement', $agreementId, 'Vigencia ' . $validFrom . ' · ' . count($values) . ' categorías');
        $this->flash('success', 'Escala salarial cargada con vigencia desde el ' . fmt_date($validFrom) . '.');
        $this->redirect('categories', ['agreement' => $agreementId]);
    }

    /** Elimina todos los básicos de una fecha de vigencia del convenio (para corregir una carga errónea). */
    public function deleteScale(): void
    {
        $agreementId = (int) $this->input('agreement_id');
        $validFrom = (string) $this->input('valid_from');
        $used = (int) $this->db->value(
            "SELECT COUNT(*) FROM payroll_periods WHERE status = 'cerrada' AND LAST_DAY(CONCAT(year, '-', LPAD(month, 2, '0'), '-01')) >= ?",
            [$validFrom]
        );
        if ($used) {
            $this->flash('warning', 'Atención: hay liquidaciones cerradas posteriores a esa vigencia. Sus recibos no cambian, pero si las reabrís y recalculás usarán la escala anterior.');
        }
        $deleted = $this->db->run(
            'DELETE cs FROM category_salaries cs JOIN categories c ON c.id = cs.category_id
              WHERE c.agreement_id = ? AND cs.valid_from = ?',
            [$agreementId, $validFrom]
        )->rowCount();
        Audit::log('eliminar_escala', 'agreement', $agreementId, 'Vigencia ' . $validFrom);
        $this->flash('success', "Se eliminó la escala del " . fmt_date($validFrom) . " ($deleted básicos).");
        $this->redirect('categories', ['agreement' => $agreementId]);
    }
}
