<?php
namespace Flexa\Extra\Tests\Unit;

use Flexa\Extra\Fields\OptionSetSchema;
use Flexa\Extra\Migration\AcowebsWcpaSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The Acowebs WCPA converter maps that plugin's `_wcpa_fb_json_data` form (a
 * section-keyed object of field rows, choices under `values`) into Flexa Extra
 * input: field types by their `type`/`subtype`, per-option prices, colour/image
 * options into swatches, and product/category scope into targeting.
 */
#[CoversClass( AcowebsWcpaSource::class )]
final class AcowebsWcpaSourceTest extends TestCase {

    /**
     * @param array<string,mixed> $set
     * @return array{input:array<string,mixed>,warnings:list<string>}
     */
    private function convert( array $set ): array {
        return ( new AcowebsWcpaSource() )->convert( $set );
    }

    /**
     * Wrap fields in the section → rows structure read_raw() passes to convert().
     *
     * @param list<array<string,mixed>> $fields
     * @return array<string,mixed>
     */
    private function form( array $fields, string $section_name = 'Default' ): array {
        $rows = array_map( static fn( $f ) => array( $f ), $fields );
        return array(
            'name'     => 'Form 1',
            'sections' => array(
                'sec_1' => array(
                    'extra'  => array( 'name' => $section_name ),
                    'fields' => $rows,
                ),
            ),
        );
    }

    public function test_basic_types_map(): void {
        $out = $this->convert(
            $this->form(
                array(
                    array( 'type' => 'text', 'label' => 'Name' ),
                    array( 'type' => 'textarea', 'label' => 'Note' ),
                    array( 'type' => 'number', 'label' => 'Qty' ),
                    array( 'type' => 'url', 'label' => 'Link' ),
                    array( 'type' => 'date', 'label' => 'When' ),
                    array( 'type' => 'color', 'label' => 'Shade' ),
                )
            )
        );

        $fields = $out['input']['fields'];
        $this->assertSame( 'text', $fields[0]['type'] );
        $this->assertSame( 'textarea', $fields[1]['type'] );
        $this->assertSame( 'number', $fields[2]['type'] );
        $this->assertSame( 'text', $fields[3]['type'] );
        $this->assertSame( 'url', $fields[3]['textFormat'] );
        $this->assertSame( 'date_picker', $fields[4]['type'] );
        $this->assertSame( 'color_picker', $fields[5]['type'] );
    }

    public function test_text_subtype_email_sets_format(): void {
        $out    = $this->convert( $this->form( array( array( 'type' => 'text', 'subtype' => 'email', 'label' => 'Email' ) ) ) );
        $field  = $out['input']['fields'][0];
        $this->assertSame( 'text', $field['type'] );
        $this->assertSame( 'email', $field['textFormat'] );
    }

    public function test_select_with_option_prices(): void {
        $out = $this->convert(
            $this->form(
                array(
                    array(
                        'type'   => 'select',
                        'label'  => 'Size',
                        'values' => array(
                            array( 'label' => 'S', 'value' => 's' ),
                            array( 'label' => 'M', 'value' => 'm', 'price' => 5 ),
                            array( 'label' => 'L', 'value' => 'l', 'price' => 10, 'priceType' => 'percent' ),
                        ),
                    ),
                )
            )
        );
        $field = $out['input']['fields'][0];
        $this->assertSame( 'dropdown', $field['type'] );
        $this->assertCount( 3, $field['options'] );
        $this->assertSame( array( 'type' => 'none', 'amount' => 0.0 ), $field['options'][0]['price'] );
        $this->assertSame( array( 'type' => 'fixed', 'amount' => 5.0 ), $field['options'][1]['price'] );
        $this->assertSame( array( 'type' => 'percent', 'amount' => 10.0 ), $field['options'][2]['price'] );
    }

    public function test_radio_group_maps_to_radio(): void {
        $out = $this->convert(
            $this->form(
                array(
                    array( 'type' => 'radio-group', 'label' => 'Pick', 'values' => array( array( 'label' => 'A', 'value' => 'a' ) ) ),
                )
            )
        );
        $this->assertSame( 'radio', $out['input']['fields'][0]['type'] );
        $this->assertFalse( $out['input']['fields'][0]['multiple'] );
    }

    public function test_checkbox_group_is_multiple(): void {
        $out = $this->convert(
            $this->form(
                array(
                    array( 'type' => 'checkbox-group', 'label' => 'Extras', 'values' => array( array( 'label' => 'A', 'value' => 'a' ), array( 'label' => 'B', 'value' => 'b' ) ) ),
                )
            )
        );
        $field = $out['input']['fields'][0];
        $this->assertSame( 'checkbox', $field['type'] );
        $this->assertTrue( $field['multiple'] );
        $this->assertCount( 2, $field['options'] );
    }

    public function test_single_checkbox_becomes_one_option(): void {
        $out = $this->convert(
            $this->form(
                array(
                    array( 'type' => 'checkbox', 'label' => 'Gift wrap', 'check_value' => 'Yes' ),
                )
            )
        );
        $field = $out['input']['fields'][0];
        $this->assertSame( 'checkbox', $field['type'] );
        $this->assertCount( 1, $field['options'] );
        $this->assertSame( 'Gift wrap', $field['options'][0]['label'] );
        $this->assertSame( 'Yes', $field['options'][0]['value'] );
    }

