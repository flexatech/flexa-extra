<?php
namespace Flexa\Extra\Tests\Integration;

use WC_Order;

/**
 * Per-option inventory against real WooCommerce: add-to-cart is blocked when an
 * option would oversell, cart reservations count, and an order moves the counter.
 */
final class StockFlowTest extends IntegrationTestCase {

    /**
     * A single radio field whose "small" option has limited stock.
     *
     * @return array<string,mixed>
     */
    private function size_set_input( int $small_stock = 3 ): array {
        return array(
            'name'      => 'Size',
            'status'    => true,
            'targeting' => array( 'mode' => 'all' ),
            'fields'    => array(
                array(
                    'type'    => 'radio',
                    'id'      => 'size',
                    'label'   => 'Size',
                    'options' => array(
                        array( 'id' => 'small', 'label' => 'Small', 'value' => 'small', 'stock' => $small_stock ),
                        array( 'id' => 'large', 'label' => 'Large', 'value' => 'large' ),
                    ),
                ),
            ),
        );
    }

    private function option_stock( int $post_id, string $option_id ): ?int {
        $fields = get_post_meta( $post_id, '_flexa_extra_fields', true );
        foreach ( $fields as $field ) {
            foreach ( $field['options'] ?? array() as $option ) {
                if ( ( $option['id'] ?? '' ) === $option_id ) {
                    return $option['stock'];
                }
            }
        }
        return null;
    }

    public function test_out_of_stock_option_blocks_add_to_cart(): void {
        $this->register_option_set( $this->size_set_input( 0 ) );
        $product = $this->create_product( 40.0 );

        $key = $this->add_to_cart( $product->get_id(), array( 'size' => 'small' ) );

        $this->assertFalse( $key );
        $this->assertSame( 0, WC()->cart->get_cart_contents_count() );
        $this->assertTrue( wc_notice_count( 'error' ) > 0 );
        wc_clear_notices();
    }

    public function test_add_within_stock_is_allowed(): void {
        $this->register_option_set( $this->size_set_input( 3 ) );
        $product = $this->create_product( 40.0 );

        $key = $this->add_to_cart( $product->get_id(), array( 'size' => 'small' ), 3 );

        $this->assertNotFalse( $key );
    }

    public function test_quantity_over_stock_blocks_add_to_cart(): void {
        $this->register_option_set( $this->size_set_input( 3 ) );
        $product = $this->create_product( 40.0 );

        $key = $this->add_to_cart( $product->get_id(), array( 'size' => 'small' ), 4 );

        $this->assertFalse( $key );
        wc_clear_notices();
    }

    public function test_cart_reservation_blocks_a_second_add(): void {
        $this->register_option_set( $this->size_set_input( 3 ) );
        $product = $this->create_product( 40.0 );

        // Reserve two of three.
        $first = $this->add_to_cart( $product->get_id(), array( 'size' => 'small' ), 2 );
        $this->assertNotFalse( $first );

        // Two more would exceed the remaining one.
        $second = $this->add_to_cart( $product->get_id(), array( 'size' => 'small' ), 2 );
        $this->assertFalse( $second );
        wc_clear_notices();

        // One more fits exactly.
        $third = $this->add_to_cart( $product->get_id(), array( 'size' => 'small' ), 1 );
        $this->assertNotFalse( $third );
    }

    public function test_order_reduces_then_restores_option_stock(): void {
        $post_id = $this->register_option_set( $this->size_set_input( 3 ) );
        $product = $this->create_product( 40.0 );

        $order   = new WC_Order();
        $item_id = $order->add_product( $product, 2 );
        $item    = $order->get_item( $item_id );
        $item->add_meta_data( '_flexa_extra', array( 'size' => 'small' ), true );
        $item->save();
        $order->save();

        wc_reduce_stock_levels( $order->get_id() );
        $this->assertSame( 1, $this->option_stock( $post_id, 'small' ), 'Two units are consumed.' );

        wc_increase_stock_levels( $order->get_id() );
        $this->assertSame( 3, $this->option_stock( $post_id, 'small' ), 'Cancelled order returns the stock.' );
    }
}
