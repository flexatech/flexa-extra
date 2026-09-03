<?php
namespace Flexa\Extra\Tests\Unit;

use Flexa\Extra\Cart\SelectionProcessor;
use Flexa\Extra\Tests\Support\OptionSetFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WC_Product;

/**
 * The authoritative server-side engine: pricing, validation and conditional
 * logic. This is where money correctness lives, so it gets the most coverage.
 */
#[CoversClass( SelectionProcessor::class )]
final class SelectionProcessorTest extends TestCase {

    protected function setUp(): void {
        OptionSetFactory::reset();
    }

    private function product( float $price = 100.0 ): WC_Product {
        return new WC_Product( 1, $price );
    }

    public function test_fixed_choice_price_is_summed(): void {
        OptionSetFactory::register(
            1,
            array(
                'name'      => 'Colour',
                'status'    => true,
                'targeting' => array( 'mode' => 'all' ),
                'fields'    => array(
                    array(
                        'type'    => 'radio',
                        'id'      => 'color',
                        'label'   => 'Colour',
                        'options' => array(
                            OptionSetFactory::choice( 'red', 'Red', OptionSetFactory::price( 'fixed', 5.0 ) ),
                            OptionSetFactory::choice( 'blue', 'Blue', OptionSetFactory::price( 'fixed', 8.0 ) ),
                        ),
                    ),
                ),
            )
        );

        $result = SelectionProcessor::process( $this->product(), array( 'color' => 'red' ) );

        $this->assertSame( 5.0, $result['total'] );
        $this->assertSame( array(), $result['errors'] );
        $this->assertSame( 'Red', $result['lines'][0]['display'] );
        $this->assertSame( 5.0, $result['lines'][0]['amount'] );
    }

    public function test_percent_price_is_relative_to_base(): void {
        OptionSetFactory::register(
            1,
            array(
                'name'      => 'Plan',
                'status'    => true,
                'targeting' => array( 'mode' => 'all' ),
                'fields'    => array(
                    array(
                        'type'    => 'radio',
                        'id'      => 'plan',
                        'label'   => 'Plan',
                        'options' => array(
                            OptionSetFactory::choice( 'gold', 'Gold', OptionSetFactory::price( 'percent', 10.0 ) ),
                        ),
                    ),
                ),
            )
        );

        $result = SelectionProcessor::process( $this->product( 200.0 ), array( 'plan' => 'gold' ) );

        $this->assertSame( 20.0, $result['total'] );
    }

    public function test_multiple_checkbox_sums_every_selected_option(): void {
        OptionSetFactory::register(
            1,
            array(
                'name'      => 'Add-ons',
                'status'    => true,
                'targeting' => array( 'mode' => 'all' ),
                'fields'    => array(
                    array(
                        'type'    => 'checkbox',
                        'id'      => 'addons',
                        'label'   => 'Add-ons',
                        'options' => array(
                            OptionSetFactory::choice( 'a', 'A', OptionSetFactory::price( 'fixed', 5.0 ) ),
                            OptionSetFactory::choice( 'b', 'B', OptionSetFactory::price( 'fixed', 7.0 ) ),
                            OptionSetFactory::choice( 'c', 'C', OptionSetFactory::price( 'fixed', 9.0 ) ),
                        ),
                    ),
                ),
            )
        );

        $result = SelectionProcessor::process( $this->product(), array( 'addons' => array( 'a', 'c' ) ) );

        $this->assertSame( 14.0, $result['total'] );
        $this->assertSame( 'A, C', $result['lines'][0]['display'] );
    }

