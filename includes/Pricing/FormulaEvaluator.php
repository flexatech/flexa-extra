<?php
namespace Flexa\Extra\Pricing;

defined( 'ABSPATH' ) || exit;

/**
 * Safe arithmetic evaluator for formula prices.
 *
 * Parses and evaluates a small expression grammar with a hand-written
 * recursive-descent parser — never `eval()`. Supported:
 *   - numbers (integer / decimal)
 *   - operators `+ - * /`, parentheses, unary minus/plus
 *   - variables `base` (priced entity), `qty` (line quantity)
 *   - field references `{field_id}` (a field's numeric value, else 0)
 *   - functions `round(x[, n])`, `min(a, ...)`, `max(a, ...)`
 *
 * Anything outside the grammar (unknown identifier, stray token, malformed
 * expression) makes evaluation fail, and {@see self::evaluate()} returns 0.0 so
 * a bad formula can never surface an error or a bogus charge on the storefront.
 *
 * This class is pure (no WordPress calls) and is mirrored byte-for-behaviour in
 * the storefront (`assets/frontend/flexa-extra.js`) and the builder preview
 * (`apps/admin/src/lib/preview/engine.ts`); keep the three in lockstep.
 */
final class FormulaEvaluator {

    /** @var list<array{type:string,value:string}> */
    private array $tokens = array();

    private int $pos = 0;

    /** @var array{base:float,qty:float,fields:array<string,float>} */
    private array $context;

    /**
     * @param array{base?:float,qty?:float,fields?:array<string,float>} $context
     */
    private function __construct( array $context ) {
        $this->context = array(
            'base'   => isset( $context['base'] ) ? (float) $context['base'] : 0.0,
            'qty'    => isset( $context['qty'] ) ? (float) $context['qty'] : 1.0,
            'fields' => $context['fields'] ?? array(),
        );
    }

    /**
     * Evaluate a formula string against a variable context.
     *
     * @param array{base?:float,qty?:float,fields?:array<string,float>} $context
     * @return float The result, or 0.0 when the formula is empty or invalid.
     */
    public static function evaluate( string $formula, array $context ): float {
        $formula = trim( $formula );
        if ( '' === $formula ) {
            return 0.0;
        }

        $engine = new self( $context );
        try {
            $engine->tokens = $engine->tokenize( $formula );
            $engine->pos    = 0;
            $result         = $engine->parse_expression();
            if ( $engine->pos !== count( $engine->tokens ) ) {
                return 0.0; // Trailing garbage.
            }
        } catch ( \Throwable $e ) {
            return 0.0;
        }

        if ( ! is_finite( $result ) ) {
            return 0.0;
        }
        return (float) $result;
    }

    /**
     * Static-analysis helper for the builder: is a formula parseable?
     * Uses a zeroed context so it validates shape only, not the result.
     */
    public static function is_valid( string $formula ): bool {
        $formula = trim( $formula );
        if ( '' === $formula ) {
            return false;
        }
        $engine = new self( array() );
        try {
            $engine->tokens = $engine->tokenize( $formula );
            $engine->pos    = 0;
            $engine->parse_expression();
            return $engine->pos === count( $engine->tokens );
        } catch ( \Throwable $e ) {
            return false;
        }
    }

    /**
     * @return list<array{type:string,value:string}>
     */
    private function tokenize( string $formula ): array {
        $tokens = array();
        $len    = strlen( $formula );
        $i      = 0;

        while ( $i < $len ) {
            $ch = $formula[ $i ];

            if ( ' ' === $ch || "\t" === $ch || "\n" === $ch || "\r" === $ch ) {
                $i++;
                continue;
            }

            if ( ( $ch >= '0' && $ch <= '9' ) || '.' === $ch ) {
                $num = '';
                while ( $i < $len && ( ( $formula[ $i ] >= '0' && $formula[ $i ] <= '9' ) || '.' === $formula[ $i ] ) ) {
                    $num .= $formula[ $i ];
                    $i++;
                }
                if ( ! is_numeric( $num ) ) {
                    throw new \RuntimeException( 'bad number' );
                }
                $tokens[] = array( 'type' => 'number', 'value' => $num );
                continue;
            }

            if ( ( $ch >= 'a' && $ch <= 'z' ) || ( $ch >= 'A' && $ch <= 'Z' ) || '_' === $ch ) {
                $ident = '';
                while ( $i < $len ) {
                    $c = $formula[ $i ];
                    if ( ( $c >= 'a' && $c <= 'z' ) || ( $c >= 'A' && $c <= 'Z' ) || ( $c >= '0' && $c <= '9' ) || '_' === $c ) {
                        $ident .= $c;
                        $i++;
                    } else {
                        break;
                    }
                }
                $tokens[] = array( 'type' => 'ident', 'value' => strtolower( $ident ) );
                continue;
            }

            if ( '{' === $ch ) {
                $ref = '';
                $i++;
                while ( $i < $len && '}' !== $formula[ $i ] ) {
                    $ref .= $formula[ $i ];
                    $i++;
                }
                if ( $i >= $len ) {
                    throw new \RuntimeException( 'unterminated field ref' );
                }
                $i++; // Consume '}'.
                $tokens[] = array( 'type' => 'field', 'value' => trim( $ref ) );
                continue;
            }

            if ( false !== strpos( '+-*/(),', $ch ) ) {
                $tokens[] = array( 'type' => 'op', 'value' => $ch );
                $i++;
                continue;
            }

            throw new \RuntimeException( 'unexpected char' );
        }

        return $tokens;
    }

