<?php
declare(strict_types=1);

namespace App\Services;

use App\Services\Formula\Formula;
use App\Services\Formula\FormulaException;
use DateTimeImmutable;

/**
 * Motor de cálculo de un recibo. Es una clase pura (no accede a la base de datos),
 * por lo que puede probarse de forma aislada.
 *
 * Orden de evaluación:
 *   1. Haberes (remunerativos y no remunerativos) según sort_order.
 *      Un porcentaje sobre "remunerativo" usa lo acumulado HASTA ese concepto
 *      (así el presentismo se calcula sobre básico + antigüedad).
 *   2. Descuentos, sobre los totales finales de haberes.
 *   3. Contribuciones patronales (costo de la empresa, no afectan el neto).
 */
final class PayrollCalculator
{
    public function __construct(private int $hoursDivisor = 200)
    {
    }

    /**
     * @param array $employee ['base_salary' => float, 'hire_date' => 'Y-m-d', 'termination_date' => ?string, 'birth_date' => ?string]
     * @param array $period   ['year' => int, 'month' => int, 'type' => 'mensual'|'sac']
     * @param array $lines    Conceptos a liquidar. Cada uno con las claves de la tabla `concepts`
     *                        más, opcionalmente, 'quantity' y 'amount' (importe informado que
     *                        reemplaza al cálculo).
     * @param float $sacBestSalary Mejor remuneración mensual del semestre (solo para SAC).
     */
    public function calculate(array $employee, array $period, array $lines, float $sacBestSalary = 0.0): array
    {
        $basic = (float) $employee['base_salary'];
        $hire = new DateTimeImmutable($employee['hire_date']);
        $termination = !empty($employee['termination_date']) ? new DateTimeImmutable($employee['termination_date']) : null;

        $monthStart = new DateTimeImmutable(sprintf('%04d-%02d-01', $period['year'], $period['month']));
        $monthEnd = $monthStart->modify('last day of this month');
        $seniority = self::seniorityYears($hire, $monthEnd);

        $order = ['haber_rem' => 1, 'haber_no_rem' => 1, 'descuento' => 2, 'contribucion' => 3];
        usort($lines, static fn ($a, $b) =>
            [$order[$a['type']], (int) $a['sort_order'], $a['code']] <=> [$order[$b['type']], (int) $b['sort_order'], $b['code']]);

        $rem = 0.0;
        $noRem = 0.0;
        $deductions = 0.0;
        $contrib = 0.0;
        $items = [];
        $computed = []; // código => ['amount' => x, 'quantity' => y] para C() y CANT()

        $monthDays = (int) $monthEnd->format('j');
        $monthWorked = self::workedDays($monthStart, $monthEnd, $hire, $termination);
        [$semStart, $semEnd] = self::semester((int) $period['year'], (int) $period['month']);
        $semesterDays = (int) $semStart->diff($semEnd)->days + 1;
        $isSac = ($period['type'] ?? 'mensual') === 'sac';
        $age = !empty($employee['birth_date']) ? self::seniorityYears(new DateTimeImmutable($employee['birth_date']), $monthEnd) : 0;

        foreach ($lines as $line) {
            $value = (float) $line['value'];
            $qty = isset($line['quantity']) && $line['quantity'] !== '' ? (float) $line['quantity'] : null;
            $rate = null;
            $amount = 0.0;

            if (isset($line['amount']) && $line['amount'] !== null && $line['amount'] !== '') {
                // Importe informado manualmente (novedad)
                $amount = (float) $line['amount'];
            } else {
                switch ($line['calc_mode']) {
                    case 'basico':
                        $amount = $monthWorked >= $monthDays ? $basic : $basic * $monthWorked / $monthDays;
                        $qty = $monthWorked >= $monthDays ? 30 : $monthWorked;
                        break;
                    case 'fijo':
                        $amount = $value;
                        break;
                    case 'porcentaje':
                        $base = match ($line['base'] ?? 'remunerativo') {
                            'basico'          => $basic,
                            'no_remunerativo' => $noRem,
                            'bruto'           => $rem + $noRem,
                            default           => $rem,
                        };
                        $amount = $base * $value / 100;
                        $rate = $value;
                        break;
                    case 'cantidad':
                        $qty ??= 1;
                        $amount = $qty * $value;
                        $rate = $value;
                        break;
                    case 'horas':
                        $qty ??= 0;
                        $amount = $qty * ($basic / max(1, $this->hoursDivisor)) * $value / 100;
                        $rate = $value;
                        break;
                    case 'dias':
                        $qty ??= 0;
                        $amount = $qty * ($basic / 30) * $value / 100;
                        $rate = $value;
                        break;
                    case 'antiguedad':
                        $qty = $seniority;
                        $amount = $basic * $value / 100 * $seniority;
                        $rate = $value;
                        break;
                    case 'sac':
                        $worked = self::workedDays($semStart, $semEnd, $hire, $termination);
                        $amount = $sacBestSalary * $value / 100 * ($worked / $semesterDays);
                        $qty = $worked;
                        $rate = $value;
                        break;
                    case 'formula':
                        $vars = [
                            'BASICO'             => $basic,
                            'ANTIGUEDAD'         => $seniority,
                            'CANTIDAD'           => $qty ?? 0,
                            'VALOR'              => $value,
                            'VALOR_HORA'         => $basic / max(1, $this->hoursDivisor),
                            'VALOR_DIA'          => $basic / 30,
                            'DIAS_TRABAJADOS'    => $isSac ? self::workedDays($semStart, $semEnd, $hire, $termination) : $monthWorked,
                            'DIAS_MES'           => $monthDays,
                            'REMUNERATIVO'       => $rem,
                            'NO_REMUNERATIVO'    => $noRem,
                            'BRUTO'              => $rem + $noRem,
                            'DESCUENTOS'         => $deductions,
                            'MEJOR_REMUNERACION' => $sacBestSalary,
                            'DIAS_SEMESTRE'      => $semesterDays,
                            'EDAD'               => $age,
                            'MES'                => (int) $period['month'],
                            'ANIO'               => (int) $period['year'],
                            'ES_SAC'             => $isSac ? 1 : 0,
                        ];
                        try {
                            $amount = Formula::evaluate(
                                Formula::compile((string) ($line['formula'] ?? '')),
                                $vars,
                                static fn (string $code, string $field) => (float) ($computed[$code][$field] ?? 0)
                            );
                        } catch (FormulaException $e) {
                            throw new FormulaException("Concepto {$line['code']} ({$line['name']}): " . $e->getMessage(), 0, $e);
                        }
                        break;
                }
            }

            $amount = round($amount, 2);
            if (abs($amount) < 0.005) {
                continue;
            }
            $computed[$line['code']]['amount'] = ($computed[$line['code']]['amount'] ?? 0) + $amount;
            $computed[$line['code']]['quantity'] = ($computed[$line['code']]['quantity'] ?? 0) + (float) ($qty ?? 0);

            match ($line['type']) {
                'haber_rem'    => $rem += $amount,
                'haber_no_rem' => $noRem += $amount,
                'descuento'    => $deductions += $amount,
                'contribucion' => $contrib += $amount,
            };

            $items[] = [
                'concept_id' => $line['id'] ?? null,
                'code'       => $line['code'],
                'name'       => $line['name'],
                'type'       => $line['type'],
                'quantity'   => $qty,
                'rate'       => $rate,
                'amount'     => $amount,
                'sort_order' => (int) $line['sort_order'],
            ];
        }

        $rem = round($rem, 2);
        $noRem = round($noRem, 2);
        $deductions = round($deductions, 2);

        return [
            'items'            => $items,
            'seniority_years'  => $seniority,
            'gross_rem'        => $rem,
            'gross_no_rem'     => $noRem,
            'deductions'       => $deductions,
            'net_pay'          => round($rem + $noRem - $deductions, 2),
            'employer_contrib' => round($contrib, 2),
        ];
    }

    public static function seniorityYears(DateTimeImmutable $hire, DateTimeImmutable $at): int
    {
        return $hire > $at ? 0 : (int) $hire->diff($at)->y;
    }

    /** Días calendario trabajados dentro de [from, to] según ingreso y egreso. */
    public static function workedDays(DateTimeImmutable $from, DateTimeImmutable $to, DateTimeImmutable $hire, ?DateTimeImmutable $termination): int
    {
        $start = $hire > $from ? $hire : $from;
        $end = ($termination !== null && $termination < $to) ? $termination : $to;
        return $start > $end ? 0 : (int) $start->diff($end)->days + 1;
    }

    /** @return DateTimeImmutable[] [inicio, fin] del semestre al que pertenece el mes. */
    public static function semester(int $year, int $month): array
    {
        return $month <= 6
            ? [new DateTimeImmutable("$year-01-01"), new DateTimeImmutable("$year-06-30")]
            : [new DateTimeImmutable("$year-07-01"), new DateTimeImmutable("$year-12-31")];
    }
}
