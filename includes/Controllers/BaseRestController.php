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

    /**
     * Normalise a Y-m-d input to a day boundary timestamp. `$end` pins to the
     * end of the day so a range is inclusive; falls back to `$default`.
     */
    protected function day_bound( string $value, string $default, bool $end ): int {
        $ts = '' !== $value ? strtotime( $value ) : false;
        if ( false === $ts ) {
            $ts = strtotime( $default );
        }
        $ts = false === $ts ? time() : $ts;

        $suffix = $end ? ' 23:59:59' : ' 00:00:00';
        $bound  = strtotime( gmdate( 'Y-m-d', $ts ) . $suffix );

        return false === $bound ? $ts : $bound;
    }

    /**
     * Parse a comma-separated status filter into WooCommerce order statuses.
     * Empty / "any" means paid-and-fulfilled statuses (the useful default).
     *
     * @return list<string>
     */
    protected function parse_order_statuses( string $raw ): array {
        $raw = trim( $raw );
        if ( '' === $raw || 'any' === $raw ) {
            return [ 'completed', 'processing' ];
        }

        $valid = array_keys( wc_get_order_statuses() ); // e.g. wc-completed.
        $out   = [];
        foreach ( explode( ',', $raw ) as $status ) {
            $status = sanitize_key( trim( $status ) );
            if ( '' === $status ) {
                continue;
            }
            $prefixed = 0 === strpos( $status, 'wc-' ) ? $status : 'wc-' . $status;
            if ( in_array( $prefixed, $valid, true ) ) {
                $out[] = substr( $prefixed, 3 );
            }
        }

        return empty( $out ) ? [ 'completed', 'processing' ] : $out;
    }
}
