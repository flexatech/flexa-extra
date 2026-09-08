<?php
namespace Flexa\Extra\Tests\Unit;

use Flexa\Extra\Fields\OptionSetSchema;
use Flexa\Extra\Migration\YayExtraSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The YayExtra converter is a pure array-to-array map from YayExtra's saved
 * shape into Flexa Extra's raw input. These tests pin the field-type, pricing,
 * swatch and targeting mapping, and prove the output survives the sanitizer.
 */
#[CoversClass( YayExtraSource::class )]
final class YayExtraSourceTest extends TestCase {

    private function convert( array $set ): array {
        return ( new YayExtraSource() )->convert( $set );
    }

    public function test_maps_basic_text_field(): void {
        $out = $this->convert(
            array(
                'name'    => 'My set',
                'status'  => 1,
                'options' => array(
                    array(
                        'fieldType'   => 'text',
                        'name'        => 'Your name',
                        'fieldName'   => 'your_name',
                        'isRequired'  => true,
                        'placeHolder' => 'Type here',
                    ),
                ),
            )
        );

        $this->assertSame( 'My set', $out['input']['name'] );
        $this->assertTrue( $out['input']['status'] );
        $field = $out['input']['fields'][0];
        $this->assertSame( 'text', $field['type'] );
        $this->assertSame( 'Your name', $field['label'] );
        $this->assertSame( 'your_name', $field['name'] );
        $this->assertTrue( $field['required'] );
        $this->assertSame( 'Type here', $field['placeholder'] );
        $this->assertSame( 'text', $field['textFormat'] );
    }

    public function test_text_email_format_carries_over(): void {
        $out   = $this->convert(
            array(
                'options' => array(
                    array( 'fieldType' => 'text', 'name' => 'Email', 'textFormat' => array( 'value' => 'email' ) ),
                ),
            )
        );
        $this->assertSame( 'email', $out['input']['fields'][0]['textFormat'] );
    }

    public function test_dropdown_options_with_fixed_and_percent_prices(): void {
        $out = $this->convert(
            array(
                'options' => array(
                    array(
                        'fieldType'    => 'dropdown',
                        'name'         => 'Size',
                        'optionValues' => array(
                            array( 'label' => 'Small', 'value' => 's', 'isDefault' => true ),
                            array(
                                'label'          => 'Large',
                                'value'          => 'l',
                                'additionalCost' => array(
                                    'isEnabled' => true,
                                    'value'     => 10,
                                    'costType'  => array( 'value' => 'fixed' ),
                                ),
                            ),
                            array(
                                'label'          => 'XL',
                                'value'          => 'xl',
                                'additionalCost' => array(
                                    'isEnabled' => true,
                                    'value'     => 15,
                                    'costType'  => array( 'value' => 'percentage' ),
                                ),
                            ),
                        ),
                    ),
                ),
            )
        );

        $field = $out['input']['fields'][0];
        $this->assertSame( 'dropdown', $field['type'] );
        $this->assertFalse( $field['multiple'] );
        $this->assertCount( 3, $field['options'] );
        $this->assertTrue( $field['options'][0]['default'] );
        $this->assertSame( array( 'type' => 'none', 'amount' => 0.0 ), $field['options'][0]['price'] );
        $this->assertSame( array( 'type' => 'fixed', 'amount' => 10.0 ), $field['options'][1]['price'] );
        $this->assertSame( array( 'type' => 'percent', 'amount' => 15.0 ), $field['options'][2]['price'] );
    }

    public function test_checkbox_is_multiple(): void {
        $out = $this->convert(
            array( 'options' => array( array( 'fieldType' => 'checkbox', 'name' => 'Extras', 'optionValues' => array() ) ) )
        );
        $this->assertSame( 'checkbox', $out['input']['fields'][0]['type'] );
        $this->assertTrue( $out['input']['fields'][0]['multiple'] );
    }

    public function test_button_with_colour_becomes_swatch(): void {
        $out = $this->convert(
            array(
                'options' => array(
                    array(
                        'fieldType'    => 'button',
                        'name'         => 'Colour',
                        'optionValues' => array(
                            array( 'label' => 'Red', 'value' => 'red', 'swatchColor' => '#ff0000' ),
                        ),
                    ),
                ),
            )
        );
        $field = $out['input']['fields'][0];
        $this->assertSame( 'swatch', $field['type'] );
        $this->assertSame( '#ff0000', $field['options'][0]['color'] );
    }

