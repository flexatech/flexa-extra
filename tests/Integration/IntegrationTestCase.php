<?php
namespace Flexa\Extra\Tests\Integration;

use Flexa\Extra\Engine\Admin\CustomPostType;
use Flexa\Extra\Fields\OptionSetSchema;
use Flexa\Extra\Frontend\OptionSetResolver;
use WC_Product_Simple;
use WP_UnitTestCase;

/**
 * Base case for the WP + WooCommerce integration suite: helpers to create real
 * products, register option sets into post meta (the same shape the REST
 * controller writes), and drive the cart.
 */
abstract class IntegrationTestCase extends WP_UnitTestCase {

    private const META_FIELDS    = '_flexa_extra_fields';
    private const META_TARGETING = '_flexa_extra_targeting';
    private const META_STATUS    = '_flexa_extra_status';

    protected function setUp(): void {
        parent::setUp();
        OptionSetResolver::flush_cache();
        if ( function_exists( 'WC' ) && WC()->cart ) {
            WC()->cart->empty_cart();
        }
        $_POST    = array();
        $_REQUEST = array();
    }

    protected function tearDown(): void {
        $_POST    = array();
        $_REQUEST = array();
        if ( function_exists( 'WC' ) && WC()->cart ) {
            WC()->cart->empty_cart();
        }
        parent::tearDown();
    }

    protected function create_product( float $price = 100.0 ): WC_Product_Simple {
        $product = new WC_Product_Simple();
        $product->set_name( 'Test Product' );
        $product->set_regular_price( (string) $price );
        $product->set_price( (string) $price );
        $product->set_stock_status( 'instock' );
        $product->save();

        return $product;
    }

    /**
     * Persist an option set exactly as the REST controller would.
     *
     * @param array<string,mixed> $input Raw option-set body (pre-sanitize).
     */
    protected function register_option_set( array $input ): int {
        $data = OptionSetSchema::sanitize( $input );

        $post_id = wp_insert_post(
            array(
                'post_type'   => CustomPostType::POST_TYPE,
                'post_title'  => $data['name'],
                'post_status' => 'publish',
            )
        );

        update_post_meta( $post_id, self::META_FIELDS, $data['fields'] );
        update_post_meta( $post_id, self::META_TARGETING, $data['targeting'] );
        update_post_meta( $post_id, self::META_STATUS, $data['status'] ? 1 : 0 );

        OptionSetResolver::flush_cache();

        return $post_id;
    }

    /**
     * Add a product to the cart with the given extra-option POST payload.
     *
     * Mirrors WooCommerce's form handler: the `woocommerce_add_to_cart_validation`
     * filter (which WC applies in class-wc-form-handler, NOT in WC_Cart::add_to_cart)
     * is evaluated first, and the item is only added when it passes.
     *
     * @param array<string,mixed> $selections Raw `flexa_extra[<field_id>]` values.
     * @return string|false Cart item key, or false when validation blocked it.
     */
    protected function add_to_cart( int $product_id, array $selections, int $quantity = 1 ) {
        $_POST['flexa_extra']    = $selections;
        $_REQUEST['flexa_extra'] = $selections;

        $passed = apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity );
        if ( ! $passed ) {
            return false;
        }

        $key = WC()->cart->add_to_cart( $product_id, $quantity );
        WC()->cart->calculate_totals();
        return $key;
    }

    protected function first_cart_item(): array {
        $cart = WC()->cart->get_cart();
        return $cart ? array_values( $cart )[0] : array();
    }
}
