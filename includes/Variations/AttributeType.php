<?php

declare(strict_types=1);

namespace Flexa\Extra\Variations;

defined( 'ABSPATH' ) || exit;

/**
 * Single source of truth for which global product attributes render as variation
 * swatches, and in what style. Stored in the `flexa_extra_variation_swatches`
 * option as a flat map of `attribute taxonomy => swatch type`.
 *
 * Deliberately does NOT touch WooCommerce's core
 * `woocommerce_attribute_taxonomies` table (where woo-variation-swatches keeps
 * its `attribute_type` column). Keeping the map in our own option means the
 * feature uninstalls cleanly and never collides with that plugin's column.
 *
 * `off` is the absence of a mapping: it is never persisted, so the stored option
 * only ever lists attributes the shop has actively opted in.
 */
final class AttributeType {

	public const OPTION_KEY = 'flexa_extra_variation_swatches';

	/** The one non-swatch value; means "leave WooCommerce's dropdown alone". */
	public const TYPE_OFF = 'off';

	/** @var list<string> The swatch styles a shop can pick per attribute. */
	private const SWATCH_TYPES = [ 'color', 'image', 'button' ];

	/**
	 * The full stored map, cleaned: only valid taxonomy keys mapped to a valid
	 * swatch type. A hand-edited or corrupt option can never hand a consumer an
	 * unknown attribute or an unknown type.
	 *
	 * @return array<string,string>
	 */
	public static function all(): array {
		$stored = get_option( self::OPTION_KEY, [] );

		return is_array( $stored ) ? self::sanitize( $stored ) : [];
	}

	/**
	 * The swatch type configured for one attribute taxonomy, or `off` when the
	 * attribute has no mapping (the default: WooCommerce keeps its dropdown).
	 */
	public static function get_type( string $taxonomy ): string {
		$taxonomy = sanitize_key( $taxonomy );

		return self::all()[ $taxonomy ] ?? self::TYPE_OFF;
	}

	/**
	 * Set (or clear) the swatch type for one attribute and persist. Passing
	 * `off` (or any invalid type) removes the mapping so the option never grows
	 * dead `off` entries.
	 */
	public static function set_type( string $taxonomy, string $type ): void {
		$taxonomy = sanitize_key( $taxonomy );
		if ( '' === $taxonomy ) {
			return;
		}

		$map = self::all();

		if ( in_array( $type, self::SWATCH_TYPES, true ) ) {
			$map[ $taxonomy ] = $type;
		} else {
			unset( $map[ $taxonomy ] );
		}

		update_option( self::OPTION_KEY, $map );
	}

	/**
	 * Clean an incoming map against the schema: keys sanitized as taxonomy slugs,
	 * values kept only when they name a real swatch type. Unknown keys, empty
	 * keys, and `off`/garbage values are dropped.
	 *
	 * @param array<mixed,mixed> $incoming
	 * @return array<string,string>
	 */
	public static function sanitize( array $incoming ): array {
		$clean = [];

		foreach ( $incoming as $taxonomy => $type ) {
			$taxonomy = sanitize_key( (string) $taxonomy );
			if ( '' === $taxonomy ) {
				continue;
			}
			if ( is_string( $type ) && in_array( $type, self::SWATCH_TYPES, true ) ) {
				$clean[ $taxonomy ] = $type;
			}
		}

		return $clean;
	}

	/**
	 * The swatch types a shop may choose from, for building admin controls.
	 *
	 * @return list<string>
	 */
	public static function swatch_types(): array {
		return self::SWATCH_TYPES;
	}
}
