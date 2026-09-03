<?php
namespace Flexa\Extra\Frontend;

defined( 'ABSPATH' ) || exit;

use Flexa\Extra\Utils\SingletonTrait;
use Flexa\Extra\Register\ScriptName;
use Flexa\Extra\Helpers\Helper;
use Flexa\Extra\Cart\EditContext;

/**
 * Renders applicable option sets on the single-product page and enqueues the
 * storefront pricing/logic asset.
 *
 * Position (before/after the add-to-cart button) follows
 * `settings.display.position`. Field prices and conditional logic travel to the
 * browser as a JSON island inside the container so the storefront script can
 * compute the live subtotal from the server-provided definition — the same
 * definition the cart layer will recompute authoritatively on add-to-cart.
 */
final class ProductRenderer {
    use SingletonTrait;

    protected function __construct() {
        add_action( 'wp_enqueue_scripts', [ $this, 'maybe_enqueue' ] );
        add_action( 'woocommerce_before_add_to_cart_button', [ $this, 'render_before' ] );
        add_action( 'woocommerce_after_add_to_cart_button', [ $this, 'render_after' ] );
    }

    public function maybe_enqueue(): void {
        $settings = Helper::get_settings();

        if ( empty( $settings['general']['enabled'] ) ) {
            return;
        }

        $load_everywhere = ! empty( $settings['advanced']['loadScriptsAllPages'] );
        if ( ! $load_everywhere && ! is_product() ) {
            return;
        }

        wp_enqueue_style( ScriptName::STYLE_FRONTEND );
        wp_enqueue_script( ScriptName::PAGE_FRONTEND );

        wp_localize_script(
            ScriptName::PAGE_FRONTEND,
            'flexaExtraFront',
            [
                'currency' => Helper::get_js_config()['currency_settings'],
                'settings' => [
                    'showExtraSubtotal' => ! empty( $settings['general']['showExtraSubtotal'] ),
                    'showTotalPrice'    => ! empty( $settings['general']['showTotalPrice'] ),
                    'showPriceBreakdown' => ! empty( $settings['general']['showPriceBreakdown'] ),
                    'hideZeroSubtotal'  => ! empty( $settings['advanced']['hideZeroSubtotal'] ),
                    'subtotalLabel'     => (string) $settings['display']['subtotalLabel'],
                    'totalPriceLabel'   => (string) $settings['display']['totalPriceLabel'],
                ],
                'i18n'     => [
                    'required' => __( 'This field is required.', 'flexa-extra' ),
                    'fee'      => __( 'Fee', 'flexa-extra' ),
                    'discount' => __( 'Discount', 'flexa-extra' ),
                ],
            ]
        );
    }

    public function render_before(): void {
        $this->render_for_position( 'before_add_to_cart' );
    }

    public function render_after(): void {
        $this->render_for_position( 'after_add_to_cart' );
    }

