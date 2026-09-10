<?php

declare(strict_types=1);

namespace Flexa\Extra\Variations;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the per-term swatch data (a hex color, an image attachment)
 * for attribute taxonomy terms.
 *
 * The meta keys are shared verbatim with woo-variation-swatches
 * (`product_attribute_color`, `product_attribute_image`) on purpose: a shop that
 * already assigned colors/images there gets them for free, and switching between
 * the two plugins is lossless. This is the compatibility layer, not a coupling:
 * flexa-extra reads and writes the keys itself without that plugin present.
 */
final class TermMeta {

	/** Term meta key holding a hex color string, e.g. `#ff0000`. */
	public const META_COLOR = 'product_attribute_color';

	/** Term meta key holding an image attachment ID. */
	public const META_IMAGE = 'product_attribute_image';

	/**
	 * The term's swatch color as a sanitized hex string, or `''` when unset or
	 * invalid so callers can fall back to a neutral chip.
	 */
	public static function get_color( int $term_id ): string {
		$raw = get_term_meta( $term_id, self::META_COLOR, true );
		if ( ! is_string( $raw ) || '' === $raw ) {
			return '';
		}
		$hex = sanitize_hex_color( $raw );

		return is_string( $hex ) ? $hex : '';
	}

	/**
	 * The term's swatch image attachment ID, or `0` when unset.
	 */
	public static function get_image_id( int $term_id ): int {
		return absint( get_term_meta( $term_id, self::META_IMAGE, true ) );
	}

	/**
	 * The URL of the term's swatch image at the given size, or `''` when no image
	 * is set. Renderers fall back to the variation image when this is empty.
	 */
	public static function get_image_url( int $term_id, string $size = 'thumbnail' ): string {
		$id = self::get_image_id( $term_id );
		if ( 0 === $id ) {
			return '';
		}
		$url = wp_get_attachment_image_url( $id, $size );

		return is_string( $url ) ? $url : '';
	}

	/**
	 * Persist the term's swatch color. An empty or invalid value deletes the meta
	 * so a cleared color leaves no stale row behind.
	 */
	public static function set_color( int $term_id, string $color ): void {
		$hex = sanitize_hex_color( $color );
		if ( ! is_string( $hex ) || '' === $hex ) {
			delete_term_meta( $term_id, self::META_COLOR );
			return;
		}
		update_term_meta( $term_id, self::META_COLOR, $hex );
	}

	/**
	 * Persist the term's swatch image attachment ID. A zero/invalid ID deletes
	 * the meta.
	 */
	public static function set_image_id( int $term_id, int $attachment_id ): void {
		$attachment_id = absint( $attachment_id );
		if ( 0 === $attachment_id ) {
			delete_term_meta( $term_id, self::META_IMAGE );
			return;
		}
		update_term_meta( $term_id, self::META_IMAGE, $attachment_id );
	}
}
