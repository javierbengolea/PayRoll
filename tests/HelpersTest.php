<?php
declare(strict_types=1);

namespace Tests;

use App\Core\Validator;
use App\Services\NumberToWords;
use PHPUnit\Framework\TestCase;

final class HelpersTest extends TestCase
{
    public function testCuilValidation(): void
    {
        $this->assertTrue(Validator::isCuil('20-12345678-6'));
        $this->assertTrue(Validator::isCuil('30712345671'));
        $this->assertFalse(Validator::isCuil('20-12345678-5'));
        $this->assertFalse(Validator::isCuil('1234'));
        $this->assertFalse(Validator::isCuil('99123456781'));
    }

    public function testCbuValidation(): void
    {
        $this->assertTrue(Validator::isCbu('2850590940090418135201'));
        $this->assertFalse(Validator::isCbu('2850590940090418135202'));
        $this->assertFalse(Validator::isCbu('123'));
    }

    public function testNumberNormalization(): void
    {
        $this->assertSame('1234567.89', Validator::normalizeNumber('1.234.567,89'));
        $this->assertSame('1500.5', Validator::normalizeNumber('1500.5'));
        $this->assertSame('8.33', Validator::normalizeNumber('8,33'));
        $this->assertSame('1200', Validator::normalizeNumber('$ 1200'));
        $this->assertSame('1100000', Validator::normalizeNumber('1.100.000'));
        $this->assertSame('1500', Validator::normalizeNumber('1.500'));
        $this->assertSame('0.125', Validator::normalizeNumber('0.125'));
        $this->assertSame('8.33', Validator::normalizeNumber('8.33'));
    }

    public function testValidatorRules(): void
    {
        $v = Validator::make(
            ['name' => '', 'email' => 'x@', 'amount' => 'abc', 'date' => '2026-02-30', 'type' => 'otro'],
            ['name' => 'required', 'email' => 'email', 'amount' => 'numeric', 'date' => 'date', 'type' => 'in:a,b']
        );
        $this->assertTrue($v->fails());
        $this->assertSame(['name', 'email', 'amount', 'date', 'type'], array_keys($v->errors()));
    }

    public function testMoneyInWords(): void
    {
        $this->assertSame('PESOS CERO CON 00/100', NumberToWords::money(0));
        $this->assertSame('PESOS CIEN CON 00/100', NumberToWords::money(100));
        $this->assertSame('PESOS MIL DOSCIENTOS TREINTA Y CUATRO CON 50/100', NumberToWords::money(1234.5));
        $this->assertSame('PESOS VEINTIÚN MIL CON 00/100', NumberToWords::money(21000));
        $this->assertSame('PESOS UN MILLÓN QUINIENTOS MIL CIENTO UNO CON 99/100', NumberToWords::money(1500101.99));
        $this->assertSame('PESOS TRESCIENTOS UN MIL SEISCIENTOS CATORCE CON 20/100', NumberToWords::money(301614.2));
        $this->assertSame('PESOS DOS MILLONES CON 01/100', NumberToWords::money(2000000.01));
    }

    public function testFormatHelpers(): void
    {
        $this->assertSame('$ 1.234,50', money(1234.5));
        $this->assertSame('20-12345678-6', fmt_cuil('20123456786'));
        $this->assertSame('8,33', num('8.3300', 4));
        $this->assertSame('11', num('11.00'));
        $this->assertSame('Marzo 2026', period_label(['year' => 2026, 'month' => 3, 'type' => 'mensual']));
        $this->assertSame('SAC 2do semestre 2026', period_label(['year' => 2026, 'month' => 12, 'type' => 'sac']));
        $this->assertSame('&lt;script&gt;', e('<script>'));
    }
}
