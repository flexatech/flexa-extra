<?php
namespace Flexa\Extra\Cart;

defined( 'ABSPATH' ) || exit;

use Flexa\Extra\Utils\SingletonTrait;
use WC_Cart;

/**
 * Applies the extra-option surcharge to each cart item.
 *
 * The surcharge is recomputed from the stored selections against the current
 * field definitions on every totals pass — the price is set absolutely
 * (`base + extra`) rather than incremented, so repeated calls never compound
 * and a client-submitted price is never used.
 */
final class PriceCalculator {
    use SingletonTrait;

    private const KEY = 'flexa_extra';

    protected function __construct() {
        add_action( 'woocommerce_before_calculate_totals', [ $this, 'apply' ], 20 );
    }

    /**
     * @param WC_Cart|mixed $cart
     */
    public function apply( $cart ): void {
        if ( ! $cart instanceof WC_Cart ) {
            return;
        }

        foreach ( $cart->get_cart() as $cart_item ) {
            if ( ! isset( $cart_item[ self::KEY ], $cart_item['data'] ) ) {
                continue;
            }

            $product = $cart_item['data'];
            if ( ! $product instanceof \WC_Product ) {
                continue;
            }

            $base   = isset( $cart_item[ self::KEY ]['base'] ) ? (float) $cart_item[ self::KEY ]['base'] : (float) $product->get_price();
            $result = SelectionProcessor::process( $product, (array) ( $cart_item[ self::KEY ]['selections'] ?? array() ), $base );

            $extra = (float) apply_filters( 'flexa_extra/cart/item_extra', $result['total'], $cart_item, $result );

            // A discount action can push the extra negative; never let the line
            // price fall below zero.
            $product->set_price( (string) max( 0.0, $base + $extra ) );
        }
    }
}
