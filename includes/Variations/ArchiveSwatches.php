<?php
namespace Flexa\Extra\Variations;

defined( 'ABSPATH' ) || exit;

use Flexa\Extra\Utils\SingletonTrait;
use Flexa\Extra\Register\ScriptName;
use Flexa\Extra\Helpers\Helper;

/**
 * Renders variation swatches in the shop / category loop.
 *
 * Unlike the single-product overlay, archive items have no variation form, so
 * each swatch is a plain link to the product with the chosen term pre-selected
 * via WooCommerce's own `?attribute_pa_x=slug` query args. That keeps it robust
 * across themes (no in-loop AJAX) and doubles as a shareable variation URL.
 */
final class ArchiveSwatches {
	use SingletonTrait;

	protected function __construct() {
		// Reuse the single-product renderer's defer + enable rules.
		$renderer = SwatchRenderer::get_instance();
		if ( $renderer->should_defer() ) {
			return;
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue' ) );

		$hook     = (string) apply_filters( 'flexa_extra/variation_swatches/archive_hook', 'woocommerce_after_shop_loop_item_title' );
		$priority = (int) apply_filters( 'flexa_extra/variation_swatches/archive_hook_priority', 20 );
		add_action( $hook, array( $this, 'render' ), $priority );
	}

	private function is_active(): bool {
		if ( ! SwatchRenderer::get_instance()->is_enabled() ) {
			return false;
		}

		$style = Helper::get_settings()['style'] ?? array();

		return ! empty( $style['vswatchShowOnArchive'] ) && array() !== AttributeType::all();
	}

	private function is_archive_context(): bool {
		return function_exists( 'is_shop' )
			&& ( is_shop() || is_product_taxonomy() || is_product_category() || is_product_tag() );
	}

	public function maybe_enqueue(): void {
		if ( $this->is_active() && $this->is_archive_context() ) {
			wp_enqueue_style( ScriptName::STYLE_VARIATIONS );
		}
	}

	public function render(): void {
		if ( ! $this->is_active() ) {
			return;
		}

		global $product;
		if ( ! $product instanceof \WC_Product_Variable ) {
			return;
		}

		$style      = Helper::get_settings()['style'] ?? array();
		$image_size = isset( $style['vswatchImageSize'] ) && '' !== (string) $style['vswatchImageSize']
			? (string) $style['vswatchImageSize']
			: 'thumbnail';
		$vars      = SwatchRenderer::css_vars( $style );
		$permalink = (string) $product->get_permalink();

		$groups = '';
		foreach ( $product->get_variation_attributes() as $taxonomy => $slugs ) {
			$taxonomy = (string) $taxonomy;
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue; // Custom (non-taxonomy) attributes have no term meta to draw from.
			}

			$type = AttributeType::get_type( $taxonomy );
			if ( AttributeType::TYPE_OFF === $type ) {
				continue;
			}

			$items = '';
			foreach ( (array) $slugs as $slug ) {
				$slug = (string) $slug;
				$term = get_term_by( 'slug', $slug, $taxonomy );
				if ( ! $term instanceof \WP_Term ) {
					continue;
				}
				$items .= $this->render_link( $term, $type, $taxonomy, $permalink, $image_size );
			}

			if ( '' !== $items ) {
				$groups .= sprintf(
					'<div class="flexa-extra-vswatch flexa-extra-vswatch--%1$s" style="%2$s">%3$s</div>',
					esc_attr( $type ),
					esc_attr( $vars ),
					$items
				);
			}
		}

		if ( '' !== $groups ) {
			echo '<div class="flexa-extra-vswatch-archive">' . $groups . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
		}
	}

	private function render_link( \WP_Term $term, string $type, string $taxonomy, string $permalink, string $image_size ): string {
		$slug  = (string) $term->slug;
		$title = (string) $term->name;
		$url   = add_query_arg( 'attribute_' . $taxonomy, $slug, $permalink );

		if ( 'color' === $type ) {
			$color = TermMeta::get_color( (int) $term->term_id );
			$inner = sprintf(
				'<span class="flexa-extra-vswatch__chip" style="background-color:%1$s"></span>',
				esc_attr( $color ?: 'transparent' )
			);
		} elseif ( 'image' === $type ) {
			$img_url = TermMeta::get_image_url( (int) $term->term_id, $image_size );
			$inner   = '' !== $img_url
				? sprintf( '<img class="flexa-extra-vswatch__chip" src="%1$s" alt="%2$s" loading="lazy" />', esc_url( $img_url ), esc_attr( $title ) )
				: sprintf( '<span class="flexa-extra-vswatch__label">%s</span>', esc_html( $title ) );
		} else { // button.
			$inner = sprintf( '<span class="flexa-extra-vswatch__label">%s</span>', esc_html( $title ) );
		}

		return sprintf(
			'<a class="flexa-extra-vswatch__item" href="%1$s" title="%2$s" data-title="%2$s" rel="nofollow">%3$s</a>',
			esc_url( $url ),
			esc_attr( $title ),
			$inner
		);
	}
}
