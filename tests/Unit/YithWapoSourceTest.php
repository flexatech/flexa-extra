<?php
namespace Flexa\Extra\Tests\Unit;

use Flexa\Extra\Fields\OptionSetSchema;
use Flexa\Extra\Migration\YithWapoSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The YITH WAPO converter maps that plugin's add-on rows (a serialized `settings`
 * array plus a column-oriented `options` array) into Flexa Extra input. It
 * transposes the parallel option lists into per-option rows, maps free-version
 * types, and honours per-option pricing (increase / decrease / percentage).
 */
#[CoversClass( YithWapoSource::class )]
final class YithWapoSourceTest extends TestCase {

    /**
     * @param array<string,mixed> $set
     * @return array{input:array<string,mixed>,warnings:list<string>}
     */
    private function convert( array $set ): array {
        return ( new YithWapoSource() )->convert( $set );
    }

    /**
     * One add-on in the { settings, options } row shape read_raw() produces.
     *
     * @param array<string,mixed>     $settings
     * @param array<string,mixed>     $options
     * @return array{settings:array<string,mixed>,options:array<string,mixed>}
     */
    private function addon( array $settings, array $options = array() ): array {
        return array( 'settings' => $settings, 'options' => $options );
    }

    public function test_free_types_map(): void {
        $out = $this->convert(
            array(
                'name'   => 'Block',
                'addons' => array(
                    $this->addon( array( 'type' => 'text', 'title' => 'Name' ) ),
                    $this->addon( array( 'type' => 'select', 'title' => 'Size' ), array( 'label' => array( 'S', 'M' ) ) ),
                    $this->addon( array( 'type' => 'radio', 'title' => 'Colour' ), array( 'label' => array( 'Red' ) ) ),
                    $this->addon( array( 'type' => 'checkbox', 'title' => 'Extras' ), array( 'label' => array( 'A' ) ) ),
                    $this->addon( array( 'type' => 'label', 'title' => 'A heading' ) ),
                ),
            )
        );

        $fields = $out['input']['fields'];
        $this->assertSame( 'text', $fields[0]['type'] );
        $this->assertSame( 'dropdown', $fields[1]['type'] );
        $this->assertSame( 'radio', $fields[2]['type'] );
        $this->assertSame( 'checkbox', $fields[3]['type'] );
        $this->assertTrue( $fields[3]['multiple'] );
        $this->assertSame( 'heading', $fields[4]['type'] );
    }

    public function test_column_oriented_options_are_transposed(): void {
        $out = $this->convert(
            array(
                'addons' => array(
                    $this->addon(
                        array( 'type' => 'select', 'title' => 'Size' ),
                        array(
                            'label'        => array( 'Small', 'Large' ),
                            'price'        => array( '0', '5' ),
                            'price_method' => array( 'free', 'increase' ),
                            'price_type'   => array( 'fixed', 'fixed' ),
                        )
                    ),
                ),
            )
        );

        $options = $out['input']['fields'][0]['options'];
        $this->assertCount( 2, $options );
        $this->assertSame( 'Small', $options[0]['label'] );
        $this->assertSame( array( 'type' => 'none', 'amount' => 0.0 ), $options[0]['price'] );
        $this->assertSame( 'Large', $options[1]['label'] );
        $this->assertSame( array( 'type' => 'fixed', 'amount' => 5.0 ), $options[1]['price'] );
    }

    public function test_decrease_method_makes_negative_price(): void {
        $out = $this->convert(
            array(
                'addons' => array(
                    $this->addon(
                        array( 'type' => 'radio', 'title' => 'Discount' ),
                        array(
                            'label'        => array( 'Less' ),
                            'price'        => array( '4' ),
                            'price_method' => array( 'decrease' ),
                            'price_type'   => array( 'fixed' ),
                        )
                    ),
                ),
            )
        );
        $this->assertSame( array( 'type' => 'fixed', 'amount' => -4.0 ), $out['input']['fields'][0]['options'][0]['price'] );
    }

    public function test_percentage_price_type_maps_to_percent(): void {
        $out = $this->convert(
            array(
                'addons' => array(
                    $this->addon(
                        array( 'type' => 'select', 'title' => 'X' ),
                        array(
                            'label'        => array( 'Ten' ),
                            'price'        => array( '10' ),
                            'price_method' => array( 'increase' ),
                            'price_type'   => array( 'percentage' ),
                        )
                    ),
                ),
            )
        );
        $this->assertSame( array( 'type' => 'percent', 'amount' => 10.0 ), $out['input']['fields'][0]['options'][0]['price'] );
    }