    private function render_for_position( string $position ): void {
        $settings = Helper::get_settings();

        if ( empty( $settings['general']['enabled'] ) ) {
            return;
        }

        $configured = isset( $settings['display']['position'] ) ? (string) $settings['display']['position'] : 'before_add_to_cart';
        if ( $configured !== $position ) {
            return;
        }

        global $product;
        if ( ! $product instanceof \WC_Product ) {
            return;
        }

        $sets = OptionSetResolver::for_product( $product );
        if ( empty( $sets ) ) {
            return;
        }

        echo $this->render_container( $product, $sets, $settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Composed from escaped parts in FieldRenderer + wp_json_encode island.
    }

    /**
     * @param list<array{id:int,name:string,fields:list<mixed>,actions:list<mixed>}> $sets
     * @param array<string,mixed>                                                     $settings
     */
    private function render_container( \WC_Product $product, array $sets, array $settings ): string {
        $fields_html = '';
        $island_sets = array();

        // Edit-in-cart: when the page was opened from a cart line's "Edit"
        // link, pre-fill fields from that line's stored selections instead of
        // the configured defaults.
        $edit_key   = EditContext::editing_key();
        $editing    = '' !== $edit_key;
        $selections = $editing ? EditContext::selections_for( $edit_key ) : array();

        foreach ( $sets as $set ) {
            $rendered = '';
            foreach ( $set['fields'] as $field ) {
                if ( is_array( $field ) ) {
                    $selected  = $editing ? ( $selections[ $field['id'] ] ?? '' ) : null;
                    $rendered .= FieldRenderer::render( $field, $product, $selected );
                }
            }
            if ( '' === $rendered ) {
                continue;
            }
            $fields_html  .= '<div class="flexa-extra-set" data-set-id="' . esc_attr( (string) $set['id'] ) . '">' . $rendered . '</div>';
            $island_sets[] = array(
                'id'      => $set['id'],
                'fields'  => $set['fields'],
                'actions' => $set['actions'],
            );
        }

        if ( '' === $fields_html ) {
            return '';
        }

        // Carry the edited cart-line key (and a nonce) so the add-to-cart POST
        // can replace that line instead of creating a new one.
        if ( $editing ) {
            $fields_html .= sprintf(
                '<input type="hidden" name="%1$s" value="%2$s" />',
                esc_attr( EditContext::QUERY ),
                esc_attr( $edit_key )
            );
            $fields_html .= wp_nonce_field( EditContext::NONCE_ACTION, EditContext::NONCE_FIELD, true, false );
        }

        $price = (float) wc_get_price_to_display( $product );

        // JSON_HEX_TAG|JSON_HEX_AMP so an admin-authored label containing
        // `</script>` can never break out of the inline JSON island.
        $island = wp_json_encode(
            array(
                'productId'    => $product->get_id(),
                'productPrice' => $price,
                'sets'         => $island_sets,
            ),
            JSON_HEX_TAG | JSON_HEX_AMP
        );

        $totals = $this->render_totals( $settings );

        $classes    = $this->container_classes( $settings );
        $style_attr = $this->container_style( $settings );

        return sprintf(
            '<div class="%1$s" data-product-id="%2$s" data-product-price="%3$s"%4$s>'
                . '<script type="application/json" class="flexa-extra-data">%5$s</script>'
                . '%6$s%7$s</div>',
            esc_attr( $classes ),
            esc_attr( (string) $product->get_id() ),
            esc_attr( (string) $price ),
            '' !== $style_attr ? ' style="' . esc_attr( $style_attr ) . '"' : '',
            $island ? $island : '{}',
            $fields_html,
            $totals
        );
    }

    /**
     * Container class list, including style modifiers from settings.
     *
     * @param array<string,mixed> $settings
     */
    private function container_classes( array $settings ): string {
        $style = isset( $settings['style'] ) && is_array( $settings['style'] ) ? $settings['style'] : array();

        $size  = isset( $style['swatchSize'] ) ? (string) $style['swatchSize'] : 'md';
        $shape = isset( $style['swatchShape'] ) ? (string) $style['swatchShape'] : 'circle';

        $classes = array(
            'flexa-extra-fields',
            'flexa-extra-fields--swatch-' . sanitize_html_class( $size ),
            'flexa-extra-fields--shape-' . sanitize_html_class( $shape ),
        );

        if ( empty( $style['showTooltips'] ) ) {
            $classes[] = 'flexa-extra-fields--no-tooltips';
        }

        return implode( ' ', $classes );
    }

    /**
     * Inline CSS custom properties driving swatch size/shape and button colors.
     * Empty color settings are omitted so the storefront theme's own colors win.
     *
     * @param array<string,mixed> $settings
     */
    private function container_style( array $settings ): string {
        $style = isset( $settings['style'] ) && is_array( $settings['style'] ) ? $settings['style'] : array();

        $sizes  = array( 'sm' => '28px', 'md' => '36px', 'lg' => '48px' );
        $shapes = array( 'circle' => '50%', 'rounded' => '8px', 'square' => '0' );

        $size  = isset( $style['swatchSize'] ) && isset( $sizes[ $style['swatchSize'] ] ) ? $sizes[ $style['swatchSize'] ] : $sizes['md'];
        $shape = isset( $style['swatchShape'] ) && isset( $shapes[ $style['swatchShape'] ] ) ? $shapes[ $style['swatchShape'] ] : $shapes['circle'];

        $vars = array(
            '--fxe-swatch-size'   => $size,
            '--fxe-swatch-radius' => $shape,
        );

        $colors = array(
            '--fxe-btn-bg'          => 'buttonBg',
            '--fxe-btn-text'        => 'buttonText',
            '--fxe-btn-active-bg'   => 'buttonActiveBg',
            '--fxe-btn-active-text' => 'buttonActiveText',
        );
        foreach ( $colors as $var => $key ) {
            if ( ! empty( $style[ $key ] ) ) {
                $sanitized = sanitize_hex_color( (string) $style[ $key ] );
                if ( $sanitized ) {
                    $vars[ $var ] = $sanitized;
                }
            }
        }

        $out = '';
        foreach ( $vars as $var => $value ) {
            $out .= $var . ':' . $value . ';';
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $settings
     */
    private function render_totals( array $settings ): string {
        $show_subtotal  = ! empty( $settings['general']['showExtraSubtotal'] );
        $show_total     = ! empty( $settings['general']['showTotalPrice'] );
        $show_breakdown = ! empty( $settings['general']['showPriceBreakdown'] );

        if ( ! $show_subtotal && ! $show_total && ! $show_breakdown ) {
            return '';
        }

        $html = '<div class="flexa-extra-totals" hidden>';

        if ( $show_breakdown ) {
            // Filled live by the storefront script, one row per priced selection.
            $html .= '<div class="flexa-extra-breakdown" data-role="breakdown" hidden></div>';
        }

        if ( $show_subtotal ) {
            $html .= sprintf(
                '<div class="flexa-extra-totals__row flexa-extra-totals__subtotal"><span class="flexa-extra-totals__label">%1$s</span> <span class="flexa-extra-totals__value" data-role="subtotal"></span></div>',
                esc_html( (string) $settings['display']['subtotalLabel'] )
            );
        }

        if ( $show_total ) {
            $html .= sprintf(
                '<div class="flexa-extra-totals__row flexa-extra-totals__total"><span class="flexa-extra-totals__label">%1$s</span> <span class="flexa-extra-totals__value" data-role="total"></span></div>',
                esc_html( (string) $settings['display']['totalPriceLabel'] )
            );
        }

        return $html . '</div>';
    }
}
