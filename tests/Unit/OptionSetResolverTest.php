<?php
namespace Flexa\Extra\Tests\Unit;

use Flexa\Extra\Frontend\OptionSetResolver;
use Flexa\Extra\Tests\Support\OptionSetFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WC_Product;

/**
 * Targeting resolution: which option sets apply to a given product.
 */
#[CoversClass( OptionSetResolver::class )]
final class OptionSetResolverTest extends TestCase {

    protected function setUp(): void {
        OptionSetFactory::reset();
    }

    /** @return array<string,mixed> */
    private function text_field(): array {
        return array( 'type' => 'text', 'id' => 'a', 'label' => 'A' );
    }

    public function test_mode_all_matches_every_product(): void {
        OptionSetFactory::register(
            1,
            array( 'name' => 'Global', 'status' => true, 'targeting' => array( 'mode' => 'all' ), 'fields' => array( $this->text_field() ) )
        );

        $sets = OptionSetResolver::for_product( new WC_Product( 99, 10.0 ) );

        $this->assertCount( 1, $sets );
        $this->assertSame( 1, $sets[0]['id'] );
    }

    public function test_inactive_set_is_excluded(): void {
        OptionSetFactory::register(
            1,
            array( 'name' => 'Draft', 'status' => false, 'targeting' => array( 'mode' => 'all' ), 'fields' => array( $this->text_field() ) )
        );

        $this->assertCount( 0, OptionSetResolver::for_product( new WC_Product( 5, 10.0 ) ) );
    }

    public function test_set_without_fields_is_excluded(): void {
        OptionSetFactory::register(
            1,
            array( 'name' => 'Empty', 'status' => true, 'targeting' => array( 'mode' => 'all' ), 'fields' => array() )
        );

        $this->assertCount( 0, OptionSetResolver::for_product( new WC_Product( 5, 10.0 ) ) );
    }

    public function test_manual_mode_matches_listed_product_and_parent(): void {
        OptionSetFactory::register(
            1,
            array(
                'name'      => 'Manual',
                'status'    => true,
                'targeting' => array( 'mode' => 'manual', 'productIds' => array( 42 ) ),
                'fields'    => array( $this->text_field() ),
            )
        );

        $this->assertCount( 1, OptionSetResolver::for_product( new WC_Product( 42, 10.0 ) ) );
        // Variation whose parent is 42 also matches.
        $this->assertCount( 1, OptionSetResolver::for_product( new WC_Product( 77, 10.0, 42 ) ) );
        // Unrelated product does not.
        OptionSetFactory::reset();
        OptionSetFactory::register(
            1,
            array(
                'name'      => 'Manual',
                'status'    => true,
                'targeting' => array( 'mode' => 'manual', 'productIds' => array( 42 ) ),
                'fields'    => array( $this->text_field() ),
            )
        );
        $this->assertCount( 0, OptionSetResolver::for_product( new WC_Product( 43, 10.0 ) ) );
    }

    public function test_conditions_category_match_any(): void {
        OptionSetFactory::register(
            1,
            array(
                'name'      => 'Cat',
                'status'    => true,
                'targeting' => array(
                    'mode'       => 'conditions',
                    'match'      => 'any',
                    'conditions' => array(
                        array( 'type' => 'category', 'operator' => 'is', 'value' => '7' ),
                    ),
                ),
                'fields'    => array( $this->text_field() ),
            )
        );

        OptionSetFactory::assign_terms( 100, 'product_cat', array( 7 ) );

        $this->assertCount( 1, OptionSetResolver::for_product( new WC_Product( 100, 10.0 ) ) );
        $this->assertCount( 0, OptionSetResolver::for_product( new WC_Product( 200, 10.0 ) ) );
    }

    public function test_conditions_price_greater_than(): void {
        OptionSetFactory::register(
            1,
            array(
                'name'      => 'Pricey',
                'status'    => true,
                'targeting' => array(
                    'mode'       => 'conditions',
                    'match'      => 'any',
                    'conditions' => array(
                        array( 'type' => 'price', 'operator' => 'gt', 'value' => '50' ),
                    ),
                ),
                'fields'    => array( $this->text_field() ),
            )
        );

        $this->assertCount( 1, OptionSetResolver::for_product( new WC_Product( 1, 80.0 ) ) );
        OptionSetResolver::flush_cache();
        $this->assertCount( 0, OptionSetResolver::for_product( new WC_Product( 2, 20.0 ) ) );
    }

    public function test_conditions_match_all_requires_every_condition(): void {
        OptionSetFactory::register(
            1,
            array(
                'name'      => 'Both',
                'status'    => true,
                'targeting' => array(
                    'mode'       => 'conditions',
                    'match'      => 'all',
                    'conditions' => array(
                        array( 'type' => 'category', 'operator' => 'is', 'value' => '7' ),
                        array( 'type' => 'price', 'operator' => 'gt', 'value' => '50' ),
                    ),
                ),
                'fields'    => array( $this->text_field() ),
            )
        );

        // Category matches but price too low => excluded under match=all.
        OptionSetFactory::assign_terms( 300, 'product_cat', array( 7 ) );
        $this->assertCount( 0, OptionSetResolver::for_product( new WC_Product( 300, 20.0 ) ) );

        // Both satisfied => included.
        OptionSetResolver::flush_cache();
        OptionSetFactory::assign_terms( 301, 'product_cat', array( 7 ) );
        $this->assertCount( 1, OptionSetResolver::for_product( new WC_Product( 301, 90.0 ) ) );
    }
}
