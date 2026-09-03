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

    public function test_color_picker_default_is_hex_validated(): void {
        $result = OptionSetSchema::sanitize(
            array(
                'name'   => 'Set',
                'fields' => array(
                    array( 'type' => 'color_picker', 'id' => 'a', 'label' => 'A', 'default' => '#ABC' ),
                    array( 'type' => 'color_picker', 'id' => 'b', 'label' => 'B', 'default' => 'red' ),
                ),
            )
        );

        $this->assertSame( '#ABC', $result['fields'][0]['default'] );
        $this->assertSame( '', $result['fields'][1]['default'] );
    }

    public function test_date_picker_default_accepts_only_valid_iso_dates(): void {
        $result = OptionSetSchema::sanitize(
            array(
                'name'   => 'Set',
                'fields' => array(
                    array( 'type' => 'date_picker', 'id' => 'a', 'label' => 'A', 'default' => '2026-09-03' ),
                    array( 'type' => 'date_picker', 'id' => 'b', 'label' => 'B', 'default' => '2026-13-40' ),
                    array( 'type' => 'date_picker', 'id' => 'c', 'label' => 'C', 'default' => '03/09/2026' ),
                ),
            )
        );

        $this->assertSame( '2026-09-03', $result['fields'][0]['default'] );
        $this->assertSame( '', $result['fields'][1]['default'] );
        $this->assertSame( '', $result['fields'][2]['default'] );
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

    public function test_choice_stock_is_normalized(): void {
        $result = OptionSetSchema::sanitize(
            array(
                'name'   => 'Set',
                'fields' => array(
                    array(
                        'type'    => 'dropdown',
                        'id'      => 'd',
                        'label'   => 'D',
                        'options' => array(
                            array( 'id' => 'a', 'label' => 'A', 'stock' => '5' ),
                            array( 'id' => 'b', 'label' => 'B', 'stock' => -3 ),
                            array( 'id' => 'c', 'label' => 'C', 'stock' => '' ),
                            array( 'id' => 'e', 'label' => 'E' ),
                            array( 'id' => 'f', 'label' => 'F', 'stock' => 'abc' ),
                        ),
                    ),
                ),
            )
        );

        $options = $result['fields'][0]['options'];
        $this->assertSame( 5, $options[0]['stock'], 'Numeric string becomes int.' );
        $this->assertSame( 0, $options[1]['stock'], 'Negative clamps to zero.' );
        $this->assertNull( $options[2]['stock'], 'Empty string means unlimited.' );
        $this->assertNull( $options[3]['stock'], 'Missing means unlimited.' );
        $this->assertNull( $options[4]['stock'], 'Non-numeric means unlimited.' );
    }

    public function test_select_bounds_are_normalized(): void {
        $result = OptionSetSchema::sanitize(
            array(
                'name'   => 'Set',
                'fields' => array(
                    array(
                        'type'      => 'checkbox',
                        'id'        => 'addons',
                        'label'     => 'Add-ons',
                        'minSelect' => '2',
                        'maxSelect' => -1,
                        'options'   => array( array( 'id' => 'a', 'label' => 'A' ) ),
                    ),
                    array(
                        'type'    => 'radio',
                        'id'      => 'plain',
                        'label'   => 'Plain',
                        'options' => array( array( 'id' => 'x', 'label' => 'X' ) ),
                    ),
                ),
            )
        );

        $this->assertSame( 2, $result['fields'][0]['minSelect'], 'Numeric string becomes int.' );
        $this->assertSame( 0, $result['fields'][0]['maxSelect'], 'Negative clamps to zero.' );
        $this->assertNull( $result['fields'][1]['minSelect'], 'Unset stays null.' );
        $this->assertNull( $result['fields'][1]['maxSelect'], 'Unset stays null.' );
    }

    public function test_actions_are_normalized(): void {
        $result = OptionSetSchema::sanitize(
            array(
                'name'    => 'Set',
                'actions' => array(
                    array(
                        'id'    => 'a1',
                        'label' => 'Rush',
                        'kind'  => 'bogus',
                        'price' => array( 'type' => 'fixed', 'amount' => 12 ),
                        'match' => 'all',
                        'rules' => array(
                            array( 'field' => 'color', 'operator' => 'weird', 'value' => 'red' ),
                        ),
                    ),
                    'not-an-array',
                ),
            )
        );

        $this->assertCount( 1, $result['actions'], 'Non-array actions are dropped.' );
        $action = $result['actions'][0];
        $this->assertSame( 'fee', $action['kind'], 'Unknown kind falls back to fee.' );
        $this->assertSame( 'all', $action['match'] );
        $this->assertSame( 12.0, $action['price']['amount'] );
        $this->assertSame( 'is', $action['rules'][0]['operator'], 'Unknown operator falls back to is.' );
        $this->assertSame( 'color', $action['rules'][0]['field'] );
    }
}