    public function test_color_type_becomes_swatch_with_colour(): void {
        $out = $this->convert(
            array(
                'addons' => array(
                    $this->addon(
                        array( 'type' => 'color', 'title' => 'Colour' ),
                        array(
                            'label' => array( 'Red', 'Blue' ),
                            'color' => array( '#ff0000', '#0000ff' ),
                        )
                    ),
                ),
            )
        );
        $field = $out['input']['fields'][0];
        $this->assertSame( 'swatch', $field['type'] );
        $this->assertSame( '#ff0000', $field['options'][0]['color'] );
        $this->assertSame( '#0000ff', $field['options'][1]['color'] );
    }

    public function test_html_text_uses_its_content_as_heading_label(): void {
        $out = $this->convert(
            array(
                'addons' => array(
                    $this->addon( array( 'type' => 'html_text', 'text_content' => 'Some help copy' ) ),
                ),
            )
        );
        $field = $out['input']['fields'][0];
        $this->assertSame( 'heading', $field['type'] );
        $this->assertSame( 'Some help copy', $field['label'] );
    }

    public function test_separator_is_dropped_silently(): void {
        $out = $this->convert(
            array(
                'addons' => array(
                    $this->addon( array( 'type' => 'html_separator' ) ),
                ),
            )
        );
        $this->assertSame( array(), $out['input']['fields'] );
        $this->assertSame( array(), $out['warnings'] );
    }

    public function test_file_and_product_are_skipped_with_warning(): void {
        $out = $this->convert(
            array(
                'addons' => array(
                    $this->addon( array( 'type' => 'file', 'title' => 'Upload' ) ),
                    $this->addon( array( 'type' => 'product', 'title' => 'Bundle' ) ),
                ),
            )
        );
        $this->assertSame( array(), $out['input']['fields'] );
        $this->assertNotEmpty( $out['warnings'] );
    }

    public function test_list_style_options_are_accepted(): void {
        // Some versions store options already as a list of per-option arrays.
        $out = $this->convert(
            array(
                'addons' => array(
                    $this->addon(
                        array( 'type' => 'select', 'title' => 'Size' ),
                        array(
                            array( 'label' => 'S', 'price_method' => 'free' ),
                            array( 'label' => 'M', 'price' => '2', 'price_method' => 'increase', 'price_type' => 'fixed' ),
                        )
                    ),
                ),
            )
        );
        $options = $out['input']['fields'][0]['options'];
        $this->assertCount( 2, $options );
        $this->assertSame( 'M', $options[1]['label'] );
        $this->assertSame( array( 'type' => 'fixed', 'amount' => 2.0 ), $options[1]['price'] );
    }

    public function test_manual_scope_targets_products(): void {
        $out = $this->convert(
            array(
                'addons' => array( $this->addon( array( 'type' => 'text', 'title' => 'X' ) ) ),
                'scope'  => array( 'mode' => 'manual', 'productIds' => array( 11, 12 ), 'categoryIds' => array() ),
            )
        );
        $this->assertSame( 'manual', $out['input']['targeting']['mode'] );
        $this->assertSame( array( 11, 12 ), $out['input']['targeting']['productIds'] );
    }

    public function test_output_survives_the_sanitizer(): void {
        $out  = $this->convert(
            array(
                'name'   => 'YITH block',
                'addons' => array(
                    $this->addon(
                        array( 'type' => 'color', 'title' => 'Colour' ),
                        array( 'label' => array( 'Red' ), 'color' => array( '#ff0000' ) )
                    ),
                    $this->addon( array( 'type' => 'text', 'title' => 'Note', 'required' => 'yes' ) ),
                ),
                'scope'  => array( 'mode' => 'conditions', 'productIds' => array(), 'categoryIds' => array( 9 ) ),
            )
        );
        $data = OptionSetSchema::sanitize( $out['input'] );

        $this->assertSame( 'YITH block', $data['name'] );
        $this->assertCount( 2, $data['fields'] );
        $this->assertSame( 'swatch', $data['fields'][0]['type'] );
        $this->assertSame( '#ff0000', $data['fields'][0]['options'][0]['color'] );
        $this->assertTrue( $data['fields'][1]['required'] );
        $this->assertSame( 'conditions', $data['targeting']['mode'] );
    }
}
