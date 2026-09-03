<?php
namespace Flexa\Extra\Tests\Integration;

use WC_Order;
use WC_Order_Item_Product;

/**
 * Set-level fee / discount actions against real WooCommerce: the adjustment
 * lands on the cart line price, shows in the cart, and reaches order meta.
 */
final class ActionFlowTest extends IntegrationTestCase {

    /**
     * A colour radio plus one conditional action.
     *
     * @param array<string,mixed> $action
     * @return array<string,mixed>
     */
    private function colour_set_with_action( array $action ): array {
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
                        array( 'id' => 'red', 'label' => 'Red', 'value' => 'red' ),
                        array( 'id' => 'blue', 'label' => 'Blue', 'value' => 'blue' ),
                    ),
                ),
            ),
            'actions'   => array( $action ),
        );
    }

    public function test_fee_action_raises_the_cart_line_price(): void {
        $this->register_option_set(
            $this->colour_set_with_action(
                array(
                    'id'    => 'rush',
                    'label' => 'Rush fee',
                    'kind'  => 'fee',
                    'price' => array( 'type' => 'fixed', 'amount' => 15 ),
                    'match' => 'any',
                    'rules' => array( array( 'field' => 'color', 'operator' => 'is', 'value' => 'red' ) ),
                )
            )
        );
        $product = $this->create_product( 100.0 );

        $this->add_to_cart( $product->get_id(), array( 'color' => 'red' ) );

        $this->assertSame( 115.0, (float) $this->first_cart_item()['data']->get_price() );
    }

    public function test_fee_action_is_skipped_when_condition_fails(): void {
        $this->register_option_set(
            $this->colour_set_with_action(
                array(
                    'id'    => 'rush',
                    'label' => 'Rush fee',
                    'kind'  => 'fee',
                    'price' => array( 'type' => 'fixed', 'amount' => 15 ),
                    'match' => 'any',
                    'rules' => array( array( 'field' => 'color', 'operator' => 'is', 'value' => 'red' ) ),
                )
            )
        );
        $product = $this->create_product( 100.0 );

        $this->add_to_cart( $product->get_id(), array( 'color' => 'blue' ) );

        $this->assertSame( 100.0, (float) $this->first_cart_item()['data']->get_price() );
    }

    public function test_discount_action_lowers_the_price_and_never_goes_negative(): void {
        $this->register_option_set(
            $this->colour_set_with_action(
                array(
                    'id'    => 'promo',
                    'label' => 'Promo',
                    'kind'  => 'discount',
                    'price' => array( 'type' => 'fixed', 'amount' => 250 ), // Larger than the product price.
                    'match' => 'any',
                    'rules' => array( array( 'field' => 'color', 'operator' => 'is', 'value' => 'red' ) ),
                )
            )
        );
        $product = $this->create_product( 100.0 );

        $this->add_to_cart( $product->get_id(), array( 'color' => 'red' ) );

        $this->assertSame( 0.0, (float) $this->first_cart_item()['data']->get_price() );
    }

    public function test_action_line_is_written_to_the_order(): void {
        $this->register_option_set(
            $this->colour_set_with_action(
                array(
                    'id'    => 'rush',
                    'label' => 'Rush fee',
                    'kind'  => 'fee',
                    'price' => array( 'type' => 'fixed', 'amount' => 15 ),
                    'match' => 'any',
                    'rules' => array( array( 'field' => 'color', 'operator' => 'is', 'value' => 'red' ) ),
                )
            )
        );
        $product = $this->create_product( 100.0 );
        $this->add_to_cart( $product->get_id(), array( 'color' => 'red' ) );

        $item = new WC_Order_Item_Product();
        do_action( 'woocommerce_checkout_create_order_line_item', $item, 'cart-key', $this->first_cart_item(), new WC_Order() );

        // The action label is the meta key; its value carries the fee amount.
        $value = (string) $item->get_meta( 'Rush fee' );
        $this->assertNotEmpty( $value );
        $this->assertStringContainsString( '15', $value );
    }
}
