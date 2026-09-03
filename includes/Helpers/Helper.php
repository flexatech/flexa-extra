<?php
namespace Flexa\Extra\Helpers;

defined( 'ABSPATH' ) || exit;

use Flexa\Extra\Fields\FieldType;
use Flexa\Extra\Support\OnboardingState;

/**
 * Shared helpers: JS config for the admin app and the settings schema.
 */
class Helper {

    protected function __construct() {}

    /**
     * Config localized to the admin React app as `window.flexaExtra`.
     *
     * @return array<string,mixed>
     */
    public static function get_js_config(): array {
        $config = [
            'plugin_url' => FLEXA_EXTRA_PLUGIN_URL,
            'rest_url'   => esc_url_raw( rest_url() ),
            'rest_nonce' => wp_create_nonce( 'wp_rest' ),
            'rest_base'  => FLEXA_EXTRA_REST_NAMESPACE,
            'settings'   => self::get_settings(),
            'field_catalog' => FieldType::catalog(),
            'onboarding' => OnboardingState::all(),

            'currency_settings' => [
                'currency'     => get_woocommerce_currency(),
                'symbol'       => html_entity_decode( get_woocommerce_currency_symbol(), ENT_COMPAT ),
                'position'     => get_option( 'woocommerce_currency_pos' ),
                'thousand_sep' => get_option( 'woocommerce_price_thousand_sep' ),
                'decimal_sep'  => get_option( 'woocommerce_price_decimal_sep' ),
                'num_decimals' => intval( get_option( 'woocommerce_price_num_decimals' ) ),
            ],
        ];

        return apply_filters( 'flexa_extra/js_config', $config );
    }

    /**
     * Stored settings merged over defaults.
     *
     * @return array<string,mixed>
     */
    public static function get_settings(): array {
        $settings = get_option( 'flexa_extra_settings' );
        if ( ! is_array( $settings ) ) {
            return self::get_default_settings();
        }
        return array_replace_recursive( self::get_default_settings(), $settings );
    }

    /**
     * Default settings schema.
     *
     * @return array<string,mixed>
     */
    public static function get_default_settings(): array {
        $defaults = [
            'general'  => [
                'enabled'            => true,
                'showExtraSubtotal'  => true,
                'showTotalPrice'     => true,
                'showPriceBreakdown' => true,
                'showValueInMiniCart' => true,
            ],
            'display'  => [
                'subtotalLabel'   => __( 'Extra subtotal:', 'flexa-extra' ),
                'totalPriceLabel' => __( 'Total price:', 'flexa-extra' ),
                'position'        => 'before_add_to_cart',
            ],
            'style'    => [
                'swatchSize'       => 'md',     // sm | md | lg
                'swatchShape'      => 'circle', // circle | rounded | square
                'showTooltips'     => true,
                'buttonBg'         => '',       // hex or '' = inherit theme
                'buttonText'       => '',
                'buttonActiveBg'   => '',
                'buttonActiveText' => '',
            ],
            'advanced' => [
                'hideZeroSubtotal' => true,
                'loadScriptsAllPages' => false,
            ],
        ];

        return apply_filters( 'flexa_extra/default_settings', $defaults );
    }

