<?php
namespace Flexa\Extra\Tests\Integration;

use WC_Order;
use WC_Order_Item_Product;

/**
 * End-to-end cart behaviour with real WooCommerce: fee application, validation,
 * anti-tampering, cart display and order-item meta.
 */
final class CartFlowTest extends IntegrationTestCase {

    private function colour_set_input(): array {
        return array(
            'name'      => 'Colour',
            'status'    => true,
            'targeting' => array( 'mode' => 'all' ),
            'fields'    => array(
                array(
                    'type'    => 'radio',
                    'id'      => 'color',
                    'label'   => 'Colour',
                    'options' => array(
                        array( 'id' => 'red', 'label' => 'Red', 'value' => 'red', 'price' => array( 'type' => 'fixed', 'amount' => 5 ) ),
                        array( 'id' => 'blue', 'label' => 'Blue', 'value' => 'blue', 'price' => array( 'type' => 'fixed', 'amount' => 8 ) ),
                    ),
                ),
            ),
        );
    }

    public function test_fixed_fee_is_added_to_cart_line_price(): void {
        $this->register_option_set( $this->colour_set_input() );
        $product = $this->create_product( 100.0 );

        $key = $this->add_to_cart( $product->get_id(), array( 'color' => 'red' ) );

        $this->assertNotFalse( $key );
        $item = $this->first_cart_item();
        $this->assertSame( 105.0, (float) $item['data']->get_price() );
    }

    public function test_percent_fee_is_relative_to_product_price(): void {
        $this->register_option_set(
            array(
                'name'      => 'Plan',
                'status'    => true,
                'targeting' => array( 'mode' => 'all' ),
                'fields'    => array(
                    array(
                        'type'    => 'radio',
                        'id'      => 'plan',
                        'label'   => 'Plan',
                        'options' => array(
                            array( 'id' => 'gold', 'label' => 'Gold', 'value' => 'gold', 'price' => array( 'type' => 'percent', 'amount' => 10 ) ),
                        ),
                    ),
                ),
            )
        );
        $product = $this->create_product( 200.0 );

        $this->add_to_cart( $product->get_id(), array( 'plan' => 'gold' ) );

        $item = $this->first_cart_item();
        $this->assertSame( 220.0, (float) $item['data']->get_price() );
    }

    public function test_required_field_blocks_add_to_cart(): void {
        $this->register_option_set(
            array(
                'name'      => 'Engrave',
                'status'    => true,
                'targeting' => array( 'mode' => 'all' ),
                'fields'    => array(
                    array( 'type' => 'text', 'id' => 'engrave', 'label' => 'Engraving', 'required' => true ),
                ),
            )
        );
        $product = $this->create_product( 50.0 );

        $key = $this->add_to_cart( $product->get_id(), array() );

        $this->assertFalse( $key );
        $this->assertSame( 0, WC()->cart->get_cart_contents_count() );
        $this->assertTrue( wc_notice_count( 'error' ) > 0 );
        wc_clear_notices();
    }

    public function test_client_supplied_price_is_ignored(): void {
        $this->register_option_set( $this->colour_set_input() );
        $product = $this->create_product( 100.0 );

        // Attacker appends bogus price fields to the POST.
        $key = $this->add_to_cart(
            $product->get_id(),
            array( 'color' => 'red', 'price' => '0', 'total' => '1', 'amount' => '9999' )
        );

        $this->assertNotFalse( $key );
        $item = $this->first_cart_item();
        $this->assertSame( 105.0, (float) $item['data']->get_price() );
    }

    public function test_selection_is_shown_in_cart(): void {
        $this->register_option_set( $this->colour_set_input() );
        $product = $this->create_product( 100.0 );
        $this->add_to_cart( $product->get_id(), array( 'color' => 'red' ) );

        $formatted = wc_get_formatted_cart_item_data( $this->first_cart_item() );

        $this->assertStringContainsString( 'Colour', $formatted );
        $this->assertStringContainsString( 'Red', $formatted );
    }

    public function test_selection_is_written_to_order_item_meta(): void {
        $this->register_option_set( $this->colour_set_input() );
        $product = $this->create_product( 100.0 );
        $this->add_to_cart( $product->get_id(), array( 'color' => 'red' ) );

        $item      = new WC_Order_Item_Product();
        $cart_item = $this->first_cart_item();
        do_action( 'woocommerce_checkout_create_order_line_item', $item, 'cart-key', $cart_item, new WC_Order() );

        $this->assertStringContainsString( 'Red', (string) $item->get_meta( 'Colour' ) );
        $this->assertNotEmpty( $item->get_meta( '_flexa_extra' ) );
    }
}
