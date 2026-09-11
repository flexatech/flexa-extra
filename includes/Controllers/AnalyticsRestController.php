<?php
namespace Flexa\Extra\Controllers;

defined( 'ABSPATH' ) || exit;

use Flexa\Extra\Cart\CartHandler;
use Flexa\Extra\Utils\SingletonTrait;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Read-only option analytics.
 *
 *   GET /flexa-extra/v1/analytics?from=Y-m-d&to=Y-m-d&status=completed,processing
 *
 * Aggregates the structured {@see CartHandler::META_REPORT} order-item meta into
 * "which options sell, and for how much revenue". HPOS-safe: orders are read
 * through {@see wc_get_orders()} rather than raw SQL, and item meta lives in the
 * same table under both storage engines. Reports cover orders placed after the
 * report meta shipped; older orders simply don't contribute.
 */
final class AnalyticsRestController extends BaseRestController {
    use SingletonTrait;

    /** Hard ceiling on orders scanned per request; surfaced to the client. */
    private const MAX_ORDERS = 5000;

    protected function __construct() {
        register_rest_route(
            $this->namespace,
            '/analytics',
            [
                [
                    'methods'             => 'GET',
                    'callback'            => [ $this, 'report' ],
                    'permission_callback' => [ $this, 'permission_callback' ],
                ],
            ]
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

        $options    = [];
        $fields     = [];
        $revenue    = 0.0;
        $selections = 0;
        $orders_hit = 0;

        foreach ( $orders as $order ) {
            $order_had_options = false;

            foreach ( $order->get_items() as $item ) {
                $report = $item->get_meta( CartHandler::META_REPORT, true );
                if ( ! is_array( $report ) ) {
                    continue;
                }

                foreach ( $report as $row ) {
                    if ( ! is_array( $row ) ) {
                        continue;
                    }

                    $order_had_options = true;

                    $field   = isset( $row['field'] ) ? (string) $row['field'] : '';
                    $option  = isset( $row['option'] ) ? (string) $row['option'] : '';
                    $type    = isset( $row['type'] ) ? (string) $row['type'] : '';
                    $name    = isset( $row['name'] ) ? (string) $row['name'] : $field;
                    $flabel  = isset( $row['field_label'] ) ? (string) $row['field_label'] : $field;
                    $qty     = isset( $row['qty'] ) ? max( 1, (int) $row['qty'] ) : 1;
                    $line    = ( isset( $row['amount'] ) ? (float) $row['amount'] : 0.0 ) * $qty;

                    $revenue    += $line;
                    $selections += $qty;

                    $okey = $field . '|' . $option;
                    if ( ! isset( $options[ $okey ] ) ) {
                        $options[ $okey ] = [
                            'field'       => $field,
                            'field_label' => $flabel,
                            'option'      => $option,
                            'name'        => $name,
                            'type'        => $type,
                            'count'       => 0,
                            'revenue'     => 0.0,
                        ];
                    }
                    $options[ $okey ]['count']   += $qty;
                    $options[ $okey ]['revenue'] += $line;

                    if ( ! isset( $fields[ $field ] ) ) {
                        $fields[ $field ] = [
                            'field'   => $field,
                            'label'   => $flabel,
                            'type'    => $type,
                            'count'   => 0,
                            'revenue' => 0.0,
                        ];
                    }
                    $fields[ $field ]['count']   += $qty;
                    $fields[ $field ]['revenue'] += $line;
                }
            }

            if ( $order_had_options ) {
                $orders_hit++;
            }
        }

        $options = array_values( $options );
        $fields  = array_values( $fields );
        usort( $options, static fn( $a, $b ) => $b['count'] <=> $a['count'] );
        usort( $fields, static fn( $a, $b ) => $b['count'] <=> $a['count'] );

        return $this->success(
            [
                'range'   => [
                    'from' => gmdate( 'Y-m-d', $from_ts ),
                    'to'   => gmdate( 'Y-m-d', $to_ts ),
                ],
                'totals'  => [
                    'orders'     => $orders_hit,
                    'selections' => $selections,
                    'revenue'    => round( $revenue, wc_get_price_decimals() ),
                ],
                'options' => $options,
                'fields'  => $fields,
                'scanned' => [
                    'orders' => count( $orders ),
                    'capped' => count( $orders ) >= self::MAX_ORDERS,
                    'max'    => self::MAX_ORDERS,
                ],
            ]
        );
    }
}
