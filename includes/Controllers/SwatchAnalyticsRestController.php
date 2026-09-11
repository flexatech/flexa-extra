<?php
namespace Flexa\Extra\Controllers;

defined( 'ABSPATH' ) || exit;

use Flexa\Extra\Utils\SingletonTrait;
use Flexa\Extra\Variations\AttributeType;
use Flexa\Extra\Variations\TermMeta;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Read-only variation-swatch analytics.
 *
 *   GET /flexa-extra/v1/variation-swatches/analytics?from=Y-m-d&to=Y-m-d&status=completed,processing
 *
 * Answers "which attribute values (swatches) do shoppers actually buy". It reads
 * the variation attributes recorded on each order line item, so it needs no
 * extra tracking: WooCommerce already stores the chosen attribute on the order.
 * HPOS-safe (orders come through {@see wc_get_orders()}, item meta lives in the
 * same table under both storage engines).
 *
 * Store-admin only (`manage_woocommerce`), matching the swatch settings screens.
 */
final class SwatchAnalyticsRestController extends BaseRestController {
    use SingletonTrait;

    /** Hard ceiling on orders scanned per request; surfaced to the client. */
    private const MAX_ORDERS = 5000;

    protected function __construct() {
        register_rest_route(
            $this->namespace,
            '/variation-swatches/analytics',
            [
                [
                    'methods'             => 'GET',
                    'callback'            => [ $this, 'report' ],
                    'permission_callback' => [ $this, 'permission_callback' ],
                ],
            ]
        );
    }

    /**
     * Variation swatches are store-level configuration: gate on WooCommerce
     * management rather than the generic `manage_options`.
     *
     * @return bool|\WP_Error
     */
    public function permission_callback( WP_REST_Request $request ) {
        unset( $request );

        return current_user_can( 'manage_woocommerce' )
            ? true
            : new \WP_Error(
                'rest_forbidden',
                __( 'You are not allowed to manage Flexa Extra.', 'flexa-extra' ),
                [ 'status' => 403 ]
            );
    }