    public function test_button_without_media_stays_button(): void {
        $out = $this->convert(
            array(
                'options' => array(
                    array(
                        'fieldType'    => 'button',
                        'name'         => 'Pick',
                        'optionValues' => array( array( 'label' => 'A', 'value' => 'a' ) ),
                    ),
                ),
            )
        );
        $this->assertSame( 'button', $out['input']['fields'][0]['type'] );
    }

    public function test_label_becomes_heading(): void {
        $out = $this->convert( array( 'options' => array( array( 'fieldType' => 'label', 'name' => 'Note' ) ) ) );
        $this->assertSame( 'heading', $out['input']['fields'][0]['type'] );
    }

    public function test_number_bounds(): void {
        $out = $this->convert(
            array(
                'options' => array(
                    array( 'fieldType' => 'number', 'name' => 'Qty', 'minNumber' => 1, 'maxNumber' => 9, 'numberStep' => 2 ),
                ),
            )
        );
        $field = $out['input']['fields'][0];
        $this->assertSame( 1.0, $field['min'] );
        $this->assertSame( 9.0, $field['max'] );
        $this->assertSame( 2.0, $field['step'] );
    }

    public function test_file_upload_is_skipped_with_warning(): void {
        $out = $this->convert(
            array( 'options' => array( array( 'fieldType' => 'file_upload', 'name' => 'Artwork' ) ) )
        );
        $this->assertSame( array(), $out['input']['fields'] );
        $this->assertNotEmpty( $out['warnings'] );
    }

    public function test_targeting_manual_products(): void {
        $out = $this->convert(
            array(
                'options'  => array( array( 'fieldType' => 'text', 'name' => 'A' ) ),
                'products' => array(
                    'product_filter_type'       => 1,
                    'product_filter_one_by_one' => array( 12, '34', 0 ),
                ),
            )
        );
        $targeting = $out['input']['targeting'];
        $this->assertSame( 'manual', $targeting['mode'] );
        $this->assertSame( array( 12, 34 ), $targeting['productIds'] );
    }

    public function test_targeting_all_products(): void {
        $out = $this->convert(
            array(
                'options'  => array( array( 'fieldType' => 'text', 'name' => 'A' ) ),
                'products' => array( 'product_filter_type' => 3 ),
            )
        );
        $this->assertSame( 'all', $out['input']['targeting']['mode'] );
    }

    public function test_conditional_targeting_falls_back_to_all_with_warning(): void {
        $out = $this->convert(
            array(
                'options'  => array( array( 'fieldType' => 'text', 'name' => 'A' ) ),
                'products' => array( 'product_filter_type' => 2 ),
            )
        );
        $this->assertSame( 'all', $out['input']['targeting']['mode'] );
        $this->assertNotEmpty( $out['warnings'] );
    }

    public function test_actions_produce_a_warning(): void {
        $out = $this->convert(
            array(
                'options' => array( array( 'fieldType' => 'text', 'name' => 'A' ) ),
                'actions' => array( array( 'id' => 'x' ) ),
            )
        );
        $this->assertNotEmpty( $out['warnings'] );
    }

    public function test_output_survives_the_sanitizer(): void {
        $out    = $this->convert(
            array(
                'name'    => 'Set',
                'status'  => 1,
                'options' => array(
                    array(
                        'fieldType'    => 'dropdown',
                        'name'         => 'Size',
                        'optionValues' => array(
                            array(
                                'label'          => 'L',
                                'value'          => 'l',
                                'additionalCost' => array( 'isEnabled' => true, 'value' => 15, 'costType' => array( 'value' => 'percentage' ) ),
                            ),
                        ),
                    ),
                ),
            )
        );
        $data = OptionSetSchema::sanitize( $out['input'] );

        $this->assertSame( 'Set', $data['name'] );
        $this->assertCount( 1, $data['fields'] );
        $this->assertSame( 'percent', $data['fields'][0]['options'][0]['price']['type'] );
        $this->assertSame( 15.0, $data['fields'][0]['options'][0]['price']['amount'] );
    }
}
