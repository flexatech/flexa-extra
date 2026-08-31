<?php
/**
 * PHPUnit bootstrap for the Flexa Extra unit suite.
 *
 * These are fast, DB-less unit tests: instead of loading WordPress we define a
 * minimal set of WP/WooCommerce function + class stubs (tests/stubs) and let
 * the plugin's own classes run against them. The goal is to pin down the
 * money-critical, framework-agnostic logic (sanitizer, pricing/validation
 * engine, targeting resolver) so refactors stay safe.
 */

declare(strict_types=1);

// Plugin classes guard on ABSPATH; define it so they load outside WordPress.
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}

require __DIR__ . '/../vendor/autoload.php';

// WP/WC function + class stubs (functions can't be autoloaded).
require __DIR__ . '/stubs/wp-functions.php';
require __DIR__ . '/stubs/wc-classes.php';
