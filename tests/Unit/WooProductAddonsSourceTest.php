<?php
namespace Flexa\Extra\Tests\Unit;

use Flexa\Extra\Fields\OptionSetSchema;
use Flexa\Extra\Migration\WooProductAddonsSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The WooCommerce Product Add-Ons converter maps that plugin's `_product_addons`
 * shape into Flexa Extra input: multiple_choice widgets by their display,
 * per-option prices by price_type, and global category scope into targeting.
 */
#[CoversClass( WooProductAddonsSource::class )]
final class WooProductAddonsSourceTest extends TestCase {

    /**
     * @param array<string,mixed> $set
     * @return array{input:array<string,mixed>,warnings:list<string>}
     */
    private function convert( array $set ): array {
        return ( new WooProductAddonsSource() )->convert( $set );
    }

    public function test_multiple_choice_display_maps_to_widget(): void {
        $out = $this->convert(
            array(
                'name'   => 'Options',
                'addons' => array(
                    array( 'name' => 'Size', 'type' => 'multiple_choice', 'display' => 'select', 'position' => 0, 'options' => array( array( 'label' => 'S' ) ) ),
                    array( 'name' => 'Colour', 'type' => 'multiple_choice', 'display' => 'radiobutton', 'position' => 1, 'options' => array( array( 'label' => 'Red' ) ) ),
                    array( 'name' => 'Finish', 'type' => 'multiple_choice', 'display' => 'images', 'position' => 2, 'options' => array( array( 'label' => 'Matte' ) ) ),
                ),
            )
        );

        $fields = $out['input']['fields'];
        $this->assertSame( 'dropdown', $fields[0]['type'] );
        $this->assertSame( 'radio', $fields[1]['type'] );
        $this->assertSame( 'swatch', $fields[2]['type'] );
    }

    public function test_position_orders_fields(): void {
        $out = $this->convert(
            array(
                'addons' => array(
                    array( 'name' => 'Second', 'type' => 'custom_text', 'position' => 5 ),
                    array( 'name' => 'First', 'type' => 'custom_text', 'position' => 1 ),
                ),
            )
        );
        $this->assertSame( 'First', $out['input']['fields'][0]['label'] );
        $this->assertSame( 'Second', $out['input']['fields'][1]['label'] );
    }

    public function test_option_price_types_map(): void {
        $out = $this->convert(
            array(
                'addons' => array(
                    array(
                        'name'    => 'Extras',
                        'type'    => 'checkbox',
                        'options' => array(
                            array( 'label' => 'Flat', 'price' => '5', 'price_type' => 'flat_fee' ),
                            array( 'label' => 'Qty', 'price' => '3', 'price_type' => 'quantity_based' ),
                            array( 'label' => 'Pct', 'price' => '10', 'price_type' => 'percentage_based' ),
                            array( 'label' => 'Free' ),
                        ),
                    ),
                ),
            )
        );

        $options = $out['input']['fields'][0]['options'];
        $this->assertSame( array( 'type' => 'fixed', 'amount' => 5.0 ), $options[0]['price'] );
        $this->assertSame( array( 'type' => 'fixed', 'amount' => 3.0 ), $options[1]['price'] );
        $this->assertSame( array( 'type' => 'percent', 'amount' => 10.0 ), $options[2]['price'] );
        $this->assertSame( array( 'type' => 'none', 'amount' => 0.0 ), $options[3]['price'] );
        // The flat_fee → per-unit note is surfaced.
        $this->assertNotEmpty( $out['warnings'] );
    }

    public function test_checkbox_is_multiple(): void {
        $out = $this->convert(
            array(
                'addons' => array(
                    array( 'name' => 'Add-ons', 'type' => 'checkbox', 'options' => array( array( 'label' => 'A' ), array( 'label' => 'B' ) ) ),
                ),
            )
        );
        $this->assertTrue( $out['input']['fields'][0]['multiple'] );
        $this->assertCount( 2, $out['input']['fields'][0]['options'] );
    }

