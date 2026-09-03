<?php
namespace Flexa\Extra\Tests\Integration;

use Flexa\Extra\Cart\EditContext;

/**
 * Edit options in cart: the product page pre-fills from a cart line's stored
 * selections, and re-adding in edit mode replaces that line instead of stacking
 * a second one. The replace is nonce-guarded.
 */
final class EditFlowTest extends IntegrationTestCase {

    /**
     * A single colour radio with two priced options.
     */
    private function register_colour_set(): void {
        $this->register_option_set(
            array(
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
                            array( 'id' => 'blue', 'label' => 'Blue', 'value' => 'blue', 'price' => array( 'type' => 'fixed', 'amount' => 10 ) ),
                        ),
                    ),
                ),
            )
        );
    }

    private function render_on_product( int $product_id ): string {
        global $product;
        $product = wc_get_product( $product_id );

        ob_start();
        do_action( 'woocommerce_before_add_to_cart_button' );
        return (string) ob_get_clean();
    }

    public function test_product_page_prefills_stored_selection_in_edit_mode(): void {
        $this->register_colour_set();
        $product = $this->create_product( 100.0 );

        $key = $this->add_to_cart( $product->get_id(), array( 'color' => 'blue' ) );

        $_GET[ EditContext::QUERY ] = $key;
        $html                       = $this->render_on_product( $product->get_id() );
        unset( $_GET[ EditContext::QUERY ] );

        // The stored option is pre-checked, and the form carries the edit key
        // plus a nonce so the add-to-cart POST can replace the line.
        $this->assertStringContainsString( 'value="blue" checked', $html );
        $this->assertStringNotContainsString( 'value="red" checked', $html );
        $this->assertStringContainsString( 'name="flexa_edit"', $html );
        $this->assertStringContainsString( 'name="flexa_edit_nonce"', $html );
    }

    public function test_editing_replaces_the_line_instead_of_stacking(): void {
        $this->register_colour_set();
        $product = $this->create_product( 100.0 );

        $key1 = $this->add_to_cart( $product->get_id(), array( 'color' => 'red' ) );
        $this->assertCount( 1, WC()->cart->get_cart() );

        // Simulate the edit POST (key + valid nonce carried by the form).
        $_POST[ EditContext::QUERY ]       = $key1;
        $_POST[ EditContext::NONCE_FIELD ] = wp_create_nonce( EditContext::NONCE_ACTION );

        $this->add_to_cart( $product->get_id(), array( 'color' => 'blue' ) );

        $cart = WC()->cart->get_cart();
        $this->assertCount( 1, $cart );
        $item = array_values( $cart )[0];
        $this->assertSame( 'blue', $item['flexa_extra']['selections']['color'] );
        $this->assertSame( 110.0, (float) $item['data']->get_price() );
    }

    public function test_editing_to_the_same_selection_keeps_one_line_and_quantity(): void {
        $this->register_colour_set();
        $product = $this->create_product( 100.0 );

        $key1 = $this->add_to_cart( $product->get_id(), array( 'color' => 'red' ), 1 );

        $_POST[ EditContext::QUERY ]       = $key1;
        $_POST[ EditContext::NONCE_FIELD ] = wp_create_nonce( EditContext::NONCE_ACTION );

        // Same selection: WC merges into the same line; the replace resets the
        // quantity to the edited one rather than bumping it to 2.
        $this->add_to_cart( $product->get_id(), array( 'color' => 'red' ), 1 );

        $cart = WC()->cart->get_cart();
        $this->assertCount( 1, $cart );
        $this->assertSame( 1, (int) array_values( $cart )[0]['quantity'] );
    }

    public function test_replace_is_ignored_without_a_valid_nonce(): void {
        $this->register_colour_set();
        $product = $this->create_product( 100.0 );

        $key1 = $this->add_to_cart( $product->get_id(), array( 'color' => 'red' ) );

        // Edit key present but no (valid) nonce → the old line must survive.
        $_POST[ EditContext::QUERY ]       = $key1;
        $_POST[ EditContext::NONCE_FIELD ] = 'not-a-real-nonce';

        $this->add_to_cart( $product->get_id(), array( 'color' => 'blue' ) );

        $this->assertCount( 2, WC()->cart->get_cart() );
    }
}
