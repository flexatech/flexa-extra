<?php
namespace Flexa\Extra\Tests\Unit;

use Flexa\Extra\Fields\OptionSetSchema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The sanitizer is the single server-side contract for what can reach post
 * meta. These tests lock its guarantees: unknown types dropped, prices/logic/
 * targeting normalized, hostile input neutralized.
 */
#[CoversClass( OptionSetSchema::class )]
final class OptionSetSchemaTest extends TestCase {

    public function test_empty_name_falls_back_to_untitled(): void {
        $result = OptionSetSchema::sanitize( array() );
        $this->assertSame( 'Untitled Option Set', $result['name'] );
        $this->assertFalse( $result['status'] );
        $this->assertSame( array(), $result['fields'] );
    }

    public function test_unknown_field_type_is_dropped(): void {
        $result = OptionSetSchema::sanitize(
            array(
                'name'   => 'Set',
                'fields' => array(
                    array( 'type' => 'text', 'id' => 'a', 'label' => 'A' ),
                    array( 'type' => 'malware', 'id' => 'b', 'label' => 'B' ),
                ),
            )
        );

        $this->assertCount( 1, $result['fields'] );
        $this->assertSame( 'text', $result['fields'][0]['type'] );
    }

    public function test_field_label_is_tag_stripped(): void {
        $result = OptionSetSchema::sanitize(
            array(
                'name'   => 'Set',
                'fields' => array(
                    array( 'type' => 'text', 'id' => 'a', 'label' => '<script>alert(1)</script>Name' ),
                ),
            )
        );

        $this->assertSame( 'alert(1)Name', $result['fields'][0]['label'] );
    }

    public function test_field_name_is_slugified_with_underscores(): void {
        $result = OptionSetSchema::sanitize(
            array(
                'name'   => 'Set',
                'fields' => array(
                    array( 'type' => 'text', 'id' => 'a', 'label' => 'A', 'name' => 'Gift Message!!' ),
                ),
            )
        );

        $this->assertSame( 'gift_message', $result['fields'][0]['name'] );
    }

    public function test_price_none_forces_zero_amount(): void {
        $result = OptionSetSchema::sanitize(
            array(
                'name'   => 'Set',
                'fields' => array(
                    array(
                        'type'  => 'text',
                        'id'    => 'a',
                        'label' => 'A',
                        'price' => array( 'type' => 'none', 'amount' => 999 ),
                    ),
                ),
            )
        );

        $this->assertSame( 'none', $result['fields'][0]['price']['type'] );
        $this->assertSame( 0.0, $result['fields'][0]['price']['amount'] );
    }

    public function test_invalid_price_type_falls_back_to_none(): void {
        $result = OptionSetSchema::sanitize(
            array(
                'name'   => 'Set',
                'fields' => array(
                    array(
                        'type'  => 'number',
                        'id'    => 'a',
                        'label' => 'A',
                        'price' => array( 'type' => 'freebie', 'amount' => 10 ),
                    ),
                ),
            )
        );

        $this->assertSame( 'none', $result['fields'][0]['price']['type'] );
        $this->assertSame( 0.0, $result['fields'][0]['price']['amount'] );
    }

    public function test_checkbox_is_always_multiple(): void {
        $result = OptionSetSchema::sanitize(
            array(
                'name'   => 'Set',
                'fields' => array(
                    array(
                        'type'     => 'checkbox',
                        'id'       => 'c',
                        'label'    => 'C',
                        'multiple' => false,
                        'options'  => array( array( 'id' => 'x', 'label' => 'X' ) ),
                    ),
                ),
            )
        );

        $this->assertTrue( $result['fields'][0]['multiple'] );
    }

    public function test_logic_operator_is_clamped(): void {
        $result = OptionSetSchema::sanitize(
            array(
                'name'   => 'Set',
                'fields' => array(
                    array(
                        'type'  => 'text',
                        'id'    => 'a',
                        'label' => 'A',
                        'logic' => array(
                            'enabled' => true,
                            'action'  => 'hide',
                            'match'   => 'all',
                            'rules'   => array(
                                array( 'field' => 'b', 'operator' => 'sql_inject', 'value' => 'v' ),
                            ),
                        ),
                    ),
                ),
            )
        );

        $logic = $result['fields'][0]['logic'];
        $this->assertTrue( $logic['enabled'] );
        $this->assertSame( 'hide', $logic['action'] );
        $this->assertSame( 'is', $logic['rules'][0]['operator'] );
    }

    public function test_targeting_mode_falls_back_to_all(): void {
        $result = OptionSetSchema::sanitize(
            array(
                'name'      => 'Set',
                'targeting' => array( 'mode' => 'everywhere' ),
            )
        );

        $this->assertSame( 'all', $result['targeting']['mode'] );
    }

    public function test_targeting_product_ids_are_absint_filtered(): void {
        $result = OptionSetSchema::sanitize(
            array(
                'name'      => 'Set',
                'targeting' => array(
                    'mode'       => 'manual',
                    'productIds' => array( '12', -3, 'abc', 0, 45 ),
                ),
            )
        );

        $this->assertSame( array( 12, 3, 45 ), $result['targeting']['productIds'] );
    }

    public function test_choice_hex_color_is_validated(): void {
        $result = OptionSetSchema::sanitize(
            array(
                'name'   => 'Set',
                'fields' => array(
                    array(
                        'type'    => 'swatch',
                        'id'      => 's',
                        'label'   => 'S',
                        'options' => array(
                            array( 'id' => 'ok', 'label' => 'OK', 'color' => '#ff0000' ),
                            array( 'id' => 'bad', 'label' => 'Bad', 'color' => 'red; drop table' ),
                        ),
                    ),
                ),
            )
        );

        $options = $result['fields'][0]['options'];
        $this->assertSame( '#ff0000', $options[0]['color'] );
        $this->assertSame( '', $options[1]['color'] );
    }
}
