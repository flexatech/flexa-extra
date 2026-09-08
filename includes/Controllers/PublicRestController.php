<?php
namespace Flexa\Extra\Controllers;

defined( 'ABSPATH' ) || exit;

use Flexa\Extra\Frontend\OptionSetResolver;
use Flexa\Extra\Helpers\Helper;
use Flexa\Extra\Utils\SingletonTrait;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Public, read-only endpoints for headless / decoupled storefronts.
 *
 *   GET /flexa-extra/v1/public/config
 *   GET /flexa-extra/v1/public/product/{id}
 *
 * These return exactly what the on-page storefront already ships to every
 * visitor in its JSON island (field definitions, prices, conditional logic)
 * plus the presentation config (currency, labels, style), so a decoupled front
 * end can render the same configurator and compute the same live subtotal.
 * Nothing here is admin-only, so the endpoints are open by default; a site can
 * still close them with the `flexa_extra/public_api/enabled` filter. Add-to-cart
 * and the authoritative price recompute stay server-side in the cart layer.
 */
final class PublicRestController extends BaseRestController {
    use SingletonTrait;

    protected function __construct() {
        register_rest_route(
            $this->namespace,
            '/public/config',
            [
                [
                    'methods'             => 'GET',
                    'callback'            => [ $this, 'config' ],
                    'permission_callback' => [ $this, 'public_permission' ],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/public/product/(?P<id>\d+)',
            [
                [
                    'methods'             => 'GET',
                    'callback'            => [ $this, 'product' ],
                    'permission_callback' => [ $this, 'public_permission' ],
                    'args'                => [ 'id' => [ 'sanitize_callback' => 'absint' ] ],
                ],
            ]
        );
    }

    /**
     * Open to everyone unless the site closes the public API via filter.
     *
     * @return bool|\WP_Error
     */
    public function public_permission( WP_REST_Request $request ) {
        unset( $request );

        /**
         * Toggle the public headless read API. Return false to close it.
         *
         * @param bool $enabled
         */
        if ( ! apply_filters( 'flexa_extra/public_api/enabled', true ) ) {
            return new \WP_Error(
                'rest_forbidden',
                __( 'The Flexa Extra public API is disabled.', 'flexa-extra' ),
                [ 'status' => 403 ]
            );
        }

        return true;
    }

    public function config( WP_REST_Request $request ): WP_REST_Response {
        unset( $request );

        return $this->success( Helper::get_public_config() );
    }

    public function product( WP_REST_Request $request ): WP_REST_Response {
        if ( ! function_exists( 'wc_get_product' ) ) {
            return $this->error( __( 'WooCommerce is not active.', 'flexa-extra' ), 400 );
        }

        $product = wc_get_product( (int) $request['id'] );
        if ( ! $product instanceof \WC_Product ) {
            return $this->error( __( 'Product not found.', 'flexa-extra' ), 404 );
        }

        $config = Helper::get_public_config();

        // Mirror the storefront: nothing renders when the plugin is switched off.
        $sets = empty( $config['enabled'] ) ? [] : OptionSetResolver::for_product( $product );

        return $this->success(
            [
                'productId'    => $product->get_id(),
                'productPrice' => (float) wc_get_price_to_display( $product ),
                'sets'         => array_map(
                    static function ( array $set ): array {
                        return [
                            'id'      => $set['id'],
                            'name'    => $set['name'],
                            'fields'  => $set['fields'],
                            'actions' => $set['actions'],
                        ];
                    },
                    $sets
                ),
                'config'       => $config,
            ]
        );
    }
}