    public function test_options_with_colour_become_a_swatch(): void {
        $out = $this->convert(
            $this->form(
                array(
                    array(
                        'type'   => 'radio-group',
                        'label'  => 'Colour',
                        'values' => array(
                            array( 'label' => 'Red', 'value' => 'red', 'color' => '#ff0000' ),
                            array( 'label' => 'Blue', 'value' => 'blue', 'color' => '#0000ff' ),
                        ),
                    ),
                )
            )
        );
        $field = $out['input']['fields'][0];
        $this->assertSame( 'swatch', $field['type'] );
        $this->assertSame( '#ff0000', $field['options'][0]['color'] );
    }

    public function test_field_level_price_on_input(): void {
        $out = $this->convert(
            $this->form(
                array(
                    array( 'type' => 'textarea', 'label' => 'Engraving', 'enablePrice' => true, 'price' => '3.5' ),
                )
            )
        );
        $this->assertSame( array( 'type' => 'fixed', 'amount' => 3.5 ), $out['input']['fields'][0]['price'] );
    }

    public function test_custom_price_formula_is_flagged_not_migrated(): void {
        $out = $this->convert(
            $this->form(
                array(
                    array( 'type' => 'number', 'label' => 'Area', 'enablePrice' => true, 'pricingType' => 'custom', 'price' => '{width}*{height}' ),
                )
            )
        );
        $this->assertSame( array( 'type' => 'none', 'amount' => 0.0 ), $out['input']['fields'][0]['price'] );
        $this->assertNotEmpty( $out['warnings'] );
    }

    public function test_hidden_and_separator_are_dropped_silently(): void {
        $out = $this->convert(
            $this->form(
                array(
                    array( 'type' => 'hidden', 'label' => 'h' ),
                    array( 'type' => 'separator', 'label' => '' ),
                )
            )
        );
        $this->assertSame( array(), $out['input']['fields'] );
        $this->assertSame( array( 'No product assignment was found for this form, so it was imported as "all products"; review Product assignment.' ), $out['warnings'] );
    }

    public function test_inactive_field_is_skipped(): void {
        $out = $this->convert(
            $this->form(
                array(
                    array( 'type' => 'text', 'label' => 'Off', 'active' => false ),
                )
            )
        );
        $this->assertSame( array(), $out['input']['fields'] );
    }

    public function test_file_upload_is_skipped_with_warning(): void {
        $out = $this->convert( $this->form( array( array( 'type' => 'file', 'label' => 'Artwork' ) ) ) );
        $this->assertSame( array(), $out['input']['fields'] );
        $this->assertNotEmpty( $out['warnings'] );
    }

    public function test_named_section_becomes_leading_heading(): void {
        $out = $this->convert(
            $this->form(
                array( array( 'type' => 'text', 'label' => 'Name' ) ),
                'Personalization'
            )
        );
        $this->assertSame( 'heading', $out['input']['fields'][0]['type'] );
        $this->assertSame( 'Personalization', $out['input']['fields'][0]['label'] );
        $this->assertSame( 'text', $out['input']['fields'][1]['type'] );
    }

    public function test_manual_scope_targets_products(): void {
        $set          = $this->form( array( array( 'type' => 'text', 'label' => 'X' ) ) );
        $set['scope'] = array( 'mode' => 'manual', 'productIds' => array( 5, 6 ), 'categoryIds' => array() );
        $out          = $this->convert( $set );
        $this->assertSame( 'manual', $out['input']['targeting']['mode'] );
        $this->assertSame( array( 5, 6 ), $out['input']['targeting']['productIds'] );
    }

    public function test_category_scope_becomes_conditions(): void {
        $set          = $this->form( array( array( 'type' => 'text', 'label' => 'X' ) ) );
        $set['scope'] = array( 'mode' => 'conditions', 'productIds' => array(), 'categoryIds' => array( 3 ) );
        $out          = $this->convert( $set );
        $this->assertSame( 'conditions', $out['input']['targeting']['mode'] );
        $this->assertContains( array( 'type' => 'category', 'operator' => 'is', 'value' => '3' ), $out['input']['targeting']['conditions'] );
    }

    public function test_output_survives_the_sanitizer(): void {
        $set          = $this->form(
            array(
                array( 'type' => 'select', 'label' => 'Size', 'values' => array( array( 'label' => 'S', 'value' => 's', 'price' => 2 ) ) ),
                array( 'type' => 'text', 'label' => 'Note' ),
                array( 'type' => 'checkbox', 'label' => 'Wrap', 'check_value' => 'Yes' ),
            )
        );
        $set['scope'] = array( 'mode' => 'manual', 'productIds' => array( 9 ), 'categoryIds' => array() );
        $out          = $this->convert( $set );
        $data         = OptionSetSchema::sanitize( $out['input'] );

        $this->assertSame( 'Form 1', $data['name'] );
        $this->assertCount( 3, $data['fields'] );
        $this->assertSame( 'dropdown', $data['fields'][0]['type'] );
        $this->assertSame( array( 'type' => 'fixed', 'amount' => 2.0 ), $data['fields'][0]['options'][0]['price'] );
        $this->assertSame( 'manual', $data['targeting']['mode'] );
    }
}
