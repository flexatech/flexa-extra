<?php
namespace Flexa\Extra\Tests\Unit;

use Flexa\Extra\Pricing\FormulaEvaluator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The formula evaluator is the authoritative side of formula pricing; the
 * storefront and builder mirror it. These tests pin the grammar, the variable
 * resolution, and the invariant that a malformed formula is worth 0.0 and never
 * throws (so a bad formula can never surface an error or a bogus charge).
 */
#[CoversClass( FormulaEvaluator::class )]
final class FormulaEvaluatorTest extends TestCase {

    /**
     * @param array{base?:float,qty?:float,fields?:array<string,float>} $over
     * @return array{base:float,qty:float,fields:array<string,float>}
     */
    private function ctx( array $over = array() ): array {
        return array_merge( array( 'base' => 100.0, 'qty' => 1.0, 'fields' => array() ), $over );
    }

    public function test_arithmetic_precedence_and_parentheses(): void {
        $this->assertSame( 14.0, FormulaEvaluator::evaluate( '2 + 3 * 4', $this->ctx() ) );
        $this->assertSame( 20.0, FormulaEvaluator::evaluate( '(2 + 3) * 4', $this->ctx() ) );
        $this->assertSame( 2.5, FormulaEvaluator::evaluate( '10 / 4', $this->ctx() ) );
        $this->assertSame( -3.0, FormulaEvaluator::evaluate( '-5 + 2', $this->ctx() ) );
    }

    public function test_resolves_base_qty_and_field_variables(): void {
        $this->assertSame( 25.0, FormulaEvaluator::evaluate( 'base * 0.1', $this->ctx( array( 'base' => 250.0 ) ) ) );
        $this->assertSame( 60.0, FormulaEvaluator::evaluate( 'base * qty', $this->ctx( array( 'base' => 20.0, 'qty' => 3.0 ) ) ) );
        $this->assertSame(
            20.0,
            FormulaEvaluator::evaluate( '{width} * {height}', $this->ctx( array( 'fields' => array( 'width' => 4.0, 'height' => 5.0 ) ) ) )
        );
        // Missing field refs resolve to 0.
        $this->assertSame( 7.0, FormulaEvaluator::evaluate( '{missing} + 7', $this->ctx() ) );
    }

    public function test_functions_round_min_max(): void {
        $this->assertSame( 2.35, FormulaEvaluator::evaluate( 'round(2.345, 2)', $this->ctx() ) );
        $this->assertSame( 3.0, FormulaEvaluator::evaluate( 'round(2.5)', $this->ctx() ) );
        $this->assertSame( 1.0, FormulaEvaluator::evaluate( 'min(3, 8, 1)', $this->ctx() ) );
        $this->assertSame( 5.0, FormulaEvaluator::evaluate( 'max(2, base)', $this->ctx( array( 'base' => 5.0 ) ) ) );
    }

    public function test_malformed_input_is_zero_and_never_throws(): void {
        $this->assertSame( 0.0, FormulaEvaluator::evaluate( '', $this->ctx() ) );
        $this->assertSame( 0.0, FormulaEvaluator::evaluate( '2 +', $this->ctx() ) );
        $this->assertSame( 0.0, FormulaEvaluator::evaluate( '2 3', $this->ctx() ) );
        $this->assertSame( 0.0, FormulaEvaluator::evaluate( 'foo(2)', $this->ctx() ) );
        $this->assertSame( 0.0, FormulaEvaluator::evaluate( 'nope', $this->ctx() ) );
        $this->assertSame( 0.0, FormulaEvaluator::evaluate( '1 / 0', $this->ctx() ) ); // Guarded division.
    }

    public function test_is_valid_reports_parseability(): void {
        $this->assertTrue( FormulaEvaluator::is_valid( 'base * 0.1 + {x}' ) );
        $this->assertTrue( FormulaEvaluator::is_valid( 'round(base, 2)' ) );
        $this->assertFalse( FormulaEvaluator::is_valid( '' ) );
        $this->assertFalse( FormulaEvaluator::is_valid( '2 +' ) );
        $this->assertFalse( FormulaEvaluator::is_valid( 'log(2)' ) );
    }
}
