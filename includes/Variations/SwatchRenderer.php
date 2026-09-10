<?php
namespace Flexa\Extra\Variations;

defined( 'ABSPATH' ) || exit;

use Flexa\Extra\Utils\SingletonTrait;
use Flexa\Extra\Register\ScriptName;
use Flexa\Extra\Helpers\Helper;

/**
 * Overlays color / image / button swatches on WooCommerce variation dropdowns.
 *
 * The native <select> is kept (hidden) as the single source of truth: clicking a
 * swatch sets the select's value and re-fires its change event, so WooCommerce's
 * own variation form handles price, stock and gallery image unchanged. We never
 * rebuild the select, only hide it and render a sibling swatch list.
 */
final class SwatchRenderer {
	use SingletonTrait;

	/** px sizes per swatch-size token. */
	private const SIZES = array( 'sm' => '28px', 'md' => '36px', 'lg' => '48px' );

	/** border-radius per shape token (square/circular swatches). */
	private const SHAPES = array( 'circle' => '50%', 'rounded' => '8px', 'square' => '0' );

	/**
	 * border-radius per shape token for auto-width button pills. A `50%` radius on
	 * an auto-width element renders as an ellipse, so "circle" maps to a stadium
	 * radius here to get properly rounded pill ends instead of ovals.
	 */
	private const PILL_SHAPES = array( 'circle' => '999px', 'rounded' => '8px', 'square' => '0' );