    public function report( WP_REST_Request $request ): WP_REST_Response {
        if ( ! function_exists( 'wc_get_orders' ) ) {
            return $this->error( __( 'WooCommerce is not active.', 'flexa-extra' ), 400 );
        }

        $to_ts   = $this->day_bound( (string) $request->get_param( 'to' ), 'now', true );
        $from_ts = $this->day_bound( (string) $request->get_param( 'from' ), '-29 days', false );
        if ( $from_ts > $to_ts ) {
            [ $from_ts, $to_ts ] = [ $to_ts, $from_ts ];
        }

        $statuses = $this->parse_order_statuses( (string) $request->get_param( 'status' ) );

        $orders = wc_get_orders(
            [
                'limit'        => self::MAX_ORDERS,
                'status'       => $statuses,
                'type'         => 'shop_order',
                'date_created' => $from_ts . '...' . $to_ts,
                'orderby'      => 'date',
                'order'        => 'DESC',
                'return'       => 'objects',
            ]
        );
        $orders = is_array( $orders ) ? $orders : [];

        $terms      = [];
        $attributes = [];
        $revenue    = 0.0;
        $selections = 0;
        $orders_hit = 0;

        foreach ( $orders as $order ) {
            $order_had_variation = false;

            foreach ( $order->get_items() as $item ) {
                if ( ! $item instanceof \WC_Order_Item_Product || $item->get_variation_id() <= 0 ) {
                    continue;
                }

                $product = $item->get_product();
                if ( ! $product || ! is_callable( [ $product, 'get_variation_attributes' ] ) ) {
                    continue;
                }

                $qty  = max( 1, (int) $item->get_quantity() );
                $line = (float) $item->get_total();

                // The variation's price counts once, no matter how many
                // attributes it carries; per-attribute revenue is tracked
                // separately below (and may sum higher across attributes).
                $item_counted = false;

                foreach ( $product->get_variation_attributes() as $key => $value ) {
                    $taxonomy = (string) preg_replace( '/^attribute_/', '', (string) $key );
                    if ( '' === $taxonomy ) {
                        continue;
                    }

                    // A variation set to "Any <attr>" stores '' here; the real
                    // choice lives on the order item meta under the attr slug.
                    if ( '' === $value ) {
                        $value = (string) $item->get_meta( $taxonomy, true );
                    }
                    if ( '' === $value ) {
                        continue;
                    }

                    $order_had_variation = true;
                    $selections         += $qty;
                    if ( ! $item_counted ) {
                        $revenue     += $line;
                        $item_counted = true;
                    }

                    $is_taxonomy = taxonomy_exists( $taxonomy );
                    $attr_label  = $is_taxonomy
                        ? wc_attribute_label( $taxonomy )
                        : ucwords( str_replace( [ '-', '_' ], ' ', $taxonomy ) );
                    $swatch_type = $is_taxonomy ? AttributeType::get_type( $taxonomy ) : AttributeType::TYPE_OFF;

                    $term_name = $value;
                    $color     = '';
                    $image     = '';
                    if ( $is_taxonomy ) {
                        $term = get_term_by( 'slug', $value, $taxonomy );
                        if ( $term instanceof \WP_Term ) {
                            $term_name = (string) $term->name;
                            $term_id   = (int) $term->term_id;
                            $color     = TermMeta::get_color( $term_id );
                            $image     = TermMeta::get_image_url( $term_id );
                        }
                    }

                    if ( ! isset( $attributes[ $taxonomy ] ) ) {
                        $attributes[ $taxonomy ] = [
                            'taxonomy'    => $taxonomy,
                            'label'       => $attr_label,
                            'swatch_type' => $swatch_type,
                            'count'       => 0,
                            'revenue'     => 0.0,
                        ];
                    }
                    $attributes[ $taxonomy ]['count']   += $qty;
                    $attributes[ $taxonomy ]['revenue'] += $line;

                    $tkey = $taxonomy . '|' . $value;
                    if ( ! isset( $terms[ $tkey ] ) ) {
                        $terms[ $tkey ] = [
                            'taxonomy'        => $taxonomy,
                            'attribute_label' => $attr_label,
                            'slug'            => (string) $value,
                            'name'            => $term_name,
                            'swatch_type'     => $swatch_type,
                            'color'           => $color,
                            'image'           => $image,
                            'count'           => 0,
                            'revenue'         => 0.0,
                        ];
                    }
                    $terms[ $tkey ]['count']   += $qty;
                    $terms[ $tkey ]['revenue'] += $line;
                }
            }

            if ( $order_had_variation ) {
                $orders_hit++;
            }
        }

        $terms      = array_values( $terms );
        $attributes = array_values( $attributes );
        usort( $terms, static fn( $a, $b ) => $b['count'] <=> $a['count'] );
        usort( $attributes, static fn( $a, $b ) => $b['count'] <=> $a['count'] );

        $decimals = function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2;
        foreach ( $terms as &$row ) {
            $row['revenue'] = round( $row['revenue'], $decimals );
        }
        unset( $row );
        foreach ( $attributes as &$row ) {
            $row['revenue'] = round( $row['revenue'], $decimals );
        }
        unset( $row );

        return $this->success(
            [
                'range'      => [
                    'from' => gmdate( 'Y-m-d', $from_ts ),
                    'to'   => gmdate( 'Y-m-d', $to_ts ),
                ],
                'totals'     => [
                    'orders'     => $orders_hit,
                    'selections' => $selections,
                    'revenue'    => round( $revenue, $decimals ),
                ],
                'attributes' => $attributes,
                'terms'      => $terms,
                'scanned'    => [
                    'orders' => count( $orders ),
                    'capped' => count( $orders ) >= self::MAX_ORDERS,
                    'max'    => self::MAX_ORDERS,
                ],
            ]
        );
    }
}
