<?php
declare(strict_types=1);

namespace Tests;

use App\Services\PayrollCalculator;
use PHPUnit\Framework\TestCase;

final class PayrollCalculatorTest extends TestCase
{
    private function concept(string $code, string $type, string $mode, float $value = 0, ?string $base = null, int $order = 100, array $extra = []): array
    {
        return array_merge([
            'id' => (int) $code, 'code' => $code, 'name' => "Concepto $code", 'type' => $type,
            'calc_mode' => $mode, 'base' => $base, 'value' => $value, 'sort_order' => $order,
            'quantity' => null, 'amount' => null,
        ], $extra);
    }

    private function standardLines(): array
    {
        return [
            $this->concept('100', 'haber_rem', 'basico', 0, null, 10),
            $this->concept('110', 'haber_rem', 'antiguedad', 1, null, 20),
            $this->concept('120', 'haber_rem', 'porcentaje', 8.33, 'remunerativo', 30),
            $this->concept('500', 'descuento', 'porcentaje', 11, 'remunerativo', 500),
            $this->concept('510', 'descuento', 'porcentaje', 3, 'remunerativo', 510),
            $this->concept('520', 'descuento', 'porcentaje', 3, 'remunerativo', 520),
            $this->concept('800', 'contribucion', 'porcentaje', 10, 'remunerativo', 800),
        ];
    }

    private function amounts(array $result): array
    {
        return array_column($result['items'], 'amount', 'code');
    }

    public function testFullMonthWithSeniorityAndPresentismo(): void
    {
        $calc = new PayrollCalculator(200);
        $r = $calc->calculate(
            ['base_salary' => 1000000, 'hire_date' => '2021-03-15', 'termination_date' => null],
            ['year' => 2026, 'month' => 5, 'type' => 'mensual'],
            $this->standardLines()
        );
        $a = $this->amounts($r);

        $this->assertSame(5, $r['seniority_years']);
        $this->assertEquals(1000000.00, $a['100']);
        $this->assertEquals(50000.00, $a['110']);            // 5 años × 1%
        $this->assertEquals(87465.00, $a['120']);            // 8,33% de 1.050.000
        $this->assertEquals(1137465.00, $r['gross_rem']);
        $this->assertEquals(125121.15, $a['500']);           // 11%
        $this->assertEquals(34123.95, $a['510']);
        $this->assertEquals(34123.95, $a['520']);
        $this->assertEquals(193369.05, $r['deductions']);
        $this->assertEquals(944095.95, $r['net_pay']);
        $this->assertEquals(113746.50, $r['employer_contrib']);
    }

    public function testMidMonthHireIsProrated(): void
    {
        $calc = new PayrollCalculator();
        $r = $calc->calculate(
            ['base_salary' => 900000, 'hire_date' => '2026-09-16', 'termination_date' => null],
            ['year' => 2026, 'month' => 9, 'type' => 'mensual'],
            [$this->concept('100', 'haber_rem', 'basico')]
        );
        // Septiembre tiene 30 días; trabajó del 16 al 30 = 15 días
        $this->assertEquals(450000.00, $r['gross_rem']);
        $this->assertEquals(15.0, $r['items'][0]['quantity']);
    }

    public function testTerminationProratesAndNoPayAfterExit(): void
    {
        $calc = new PayrollCalculator();
        $r = $calc->calculate(
            ['base_salary' => 620000, 'hire_date' => '2020-01-01', 'termination_date' => '2026-01-10'],
            ['year' => 2026, 'month' => 1, 'type' => 'mensual'],
            [$this->concept('100', 'haber_rem', 'basico')]
        );
        $this->assertEquals(200000.00, $r['gross_rem']); // 10 de 31 días

        $r = $calc->calculate(
            ['base_salary' => 620000, 'hire_date' => '2020-01-01', 'termination_date' => '2025-12-31'],
            ['year' => 2026, 'month' => 1, 'type' => 'mensual'],
            [$this->concept('100', 'haber_rem', 'basico')]
        );
        $this->assertSame([], $r['items']);
    }

