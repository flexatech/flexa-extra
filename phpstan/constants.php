<?php
/**
 * Bootstrap constants for static analysis only.
 *
 * The real values are defined in flexa-extra.php behind `if ( ! defined() )`
 * guards, which PHPStan does not evaluate. Declaring them here lets the
 * analyser resolve `FLEXA_EXTRA_*` without running WordPress.
 *
 * Not loaded at runtime — referenced solely from phpstan.neon bootstrapFiles.
 */

define( 'FLEXA_EXTRA_FILE', '' );
define( 'FLEXA_EXTRA_VERSION', '1.0.0' );
define( 'FLEXA_EXTRA_PLUGIN_URL', '' );
define( 'FLEXA_EXTRA_PLUGIN_DIR', '' );
define( 'FLEXA_EXTRA_BASE_NAME', '' );
define( 'FLEXA_EXTRA_REST_NAMESPACE', 'flexa-extra/v1' );
