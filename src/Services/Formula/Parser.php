<?php
declare(strict_types=1);

namespace App\Services\Formula;

/**
 * Convierte el texto de una fórmula en un árbol (AST). No ejecuta nada:
 * solo reconoce números, variables, operadores y llamadas a funciones.
 *
 * Sintaxis (estilo planilla de cálculo en español):
 *   - Decimales con coma o punto: 8,33 · 0.0833 (sin separador de miles)
 *   - Argumentos separados por punto y coma: SI(ANTIGUEDAD > 5; 1000; 0)
 *   - Operadores: + - * / ^   =  <>  !=  <  <=  >  >=   Y  O  NO (o AND OR NOT)
 *   - Textos entre comillas para códigos: C("120")  (también C(120))
 *
 * Nodos del árbol:
 *   ['num', float] ['str', string] ['var', NOMBRE] ['call', NOMBRE, [args]]
 *   ['bin', op, izq, der] ['un', op, expr]
 */
final class Parser
{
    private array $tokens = [];
    private int $pos = 0;
    private string $source = '';

    public function parse(string $source): array
    {
        $this->source = $source;
        $this->tokens = $this->tokenize($source);
        $this->pos = 0;
        if ($this->peek()['type'] === 'eof') {
            throw new FormulaException('La fórmula está vacía.');
        }
        $ast = $this->parseOr();
        $tok = $this->peek();
        if ($tok['type'] !== 'eof') {
            throw $this->error("No se esperaba «{$tok['text']}»", $tok);
        }
        return $ast;
    }

