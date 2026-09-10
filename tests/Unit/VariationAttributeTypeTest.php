<?php

declare(strict_types=1);

namespace Flexa\Extra\Tests\Unit;

use Flexa\Extra\Variations\AttributeType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Covers the variation-swatch attribute map (Pha 0): valid types persist, `off`
 * clears the mapping, and a corrupt/hostile stored option is coerced to a clean
 * map with only real taxonomies and real swatch types.
 */
#[CoversClass( AttributeType::class )]
final class VariationAttributeTypeTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['fx_options'] = array();
	}

	public function test_default_type_is_off_when_unset(): void {
		$this->assertSame( AttributeType::TYPE_OFF, AttributeType::get_type( 'pa_color' ) );
		$this->assertSame( array(), AttributeType::all() );
	}

	public function test_set_valid_type_persists(): void {
		AttributeType::set_type( 'pa_color', 'image' );

		$this->assertSame( 'image', AttributeType::get_type( 'pa_color' ) );
		$this->assertSame( array( 'pa_color' => 'image' ), AttributeType::all() );
	}

	public function test_set_off_removes_mapping(): void {
		AttributeType::set_type( 'pa_color', 'color' );
		AttributeType::set_type( 'pa_color', AttributeType::TYPE_OFF );

		$this->assertSame( AttributeType::TYPE_OFF, AttributeType::get_type( 'pa_color' ) );
		$this->assertArrayNotHasKey( 'pa_color', AttributeType::all() );
	}

	public function test_invalid_type_removes_mapping(): void {
		AttributeType::set_type( 'pa_color', 'button' );
		AttributeType::set_type( 'pa_color', 'hologram' );

		$this->assertArrayNotHasKey( 'pa_color', AttributeType::all() );
	}

	public function test_empty_taxonomy_is_ignored(): void {
		AttributeType::set_type( '', 'color' );

		$this->assertSame( array(), AttributeType::all() );
	}

	public function test_sanitize_drops_unknown_types_and_empty_keys(): void {
		$clean = AttributeType::sanitize(
			array(
				'pa_color' => 'color',
				'pa_size'  => 'button',
				'pa_bad'   => 'triangle', // not a swatch type
				''         => 'color',    // empty key
				'pa_off'   => 'off',      // off is not persisted
			)
		);

		$this->assertSame(
			array(
				'pa_color' => 'color',
				'pa_size'  => 'button',
			),
			$clean
		);
	}

	public function test_all_coerces_corrupt_option(): void {
		$GLOBALS['fx_options'][ AttributeType::OPTION_KEY ] = 'not-an-array';
		$this->assertSame( array(), AttributeType::all() );

		$GLOBALS['fx_options'][ AttributeType::OPTION_KEY ] = array( 'PA Color!' => 'color' );
		// Key sanitized to a slug (spaces/punctuation dropped), type kept.
		$this->assertSame( array( 'pacolor' => 'color' ), AttributeType::all() );
	}

	public function test_swatch_types_lists_the_three_styles(): void {
		$this->assertSame( array( 'color', 'image', 'button' ), AttributeType::swatch_types() );
	}
}
