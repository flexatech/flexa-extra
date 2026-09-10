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
     * Read-only presentation config for headless / decoupled storefronts.
     *
     * Exposes only what a front end needs to render the configurator and compute
     * the same live subtotal the on-page storefront does: currency, display
     * labels, style, and the handful of i18n strings the storefront script uses.
     * This is a public-safe subset of {@see self::get_settings()} (no admin-only
     * or advanced flags beyond the ones that affect rendering).
     *
     * @return array<string,mixed>
     */
    public static function get_public_config(): array {
        $settings = self::get_settings();
        $currency = self::get_js_config()['currency_settings'];

        $config = [
            'enabled'  => ! empty( $settings['general']['enabled'] ),
            'currency' => $currency,
            'display'  => [
                'position'           => (string) $settings['display']['position'],
                'subtotalLabel'      => (string) $settings['display']['subtotalLabel'],
                'totalPriceLabel'    => (string) $settings['display']['totalPriceLabel'],
                'showExtraSubtotal'  => ! empty( $settings['general']['showExtraSubtotal'] ),
                'showTotalPrice'     => ! empty( $settings['general']['showTotalPrice'] ),
                'showPriceBreakdown' => ! empty( $settings['general']['showPriceBreakdown'] ),
                'hideZeroSubtotal'   => ! empty( $settings['advanced']['hideZeroSubtotal'] ),
            ],
            'style'    => [
                'swatchSize'       => (string) $settings['style']['swatchSize'],
                'swatchShape'      => (string) $settings['style']['swatchShape'],
                'showTooltips'     => ! empty( $settings['style']['showTooltips'] ),
                'buttonBg'         => (string) $settings['style']['buttonBg'],
                'buttonText'       => (string) $settings['style']['buttonText'],
                'buttonActiveBg'   => (string) $settings['style']['buttonActiveBg'],
                'buttonActiveText' => (string) $settings['style']['buttonActiveText'],
                'vswatchSize'      => (string) $settings['style']['vswatchSize'],
                'vswatchShape'     => (string) $settings['style']['vswatchShape'],
                'vswatchTooltip'   => ! empty( $settings['style']['vswatchTooltip'] ),
            ],
            'i18n'     => [
                'required' => __( 'This field is required.', 'flexa-extra' ),
                'fee'      => __( 'Fee', 'flexa-extra' ),
                'discount' => __( 'Discount', 'flexa-extra' ),
            ],
        ];

        /**
         * Filter the public headless config payload.
         *
         * @param array<string,mixed> $config
         */
        return apply_filters( 'flexa_extra/public_config', $config );
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
                'vswatchEnabled'   => true,     // master toggle for the variation-swatch module
                'vswatchSize'      => 'md',     // sm | md | lg — variation swatches
                'vswatchShape'     => 'circle', // circle | rounded | square
                'vswatchTooltip'   => true,
                // Display.
                'vswatchShowLabel'      => false,        // show "Attribute: Value" of the current selection
                'vswatchLabelSeparator' => ':',
                'vswatchDefaultButton'  => false,        // render un-configured attributes as buttons (not the native dropdown)
                'vswatchPreloader'      => true,         // spinner while the variation form updates
                'vswatchMaxVisible'     => 0,            // cap swatches per attribute, fold the rest behind "+N more" (0 = show all)
                // Appearance.
                'vswatchWidth'     => 0,        // px override for swatch width  (0 = use size token)
                'vswatchHeight'    => 0,        // px override for swatch height (0 = use size token)
                'vswatchFontSize'  => 0,        // px font size for button swatches (0 = inherit)
                'vswatchTickColor'  => '',      // selected-indicator color (hex, '' = theme currentColor)
                'vswatchCrossColor' => '',      // unavailable strike color (hex, '' = default)
                'vswatchImageSize'  => 'thumbnail', // registered image size for image swatches
                // Availability.
                'vswatchOosBehavior'    => 'blur',   // blur | hide | none — unavailable/OOS swatches
                'vswatchClearOnReselect' => false,   // click the selected swatch again to clear it
                'vswatchShowStock'      => false,    // show per-swatch stock ("N left" / "Out of stock")
                // Shop / archive.
                'vswatchShowOnArchive'  => false,    // render swatches in the shop / category loop
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
                'vswatchEnabled'   => isset( $style['vswatchEnabled'] ) ? rest_sanitize_boolean( $style['vswatchEnabled'] ) : $defaults['style']['vswatchEnabled'],
                'vswatchSize'      => isset( $style['vswatchSize'] ) && in_array( $style['vswatchSize'], $allowed_sizes, true ) ? $style['vswatchSize'] : $defaults['style']['vswatchSize'],
                'vswatchShape'     => isset( $style['vswatchShape'] ) && in_array( $style['vswatchShape'], $allowed_shapes, true ) ? $style['vswatchShape'] : $defaults['style']['vswatchShape'],
                'vswatchTooltip'   => isset( $style['vswatchTooltip'] ) ? rest_sanitize_boolean( $style['vswatchTooltip'] ) : $defaults['style']['vswatchTooltip'],
                'vswatchShowLabel'      => isset( $style['vswatchShowLabel'] ) ? rest_sanitize_boolean( $style['vswatchShowLabel'] ) : $defaults['style']['vswatchShowLabel'],
                'vswatchLabelSeparator' => isset( $style['vswatchLabelSeparator'] ) ? sanitize_text_field( (string) $style['vswatchLabelSeparator'] ) : $defaults['style']['vswatchLabelSeparator'],
                'vswatchDefaultButton'  => isset( $style['vswatchDefaultButton'] ) ? rest_sanitize_boolean( $style['vswatchDefaultButton'] ) : $defaults['style']['vswatchDefaultButton'],
                'vswatchPreloader'      => isset( $style['vswatchPreloader'] ) ? rest_sanitize_boolean( $style['vswatchPreloader'] ) : $defaults['style']['vswatchPreloader'],
                'vswatchMaxVisible'     => isset( $style['vswatchMaxVisible'] ) ? absint( $style['vswatchMaxVisible'] ) : $defaults['style']['vswatchMaxVisible'],
                'vswatchWidth'     => isset( $style['vswatchWidth'] ) ? absint( $style['vswatchWidth'] ) : $defaults['style']['vswatchWidth'],
                'vswatchHeight'    => isset( $style['vswatchHeight'] ) ? absint( $style['vswatchHeight'] ) : $defaults['style']['vswatchHeight'],
                'vswatchFontSize'  => isset( $style['vswatchFontSize'] ) ? absint( $style['vswatchFontSize'] ) : $defaults['style']['vswatchFontSize'],
                'vswatchTickColor'  => self::sanitize_optional_hex( $style['vswatchTickColor'] ?? null ),
                'vswatchCrossColor' => self::sanitize_optional_hex( $style['vswatchCrossColor'] ?? null ),
                'vswatchImageSize'  => isset( $style['vswatchImageSize'] ) && '' !== (string) $style['vswatchImageSize'] ? sanitize_key( (string) $style['vswatchImageSize'] ) : $defaults['style']['vswatchImageSize'],
                'vswatchOosBehavior'    => isset( $style['vswatchOosBehavior'] ) && in_array( $style['vswatchOosBehavior'], array( 'blur', 'hide', 'none' ), true ) ? $style['vswatchOosBehavior'] : $defaults['style']['vswatchOosBehavior'],
                'vswatchClearOnReselect' => isset( $style['vswatchClearOnReselect'] ) ? rest_sanitize_boolean( $style['vswatchClearOnReselect'] ) : $defaults['style']['vswatchClearOnReselect'],
                'vswatchShowStock'      => isset( $style['vswatchShowStock'] ) ? rest_sanitize_boolean( $style['vswatchShowStock'] ) : $defaults['style']['vswatchShowStock'],
                'vswatchShowOnArchive'  => isset( $style['vswatchShowOnArchive'] ) ? rest_sanitize_boolean( $style['vswatchShowOnArchive'] ) : $defaults['style']['vswatchShowOnArchive'],
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