    /**
     * @return array{type:string,value:string}|null
     */
    private function peek(): ?array {
        return $this->tokens[ $this->pos ] ?? null;
    }

    private function is_op( string $value ): bool {
        $tok = $this->peek();
        return null !== $tok && 'op' === $tok['type'] && $value === $tok['value'];
    }

    private function parse_expression(): float {
        $value = $this->parse_term();
        while ( $this->is_op( '+' ) || $this->is_op( '-' ) ) {
            $op = $this->tokens[ $this->pos ]['value'];
            $this->pos++;
            $rhs = $this->parse_term();
            $value = '+' === $op ? $value + $rhs : $value - $rhs;
        }
        return $value;
    }

    private function parse_term(): float {
        $value = $this->parse_factor();
        while ( $this->is_op( '*' ) || $this->is_op( '/' ) ) {
            $op = $this->tokens[ $this->pos ]['value'];
            $this->pos++;
            $rhs = $this->parse_factor();
            if ( '*' === $op ) {
                $value *= $rhs;
            } else {
                $value = 0.0 === (float) $rhs ? 0.0 : $value / $rhs;
            }
        }
        return $value;
    }

    private function parse_factor(): float {
        if ( $this->is_op( '-' ) ) {
            $this->pos++;
            return -$this->parse_factor();
        }
        if ( $this->is_op( '+' ) ) {
            $this->pos++;
            return $this->parse_factor();
        }
        return $this->parse_primary();
    }

    private function parse_primary(): float {
        $tok = $this->peek();
        if ( null === $tok ) {
            throw new \RuntimeException( 'unexpected end' );
        }

        if ( 'number' === $tok['type'] ) {
            $this->pos++;
            return (float) $tok['value'];
        }

        if ( 'field' === $tok['type'] ) {
            $this->pos++;
            $fields = $this->context['fields'];
            return isset( $fields[ $tok['value'] ] ) ? (float) $fields[ $tok['value'] ] : 0.0;
        }

        if ( 'op' === $tok['type'] && '(' === $tok['value'] ) {
            $this->pos++;
            $value = $this->parse_expression();
            if ( ! $this->is_op( ')' ) ) {
                throw new \RuntimeException( 'missing )' );
            }
            $this->pos++;
            return $value;
        }

        if ( 'ident' === $tok['type'] ) {
            $name = $tok['value'];
            $this->pos++;

            // Function call?
            if ( $this->is_op( '(' ) ) {
                $args = $this->parse_arguments();
                return $this->call_function( $name, $args );
            }

            if ( 'base' === $name ) {
                return $this->context['base'];
            }
            if ( 'qty' === $name ) {
                return $this->context['qty'];
            }
            throw new \RuntimeException( 'unknown identifier: ' . $name );
        }

        throw new \RuntimeException( 'unexpected token' );
    }

    /**
     * Parse `( expr (',' expr)* )`. Assumes the current token is '('.
     *
     * @return list<float>
     */
    private function parse_arguments(): array {
        $this->pos++; // Consume '('.
        $args = array();
        if ( $this->is_op( ')' ) ) {
            $this->pos++;
            return $args;
        }
        $args[] = $this->parse_expression();
        while ( $this->is_op( ',' ) ) {
            $this->pos++;
            $args[] = $this->parse_expression();
        }
        if ( ! $this->is_op( ')' ) ) {
            throw new \RuntimeException( 'missing ) in call' );
        }
        $this->pos++;
        return $args;
    }

    /**
     * @param list<float> $args
     */
    private function call_function( string $name, array $args ): float {
        switch ( $name ) {
            case 'round':
                if ( count( $args ) < 1 ) {
                    throw new \RuntimeException( 'round needs an argument' );
                }
                $precision = isset( $args[1] ) ? (int) $args[1] : 0;
                return round( $args[0], $precision );

            case 'min':
                if ( empty( $args ) ) {
                    throw new \RuntimeException( 'min needs an argument' );
                }
                return (float) min( $args );

            case 'max':
                if ( empty( $args ) ) {
                    throw new \RuntimeException( 'max needs an argument' );
                }
                return (float) max( $args );
        }
        throw new \RuntimeException( 'unknown function: ' . $name );
    }
}
