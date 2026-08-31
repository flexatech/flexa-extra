<?php
namespace Flexa\Extra\Register;

defined( 'ABSPATH' ) || exit;

use Flexa\Extra\Utils\SingletonTrait;

/**
 * Registers the Vite dev server entry (localhost:3000) and injects the React
 * Refresh preamble. Active only when FLEXA_EXTRA_IS_DEVELOPMENT === true.
 */
final class RegisterDev {
    use SingletonTrait;

    protected function __construct() {
        add_action( 'admin_footer', [ $this, 'render_dev_refresh_admin' ], 5 );
        add_action( 'init', [ $this, 'register_all_scripts' ] );
    }

    public function render_dev_refresh_admin(): void {
        echo '<script type="module">
        import RefreshRuntime from "http://localhost:3000/@react-refresh"
        RefreshRuntime.injectIntoGlobalHook(window)
        window.$RefreshReg$ = () => {}
        window.$RefreshSig$ = () => (type) => type
        window.__vite_plugin_react_preamble_installed__ = true
        </script>';
    }

    public function register_all_scripts(): void {
        $deps = [ 'react', 'react-dom', 'wp-hooks', 'wp-i18n' ];
        wp_register_script( ScriptName::PAGE_SETTINGS, 'http://localhost:3000/main.tsx', $deps, FLEXA_EXTRA_VERSION, true );
        wp_set_script_translations( ScriptName::PAGE_SETTINGS, 'flexa-extra', FLEXA_EXTRA_PLUGIN_DIR . 'languages' );
    }
}
