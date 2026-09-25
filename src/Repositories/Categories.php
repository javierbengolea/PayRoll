<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Convenios, categorías y escalas salariales.
 * El básico vigente de una categoría a una fecha es el de la escala con mayor
 * fecha de vigencia que no supere esa fecha.
 */
final class Categories
{
    /**
     * Expresión SQL con el básico de la categoría del empleado (alias `e`) a una fecha.
     * @param string $dateSql parámetro o expresión SQL, ej: ':to' o 'CURDATE()'
     */
    public static function salaryAt(string $dateSql, string $categoryColumn = 'e.category_id'): string
    {
        return "(SELECT cs.base_salary FROM category_salaries cs
                  WHERE cs.category_id = $categoryColumn AND cs.valid_from <= $dateSql
               ORDER BY cs.valid_from DESC LIMIT 1)";
    }

    /** Básico efectivo: el propio del empleado o, si no tiene, el de su categoría. */
    public static function effectiveSalary(string $dateSql): string
    {
        return 'COALESCE(e.base_salary, ' . self::salaryAt($dateSql) . ', 0)';
    }

    /** Categorías activas agrupadas por convenio, con su básico vigente hoy (para selects). */
    public static function grouped(): array
    {
        $rows = Database::connection()->all(
            'SELECT c.id, c.code, c.name, a.id AS agreement_id, a.code AS agreement_code, a.name AS agreement_name, '
            . self::salaryAt('CURDATE()', 'c.id') . ' AS salary
               FROM categories c JOIN agreements a ON a.id = c.agreement_id
              WHERE c.active = 1 AND a.active = 1
           ORDER BY a.name, c.sort_order, c.code'
        );
        $groups = [];
        foreach ($rows as $row) {
            $groups[$row['agreement_code'] . ' · ' . $row['agreement_name']][] = $row;
        }
        return $groups;
    }
}
