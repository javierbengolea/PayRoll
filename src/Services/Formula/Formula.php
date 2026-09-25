<?php
declare(strict_types=1);

namespace App\Services\Formula;

/**
 * Fórmulas de conceptos: validación y evaluación segura (sin eval de PHP).
 *
 *   $ast = Formula::compile('SI(ANTIGUEDAD >= 5; BASICO * 0,05; 0)');
 *   $valor = Formula::evaluate($ast, ['ANTIGUEDAD' => 7, 'BASICO' => 1000000]);
 */
final class Formula
{
    /** Variables disponibles en las fórmulas, con su descripción (se muestran en la ayuda). */
    public const VARIABLES = [
        'BASICO'             => 'Sueldo básico del empleado (de su categoría, o su básico propio)',
        'ANTIGUEDAD'         => 'Años completos de antigüedad al fin del período',
        'CANTIDAD'           => 'Cantidad informada en la novedad o en la asignación del concepto',
        'VALOR'              => 'Valor del concepto (o el valor propio asignado al empleado)',
        'VALOR_HORA'         => 'Básico ÷ divisor de horas (configuración)',
        'VALOR_DIA'          => 'Básico ÷ 30',
        'DIAS_TRABAJADOS'    => 'Días trabajados en el mes (o en el semestre, si es SAC)',
        'DIAS_MES'           => 'Días del mes liquidado',
        'REMUNERATIVO'       => 'Haberes remunerativos calculados hasta este concepto',
        'NO_REMUNERATIVO'    => 'Haberes no remunerativos calculados hasta este concepto',
        'BRUTO'              => 'REMUNERATIVO + NO_REMUNERATIVO',
        'DESCUENTOS'         => 'Descuentos calculados hasta este concepto',
        'MEJOR_REMUNERACION' => 'Mejor remuneración mensual del semestre (para SAC)',
        'DIAS_SEMESTRE'      => 'Días del semestre (para SAC)',
        'EDAD'               => 'Edad del empleado al fin del período (0 si no hay fecha de nacimiento)',
        'MES'                => 'Mes liquidado (1 a 12)',
        'ANIO'               => 'Año liquidado',
        'ES_SAC'             => '1 si es una liquidación de SAC, 0 si es mensual',
    ];

    /** Funciones disponibles: nombre => [mín. args, máx. args (null = sin límite), descripción]. */
    public const FUNCTIONS = [
        'SI'        => [3, 3, 'SI(condición; valor_si_verdadero; valor_si_falso)'],
        'MAX'       => [1, null, 'MAX(a; b; ...) — el mayor valor'],
        'MIN'       => [1, null, 'MIN(a; b; ...) — el menor valor (sirve como tope)'],
        'REDONDEAR' => [1, 2, 'REDONDEAR(x; decimales) — redondeo (por defecto 2 decimales)'],
        'TRUNCAR'   => [1, 2, 'TRUNCAR(x; decimales) — corta sin redondear (por defecto 0)'],
        'ABS'       => [1, 1, 'ABS(x) — valor absoluto'],
        'PORC'      => [2, 2, 'PORC(base; porcentaje) — base × porcentaje ÷ 100'],
        'C'         => [1, 1, 'C("código") — importe de otro concepto ya calculado en el recibo (0 si no está)'],
        'CANT'      => [1, 1, 'CANT("código") — cantidad de otro concepto ya calculado (0 si no está)'],
    ];

    private static array $cache = [];

    /** Parsea y valida nombres. Lanza FormulaException con un mensaje claro si hay errores. */
    public static function compile(string $formula): array
    {
        $key = trim($formula);
        if (!isset(self::$cache[$key])) {
            $ast = (new Parser())->parse($key);
            self::check($ast);
            self::$cache[$key] = $ast;
        }
        return self::$cache[$key];
    }

    /** Devuelve null si la fórmula es válida, o el mensaje de error. */
    public static function validate(string $formula): ?string
    {
        try {
            self::compile($formula);
            return null;
        } catch (FormulaException $e) {
            return $e->getMessage();
        }
    }

    /**
     * @param array $vars      Valores de las variables (NOMBRE => número)
     * @param callable|null $concept fn(string $codigo, string $campo): float — para C() y CANT()
     */
    public static function evaluate(array $ast, array $vars, ?callable $concept = null): float
    {
        $concept ??= static fn () => 0.0;
        $result = self::eval($ast, $vars, $concept);
        if (is_string($result)) {
            throw new FormulaException('La fórmula debe devolver un número, no un texto.');
        }
        if (is_nan($result) || is_infinite($result)) {
            throw new FormulaException('El resultado no es un número válido.');
        }
        return $result;
    }

    /** Códigos de conceptos referenciados con C() o CANT(). */
    public static function referencedConcepts(array $ast): array
    {
        $codes = [];
        $walk = static function (array $node) use (&$walk, &$codes): void {
            if ($node[0] === 'call') {
                if (in_array($node[1], ['C', 'CANT'], true) && isset($node[2][0]) && in_array($node[2][0][0], ['str', 'num'], true)) {
                    $codes[] = self::codeFrom($node[2][0][1]);
                }
                foreach ($node[2] as $arg) {
                    $walk($arg);
                }
            } elseif ($node[0] === 'bin') {
                $walk($node[2]);
                $walk($node[3]);
            } elseif ($node[0] === 'un') {
                $walk($node[2]);
            }
        };
        $walk($ast);
        return array_values(array_unique($codes));
    }

    // ------------------------------------------------------------------

