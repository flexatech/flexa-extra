<?php
/**
 * Plugin Name:       Flexa Extra - Extra Product Options for WooCommerce
 * Plugin URI:        https://wordpress.org/plugins/flexa-extra
 * Description:       Add customizable extra options and personalization fields to WooCommerce products (text, checkbox, radio, dropdown, swatches, buttons) with optional extra fees.
 * Version:           1.0.0
 * Author:            FlexaTech
 * Author URI:        https://profiles.wordpress.org/flexatech/
 * Text Domain:       flexa-extra
 * Domain Path:       /languages
 * Requires at least: 5.0
 * Tested up to:      7.1
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * WC requires at least: 6.0.0
 * WC tested up to:   11.0.0
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package flexatech/flexa-extra
 */

namespace Flexa\Extra;

defined( 'ABSPATH' ) || exit;

if ( function_exists( 'Flexa\\Extra\\plugin_init' ) ) {
    require_once plugin_dir_path( __FILE__ ) . 'includes/Fallback.php';
    add_action(
        'admin_init',
        function () {
            deactivate_plugins( plugin_basename( __FILE__ ) );
        }
    );
}

if ( ! defined( 'FLEXA_EXTRA_FILE' ) ) {
    define( 'FLEXA_EXTRA_FILE', __FILE__ );
}

if ( ! defined( 'FLEXA_EXTRA_VERSION' ) ) {
    define( 'FLEXA_EXTRA_VERSION', '1.0.0' );
}

if ( ! defined( 'FLEXA_EXTRA_PLUGIN_URL' ) ) {
    define( 'FLEXA_EXTRA_PLUGIN_URL', plugin_dir_url( FLEXA_EXTRA_FILE ) );
}

if ( ! defined( 'FLEXA_EXTRA_PLUGIN_DIR' ) ) {
    define( 'FLEXA_EXTRA_PLUGIN_DIR', plugin_dir_path( FLEXA_EXTRA_FILE ) );
}

if ( ! defined( 'FLEXA_EXTRA_BASE_NAME' ) ) {
    define( 'FLEXA_EXTRA_BASE_NAME', plugin_basename( FLEXA_EXTRA_FILE ) );
}

if ( ! defined( 'FLEXA_EXTRA_REST_NAMESPACE' ) ) {
    define( 'FLEXA_EXTRA_REST_NAMESPACE', 'flexa-extra/v1' );
}

// Flip to true to load the app from the Vite dev server (localhost:3000).
if ( ! defined( 'FLEXA_EXTRA_IS_DEVELOPMENT' ) ) {
    define( 'FLEXA_EXTRA_IS_DEVELOPMENT', false );
}

spl_autoload_register(
    function ( $class ) {
        $prefix   = __NAMESPACE__;
        $base_dir = __DIR__ . '/includes';

        $len = strlen( $prefix );
        if ( strncmp( $prefix, $class, $len ) !== 0 ) {
            return;
        }

        $relative_class_name = substr( $class, $len );
        $file                = $base_dir . str_replace( '\\', '/', $relative_class_name ) . '.php';

        if ( file_exists( $file ) ) {
            require $file;
        }
    }
);

if ( ! function_exists( 'Flexa\\Extra\\plugin_init' ) ) {
    function plugin_init(): void {
        if ( ! function_exists( 'WC' ) ) {
            add_action( 'admin_notices', [ \Flexa\Extra\Engine\ActDeact::class, 'install_woocommerce_admin_notice' ] );
            return;
        }

        add_action( 'before_woocommerce_init', [ \Flexa\Extra\Engine\ActDeact::class, 'before_woocommerce_init' ] );

        // Translations load just-in-time from WordPress.org language packs and the
        // bundled /languages directory (auto-loaded since WP 4.6) — no manual
        // text-domain loading is needed for a wordpress.org-hosted plugin.
        Initialize::get_instance();
    }
}

if ( ! wp_installing() ) {
    add_action( 'plugins_loaded', 'Flexa\\Extra\\plugin_init' );
}

register_activation_hook( FLEXA_EXTRA_FILE, [ \Flexa\Extra\Engine\ActDeact::class, 'activate' ] );
register_deactivation_hook( FLEXA_EXTRA_FILE, [ \Flexa\Extra\Engine\ActDeact::class, 'deactivate' ] );
