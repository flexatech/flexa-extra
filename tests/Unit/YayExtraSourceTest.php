<?php
namespace Flexa\Extra\Tests\Unit;

use Flexa\Extra\Fields\OptionSetSchema;
use Flexa\Extra\Migration\YayExtraSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The YayExtra converter is a pure array-to-array map from YayExtra's saved
 * shape into Flexa Extra's raw input. The fixtures below mirror that shape as
 * YayExtra actually writes it: select-style settings (the field type included)
 * are `{ value, label }` pairs, not plain strings. These tests pin the field-type, pricing,
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
                        'type'   => array( 'value' => 'text' ),
                        'name'        => 'Your name',
                        'nameOnCart'  => 'your_name',
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
                    array( 'type' => array( 'value' => 'text' ), 'name' => 'Email', 'textFormat' => array( 'value' => 'email' ) ),
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
                        'type'    => array( 'value' => 'dropdown' ),
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
            array( 'options' => array( array( 'type' => array( 'value' => 'checkbox' ), 'name' => 'Extras', 'optionValues' => array() ) ) )
        );
        $this->assertSame( 'checkbox', $out['input']['fields'][0]['type'] );
        $this->assertTrue( $out['input']['fields'][0]['multiple'] );
    }

    /**
     * YayExtra seeds every option with a default swatch colour, so a button
     * field carrying one is still a button — only the swatches field types
     * render a colour or image.
     */
    public function test_button_with_a_leftover_colour_stays_a_button(): void {
        $out = $this->convert(
            array(
                'options' => array(
                    array(
                        'type'         => array( 'value' => 'button' ),
                        'name'         => 'Pick',
                        'optionValues' => array(
                            array( 'value' => 'Ash glaze', 'swatchesType' => 'color', 'swatchColor' => '#2271b1' ),
                        ),
                    ),
                ),
            )
        );
        $field = $out['input']['fields'][0];
        $this->assertSame( 'button', $field['type'] );
        $this->assertSame( '', $field['options'][0]['color'] );
        $this->assertSame( '', $field['options'][0]['image'] );
    }

    public function test_swatches_carry_the_colour_or_the_image_per_option(): void {
        $out = $this->convert(
            array(
                'options' => array(
                    array(
                        'type'         => array( 'value' => 'swatches' ),
                        'name'         => 'Colour',
                        'optionValues' => array(
                            array( 'value' => 'Red', 'swatchesType' => 'color', 'swatchColor' => '#ff0000', 'imageUrl' => 'https://example.com/red.png' ),
                            array( 'value' => 'Oak', 'swatchesType' => 'image', 'swatchColor' => '#2271b1', 'imageUrl' => 'https://example.com/oak.png' ),
                        ),
                    ),
                ),
            )
        );
        $options = $out['input']['fields'][0]['options'];
        $this->assertSame( 'swatch', $out['input']['fields'][0]['type'] );
        $this->assertSame( '#ff0000', $options[0]['color'] );
        $this->assertSame( '', $options[0]['image'] );
        $this->assertSame( '', $options[1]['color'] );
        $this->assertSame( 'https://example.com/oak.png', $options[1]['image'] );
    }

    public function test_swatches_type_maps_to_swatch(): void {
        $out = $this->convert(
            array(
                'options' => array(
                    array(
                        'type'         => array( 'value' => 'swatches_multi' ),
                        'name'         => 'Finish',
                        'optionValues' => array( array( 'value' => 'Matte' ) ),
                    ),
                ),
            )
        );
        $field = $out['input']['fields'][0];
        $this->assertSame( 'swatch', $field['type'] );
        $this->assertTrue( $field['multiple'] );
    }

    public function test_option_without_a_label_falls_back_to_its_value(): void {
        $out = $this->convert(
            array(
                'options' => array(
                    array(
                        'type'         => array( 'value' => 'dropdown' ),
                        'name'         => 'Pot',
                        'optionValues' => array( array( 'value' => 'Ash glaze' ) ),
                    ),
                ),
            )
        );
        $this->assertSame( 'Ash glaze', $out['input']['fields'][0]['options'][0]['label'] );
    }

    public function test_name_on_cart_becomes_the_field_name(): void {
        $out = $this->convert(
            array(
                'options' => array(
                    array( 'type' => array( 'value' => 'text' ), 'name' => 'Choose a pot', 'nameOnCart' => 'pot' ),
                ),
            )
        );
        $this->assertSame( 'pot', $out['input']['fields'][0]['name'] );
    }

    public function test_legacy_flat_field_type_is_still_read(): void {
        $out = $this->convert(
            array( 'options' => array( array( 'fieldType' => 'textarea', 'name' => 'Notes' ) ) )
        );
        $this->assertSame( 'textarea', $out['input']['fields'][0]['type'] );
    }

    public function test_label_becomes_heading(): void {
        $out = $this->convert( array( 'options' => array( array( 'type' => array( 'value' => 'label' ), 'name' => 'Note' ) ) ) );
        $this->assertSame( 'heading', $out['input']['fields'][0]['type'] );
    }

    public function test_number_bounds(): void {
        $out = $this->convert(
            array(
                'options' => array(
                    array( 'type' => array( 'value' => 'number' ), 'name' => 'Qty', 'minNumber' => 1, 'maxNumber' => 9, 'numberStep' => 2 ),
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
            array( 'options' => array( array( 'type' => array( 'value' => 'file_upload' ), 'name' => 'Artwork' ) ) )
        );
        $this->assertSame( array(), $out['input']['fields'] );
        $this->assertNotEmpty( $out['warnings'] );
    }

    public function test_targeting_manual_products(): void {
        $out = $this->convert(
            array(
                'options'  => array( array( 'type' => array( 'value' => 'text' ), 'name' => 'A' ) ),
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
                'options'  => array( array( 'type' => array( 'value' => 'text' ), 'name' => 'A' ) ),
                'products' => array( 'product_filter_type' => 3 ),
            )
        );
        $this->assertSame( 'all', $out['input']['targeting']['mode'] );
    }

    /**
     * @param list<array<string,mixed>> $conditions
     * @return array{input:array<string,mixed>,warnings:list<string>}
     */
    private function with_conditions( array $conditions, string $match = 'any' ): array {
        return $this->convert(
            array(
                'options'  => array( array( 'type' => array( 'value' => 'text' ), 'name' => 'A' ) ),
                'products' => array(
                    'product_filter_type'          => 2,
                    'product_filter_by_conditions' => array(
                        'match_type' => array( 'value' => $match ),
                        'conditions' => $conditions,
                    ),
                ),
            )
        );
    }

    public function test_category_and_tag_conditions_become_targeting_rules(): void {
        $out = $this->with_conditions(
            array(
                array(
                    'type'        => array( 'value' => 'prod_category' ),
                    'comparation' => array( 'value' => 'is_one_of' ),
                    'value'       => array( array( 'label' => 'Plants', 'value' => 227, 'termId' => 227 ) ),
                ),
                array(
                    'type'        => array( 'value' => 'prod_tag' ),
                    'comparation' => array( 'value' => 'is_one_of' ),
                    'value'       => array( array( 'label' => 'Tabletop', 'value' => 'Tabletop', 'termId' => 88 ) ),
                ),
            )
        );

        $targeting = $out['input']['targeting'];
        $this->assertSame( 'conditions', $targeting['mode'] );
        $this->assertSame( 'any', $targeting['match'] );
        $this->assertSame(
            array(
                array( 'type' => 'category', 'operator' => 'is', 'value' => '227' ),
                array( 'type' => 'tag', 'operator' => 'is', 'value' => '88' ),
            ),
            $targeting['conditions']
        );
    }

    public function test_is_none_of_becomes_is_not(): void {
        $out = $this->with_conditions(
            array(
                array(
                    'type'        => array( 'value' => 'prod_tag' ),
                    'comparation' => array( 'value' => 'is_none_of' ),
                    'value'       => array( array( 'label' => 'Floor', 'termId' => 90 ) ),
                ),
            )
        );
        $this->assertSame( 'is_not', $out['input']['targeting']['conditions'][0]['operator'] );
    }

    public function test_multi_term_condition_expands_into_one_rule_per_term(): void {
        $out = $this->with_conditions(
            array(
                array(
                    'type'        => array( 'value' => 'prod_category' ),
                    'comparation' => array( 'value' => 'is_one_of' ),
                    'value'       => array(
                        array( 'label' => 'Plants', 'termId' => 227 ),
                        array( 'label' => 'Pots', 'termId' => 228 ),
                    ),
                ),
            )
        );
        $this->assertCount( 2, $out['input']['targeting']['conditions'] );
        $this->assertSame( array(), $out['warnings'] );
    }

    public function test_multi_term_condition_warns_when_the_match_mode_changes_its_meaning(): void {
        $out = $this->with_conditions(
            array(
                array(
                    'type'        => array( 'value' => 'prod_category' ),
                    'comparation' => array( 'value' => 'is_one_of' ),
                    'value'       => array(
                        array( 'label' => 'Plants', 'termId' => 227 ),
                        array( 'label' => 'Pots', 'termId' => 228 ),
                    ),
                ),
            ),
            'all'
        );
        $this->assertSame( 'all', $out['input']['targeting']['match'] );
        $this->assertNotEmpty( $out['warnings'] );
    }

    public function test_unresolved_term_is_skipped_with_a_warning(): void {
        $out = $this->with_conditions(
            array(
                array(
                    'type'        => array( 'value' => 'prod_tag' ),
                    'comparation' => array( 'value' => 'is_one_of' ),
                    'value'       => array( array( 'label' => 'Gone', 'termId' => 0 ) ),
                ),
            )
        );
        $this->assertSame( 'all', $out['input']['targeting']['mode'] );
        $this->assertNotEmpty( $out['warnings'] );
    }

    public function test_unsupported_condition_type_falls_back_to_all_with_warning(): void {
        $out = $this->with_conditions(
            array(
                array(
                    'type'        => array( 'value' => 'prod_name' ),
                    'comparation' => array( 'value' => 'is_one_of' ),
                    'value'       => array( array( 'label' => 'Monstera' ) ),
                ),
            )
        );
        $this->assertSame( 'all', $out['input']['targeting']['mode'] );
        $this->assertNotEmpty( $out['warnings'] );
    }

    public function test_targeting_conditions_survive_the_sanitizer(): void {
        $out  = $this->with_conditions(
            array(
                array(
                    'type'        => array( 'value' => 'prod_category' ),
                    'comparation' => array( 'value' => 'is_one_of' ),
                    'value'       => array( array( 'label' => 'Plants', 'termId' => 227 ) ),
                ),
            )
        );
        $data = OptionSetSchema::sanitize( $out['input'] );

        $this->assertSame( 'conditions', $data['targeting']['mode'] );
        $this->assertSame(
            array( array( 'type' => 'category', 'operator' => 'is', 'value' => '227' ) ),
            $data['targeting']['conditions']
        );
    }

    public function test_actions_produce_a_warning(): void {
        $out = $this->convert(
            array(
                'options' => array( array( 'type' => array( 'value' => 'text' ), 'name' => 'A' ) ),
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
                        'type'    => array( 'value' => 'dropdown' ),
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