	protected function __construct() {
		// Always let the admin app know whether we're deferring, so the
		// Variation Swatches screen can show the "handled elsewhere" notice.
		add_filter( 'flexa_extra/js_config', array( $this, 'advertise_defer_state' ) );

		if ( $this->should_defer() ) {
			return;
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue' ) );
		add_filter( 'woocommerce_dropdown_variation_attribute_options_html', array( $this, 'render_dropdown' ), 20, 2 );
	}

	/**
	 * Stand down when another variation-swatches plugin is active: its term meta
	 * uses the same keys, so flexa-extra defers to avoid double rendering.
	 * Deactivating the other plugin brings our overlay straight back.
	 */
	public function should_defer(): bool {
		$active = function_exists( 'woo_variation_swatches' )
			|| defined( 'WOO_VARIATION_SWATCHES_PLUGIN_VERSION' )
			|| class_exists( 'Woo_Variation_Swatches' );

		return (bool) apply_filters( 'flexa_extra/variation_swatches/defer_to_wvs', $active );
	}

	/**
	 * @param array<string,mixed> $config
	 * @return array<string,mixed>
	 */
	public function advertise_defer_state( array $config ): array {
		$config['variation_swatches_deferred'] = $this->should_defer();
		return $config;
	}

	/**
	 * Whether the swatch overlay should run at all.
	 */
	public function is_enabled(): bool {
		if ( $this->should_defer() ) {
			return false;
		}

		$settings = Helper::get_settings();
		$style    = isset( $settings['style'] ) && is_array( $settings['style'] ) ? $settings['style'] : array();

		// Respect both the plugin-wide kill switch and the swatch module's own
		// toggle (Variation Swatches -> Settings -> Enable variation swatches).
		$enabled = ! empty( $settings['general']['enabled'] )
			&& ! empty( $style['vswatchEnabled'] );

		return (bool) apply_filters( 'flexa_extra/variation_swatches/enabled', $enabled );
	}

	public function maybe_enqueue(): void {
		if ( ! $this->is_enabled() || ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		$product = wc_get_product( get_the_ID() );
		if ( ! $product instanceof \WC_Product || ! $product->is_type( 'variable' ) ) {
			return;
		}

		// Nothing to overlay if no attribute is configured as a swatch.
		if ( array() === AttributeType::all() ) {
			return;
		}

		wp_enqueue_style( ScriptName::STYLE_VARIATIONS );
		wp_enqueue_script( ScriptName::PAGE_VARIATIONS );

		$settings = Helper::get_settings();
		$style    = isset( $settings['style'] ) && is_array( $settings['style'] ) ? $settings['style'] : array();

		if ( ! empty( $style['vswatchShowStock'] ) ) {
			wp_localize_script(
				ScriptName::PAGE_VARIATIONS,
				'flexaExtraVswatch',
				array(
					// WooCommerce's global "notify low stock" amount: only show a count
					// at or below it, so plentiful stock stays uncluttered.
					'lowStockAmount' => (int) get_option( 'woocommerce_notify_low_stock_amount', 2 ),
					'i18n'           => array(
						'outOfStock' => __( 'Out of stock', 'flexa-extra' ),
						/* translators: %d: number of items left in stock for a swatch. */
						'left'       => __( '%d left', 'flexa-extra' ),
					),
				)
			);
		}
	}

	/**
	 * @param string              $html Native <select> markup built by WooCommerce.
	 * @param array<string,mixed> $args Dropdown args (options, attribute, product, selected…).
	 */
	public function render_dropdown( string $html, array $args ): string {
		if ( ! $this->is_enabled() ) {
			return $html;
		}

		$settings = Helper::get_settings();
		$style    = isset( $settings['style'] ) && is_array( $settings['style'] ) ? $settings['style'] : array();

		$taxonomy = isset( $args['attribute'] ) ? (string) $args['attribute'] : '';
		$type     = AttributeType::get_type( $taxonomy );

		// "Default to button": attributes with no swatch type still render as
		// buttons instead of the native dropdown, when the option is on.
		if ( AttributeType::TYPE_OFF === $type ) {
			if ( empty( $style['vswatchDefaultButton'] ) ) {
				return $html;
			}
			$type = 'button';
		}

		$options = isset( $args['options'] ) && is_array( $args['options'] ) ? $args['options'] : array();
		if ( array() === $options ) {
			return $html;
		}

		$selected   = isset( $args['selected'] ) ? (string) $args['selected'] : '';
		$image_size = isset( $style['vswatchImageSize'] ) && '' !== (string) $style['vswatchImageSize']
			? (string) $style['vswatchImageSize']
			: 'thumbnail';

		// Resolve valid terms first so we know the real count before applying the
		// "+N more" limit (some options can be missing terms and get skipped).
		$terms = array();
		foreach ( $options as $slug ) {
			$slug = (string) $slug;
			$term = get_term_by( 'slug', $slug, $taxonomy );
			if ( $term instanceof \WP_Term ) {
				$terms[] = $term;
			}
		}

		if ( array() === $terms ) {
			return $html;
		}

		// Cap the number of visible swatches; the rest are rendered hidden and
		// revealed by a "+N more" toggle. The selected swatch always stays
		// visible so the current choice is never folded away.
		$limit        = isset( $style['vswatchMaxVisible'] ) ? (int) $style['vswatchMaxVisible'] : 0;
		$show_stock   = ! empty( $style['vswatchShowStock'] );
		$hidden_count = 0;
		$items        = '';
		foreach ( $terms as $index => $term ) {
			$is_selected = ( (string) $term->slug === $selected );
			$is_overflow = $limit > 0 && $index >= $limit && ! $is_selected;
			if ( $is_overflow ) {
				++$hidden_count;
			}
			$items .= $this->render_item( $term, $type, $is_selected, $image_size, $is_overflow, $show_stock );
		}

		$items .= $this->more_toggle_html( $hidden_count );

		// Hide the real select but keep it in the DOM as the source of truth.
		$hidden = preg_replace(
			'/<select /',
			'<select data-flexa-raw="1" style="display:none;" ',
			$html,
			1
		);
		$hidden = is_string( $hidden ) ? $hidden : $html;

		return sprintf(
			'<div class="flexa-extra-vswatch-wrap">%1$s<ul class="%2$s" role="radiogroup" data-attribute="%3$s"%4$s style="%5$s">%6$s</ul>%7$s</div>',
			$hidden,
			esc_attr( $this->list_classes( $type, $settings ) ),
			esc_attr( $taxonomy ),
			$this->list_data_attributes( $style ),
			esc_attr( $this->list_style( $settings ) ),
			$items,
			$this->selected_label_html( $taxonomy, $selected, $style )
		);
	}

	private function render_item( \WP_Term $term, string $type, bool $is_selected, string $image_size = 'thumbnail', bool $is_overflow = false, bool $show_stock = false ): string {
		$slug  = (string) $term->slug;
		$title = (string) $term->name;

		if ( 'color' === $type ) {
			$color = TermMeta::get_color( (int) $term->term_id );
			$inner = sprintf(
				'<span class="flexa-extra-vswatch__chip" style="background-color:%1$s"></span>',
				esc_attr( $color ?: 'transparent' )
			);
		} elseif ( 'image' === $type ) {
			$url = TermMeta::get_image_url( (int) $term->term_id, $image_size );
			if ( '' !== $url ) {
				$inner = sprintf(
					'<img class="flexa-extra-vswatch__chip" src="%1$s" alt="%2$s" loading="lazy" />',
					esc_url( $url ),
					esc_attr( $title )
				);
			} else {
				// No image assigned: fall back to the term name so the choice is still usable.
				$inner = sprintf( '<span class="flexa-extra-vswatch__label">%s</span>', esc_html( $title ) );
			}
		} else { // button.
			$inner = sprintf( '<span class="flexa-extra-vswatch__label">%s</span>', esc_html( $title ) );
		}

		/**
		 * Filter the inner markup of a single swatch item.
		 *
		 * @param string   $inner Inner HTML (chip / image / label).
		 * @param \WP_Term $term  The attribute term.
		 * @param string   $type  Swatch type: color|image|button.
		 */
		$inner = (string) apply_filters( 'flexa_extra/variation_swatches/item_html', $inner, $term, $type );

		$classes = 'flexa-extra-vswatch__item';
		if ( $is_selected ) {
			$classes .= ' is-selected';
		}
		if ( $is_overflow ) {
			$classes .= ' flexa-extra-vswatch__item--overflow';
		}

		// Empty stock node; the storefront script fills it from the matching
		// variation ("N left" / "Out of stock") once WooCommerce resolves state.
		$stock = $show_stock
			? '<span class="flexa-extra-vswatch__stock" aria-live="polite"></span>'
			: '';

		return sprintf(
			'<li class="%1$s" role="radio" tabindex="0" aria-checked="%2$s" data-value="%3$s" data-title="%4$s" title="%4$s">%5$s%6$s</li>',
			esc_attr( $classes ),
			$is_selected ? 'true' : 'false',
			esc_attr( $slug ),
			esc_attr( $title ),
			$inner,
			$stock
		);
	}

	/**
	 * The "+N more" toggle appended to the swatch list when some swatches are
	 * folded away. Returns an empty string when nothing is hidden. The storefront
	 * script reveals the overflow items and hides this toggle on activation.
	 */
	private function more_toggle_html( int $hidden_count ): string {
		if ( $hidden_count < 1 ) {
			return '';
		}

		$label = sprintf(
			/* translators: %d: number of additional swatches hidden behind the toggle. */
			_n( '+%d more', '+%d more', $hidden_count, 'flexa-extra' ),
			$hidden_count
		);

		return sprintf(
			'<li class="flexa-extra-vswatch__more" role="button" tabindex="0" aria-expanded="false">%s</li>',
			esc_html( $label )
		);
	}

	/**
	 * @param array<string,mixed> $settings
	 */
	private function list_classes( string $type, array $settings ): string {
		$style   = isset( $settings['style'] ) && is_array( $settings['style'] ) ? $settings['style'] : array();
		$classes = array(
			'flexa-extra-vswatch',
			'flexa-extra-vswatch--' . sanitize_html_class( $type ),
		);

		if ( ! empty( $style['vswatchTooltip'] ) ) {
			$classes[] = 'flexa-extra-vswatch--tooltip';
		}

		if ( ! empty( $style['vswatchShowStock'] ) ) {
			$classes[] = 'flexa-extra-vswatch--stock';
		}

		return implode( ' ', $classes );
	}

	/**
	 * Inline CSS custom properties for swatch size / shape / colors.
	 *
	 * @param array<string,mixed> $settings
	 */
	private function list_style( array $settings ): string {
		$style = isset( $settings['style'] ) && is_array( $settings['style'] ) ? $settings['style'] : array();

		return self::css_vars( $style );
	}

	/**
	 * The `--fxe-vswatch-*` custom properties for a swatch list, derived from the
	 * style settings. Shared with the archive renderer so both look identical.
	 *
	 * @param array<string,mixed> $style The `style` settings group.
	 */
	public static function css_vars( array $style ): string {
		$size_key  = isset( $style['vswatchSize'] ) ? (string) $style['vswatchSize'] : 'md';
		$shape_key = isset( $style['vswatchShape'] ) ? (string) $style['vswatchShape'] : 'circle';

		$size  = self::SIZES[ $size_key ] ?? self::SIZES['md'];
		$shape = self::SHAPES[ $shape_key ] ?? self::SHAPES['circle'];
		$pill  = self::PILL_SHAPES[ $shape_key ] ?? self::PILL_SHAPES['circle'];

		$vars = array(
			'--fxe-vswatch-size'        => $size,
			'--fxe-vswatch-radius'      => $shape,
			'--fxe-vswatch-pill-radius' => $pill,
		);

		// Optional px overrides (0 = keep the size token).
		$width  = isset( $style['vswatchWidth'] ) ? (int) $style['vswatchWidth'] : 0;
		$height = isset( $style['vswatchHeight'] ) ? (int) $style['vswatchHeight'] : 0;
		$font   = isset( $style['vswatchFontSize'] ) ? (int) $style['vswatchFontSize'] : 0;
		if ( $width > 0 ) {
			$vars['--fxe-vswatch-w'] = $width . 'px';
		}
		if ( $height > 0 ) {
			$vars['--fxe-vswatch-h'] = $height . 'px';
		}
		if ( $font > 0 ) {
			$vars['--fxe-vswatch-font'] = $font . 'px';
		}

		$tick  = isset( $style['vswatchTickColor'] ) ? (string) $style['vswatchTickColor'] : '';
		$cross = isset( $style['vswatchCrossColor'] ) ? (string) $style['vswatchCrossColor'] : '';
		if ( '' !== $tick ) {
			$vars['--fxe-vswatch-tick'] = $tick;
		}
		if ( '' !== $cross ) {
			$vars['--fxe-vswatch-cross'] = $cross;
		}

		$out = '';
		foreach ( $vars as $prop => $value ) {
			$out .= $prop . ':' . $value . ';';
		}

		return $out;
	}

	/**
	 * Behaviour hints consumed by the storefront script and CSS.
	 *
	 * @param array<string,mixed> $style
	 */
	private function list_data_attributes( array $style ): string {
		$oos = isset( $style['vswatchOosBehavior'] ) && in_array( $style['vswatchOosBehavior'], array( 'blur', 'hide', 'none' ), true )
			? (string) $style['vswatchOosBehavior']
			: 'blur';

		$attrs = ' data-oos="' . esc_attr( $oos ) . '"';

		if ( ! empty( $style['vswatchClearOnReselect'] ) ) {
			$attrs .= ' data-clear-reselect="1"';
		}
		if ( ! empty( $style['vswatchPreloader'] ) ) {
			$attrs .= ' data-preloader="1"';
		}
		if ( ! empty( $style['vswatchShowStock'] ) ) {
			$attrs .= ' data-stock="1"';
		}

		return $attrs;
	}

	/**
	 * Optional "Attribute: Value" line that mirrors the current selection. The
	 * storefront script keeps the value in sync; PHP prefills the initial one.
	 *
	 * @param array<string,mixed> $style
	 */
	private function selected_label_html( string $taxonomy, string $selected, array $style ): string {
		if ( empty( $style['vswatchShowLabel'] ) ) {
			return '';
		}

		$label = function_exists( 'wc_attribute_label' ) ? wc_attribute_label( $taxonomy ) : $taxonomy;
		$sep   = isset( $style['vswatchLabelSeparator'] ) ? (string) $style['vswatchLabelSeparator'] : ':';

		$value = '';
		if ( '' !== $selected ) {
			$term = get_term_by( 'slug', $selected, $taxonomy );
			if ( $term instanceof \WP_Term ) {
				$value = (string) $term->name;
			}
		}

		return sprintf(
			'<div class="flexa-extra-vswatch__selected"><span class="flexa-extra-vswatch__selected-label">%1$s%2$s</span> <span class="flexa-extra-vswatch__selected-value">%3$s</span></div>',
			esc_html( $label ),
			esc_html( $sep ),
			esc_html( $value )
		);
	}
}