    private static function check(array $node): void
    {
        switch ($node[0]) {
            case 'var':
                if (!array_key_exists($node[1], self::VARIABLES)) {
                    $hint = self::suggest($node[1], array_keys(self::VARIABLES));
                    throw new FormulaException("Variable desconocida «{$node[1]}»" . ($hint ? ". ¿Quisiste decir {$hint}?" : '.'));
                }
                break;
            case 'call':
                $fn = self::FUNCTIONS[$node[1]] ?? null;
                if ($fn === null) {
                    $hint = self::suggest($node[1], array_keys(self::FUNCTIONS));
                    throw new FormulaException("Función desconocida «{$node[1]}»" . ($hint ? ". ¿Quisiste decir {$hint}?" : '.'));
                }
                $n = count($node[2]);
                if ($n < $fn[0] || ($fn[1] !== null && $n > $fn[1])) {
                    throw new FormulaException("{$node[1]} recibe " . self::arity($fn) . " argumento(s) y se pasaron $n. Uso: {$fn[2]}");
                }
                if (in_array($node[1], ['C', 'CANT'], true) && !in_array($node[2][0][0], ['str', 'num'], true)) {
                    throw new FormulaException("{$node[1]}() espera un código de concepto, por ejemplo {$node[1]}(\"120\").");
                }
                if (in_array($node[1], ['C', 'CANT'], true)) {
                    break;
                }
                foreach ($node[2] as $arg) {
                    self::check($arg);
                }
                break;
            case 'bin':
                self::check($node[2]);
                self::check($node[3]);
                break;
            case 'un':
                self::check($node[2]);
                break;
            case 'str':
                throw new FormulaException('Los textos entre comillas solo se usan como código en C() o CANT().');
        }
    }

    private static function eval(array $node, array $vars, callable $concept): float|string
    {
        switch ($node[0]) {
            case 'num':
                return (float) $node[1];
            case 'str':
                return (string) $node[1];
            case 'var':
                return (float) ($vars[$node[1]] ?? 0);
            case 'un':
                $v = self::num(self::eval($node[2], $vars, $concept));
                return $node[1] === '-' ? -$v : (float) !self::truthy($v);
            case 'bin':
                $op = $node[1];
                if ($op === 'Y') {
                    return (float) (self::truthy(self::eval($node[2], $vars, $concept)) && self::truthy(self::eval($node[3], $vars, $concept)));
                }
                if ($op === 'O') {
                    return (float) (self::truthy(self::eval($node[2], $vars, $concept)) || self::truthy(self::eval($node[3], $vars, $concept)));
                }
                $a = self::num(self::eval($node[2], $vars, $concept));
                $b = self::num(self::eval($node[3], $vars, $concept));
                return match ($op) {
                    '+' => $a + $b,
                    '-' => $a - $b,
                    '*' => $a * $b,
                    '/' => $b == 0.0 ? throw new FormulaException('División por cero.') : $a / $b,
                    '^' => $a ** $b,
                    '='  => (float) (abs($a - $b) < 1e-9),
                    '<>' => (float) (abs($a - $b) >= 1e-9),
                    '<'  => (float) ($a < $b),
                    '<=' => (float) ($a <= $b + 1e-9),
                    '>'  => (float) ($a > $b),
                    '>=' => (float) ($a >= $b - 1e-9),
                };
            case 'call':
                return self::call($node[1], $node[2], $vars, $concept);
        }
        throw new FormulaException('Expresión no válida.');
    }

    private static function call(string $name, array $args, array $vars, callable $concept): float
    {
        if ($name === 'SI') { // evaluación perezosa: solo se calcula la rama elegida
            return self::num(self::eval(self::truthy(self::eval($args[0], $vars, $concept)) ? $args[1] : $args[2], $vars, $concept));
        }
        if ($name === 'C' || $name === 'CANT') {
            return (float) $concept(self::codeFrom($args[0][1]), $name === 'C' ? 'amount' : 'quantity');
        }
        $v = array_map(fn ($a) => self::num(self::eval($a, $vars, $concept)), $args);
        return match ($name) {
            'MAX'       => max($v),
            'MIN'       => min($v),
            'REDONDEAR' => round($v[0], (int) ($v[1] ?? 2)),
            'TRUNCAR'   => self::truncate($v[0], (int) ($v[1] ?? 0)),
            'ABS'       => abs($v[0]),
            'PORC'      => $v[0] * $v[1] / 100,
        };
    }

    private static function codeFrom(mixed $raw): string
    {
        return is_float($raw) && floor($raw) === $raw ? (string) (int) $raw : (string) $raw;
    }

    private static function truncate(float $x, int $decimals): float
    {
        $f = 10 ** $decimals;
        return ($x < 0 ? ceil($x * $f) : floor($x * $f)) / $f;
    }

    private static function num(float|string $v): float
    {
        if (is_string($v)) {
            throw new FormulaException('No se puede operar con un texto.');
        }
        return $v;
    }

    private static function truthy(float|string $v): bool
    {
        return is_string($v) ? $v !== '' : abs($v) > 1e-9;
    }

    private static function arity(array $fn): string
    {
        return match (true) {
            $fn[1] === null => "al menos {$fn[0]}",
            $fn[0] === $fn[1] => (string) $fn[0],
            default => "entre {$fn[0]} y {$fn[1]}",
        };
    }

    private static function suggest(string $name, array $options): ?string
    {
        $best = null;
        $bestDist = 3;
        foreach ($options as $opt) {
            $d = levenshtein($name, $opt);
            if ($d < $bestDist || str_starts_with($opt, $name) && strlen($name) >= 3) {
                $best = $opt;
                $bestDist = min($d, $bestDist);
            }
        }
        return $best;
    }
}