    public function testOvertimeAndAbsences(): void
    {
        $calc = new PayrollCalculator(200);
        $r = $calc->calculate(
            ['base_salary' => 600000, 'hire_date' => '2026-01-01', 'termination_date' => null],
            ['year' => 2026, 'month' => 4, 'type' => 'mensual'],
            [
                $this->concept('100', 'haber_rem', 'basico', 0, null, 10),
                $this->concept('140', 'haber_rem', 'dias', -100, null, 25, ['quantity' => 2]),
                $this->concept('130', 'haber_rem', 'horas', 150, null, 40, ['quantity' => 8]),
            ]
        );
        $a = $this->amounts($r);
        $this->assertEquals(-40000.00, $a['140']);   // 2 × 600.000/30
        $this->assertEquals(36000.00, $a['130']);    // 8 × 3.000 × 1,5
        $this->assertEquals(596000.00, $r['gross_rem']);
    }

    public function testManualAmountOverridesCalculationAndZeroIsSkipped(): void
    {
        $calc = new PayrollCalculator();
        $r = $calc->calculate(
            ['base_salary' => 500000, 'hire_date' => '2024-01-01', 'termination_date' => null],
            ['year' => 2026, 'month' => 2, 'type' => 'mensual'],
            [
                $this->concept('100', 'haber_rem', 'basico', 0, null, 10),
                $this->concept('150', 'haber_rem', 'fijo', 0, null, 50, ['amount' => 75000]),
                $this->concept('310', 'haber_no_rem', 'fijo', 0, null, 75),         // sin importe: no aparece
                $this->concept('540', 'descuento', 'fijo', 0, null, 540, ['amount' => 100000]),
            ]
        );
        $a = $this->amounts($r);
        $this->assertArrayNotHasKey('310', $a);
        $this->assertEquals(575000.00, $r['gross_rem']);
        $this->assertEquals(475000.00, $r['net_pay']);
    }

    public function testNonRemunerativeIsNotSubjectToDeductions(): void
    {
        $calc = new PayrollCalculator();
        $r = $calc->calculate(
            ['base_salary' => 400000, 'hire_date' => '2025-06-01', 'termination_date' => null],
            ['year' => 2026, 'month' => 3, 'type' => 'mensual'],
            [
                $this->concept('100', 'haber_rem', 'basico', 0, null, 10),
                $this->concept('300', 'haber_no_rem', 'fijo', 50000, null, 70),
                $this->concept('500', 'descuento', 'porcentaje', 11, 'remunerativo', 500),
            ]
        );
        $this->assertEquals(44000.00, $this->amounts($r)['500']);
        $this->assertEquals(406000.00, $r['net_pay']);
    }

    public function testSacFullAndProportional(): void
    {
        $calc = new PayrollCalculator();
        $sac = [$this->concept('200', 'haber_rem', 'sac', 50, null, 60)];

        $full = $calc->calculate(
            ['base_salary' => 0, 'hire_date' => '2020-01-01', 'termination_date' => null],
            ['year' => 2026, 'month' => 6, 'type' => 'sac'], $sac, 1200000
        );
        $this->assertEquals(600000.00, $full['gross_rem']);

        // Ingresó el 1/4: 91 de 181 días del primer semestre de 2026
        $partial = $calc->calculate(
            ['base_salary' => 0, 'hire_date' => '2026-04-01', 'termination_date' => null],
            ['year' => 2026, 'month' => 6, 'type' => 'sac'], $sac, 1200000
        );
        $this->assertEquals(round(600000 * 91 / 181, 2), $partial['gross_rem']);
    }

    public function testConceptOrderIsRespectedForPercentageBase(): void
    {
        $calc = new PayrollCalculator();
        // El presentismo (orden 30) no debe incluir el adicional con orden 50
        $r = $calc->calculate(
            ['base_salary' => 100000, 'hire_date' => '2026-01-01', 'termination_date' => null],
            ['year' => 2026, 'month' => 1, 'type' => 'mensual'],
            [
                $this->concept('150', 'haber_rem', 'fijo', 20000, null, 50),
                $this->concept('120', 'haber_rem', 'porcentaje', 10, 'remunerativo', 30),
                $this->concept('100', 'haber_rem', 'basico', 0, null, 10),
            ]
        );
        $this->assertEquals(10000.00, $this->amounts($r)['120']);
        $this->assertEquals(['100', '120', '150'], array_column($r['items'], 'code'));
    }
}
