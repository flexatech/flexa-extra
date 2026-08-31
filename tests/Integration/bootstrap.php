<?php
/**
 * Bootstrap for the WordPress + WooCommerce integration suite.
 *
 * Unlike the unit suite (tests/bootstrap.php, which stubs WordPress), this boots
 * the real WP PHPUnit test library, loads WooCommerce and this plugin, and
 * installs WooCommerce's tables into a dedicated test database. It exercises the
 * hook glue (validator, cart, price calculator, REST, renderer) end to end.
 *
 * Requires the WP test library — run bin/install-wp-tests.sh first. See
 * tests/README.md.
 */

declare(strict_types=1);

$_tests_dir = getenv( 'WP_TESTS_DIR' ) ?: '/tmp/wordpress-tests-lib';

if ( ! file_exists( "$_tests_dir/includes/functions.php" ) ) {
    fwrite( STDERR, "Could not find the WP test library at $_tests_dir. Run bin/install-wp-tests.sh first.\n" );
    exit( 1 );
}

// Composer autoload: our plugin classes, the test namespace, and PHPUnit polyfills.
require dirname( __DIR__, 2 ) . '/vendor/autoload.php';

if ( ! getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
    putenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH=' . dirname( __DIR__, 2 ) . '/vendor/yoast/phpunit-polyfills' );
}

require_once "$_tests_dir/includes/functions.php";

/**
 * Load WooCommerce and this plugin before WordPress finishes booting.
 */
function _flexa_extra_load_plugins(): void {
    $plugins_dir = getenv( 'WC_PLUGINS_DIR' ) ?: dirname( __DIR__, 3 );

    $woocommerce = $plugins_dir . '/woocommerce/woocommerce.php';
    if ( ! file_exists( $woocommerce ) ) {
        fwrite( STDERR, "WooCommerce not found at $woocommerce. Set WC_PLUGINS_DIR.\n" );
        exit( 1 );
    }

    require $woocommerce;
    require dirname( __DIR__, 2 ) . '/flexa-extra.php';
}
tests_add_filter( 'muplugins_loaded', '_flexa_extra_load_plugins' );

/**
 * Boot the plugin's engines explicitly. The plugin normally self-boots on
 * `plugins_loaded` guarded by `wp_installing()` / `function_exists('WC')`; under
 * the test harness that guard can skip it, so we boot the singleton directly
 * (idempotent — if the real boot happened, this is a no-op).
 */
function _flexa_extra_boot(): void {
    if ( class_exists( \Flexa\Extra\Initialize::class ) ) {
        \Flexa\Extra\Initialize::get_instance();
    }
}
tests_add_filter( 'plugins_loaded', '_flexa_extra_boot', 99 );

/**
 * Install WooCommerce tables/roles once WordPress is ready.
 */
function _flexa_extra_install_woocommerce(): void {
    if ( ! class_exists( 'WooCommerce' ) ) {
        return;
    }

    update_option( 'woocommerce_db_version', WC()->version );
    WC_Install::install();

    // Reinit roles/caps created during install.
    $roles = new WP_Roles();
    unset( $roles );
}
tests_add_filter( 'setup_theme', '_flexa_extra_install_woocommerce' );

require "$_tests_dir/includes/bootstrap.php";