    /** Pasa un identificador a mayúsculas y sin acentos: "antigüedad" -> "ANTIGUEDAD". */
    public static function normalizeName(string $name): string
    {
        $name = mb_strtoupper($name, 'UTF-8');
        return strtr($name, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N']);
    }

    // ------------------------------------------------------------------ lexer

    private function tokenize(string $s): array
    {
        $tokens = [];
        $len = strlen($s);
        $i = 0;
        while ($i < $len) {
            $ch = $s[$i];
            if (ctype_space($ch)) {
                $i++;
                continue;
            }
            $start = $i;
            if (ctype_digit($ch) || ($ch === '.' || $ch === ',') && $i + 1 < $len && ctype_digit($s[$i + 1])) {
                $num = '';
                $sep = false;
                while ($i < $len && (ctype_digit($s[$i]) || (!$sep && ($s[$i] === '.' || $s[$i] === ',') && $i + 1 < $len && ctype_digit($s[$i + 1])))) {
                    if ($s[$i] === '.' || $s[$i] === ',') {
                        $sep = true;
                        $num .= '.';
                    } else {
                        $num .= $s[$i];
                    }
                    $i++;
                }
                $tokens[] = ['type' => 'num', 'value' => (float) $num, 'text' => substr($s, $start, $i - $start), 'pos' => $start];
                continue;
            }
            if (preg_match('/\G[\p{L}_][\p{L}\p{N}_]*/u', $s, $m, 0, $i)) {
                $i += strlen($m[0]);
                $upper = self::normalizeName($m[0]);
                $type = match ($upper) {
                    'Y', 'AND' => 'and',
                    'O', 'OR' => 'or',
                    'NO', 'NOT' => 'not',
                    default => 'ident',
                };
                $tokens[] = ['type' => $type, 'value' => $upper, 'text' => $m[0], 'pos' => $start];
                continue;
            }
            if ($ch === '"' || $ch === "'") {
                $end = strpos($s, $ch, $i + 1);
                if ($end === false) {
                    throw $this->error('Falta cerrar las comillas', ['pos' => $i]);
                }
                $tokens[] = ['type' => 'str', 'value' => substr($s, $i + 1, $end - $i - 1), 'text' => substr($s, $i, $end - $i + 1), 'pos' => $i];
                $i = $end + 1;
                continue;
            }
            $two = substr($s, $i, 2);
            if (in_array($two, ['<=', '>=', '<>', '!=', '==', '&&', '||'], true)) {
                $type = match ($two) { '&&' => 'and', '||' => 'or', default => 'cmp' };
                $tokens[] = ['type' => $type, 'value' => $two === '==' ? '=' : ($two === '!=' ? '<>' : $two), 'text' => $two, 'pos' => $i];
                $i += 2;
                continue;
            }
            $type = match ($ch) {
                '+', '-', '*', '/', '^' => 'op',
                '<', '>', '=' => 'cmp',
                '(' => 'lparen',
                ')' => 'rparen',
                ';' => 'sep',
                '!' => 'not',
                default => null,
            };
            if ($type === null) {
                $hint = $ch === ',' ? ' — los argumentos se separan con ; y los decimales con coma o punto' : '';
                throw $this->error("Carácter no válido «{$ch}»$hint", ['pos' => $i]);
            }
            $tokens[] = ['type' => $type, 'value' => $ch, 'text' => $ch, 'pos' => $i];
            $i++;
        }
        $tokens[] = ['type' => 'eof', 'value' => null, 'text' => 'fin de la fórmula', 'pos' => $len];
        return $tokens;
    }

    // ----------------------------------------------------------------- parser

    private function parseOr(): array
    {
        $left = $this->parseAnd();
        while ($this->peek()['type'] === 'or') {
            $this->next();
            $left = ['bin', 'O', $left, $this->parseAnd()];
        }
        return $left;
    }

    private function parseAnd(): array
    {
        $left = $this->parseNot();
        while ($this->peek()['type'] === 'and') {
            $this->next();
            $left = ['bin', 'Y', $left, $this->parseNot()];
        }
        return $left;
    }

    private function parseNot(): array
    {
        if ($this->peek()['type'] === 'not') {
            $this->next();
            return ['un', 'NO', $this->parseNot()];
        }
        return $this->parseComparison();
    }

    private function parseComparison(): array
    {
        $left = $this->parseAdditive();
        while ($this->peek()['type'] === 'cmp') {
            $op = $this->next()['value'];
            $left = ['bin', $op, $left, $this->parseAdditive()];
        }
        return $left;
    }

    private function parseAdditive(): array
    {
        $left = $this->parseMultiplicative();
        while ($this->peek()['type'] === 'op' && in_array($this->peek()['value'], ['+', '-'], true)) {
            $op = $this->next()['value'];
            $left = ['bin', $op, $left, $this->parseMultiplicative()];
        }
        return $left;
    }

    private function parseMultiplicative(): array
    {
        $left = $this->parseUnary();
        while ($this->peek()['type'] === 'op' && in_array($this->peek()['value'], ['*', '/'], true)) {
            $op = $this->next()['value'];
            $left = ['bin', $op, $left, $this->parseUnary()];
        }
        return $left;
    }

    private function parseUnary(): array
    {
        $tok = $this->peek();
        if ($tok['type'] === 'op' && ($tok['value'] === '-' || $tok['value'] === '+')) {
            $this->next();
            $operand = $this->parseUnary();
            return $tok['value'] === '-' ? ['un', '-', $operand] : $operand;
        }
        return $this->parsePower();
    }

    private function parsePower(): array
    {
        $base = $this->parsePrimary();
        if ($this->peek()['type'] === 'op' && $this->peek()['value'] === '^') {
            $this->next();
            return ['bin', '^', $base, $this->parseUnary()]; // asociativo a derecha
        }
        return $base;
    }

    private function parsePrimary(): array
    {
        $tok = $this->next();
        switch ($tok['type']) {
            case 'num':
                return ['num', $tok['value']];
            case 'str':
                return ['str', $tok['value']];
            case 'lparen':
                $expr = $this->parseOr();
                $this->expect('rparen', 'Falta cerrar un paréntesis');
                return $expr;
            case 'ident':
                if ($this->peek()['type'] === 'lparen') {
                    $this->next();
                    $args = [];
                    if ($this->peek()['type'] !== 'rparen') {
                        do {
                            $args[] = $this->parseOr();
                        } while ($this->peek()['type'] === 'sep' && $this->next());
                    }
                    $this->expect('rparen', "Falta cerrar el paréntesis de {$tok['value']}( — los argumentos se separan con ;");
                    return ['call', $tok['value'], $args];
                }
                return ['var', $tok['value']];
            case 'eof':
                throw $this->error('La fórmula termina de forma incompleta', $tok);
            default:
                throw $this->error("No se esperaba «{$tok['text']}»", $tok);
        }
    }

    private function peek(): array
    {
        return $this->tokens[$this->pos];
    }

    private function next(): array
    {
        $tok = $this->tokens[$this->pos];
        if ($tok['type'] !== 'eof') {
            $this->pos++;
        }
        return $tok;
    }

    private function expect(string $type, string $message): void
    {
        if ($this->peek()['type'] !== $type) {
            throw $this->error($message, $this->peek());
        }
        $this->next();
    }

    private function error(string $message, array $tok): FormulaException
    {
        $col = mb_strlen(substr($this->source, 0, $tok['pos'])) + 1;
        return new FormulaException("$message (posición $col).");
    }
}