    public function test_custom_text_email_restriction_sets_format(): void {
        $out = $this->convert(
            array(
                'addons' => array(
                    array( 'name' => 'Email', 'type' => 'custom_text', 'restrictions_type' => 'email' ),
                ),
            )
        );
        $field = $out['input']['fields'][0];
        $this->assertSame( 'text', $field['type'] );
        $this->assertSame( 'email', $field['textFormat'] );
    }

    public function test_field_level_price_on_input(): void {
        $out = $this->convert(
            array(
                'addons' => array(
                    array( 'name' => 'Message', 'type' => 'custom_textarea', 'adjust_price' => true, 'price_type' => 'flat_fee', 'price' => '2.5' ),
                ),
            )
        );
        $this->assertSame( array( 'type' => 'fixed', 'amount' => 2.5 ), $out['input']['fields'][0]['price'] );
    }

    public function test_file_upload_is_skipped_with_warning(): void {
        $out = $this->convert(
            array(
                'addons' => array(
                    array( 'name' => 'Artwork', 'type' => 'file_upload' ),
                ),
            )
        );
        $this->assertSame( array(), $out['input']['fields'] );
        $this->assertNotEmpty( $out['warnings'] );
    }

    public function test_custom_price_becomes_number_with_warning(): void {
        $out = $this->convert(
            array(
                'addons' => array(
                    array( 'name' => 'Donation', 'type' => 'custom_price' ),
                ),
            )
        );
        $this->assertSame( 'number', $out['input']['fields'][0]['type'] );
        $this->assertNotEmpty( $out['warnings'] );
    }

    public function test_manual_scope_targets_product(): void {
        $out = $this->convert(
            array(
                'addons' => array( array( 'name' => 'X', 'type' => 'custom_text' ) ),
                'scope'  => array( 'mode' => 'manual', 'productIds' => array( 42 ), 'categoryIds' => array() ),
            )
        );
        $targeting = $out['input']['targeting'];
        $this->assertSame( 'manual', $targeting['mode'] );
        $this->assertSame( array( 42 ), $targeting['productIds'] );
    }

    public function test_category_scope_becomes_conditions(): void {
        $out = $this->convert(
            array(
                'addons' => array( array( 'name' => 'X', 'type' => 'custom_text' ) ),
                'scope'  => array( 'mode' => 'conditions', 'productIds' => array(), 'categoryIds' => array( 7, 8 ) ),
            )
        );
        $targeting = $out['input']['targeting'];
        $this->assertSame( 'conditions', $targeting['mode'] );
        $this->assertContains( array( 'type' => 'category', 'operator' => 'is', 'value' => '7' ), $targeting['conditions'] );
        $this->assertContains( array( 'type' => 'category', 'operator' => 'is', 'value' => '8' ), $targeting['conditions'] );
    }

    public function test_output_survives_the_sanitizer(): void {
        $out  = $this->convert(
            array(
                'name'   => 'Add-ons',
                'addons' => array(
                    array( 'name' => 'Size', 'type' => 'multiple_choice', 'display' => 'select', 'options' => array( array( 'label' => 'S', 'price' => '1', 'price_type' => 'flat_fee' ) ) ),
                    array( 'name' => 'Engraving', 'type' => 'custom_text' ),
                ),
                'scope'  => array( 'mode' => 'all', 'productIds' => array(), 'categoryIds' => array() ),
            )
        );
        $data = OptionSetSchema::sanitize( $out['input'] );

        $this->assertSame( 'Add-ons', $data['name'] );
        $this->assertCount( 2, $data['fields'] );
        $this->assertSame( 'dropdown', $data['fields'][0]['type'] );
        $this->assertSame( 'text', $data['fields'][1]['type'] );
        $this->assertSame( 'all', $data['targeting']['mode'] );
    }
}
