<?php
namespace Flexa\Extra;

defined( 'ABSPATH' ) || exit;

/**
 * Text-domain loader.
 */
class I18n {

    public static function load_plugin_textdomain(): void {
        load_plugin_textdomain(
            'flexa-extra',
            false,
            dirname( FLEXA_EXTRA_BASE_NAME ) . '/languages'
        );
    }
}
