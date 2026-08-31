<?php
namespace Flexa\Extra\Tests\Integration;

/**
 * The renderer outputs applicable fields at the configured add-to-cart hook.
 */
final class ProductRendererTest extends IntegrationTestCase {

    private function render_on_product( int $product_id ): string {
        global $product;
        $product = wc_get_product( $product_id );

        ob_start();
        do_action( 'woocommerce_before_add_to_cart_button' );
        return (string) ob_get_clean();
    }

    public function test_fields_render_on_product_page(): void {
        $this->register_option_set(
            array(
                'name'      => 'Colour',
                'status'    => true,
                'targeting' => array( 'mode' => 'all' ),
                'fields'    => array(
                    array(
                        'type'    => 'radio',
                        'id'      => 'color',
                        'label'   => 'Choose colour',
                        'options' => array(
                            array( 'id' => 'red', 'label' => 'Red', 'value' => 'red', 'price' => array( 'type' => 'fixed', 'amount' => 5 ) ),
                        ),
                    ),
                ),
            )
        );
        $product = $this->create_product( 100.0 );

        $html = $this->render_on_product( $product->get_id() );

        $this->assertStringContainsString( 'flexa-extra-fields', $html );
        $this->assertStringContainsString( 'Choose colour', $html );
        $this->assertStringContainsString( 'name="flexa_extra[color]"', $html );
        $this->assertStringContainsString( 'flexa-extra-data', $html ); // JSON island present
    }

    public function test_choice_group_renders_accessible_fieldset(): void {
        $this->register_option_set(
            array(
                'name'      => 'Colour',
                'status'    => true,
                'targeting' => array( 'mode' => 'all' ),
                'fields'    => array(
                    array(
                        'type'     => 'radio',
                        'id'       => 'color',
                        'label'    => 'Choose colour',
                        'required' => true,
                        'options'  => array(
                            array( 'id' => 'red', 'label' => 'Red', 'value' => 'red' ),
                        ),
                    ),
                ),
            )
        );
        $product = $this->create_product( 100.0 );

        $html = $this->render_on_product( $product->get_id() );

        // Groups use fieldset/legend (not a <label for>) and expose aria-required.
        $this->assertStringContainsString( '<fieldset class="flexa-extra-choices', $html );
        $this->assertStringContainsString( '<legend class="flexa-extra-field__label">Choose colour', $html );
        $this->assertStringContainsString( 'aria-required="true"', $html );
    }

    public function test_style_settings_drive_container_classes_and_css_vars(): void {
        update_option(
            'flexa_extra_settings',
            array(
                'style' => array(
                    'swatchSize'  => 'lg',
                    'swatchShape' => 'square',
                    'buttonBg'    => '#ff0000',
                ),
            )
        );

        $this->register_option_set(
            array(
                'name'      => 'Swatches',
                'status'    => true,
                'targeting' => array( 'mode' => 'all' ),
                'fields'    => array(
                    array(
                        'type'    => 'swatch',
                        'id'      => 'finish',
                        'label'   => 'Finish',
                        'options' => array(
                            array( 'id' => 'matte', 'label' => 'Matte', 'value' => 'matte', 'color' => '#222222' ),
                        ),
                    ),
                ),
            )
        );
        $product = $this->create_product( 100.0 );

        $html = $this->render_on_product( $product->get_id() );

        $this->assertStringContainsString( 'flexa-extra-fields--swatch-lg', $html );
        $this->assertStringContainsString( 'flexa-extra-fields--shape-square', $html );
        $this->assertStringContainsString( '--fxe-swatch-size:48px', $html );
        $this->assertStringContainsString( '--fxe-btn-bg:#ff0000', $html );
    }

    public function test_nothing_renders_without_applicable_set(): void {
        $product = $this->create_product( 100.0 );

        $html = $this->render_on_product( $product->get_id() );

        $this->assertStringNotContainsString( 'flexa-extra-fields', $html );
    }

    public function test_manual_targeting_limits_rendering(): void {
        $target = $this->create_product( 100.0 );
        $other  = $this->create_product( 100.0 );

        $this->register_option_set(
            array(
                'name'      => 'Manual',
                'status'    => true,
                'targeting' => array( 'mode' => 'manual', 'productIds' => array( $target->get_id() ) ),
                'fields'    => array(
                    array( 'type' => 'text', 'id' => 'note', 'label' => 'Note' ),
                ),
            )
        );

        $this->assertStringContainsString( 'flexa-extra-fields', $this->render_on_product( $target->get_id() ) );
        $this->assertStringNotContainsString( 'flexa-extra-fields', $this->render_on_product( $other->get_id() ) );
    }
}
