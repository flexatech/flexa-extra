<?php
namespace Flexa\Extra\Tests\Integration;

/**
 * Min / max number of options on a multi-select choice field: the bound blocks
 * add-to-cart against real WooCommerce and the product page shows the hint.
 */
final class SelectFlowTest extends IntegrationTestCase {

    /**
     * A three-option checkbox with the given selection bounds.
     *
     * @param array<string,mixed> $bounds
     */
    private function register_addons( array $bounds ): void {
        $this->register_option_set(
            array_merge(
                array(
                    'name'      => 'Add-ons',
                    'status'    => true,
                    'targeting' => array( 'mode' => 'all' ),
                    'fields'    => array(
                        array_merge(
                            array(
                                'type'    => 'checkbox',
                                'id'      => 'addons',
                                'label'   => 'Add-ons',
                                'options' => array(
                                    array( 'id' => 'a', 'label' => 'A', 'value' => 'a' ),
                                    array( 'id' => 'b', 'label' => 'B', 'value' => 'b' ),
                                    array( 'id' => 'c', 'label' => 'C', 'value' => 'c' ),
                                ),
                            ),
                            $bounds
                        ),
                    ),
                )
            )
        );
    }

    public function test_max_select_blocks_add_to_cart(): void {
        $this->register_addons( array( 'maxSelect' => 2 ) );
        $product = $this->create_product( 100.0 );

        $blocked = $this->add_to_cart( $product->get_id(), array( 'addons' => array( 'a', 'b', 'c' ) ) );
        $this->assertFalse( $blocked );

        $ok = $this->add_to_cart( $product->get_id(), array( 'addons' => array( 'a', 'b' ) ) );
        $this->assertNotFalse( $ok );
    }

    public function test_min_select_blocks_add_to_cart(): void {
        $this->register_addons( array( 'minSelect' => 2 ) );
        $product = $this->create_product( 100.0 );

        $blocked = $this->add_to_cart( $product->get_id(), array( 'addons' => array( 'a' ) ) );
        $this->assertFalse( $blocked );

        $ok = $this->add_to_cart( $product->get_id(), array( 'addons' => array( 'a', 'b' ) ) );
        $this->assertNotFalse( $ok );
    }

    public function test_hint_renders_on_product_page(): void {
        $this->register_addons( array( 'minSelect' => 1, 'maxSelect' => 2 ) );
        $created = $this->create_product( 100.0 );

        global $product;
        $product = wc_get_product( $created->get_id() );

        ob_start();
        do_action( 'woocommerce_before_add_to_cart_button' );
        $html = (string) ob_get_clean();

        $this->assertStringContainsString( 'flexa-extra-select-hint', $html );
        $this->assertStringContainsString( 'Choose between 1 and 2 options', $html );
    }
}
