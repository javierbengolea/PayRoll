<?php
declare(strict_types=1);

namespace App\Services;

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
     * @param array $employee ['base_salary' => float, 'hire_date' => 'Y-m-d', 'termination_date' => ?string]
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
                        $days = self::workedDays($monthStart, $monthEnd, $hire, $termination);
                        $monthDays = (int) $monthEnd->format('j');
                        $amount = $days >= $monthDays ? $basic : $basic * $days / $monthDays;
                        $qty = $days >= $monthDays ? 30 : $days;
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
                        [$semStart, $semEnd] = self::semester((int) $period['year'], (int) $period['month']);
                        $worked = self::workedDays($semStart, $semEnd, $hire, $termination);
                        $total = (int) $semStart->diff($semEnd)->days + 1;
                        $amount = $sacBestSalary * $value / 100 * ($worked / $total);
                        $qty = $worked;
                        $rate = $value;
                        break;
                }
            }

            $amount = round($amount, 2);
            if (abs($amount) < 0.005) {
                continue;
            }

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