    /**
     * Sanitize a settings payload against the schema.
     *
     * @param mixed $input Raw settings from the request.
     * @return array<string,mixed>
     */
    public static function sanitize_settings( $input ): array {
        if ( ! is_array( $input ) ) {
            return self::get_default_settings();
        }

        $defaults = self::get_default_settings();

        $general = isset( $input['general'] ) && is_array( $input['general'] ) ? $input['general'] : [];
        $display = isset( $input['display'] ) && is_array( $input['display'] ) ? $input['display'] : [];
        $style = isset( $input['style'] ) && is_array( $input['style'] ) ? $input['style'] : [];
        $advanced = isset( $input['advanced'] ) && is_array( $input['advanced'] ) ? $input['advanced'] : [];

        $allowed_positions = [ 'before_add_to_cart', 'after_add_to_cart' ];
        $allowed_sizes     = [ 'sm', 'md', 'lg' ];
        $allowed_shapes    = [ 'circle', 'rounded', 'square' ];

        $sanitized = [
            'general'  => [
                'enabled'             => isset( $general['enabled'] ) ? rest_sanitize_boolean( $general['enabled'] ) : $defaults['general']['enabled'],
                'showExtraSubtotal'   => isset( $general['showExtraSubtotal'] ) ? rest_sanitize_boolean( $general['showExtraSubtotal'] ) : $defaults['general']['showExtraSubtotal'],
                'showTotalPrice'      => isset( $general['showTotalPrice'] ) ? rest_sanitize_boolean( $general['showTotalPrice'] ) : $defaults['general']['showTotalPrice'],
                'showPriceBreakdown'  => isset( $general['showPriceBreakdown'] ) ? rest_sanitize_boolean( $general['showPriceBreakdown'] ) : $defaults['general']['showPriceBreakdown'],
                'showValueInMiniCart' => isset( $general['showValueInMiniCart'] ) ? rest_sanitize_boolean( $general['showValueInMiniCart'] ) : $defaults['general']['showValueInMiniCart'],
            ],
            'display'  => [
                'subtotalLabel'   => isset( $display['subtotalLabel'] ) ? sanitize_text_field( $display['subtotalLabel'] ) : $defaults['display']['subtotalLabel'],
                'totalPriceLabel' => isset( $display['totalPriceLabel'] ) ? sanitize_text_field( $display['totalPriceLabel'] ) : $defaults['display']['totalPriceLabel'],
                'position'        => isset( $display['position'] ) && in_array( $display['position'], $allowed_positions, true ) ? $display['position'] : $defaults['display']['position'],
            ],
            'style'    => [
                'swatchSize'       => isset( $style['swatchSize'] ) && in_array( $style['swatchSize'], $allowed_sizes, true ) ? $style['swatchSize'] : $defaults['style']['swatchSize'],
                'swatchShape'      => isset( $style['swatchShape'] ) && in_array( $style['swatchShape'], $allowed_shapes, true ) ? $style['swatchShape'] : $defaults['style']['swatchShape'],
                'showTooltips'     => isset( $style['showTooltips'] ) ? rest_sanitize_boolean( $style['showTooltips'] ) : $defaults['style']['showTooltips'],
                'buttonBg'         => self::sanitize_optional_hex( $style['buttonBg'] ?? null ),
                'buttonText'       => self::sanitize_optional_hex( $style['buttonText'] ?? null ),
                'buttonActiveBg'   => self::sanitize_optional_hex( $style['buttonActiveBg'] ?? null ),
                'buttonActiveText' => self::sanitize_optional_hex( $style['buttonActiveText'] ?? null ),
            ],
            'advanced' => [
                'hideZeroSubtotal'    => isset( $advanced['hideZeroSubtotal'] ) ? rest_sanitize_boolean( $advanced['hideZeroSubtotal'] ) : $defaults['advanced']['hideZeroSubtotal'],
                'loadScriptsAllPages' => isset( $advanced['loadScriptsAllPages'] ) ? rest_sanitize_boolean( $advanced['loadScriptsAllPages'] ) : $defaults['advanced']['loadScriptsAllPages'],
            ],
        ];

        return apply_filters( 'flexa_extra/sanitize_settings', $sanitized, $input );
    }

    /**
     * Sanitize an optional hex color. Empty/invalid input normalizes to '' so
     * the frontend can fall back to the theme's own button/link colors.
     *
     * @param mixed $value
     */
    private static function sanitize_optional_hex( $value ): string {
        if ( ! is_string( $value ) || '' === $value ) {
            return '';
        }
        $hex = sanitize_hex_color( $value );
        return is_string( $hex ) ? $hex : '';
    }
}
