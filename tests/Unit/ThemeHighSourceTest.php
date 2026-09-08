<?php
namespace Flexa\Extra\Tests\Unit;

use Flexa\Extra\Fields\OptionSetSchema;
use Flexa\Extra\Migration\ThemeHighSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The ThemeHigh converter maps that plugin's serialized sections into Flexa
 * Extra input. The free source has no per-option pricing, so choices come over
 * as labels only; a section title becomes a heading, and product conditions are
 * flattened into targeting.
 */
#[CoversClass( ThemeHighSource::class )]
final class ThemeHighSourceTest extends TestCase {

    private function convert( array $section ): array {
        return ( new ThemeHighSource() )->convert( $section );
    }

    public function test_section_title_becomes_leading_heading(): void {
        $out = $this->convert(
            array(
                'title'      => 'Personalize',
                'show_title' => 1,
                'fields'     => array(
                    array( 'type' => 'inputtext', 'title' => 'Name', 'name' => 'name' ),
                ),
            )
        );

        $this->assertSame( 'Personalize', $out['input']['name'] );
        $this->assertSame( 'heading', $out['input']['fields'][0]['type'] );
        $this->assertSame( 'Personalize', $out['input']['fields'][0]['label'] );
        $this->assertSame( 'text', $out['input']['fields'][1]['type'] );
    }

    public function test_email_validator_maps_to_text_format(): void {
        $out = $this->convert(
            array(
                'show_title' => 0,
                'fields'     => array(
                    array( 'type' => 'inputtext', 'title' => 'Email', 'name' => 'email', 'validator' => 'email' ),
                ),
            )
        );
        $field = $out['input']['fields'][0];
        $this->assertSame( 'text', $field['type'] );
        $this->assertSame( 'email', $field['textFormat'] );
    }

    public function test_select_options_are_labels_without_price(): void {
        $out = $this->convert(
            array(
                'show_title' => 0,
                'fields'     => array(
                    array( 'type' => 'select', 'title' => 'Size', 'name' => 'size', 'options' => array( 'S', 'M', 'L' ) ),
                ),
            )
        );
        $field = $out['input']['fields'][0];
        $this->assertSame( 'dropdown', $field['type'] );
        $this->assertCount( 3, $field['options'] );
        $this->assertSame( 'M', $field['options'][1]['label'] );
        $this->assertSame( 'M', $field['options'][1]['value'] );
        $this->assertSame( array( 'type' => 'none', 'amount' => 0.0 ), $field['options'][1]['price'] );
    }

    public function test_single_checkbox_becomes_one_option(): void {
        $out = $this->convert(
            array(
                'show_title' => 0,
                'fields'     => array(
                    array( 'type' => 'checkbox', 'title' => 'Gift wrap', 'name' => 'wrap' ),
                ),
            )
        );
        $field = $out['input']['fields'][0];
        $this->assertSame( 'checkbox', $field['type'] );
        $this->assertTrue( $field['multiple'] );
        $this->assertCount( 1, $field['options'] );
        $this->assertSame( 'Gift wrap', $field['options'][0]['label'] );
        $this->assertSame( 'yes', $field['options'][0]['value'] );
    }

    public function test_checkboxgroup_becomes_multiple(): void {
        $out = $this->convert(
            array(
                'show_title' => 0,
                'fields'     => array(
                    array( 'type' => 'checkboxgroup', 'title' => 'Extras', 'name' => 'extras', 'options' => array( 'A', 'B' ) ),
                ),
            )
        );
        $this->assertTrue( $out['input']['fields'][0]['multiple'] );
        $this->assertCount( 2, $out['input']['fields'][0]['options'] );
    }

    public function test_hidden_and_separator_are_dropped_silently(): void {
        $out = $this->convert(
            array(
                'show_title' => 0,
                'fields'     => array(
                    array( 'type' => 'hidden', 'title' => 'h', 'name' => 'h' ),
                    array( 'type' => 'separator', 'title' => '', 'name' => 's' ),
                ),
            )
        );
        $this->assertSame( array(), $out['input']['fields'] );
        $this->assertSame( array(), $out['warnings'] );
    }

    public function test_disabled_field_is_skipped(): void {
        $out = $this->convert(
            array(
                'show_title' => 0,
                'fields'     => array(
                    array( 'type' => 'inputtext', 'title' => 'Off', 'name' => 'off', 'enabled' => false ),
                ),
            )
        );
        $this->assertSame( array(), $out['input']['fields'] );
    }

    public function test_paragraph_uses_its_text_as_heading_label(): void {
        $out = $this->convert(
            array(
                'show_title' => 0,
                'fields'     => array(
                    array( 'type' => 'paragraph', 'title' => '', 'name' => 'p', 'value' => 'Some help text' ),
                ),
            )
        );
        $field = $out['input']['fields'][0];
        $this->assertSame( 'heading', $field['type'] );
        $this->assertSame( 'Some help text', $field['label'] );
        $this->assertNotEmpty( $out['warnings'] );
    }

    public function test_conditional_rules_flatten_into_targeting(): void {
        $json = json_encode(
            array(
                array(
                    array(
                        array(
                            array( 'subject' => 'category', 'comparison' => 'equals', 'cvalue' => array( 'shirts' ) ),
                            array( 'subject' => 'product', 'comparison' => 'not_equals', 'cvalue' => array( '55' ) ),
                        ),
                    ),
                ),
            )
        );

        $out = $this->convert(
            array(
                'show_title'             => 0,
                'fields'                 => array( array( 'type' => 'inputtext', 'title' => 'A', 'name' => 'a' ) ),
                'conditional_rules_json' => $json,
            )
        );

        $targeting = $out['input']['targeting'];
        $this->assertSame( 'conditions', $targeting['mode'] );
        $this->assertContains( array( 'type' => 'category', 'operator' => 'is', 'value' => 'shirts' ), $targeting['conditions'] );
        $this->assertContains( array( 'type' => 'product', 'operator' => 'is_not', 'value' => '55' ), $targeting['conditions'] );
        $this->assertNotEmpty( $out['warnings'] );
    }

    public function test_output_survives_the_sanitizer(): void {
        $out  = $this->convert(
            array(
                'title'      => 'Section',
                'show_title' => 1,
                'fields'     => array(
                    array( 'type' => 'select', 'title' => 'Size', 'name' => 'size', 'options' => array( 'S', 'M' ) ),
                    array( 'type' => 'colorpicker', 'title' => 'Colour', 'name' => 'colour', 'value' => '#123456' ),
                ),
            )
        );
        $data = OptionSetSchema::sanitize( $out['input'] );

        $this->assertSame( 'Section', $data['name'] );
        $this->assertCount( 3, $data['fields'] ); // heading + dropdown + colour
        $this->assertSame( 'heading', $data['fields'][0]['type'] );
        $this->assertSame( 'dropdown', $data['fields'][1]['type'] );
        $this->assertSame( 'color_picker', $data['fields'][2]['type'] );
    }
}
