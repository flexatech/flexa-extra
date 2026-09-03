<?php
namespace Flexa\Extra\Engine\Admin;

defined( 'ABSPATH' ) || exit;

use Flexa\Extra\Utils\SingletonTrait;
use Flexa\Extra\Register\ScriptName;
use Flexa\Extra\Helpers\Helper;

/**
 * Registers the admin menu page and mounts the React app.
 */
final class Settings {
    use SingletonTrait;

    private const PAGE_SLUG = 'flexa-extra';

    private const HOOK_SUFFIX = 'toplevel_page_flexa-extra';

    protected function __construct() {
        add_filter( 'admin_body_class', [ $this, 'admin_body_class' ] );
        add_action( 'admin_menu', [ $this, 'admin_menu' ] );
        add_filter( 'plugin_action_links_' . FLEXA_EXTRA_BASE_NAME, [ $this, 'add_action_links' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
    }

    public function admin_body_class( string $classes ): string {
        if ( strpos( $classes, 'flexa-extra-ui' ) === false ) {
            $classes .= ' flexa-extra-ui';
        }
        return $classes;
    }

    /**
     * @param array<int,string> $links
     * @return array<int,string>
     */
    public function add_action_links( $links ): array {
        return array_merge(
            [
                '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) . '">' . esc_html__( 'Settings', 'flexa-extra' ) . '</a>',
            ],
            $links
        );
    }

    public function admin_menu(): void {
        add_menu_page(
            __( 'Flexa Extra', 'flexa-extra' ),
            __( 'Flexa Extra', 'flexa-extra' ),
            'manage_options',
            self::PAGE_SLUG,
            [ $this, 'render_page' ],
            'dashicons-admin-generic',
            81
        );
    }

    public function render_page(): void {
        echo wp_kses(
            '<div id="flexa-extra-admin-root"></div>',
            [ 'div' => [ 'id' => true ] ]
        );
    }

    public function admin_enqueue_scripts( string $hook_suffix ): void {
        if ( self::HOOK_SUFFIX !== $hook_suffix ) {
            return;
        }

        wp_localize_script( ScriptName::PAGE_SETTINGS, 'flexaExtra', Helper::get_js_config() );
        wp_enqueue_media();
        wp_enqueue_script( ScriptName::PAGE_SETTINGS );
        wp_enqueue_style( ScriptName::STYLE_SETTINGS );
    }
}
