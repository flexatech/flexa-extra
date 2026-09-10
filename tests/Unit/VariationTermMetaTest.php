<?php

declare(strict_types=1);

namespace Flexa\Extra\Tests\Unit;

use Flexa\Extra\Variations\TermMeta;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Covers the per-term swatch data layer (Pha 0): hex colors round-trip and
 * sanitize, image ids coerce to int, clearing a value deletes the meta, and the
 * meta keys match woo-variation-swatches for lossless compatibility.
 */
#[CoversClass( TermMeta::class )]
final class VariationTermMetaTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['fx_term_meta'] = array();
	}

	public function test_color_defaults_to_empty(): void {
		$this->assertSame( '', TermMeta::get_color( 10 ) );
	}

	public function test_set_and_get_color_round_trips(): void {
		TermMeta::set_color( 10, '#ff0000' );

		$this->assertSame( '#ff0000', TermMeta::get_color( 10 ) );
		// Written under the woo-variation-swatches key for compatibility.
		$this->assertSame( '#ff0000', $GLOBALS['fx_term_meta'][10][ TermMeta::META_COLOR ] );
	}

	public function test_invalid_color_deletes_meta(): void {
		TermMeta::set_color( 10, '#abc' );
		TermMeta::set_color( 10, 'red' ); // invalid hex

		$this->assertSame( '', TermMeta::get_color( 10 ) );
		$this->assertArrayNotHasKey( TermMeta::META_COLOR, $GLOBALS['fx_term_meta'][10] ?? array() );
	}

	public function test_corrupt_stored_color_reads_as_empty(): void {
		$GLOBALS['fx_term_meta'][10][ TermMeta::META_COLOR ] = 'javascript:1';
		$this->assertSame( '', TermMeta::get_color( 10 ) );
	}

	public function test_image_id_defaults_to_zero(): void {
		$this->assertSame( 0, TermMeta::get_image_id( 10 ) );
	}

	public function test_set_and_get_image_id(): void {
		TermMeta::set_image_id( 10, 42 );

		$this->assertSame( 42, TermMeta::get_image_id( 10 ) );
		$this->assertSame( 42, $GLOBALS['fx_term_meta'][10][ TermMeta::META_IMAGE ] );
	}

	public function test_zero_image_id_deletes_meta(): void {
		TermMeta::set_image_id( 10, 42 );
		TermMeta::set_image_id( 10, 0 );

		$this->assertSame( 0, TermMeta::get_image_id( 10 ) );
		$this->assertArrayNotHasKey( TermMeta::META_IMAGE, $GLOBALS['fx_term_meta'][10] ?? array() );
	}

	public function test_image_url_empty_without_image(): void {
		$this->assertSame( '', TermMeta::get_image_url( 10 ) );
	}

	public function test_image_url_resolves_when_set(): void {
		TermMeta::set_image_id( 10, 42 );

		$this->assertSame(
			'https://example.test/wp-content/uploads/img-42-thumbnail.png',
			TermMeta::get_image_url( 10 )
		);
	}
}
