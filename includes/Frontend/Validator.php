<?php
namespace Flexa\Extra\Frontend;

defined( 'ABSPATH' ) || exit;

use Flexa\Extra\Utils\SingletonTrait;
use Flexa\Extra\Cart\Input;
use Flexa\Extra\Cart\SelectionProcessor;

/**
 * Validates extra-option input when a product is added to the cart.
 *
 * Runs the same authoritative {@see SelectionProcessor} the price layer uses,
 * so required fields, formats and ranges are enforced server-side before the
 * item can enter the cart.
 */
final class Validator {
    use SingletonTrait;

    protected function __construct() {
        add_filter( 'woocommerce_add_to_cart_validation', [ $this, 'validate' ], 10, 3 );
    }

    /**
     * @param bool $passed
     * @param int  $product_id
     * @param int  $quantity
     */
    public function validate( $passed, $product_id, $quantity ): bool {
        unset( $quantity );

        if ( ! $passed ) {
            return false;
        }

        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return $passed;
        }

        $raw    = Input::read();
        $result = SelectionProcessor::process( $product, $raw );

        if ( empty( $result['errors'] ) ) {
            return true;
        }

        foreach ( $result['errors'] as $message ) {
            wc_add_notice( $message, 'error' );
        }

        return false;
    }
}
