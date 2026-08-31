<?php
namespace Flexa\Extra\Register;

defined( 'ABSPATH' ) || exit;

use Flexa\Extra\Utils\SingletonTrait;

/** Registers the built admin bundle in production mode. */
final class RegisterProd {
    use SingletonTrait;

    protected function __construct() {
        add_action( 'init', [ $this, 'register_all_scripts' ] );
    }

    public function register_all_scripts(): void {
        $deps = [ 'react', 'react-dom', 'wp-hooks', 'wp-i18n' ];
        wp_register_script( ScriptName::PAGE_SETTINGS, FLEXA_EXTRA_PLUGIN_URL . 'assets/dist/admin/js/main.js', $deps, FLEXA_EXTRA_VERSION, true );
        wp_set_script_translations( ScriptName::PAGE_SETTINGS, 'flexa-extra', FLEXA_EXTRA_PLUGIN_DIR . 'languages' );
    }
}