    /**
     * A three-option checkbox with optional min/max selection bounds.
     *
     * @param array<string,mixed> $bounds
     */
    private function register_addons_with_bounds( array $bounds ): void {
        OptionSetFactory::register(
            1,
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
                                OptionSetFactory::choice( 'a', 'A' ),
                                OptionSetFactory::choice( 'b', 'B' ),
                                OptionSetFactory::choice( 'c', 'C' ),
                            ),
                        ),
                        $bounds
                    ),
                ),
            )
        );
    }

    public function test_max_select_blocks_too_many_options(): void {
        $this->register_addons_with_bounds( array( 'maxSelect' => 2 ) );

        $result = SelectionProcessor::process( $this->product(), array( 'addons' => array( 'a', 'b', 'c' ) ) );

        $this->assertNotEmpty( $result['errors'] );
        $this->assertStringContainsString( 'at most', strtolower( $result['errors'][0] ) );
    }

    public function test_min_select_requires_enough_options(): void {
        $this->register_addons_with_bounds( array( 'minSelect' => 2 ) );

        $result = SelectionProcessor::process( $this->product(), array( 'addons' => array( 'a' ) ) );

        $this->assertNotEmpty( $result['errors'] );
        $this->assertStringContainsString( 'at least', strtolower( $result['errors'][0] ) );
    }

    public function test_selection_within_bounds_passes(): void {
        $this->register_addons_with_bounds( array( 'minSelect' => 1, 'maxSelect' => 2 ) );

        $result = SelectionProcessor::process( $this->product(), array( 'addons' => array( 'a', 'b' ) ) );

        $this->assertSame( array(), $result['errors'] );
    }

    public function test_bounds_do_not_fire_on_untouched_optional_field(): void {
        // min is set but the shopper picked nothing: an optional field stays valid.
        $this->register_addons_with_bounds( array( 'minSelect' => 2 ) );

        $result = SelectionProcessor::process( $this->product(), array() );

        $this->assertSame( array(), $result['errors'] );
    }

    public function test_required_field_missing_produces_error(): void {
        $this->register_required_text();

        $result = SelectionProcessor::process( $this->product(), array() );

        $this->assertNotEmpty( $result['errors'] );
        $this->assertStringContainsString( 'required', strtolower( $result['errors'][0] ) );
    }

    public function test_required_field_present_passes(): void {
        $this->register_required_text();

        $result = SelectionProcessor::process( $this->product(), array( 'engrave' => 'Happy Birthday' ) );

        $this->assertSame( array(), $result['errors'] );
        $this->assertSame( 'Happy Birthday', $result['selections']['engrave'] );
    }

    public function test_invalid_email_format_is_rejected(): void {
        OptionSetFactory::register(
            1,
            array(
                'name'      => 'Contact',
                'status'    => true,
                'targeting' => array( 'mode' => 'all' ),
                'fields'    => array(
                    array( 'type' => 'text', 'id' => 'mail', 'label' => 'Email', 'textFormat' => 'email' ),
                ),
            )
        );

        $bad  = SelectionProcessor::process( $this->product(), array( 'mail' => 'not-an-email' ) );
        $good = SelectionProcessor::process( $this->product(), array( 'mail' => 'a@b.com' ) );

        $this->assertNotEmpty( $bad['errors'] );
        $this->assertSame( array(), $good['errors'] );
    }

    public function test_number_range_is_enforced(): void {
        OptionSetFactory::register(
            1,
            array(
                'name'      => 'Qty',
                'status'    => true,
                'targeting' => array( 'mode' => 'all' ),
                'fields'    => array(
                    array( 'type' => 'number', 'id' => 'qty', 'label' => 'Quantity', 'min' => 1, 'max' => 10 ),
                ),
            )
        );

        $this->assertNotEmpty( SelectionProcessor::process( $this->product(), array( 'qty' => '0' ) )['errors'] );
        $this->assertNotEmpty( SelectionProcessor::process( $this->product(), array( 'qty' => '11' ) )['errors'] );
        $this->assertSame( array(), SelectionProcessor::process( $this->product(), array( 'qty' => '5' ) )['errors'] );
    }

    public function test_hidden_field_is_neither_validated_nor_priced(): void {
        OptionSetFactory::register(
            1,
            array(
                'name'      => 'Gift',
                'status'    => true,
                'targeting' => array( 'mode' => 'all' ),
                'fields'    => array(
                    array(
                        'type'    => 'radio',
                        'id'      => 'gift',
                        'label'   => 'Gift wrap?',
                        'options' => array(
                            OptionSetFactory::choice( 'yes', 'Yes' ),
                            OptionSetFactory::choice( 'no', 'No' ),
                        ),
                    ),
                    array(
                        'type'     => 'text',
                        'id'       => 'msg',
                        'label'    => 'Gift message',
                        'required' => true,
                        'price'    => OptionSetFactory::price( 'fixed', 3.0 ),
                        'logic'    => array(
                            'enabled' => true,
                            'action'  => 'show',
                            'match'   => 'any',
                            'rules'   => array(
                                array( 'field' => 'gift', 'operator' => 'is', 'value' => 'yes' ),
                            ),
                        ),
                    ),
                ),
            )
        );

        // Gift = no => message hidden => its required rule and its price must not apply.
        $hidden = SelectionProcessor::process( $this->product(), array( 'gift' => 'no' ) );
        $this->assertSame( array(), $hidden['errors'] );
        $this->assertSame( 0.0, $hidden['total'] );
        $this->assertArrayNotHasKey( 'msg', $hidden['selections'] );

        // Gift = yes => message visible & required & empty => error.
        $shown = SelectionProcessor::process( $this->product(), array( 'gift' => 'yes' ) );
        $this->assertNotEmpty( $shown['errors'] );
    }

    public function test_client_supplied_price_is_ignored(): void {
        OptionSetFactory::register(
            1,
            array(
                'name'      => 'Colour',
                'status'    => true,
                'targeting' => array( 'mode' => 'all' ),
                'fields'    => array(
                    array(
                        'type'    => 'radio',
                        'id'      => 'color',
                        'label'   => 'Colour',
                        'options' => array(
                            OptionSetFactory::choice( 'red', 'Red', OptionSetFactory::price( 'fixed', 5.0 ) ),
                        ),
                    ),
                ),
            )
        );

        // Attacker appends bogus price/total keys — the engine must derive price
        // only from the stored definition.
        $result = SelectionProcessor::process(
            $this->product(),
            array( 'color' => 'red', 'price' => '9999', 'total' => '9999', 'amount' => '9999' )
        );

        $this->assertSame( 5.0, $result['total'] );
    }

    public function test_unknown_selected_value_contributes_no_price(): void {
        OptionSetFactory::register(
            1,
            array(
                'name'      => 'Colour',
                'status'    => true,
                'targeting' => array( 'mode' => 'all' ),
                'fields'    => array(
                    array(
                        'type'    => 'radio',
                        'id'      => 'color',
                        'label'   => 'Colour',
                        'options' => array(
                            OptionSetFactory::choice( 'red', 'Red', OptionSetFactory::price( 'fixed', 5.0 ) ),
                        ),
                    ),
                ),
            )
        );

        $result = SelectionProcessor::process( $this->product(), array( 'color' => 'chartreuse' ) );

        $this->assertSame( 0.0, $result['total'] );
    }

    private function register_required_text(): void {
        OptionSetFactory::register(
            1,
            array(
                'name'      => 'Engrave',
                'status'    => true,
                'targeting' => array( 'mode' => 'all' ),
                'fields'    => array(
                    array( 'type' => 'text', 'id' => 'engrave', 'label' => 'Engraving', 'required' => true ),
                ),
            )
        );
    }

    /**
     * @param array<int,array<string,mixed>> $actions
     */
    private function register_colour_with_actions( array $actions ): void {
        OptionSetFactory::register(
            1,
            array(
                'name'      => 'Colour',
                'status'    => true,
                'targeting' => array( 'mode' => 'all' ),
                'fields'    => array(
                    array(
                        'type'    => 'radio',
                        'id'      => 'color',
                        'label'   => 'Colour',
                        'options' => array(
                            OptionSetFactory::choice( 'red', 'Red' ),
                            OptionSetFactory::choice( 'blue', 'Blue' ),
                        ),
                    ),
                ),
                'actions'   => $actions,
            )
        );
    }

    public function test_fee_action_applies_only_when_rule_matches(): void {
        $this->register_colour_with_actions(
            array(
                array(
                    'id'    => 'rush',
                    'label' => 'Rush fee',
                    'kind'  => 'fee',
                    'price' => OptionSetFactory::price( 'fixed', 10.0 ),
                    'match' => 'any',
                    'rules' => array(
                        array( 'field' => 'color', 'operator' => 'is', 'value' => 'red' ),
                    ),
                ),
            )
        );

        $red = SelectionProcessor::process( $this->product(), array( 'color' => 'red' ) );
        $this->assertSame( 10.0, $red['total'] );
        $action_line = end( $red['lines'] );
        $this->assertSame( 'action', $action_line['type'] );
        $this->assertSame( 'Rush fee', $action_line['label'] );
        $this->assertSame( 10.0, $action_line['amount'] );

        $blue = SelectionProcessor::process( $this->product(), array( 'color' => 'blue' ) );
        $this->assertSame( 0.0, $blue['total'] );
    }

    public function test_discount_action_subtracts_from_total(): void {
        $this->register_colour_with_actions(
            array(
                array(
                    'id'    => 'promo',
                    'label' => 'Promo',
                    'kind'  => 'discount',
                    'price' => OptionSetFactory::price( 'percent', 10.0 ),
                    'match' => 'any',
                    'rules' => array(
                        array( 'field' => 'color', 'operator' => 'is', 'value' => 'red' ),
                    ),
                ),
            )
        );

        // 10% of the 100.0 base, applied as a discount.
        $result = SelectionProcessor::process( $this->product(), array( 'color' => 'red' ) );
        $this->assertSame( -10.0, $result['total'] );
        $this->assertSame( -10.0, end( $result['lines'] )['amount'] );
    }

    public function test_action_with_no_rules_always_applies(): void {
        $this->register_colour_with_actions(
            array(
                array(
                    'id'    => 'handling',
                    'label' => 'Handling',
                    'kind'  => 'fee',
                    'price' => OptionSetFactory::price( 'fixed', 3.0 ),
                    'match' => 'any',
                    'rules' => array(),
                ),
            )
        );

        $result = SelectionProcessor::process( $this->product(), array( 'color' => 'blue' ) );
        $this->assertSame( 3.0, $result['total'] );
    }

    public function test_match_all_requires_every_rule(): void {
        $this->register_colour_with_actions(
            array(
                array(
                    'id'    => 'combo',
                    'label' => 'Combo',
                    'kind'  => 'fee',
                    'price' => OptionSetFactory::price( 'fixed', 7.0 ),
                    'match' => 'all',
                    'rules' => array(
                        array( 'field' => 'color', 'operator' => 'is', 'value' => 'red' ),
                        array( 'field' => 'color', 'operator' => 'is_not', 'value' => 'blue' ),
                    ),
                ),
            )
        );

        $this->assertSame( 7.0, SelectionProcessor::process( $this->product(), array( 'color' => 'red' ) )['total'] );
        $this->assertSame( 0.0, SelectionProcessor::process( $this->product(), array( 'color' => 'blue' ) )['total'] );
    }
}
