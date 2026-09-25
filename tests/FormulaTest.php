<?php
declare(strict_types=1);

namespace Tests;

use App\Services\Formula\Formula;
use App\Services\Formula\FormulaException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FormulaTest extends TestCase
{
    private function calc(string $formula, array $vars = [], array $concepts = []): float
    {
        return Formula::evaluate(
            Formula::compile($formula),
            $vars,
            fn (string $code, string $field) => $concepts[$code][$field] ?? 0.0
        );
    }

    public static function arithmetic(): array
    {
        return [
            'suma y producto'        => ['2 + 3 * 4', 14],
            'paréntesis'             => ['(2 + 3) * 4', 20],
            'decimal con coma'       => ['1000 * 8,33 / 100', 83.3],
            'decimal con punto'      => ['1000 * 0.0833', 83.3],
            'potencia'               => ['2 ^ 3 ^ 2', 512],     // asociativa a derecha
            'menos unario'           => ['-2 ^ 2', -4],
            'resta encadenada'       => ['10 - 3 - 2', 5],
            'división encadenada'    => ['100 / 5 / 2', 10],
            'comparación verdadera'  => ['3 > 2', 1],
            'comparación falsa'      => ['3 <= 2', 0],
            'distinto'               => ['3 <> 2', 1],
            'Y / O / NO'             => ['(1 Y 0) O NO 0', 1],
            'AND / OR en inglés'     => ['1 AND 1 OR 0', 1],
            'MAX y MIN'              => ['MAX(1; 7; 3) + MIN(4; 2)', 9],
            'REDONDEAR por defecto'  => ['REDONDEAR(10 / 3)', 3.33],
            'REDONDEAR a 0'          => ['REDONDEAR(2,5; 0)', 3],
            'TRUNCAR'                => ['TRUNCAR(9,99)', 9],
            'TRUNCAR negativo'       => ['TRUNCAR(-9,99; 1)', -9.9],
            'ABS'                    => ['ABS(-5)', 5],
            'PORC'                   => ['PORC(200000; 11)', 22000],
        ];
    }

    #[DataProvider('arithmetic')]
    public function testArithmetic(string $formula, float $expected): void
    {
        $this->assertEqualsWithDelta($expected, $this->calc($formula), 0.0001);
    }

    public function testVariablesAreCaseAndAccentInsensitive(): void
    {
        $vars = ['BASICO' => 1000000, 'ANTIGUEDAD' => 7];
        $this->assertEquals(1070000, $this->calc('basico * (1 + antigüedad / 100)', $vars));
        $this->assertEquals(1070000, $this->calc('Básico * (1 + Antiguedad / 100)', $vars));
    }

    public function testConditionalsAreLazy(): void
    {
        // La rama no elegida tiene una división por cero y no debe evaluarse
        $this->assertEquals(5, $this->calc('SI(ANTIGUEDAD = 0; 5; BASICO / ANTIGUEDAD)', ['ANTIGUEDAD' => 0, 'BASICO' => 10]));
    }

    public function testConceptReferences(): void
    {
        $concepts = ['140' => ['amount' => -30000.0, 'quantity' => 1.0], '100' => ['amount' => 900000.0, 'quantity' => 30.0]];
        $presentismo = 'SI(C("140") = 0; REMUNERATIVO * 8,33 / 100; 0)';

        $this->assertEquals(0, $this->calc($presentismo, ['REMUNERATIVO' => 870000], $concepts));
        $this->assertEqualsWithDelta(74970, $this->calc($presentismo, ['REMUNERATIVO' => 900000], []), 0.001);
        $this->assertEquals(30, $this->calc('CANT(100)', [], $concepts));
        $this->assertEquals(900000, $this->calc("C('100')", [], $concepts));
        $this->assertSame(['140'], Formula::referencedConcepts(Formula::compile($presentismo)));
    }

    public static function invalid(): array
    {
        return [
            'vacía'                  => ['', 'vacía'],
            'variable desconocida'   => ['BASCO * 2', '¿Quisiste decir BASICO?'],
            'función desconocida'    => ['TOPE(1; 2)', 'Función desconocida'],
            'paréntesis sin cerrar'  => ['(1 + 2', 'Falta cerrar un paréntesis'],
            'coma como separador'    => ['MAX(1, 2)', 'se separan con ;'],
            'cantidad de argumentos' => ['SI(1; 2)', 'SI recibe 3'],
            'operador colgado'       => ['1 +', 'incompleta'],
            'carácter inválido'      => ['1 # 2', 'Carácter no válido'],
            'C sin código'           => ['C(BASICO)', 'espera un código'],
            'texto suelto'           => ['"hola" + 1', 'Los textos entre comillas'],
            'comillas sin cerrar'    => ['C("120)', 'Falta cerrar las comillas'],
        ];
    }

    #[DataProvider('invalid')]
    public function testInvalidFormulasHaveClearMessages(string $formula, string $message): void
    {
        $error = Formula::validate($formula);
        $this->assertNotNull($error);
        $this->assertStringContainsString($message, $error);
    }

    public function testDivisionByZeroAtRuntime(): void
    {
        $this->expectException(FormulaException::class);
        $this->expectExceptionMessage('División por cero');
        $this->calc('BASICO / ANTIGUEDAD', ['BASICO' => 1, 'ANTIGUEDAD' => 0]);
    }

    public function testNoCodeExecution(): void
    {
        // Nada de PHP: identificadores y llamadas desconocidas se rechazan
        $this->assertNotNull(Formula::validate('system("ls")'));
        $this->assertNotNull(Formula::validate('phpinfo()'));
        $this->assertNotNull(Formula::validate('$x = 1'));
    }
}
