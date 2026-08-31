<?php
namespace Flexa\Extra\Register;

defined( 'ABSPATH' ) || exit;

use Flexa\Extra\Utils\SingletonTrait;

/**
 * Registers admin assets and dispatches to the dev or prod script registrar.
 *
 * @method static RegisterFacade get_instance()
 */
final class RegisterFacade {
    use SingletonTrait;

    protected function __construct() {
        add_filter( 'script_loader_tag', [ $this, 'add_entry_as_module' ], 10, 2 );
        add_action( 'init', [ $this, 'register_all_assets' ] );
        add_filter( 'pre_load_script_translations', [ $this, 'use_mo_file_for_script_translations' ], 10, 4 );

        $is_prod = ! defined( 'FLEXA_EXTRA_IS_DEVELOPMENT' ) || FLEXA_EXTRA_IS_DEVELOPMENT !== true;
        if ( $is_prod && class_exists( '\Flexa\Extra\Register\RegisterProd' ) ) {
            RegisterProd::get_instance();
        } elseif ( ! $is_prod && class_exists( '\Flexa\Extra\Register\RegisterDev' ) ) {
            RegisterDev::get_instance();
        }
    }

    public function add_entry_as_module( string $tag, string $handle ): string {
        if ( strpos( $handle, ScriptName::MODULE_PREFIX ) !== false ) {
            if ( strpos( $tag, 'type="' ) !== false ) {
                return preg_replace( '/\stype="\S+\s/', ' type="module" ', $tag, 1 );
            }
            return str_replace( ' src=', ' type="module" src=', $tag );
        }
        return $tag;
    }

    public function register_all_assets(): void {
        wp_register_style(
            ScriptName::STYLE_SETTINGS,
            FLEXA_EXTRA_PLUGIN_URL . 'assets/dist/admin/style.css',
            [ 'woocommerce_admin_styles', 'wp-components' ],
            FLEXA_EXTRA_VERSION
        );

        wp_register_style(
            ScriptName::STYLE_FRONTEND,
            FLEXA_EXTRA_PLUGIN_URL . 'assets/frontend/flexa-extra.css',
            [],
            FLEXA_EXTRA_VERSION
        );

        wp_register_script(
            ScriptName::PAGE_FRONTEND,
            FLEXA_EXTRA_PLUGIN_URL . 'assets/frontend/flexa-extra.js',
            [ 'wp-i18n' ],
            FLEXA_EXTRA_VERSION,
            true
        );
    }

    /**
     * Flexa Extra script translations all live in the MO file.
     *
     * @param string|false $json_translations Pre-parsed translations JSON.
     * @param string|false $file              Path to the JSON file.
     * @param string       $handle            Script handle.
     * @param string       $domain            Text domain.
     * @return string|false
     */
    public function use_mo_file_for_script_translations( $json_translations, $file, $handle, $domain ) {
        $all_handles = [ ScriptName::PAGE_SETTINGS ];

        if ( 'flexa-extra' !== $domain || ! in_array( $handle, $all_handles, true ) ) {
            return $json_translations;
        }

        $translations = get_translations_for_domain( 'flexa-extra' );
        $messages     = [
            '' => [ 'domain' => 'messages' ],
        ];
        foreach ( $translations->entries as $entry ) {
            $messages[ $entry->singular ] = $entry->translations;
        }

        return wp_json_encode(
            [
                'domain'      => 'messages',
                'locale_data' => [ 'messages' => $messages ],
            ]
        );
    }
}
