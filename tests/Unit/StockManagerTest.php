<?php
namespace Flexa\Extra\Tests\Unit;

use Flexa\Extra\Cart\StockManager;
use Flexa\Extra\Tests\Support\OptionSetFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WC_Product;

/**
 * Per-option inventory: which selected options are stock-managed, and whether a
 * requested quantity (plus what the cart already reserves) oversells them.
 */
#[CoversClass( StockManager::class )]
final class StockManagerTest extends TestCase {

    protected function setUp(): void {
        OptionSetFactory::reset();
    }

    private function product(): WC_Product {
        return new WC_Product( 1, 50.0 );
    }

    private function register_sizes(): void {
        OptionSetFactory::register(
            7,
            array(
                'name'      => 'Size',
                'status'    => true,
                'targeting' => array( 'mode' => 'all' ),
                'fields'    => array(
                    array(
                        'type'    => 'radio',
                        'id'      => 'size',
                        'label'   => 'Size',
                        'options' => array(
                            OptionSetFactory::choice( 'small', 'Small', null, array( 'stock' => 3 ) ),
                            OptionSetFactory::choice( 'sold', 'Sold out', null, array( 'stock' => 0 ) ),
                            OptionSetFactory::choice( 'unlimited', 'Unlimited' ),
                        ),
                    ),
                ),
            )
        );
    }

    public function test_managed_options_only_returns_selected_stock_managed_options(): void {
        $this->register_sizes();

        $managed = StockManager::managed_options_for( $this->product(), array( 'size' => 'small' ) );

        $this->assertCount( 1, $managed );
        $this->assertSame( 'size', $managed[0]['field_id'] );
        $this->assertSame( 'small', $managed[0]['value'] );
        $this->assertSame( 3, $managed[0]['stock'] );
        $this->assertSame( 7, $managed[0]['post_id'] );
    }

    public function test_unlimited_option_is_never_managed(): void {
        $this->register_sizes();

        $managed = StockManager::managed_options_for( $this->product(), array( 'size' => 'unlimited' ) );

        $this->assertSame( array(), $managed );
    }

    public function test_out_of_stock_option_is_rejected(): void {
        $this->register_sizes();

        $errors = StockManager::shortages( $this->product(), array( 'size' => 'sold' ), 1 );

        $this->assertNotEmpty( $errors );
        $this->assertStringContainsString( 'out of stock', $errors[0] );
    }

    public function test_quantity_within_stock_passes(): void {
        $this->register_sizes();

        $this->assertSame(
            array(),
            StockManager::shortages( $this->product(), array( 'size' => 'small' ), 3 )
        );
    }

    public function test_quantity_over_stock_is_rejected(): void {
        $this->register_sizes();

        $errors = StockManager::shortages( $this->product(), array( 'size' => 'small' ), 4 );

        $this->assertNotEmpty( $errors );
        $this->assertStringContainsString( '3', $errors[0] );
    }

    public function test_existing_cart_reservation_counts_against_stock(): void {
        $this->register_sizes();

        // Two already reserved in the cart; only one more fits (stock is 3).
        $reserved = array( '7|size|small' => 2 );

        $this->assertSame(
            array(),
            StockManager::shortages( $this->product(), array( 'size' => 'small' ), 1, $reserved )
        );

        $errors = StockManager::shortages( $this->product(), array( 'size' => 'small' ), 2, $reserved );
        $this->assertNotEmpty( $errors );
    }
}
