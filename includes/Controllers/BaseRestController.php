<?php
namespace Flexa\Extra\Controllers;

defined( 'ABSPATH' ) || exit;

use WP_REST_Request;
use WP_REST_Response;

/**
 * Common helpers for REST controllers.
 */
abstract class BaseRestController {

    protected string $namespace = FLEXA_EXTRA_REST_NAMESPACE;

    /**
     * Default permission callback: settings and option sets are store-admin only.
     *
     * @return bool|\WP_Error
     */
    public function permission_callback( WP_REST_Request $request ) {
        unset( $request );

        return current_user_can( 'manage_options' )
            ? true
            : new \WP_Error(
                'rest_forbidden',
                __( 'You are not allowed to manage Flexa Extra.', 'flexa-extra' ),
                [ 'status' => 403 ]
            );
    }

    /**
     * @param array<string,mixed> $data
     */
    protected function success( array $data = [], string $message = '' ): WP_REST_Response {
        $payload = [ 'success' => true ];
        if ( '' !== $message ) {
            $payload['message'] = $message;
        }
        if ( [] !== $data ) {
            $payload['data'] = $data;
        }

        return rest_ensure_response( $payload );
    }

    protected function error( string $message, int $status = 400 ): WP_REST_Response {
        $response = rest_ensure_response(
            [
                'success' => false,
                'message' => $message,
            ]
        );
        $response->set_status( $status );
        return $response;
    }

    /**
     * @return array<string,mixed>
     */
    protected function get_json_params( WP_REST_Request $request ): array {
        return (array) $request->get_json_params();
    }
}
