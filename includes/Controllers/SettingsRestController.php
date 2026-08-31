<?php
namespace Flexa\Extra\Controllers;

defined( 'ABSPATH' ) || exit;

use Flexa\Extra\Utils\SingletonTrait;
use Flexa\Extra\Helpers\Helper;
use WP_REST_Request;
use WP_REST_Response;

/**
 * GET/POST /flexa-extra/v1/settings
 */
final class SettingsRestController extends BaseRestController {
    use SingletonTrait;

    protected function __construct() {
        register_rest_route(
            $this->namespace,
            '/settings',
            [
                [
                    'methods'             => 'GET',
                    'callback'            => [ $this, 'get_settings' ],
                    'permission_callback' => [ $this, 'permission_callback' ],
                ],
                [
                    'methods'             => 'POST',
                    'callback'            => [ $this, 'save_settings' ],
                    'permission_callback' => [ $this, 'permission_callback' ],
                ],
            ]
        );
    }

    public function get_settings( WP_REST_Request $request ): WP_REST_Response {
        unset( $request );
        return $this->success( Helper::get_settings() );
    }

    public function save_settings( WP_REST_Request $request ): WP_REST_Response {
        $incoming = $this->get_json_params( $request );

        // Merge partial updates over the stored settings, then sanitize.
        $merged    = array_replace_recursive( Helper::get_settings(), $incoming );
        $sanitized = Helper::sanitize_settings( $merged );
        $old       = Helper::get_settings();

        update_option( 'flexa_extra_settings', $sanitized );

        do_action( 'flexa_extra/settings/updated', $sanitized, $old );

        return $this->success( $sanitized, __( 'Settings saved successfully', 'flexa-extra' ) );
    }
}
