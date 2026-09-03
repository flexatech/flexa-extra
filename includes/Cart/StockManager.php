<?php
namespace Flexa\Extra\Cart;

defined( 'ABSPATH' ) || exit;

use Flexa\Extra\Fields\FieldType;
use Flexa\Extra\Frontend\OptionSetResolver;
use Flexa\Extra\Utils\SingletonTrait;
use WC_Order;
use WC_Product;

/**
 * Per-option inventory: validates stock on add-to-cart and moves the counters
 * when an order reduces or restores stock.
 *
 * A choice option is "managed" when its `stock` is an integer (>= 0); a null
 * stock means unlimited and is ignored here. The counters live inside the
 * option-set post meta (`_flexa_extra_fields`), so a purchase decrements the
 * same definition the builder edits.
 */
final class StockManager {
    use SingletonTrait;

    private const KEY           = 'flexa_extra';
    private const REDUCED_FLAG  = '_flexa_extra_stock_reduced';
    private const SELECTION_META = '_flexa_extra';
    private const META_FIELDS    = '_flexa_extra_fields';

    protected function __construct() {
        // Runs after Validator (priority 10) so format/required errors surface first.
        add_filter( 'woocommerce_add_to_cart_validation', [ $this, 'validate_add_to_cart' ], 20, 4 );
        add_action( 'woocommerce_reduce_order_stock', [ $this, 'reduce_for_order' ], 10, 1 );
        add_action( 'woocommerce_restore_order_stock', [ $this, 'restore_for_order' ], 10, 1 );
    }

    /**
     * Block an add-to-cart when a selected option would oversell its stock,
     * counting what the same option already reserves elsewhere in the cart.
     *
     * @param bool $passed
     * @param int  $product_id
     * @param int  $quantity
     * @param int  $variation_id
     */
    public function validate_add_to_cart( $passed, $product_id, $quantity, $variation_id = 0 ): bool {
        unset( $variation_id );

        if ( ! $passed ) {
            return false;
        }

        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return $passed;
        }

        $result     = SelectionProcessor::process( $product, Input::read() );
        $selections = $result['selections'];
        if ( empty( $selections ) ) {
            return $passed;
        }

        $reserved   = $this->cart_reservations();
        $shortages  = self::shortages( $product, $selections, (int) $quantity, $reserved );

        if ( empty( $shortages ) ) {
            return $passed;
        }

        foreach ( $shortages as $message ) {
            wc_add_notice( $message, 'error' );
        }

        return false;
    }

    /**
     * Every managed option touched by a set of selections, with its live stock.
     *
     * @param array<string,mixed> $selections Field-id keyed selected value(s).
     * @return list<array{post_id:int,field_id:string,option_id:string,value:string,label:string,stock:int}>
     */
    public static function managed_options_for( WC_Product $product, array $selections ): array {
        $managed = array();

        foreach ( OptionSetResolver::for_product( $product ) as $set ) {
            $post_id = (int) $set['id'];
            foreach ( $set['fields'] as $field ) {
                if ( ! is_array( $field ) || empty( $field['id'] ) || ! FieldType::is_choice( $field['type'] ?? '' ) ) {
                    continue;
                }
                $field_id = (string) $field['id'];
                if ( ! isset( $selections[ $field_id ] ) ) {
                    continue;
                }

                $chosen  = $selections[ $field_id ];
                $chosen  = is_array( $chosen ) ? array_map( 'strval', $chosen ) : array( (string) $chosen );
                $options = isset( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : array();

                foreach ( $options as $index => $option ) {
                    if ( ! is_array( $option ) ) {
                        continue;
                    }
                    $stock = self::option_stock( $option );
                    if ( null === $stock ) {
                        continue;
                    }
                    $value = self::option_value( $option, (string) $index );
                    if ( ! in_array( $value, $chosen, true ) ) {
                        continue;
                    }
                    $managed[] = array(
                        'post_id'   => $post_id,
                        'field_id'  => $field_id,
                        'option_id' => (string) ( $option['id'] ?? $index ),
                        'value'     => $value,
                        'label'     => '' !== ( (string) ( $option['label'] ?? '' ) ) ? (string) $option['label'] : $value,
                        'stock'     => $stock,
                    );
                }
            }
        }

        return $managed;
    }

    /**
     * Error strings for every managed option whose stock cannot cover the
     * requested quantity plus what is already reserved in the cart.
     *
     * @param array<string,mixed>          $selections
     * @param array<string,int>            $reserved   Units already reserved keyed by {@see reservation_key}.
     * @return list<string>
     */
    public static function shortages( WC_Product $product, array $selections, int $quantity, array $reserved = array() ): array {
        $quantity = max( 1, $quantity );
        $errors   = array();

        foreach ( self::managed_options_for( $product, $selections ) as $option ) {
            $key       = self::reservation_key( $option['post_id'], $option['field_id'], $option['value'] );
            $requested = $quantity + ( $reserved[ $key ] ?? 0 );

            if ( $requested <= $option['stock'] ) {
                continue;
            }

            if ( $option['stock'] <= 0 ) {
                /* translators: %s: option label. */
                $errors[] = sprintf( __( '"%s" is out of stock.', 'flexa-extra' ), $option['label'] );
            } else {
                $errors[] = sprintf(
                    /* translators: 1: option label, 2: remaining quantity. */
                    _n( 'Only %2$d of "%1$s" is left in stock.', 'Only %2$d of "%1$s" are left in stock.', $option['stock'], 'flexa-extra' ),
                    $option['label'],
                    $option['stock']
                );
            }
        }

        return $errors;
    }

    /**
     * Decrement option stock for a paid/processing order, once.
     *
     * @param WC_Order|mixed $order
     */
    public function reduce_for_order( $order ): void {
        if ( ! $order instanceof WC_Order || 'yes' === $order->get_meta( self::REDUCED_FLAG ) ) {
            return;
        }
        if ( $this->apply_delta_for_order( $order, -1 ) ) {
            $order->update_meta_data( self::REDUCED_FLAG, 'yes' );
            $order->save();
        }
    }

    /**
     * Give the stock back when an order's stock is restored (cancel/refund).
     *
     * @param WC_Order|mixed $order
     */
    public function restore_for_order( $order ): void {
        if ( ! $order instanceof WC_Order || 'yes' !== $order->get_meta( self::REDUCED_FLAG ) ) {
            return;
        }
        $this->apply_delta_for_order( $order, 1 );
        $order->delete_meta_data( self::REDUCED_FLAG );
        $order->save();
    }

    /**
     * Walk an order's line items and move each managed option's counter by
     * `$sign * line_quantity`.
     *
     * @param WC_Order $order
     * @return bool True when at least one counter changed.
     */
    private function apply_delta_for_order( WC_Order $order, int $sign ): bool {
        $changed = false;

        foreach ( $order->get_items() as $item ) {
            if ( ! $item instanceof \WC_Order_Item_Product ) {
                continue;
            }
            $selections = $item->get_meta( self::SELECTION_META, true );
            if ( ! is_array( $selections ) || empty( $selections ) ) {
                continue;
            }
            $product = $item->get_product();
            if ( ! $product instanceof WC_Product ) {
                continue;
            }
            $quantity = max( 1, (int) $item->get_quantity() );

            foreach ( self::managed_options_for( $product, $selections ) as $option ) {
                if ( $this->adjust_option_stock( $option['post_id'], $option['field_id'], $option['value'], $sign * $quantity ) ) {
                    $changed = true;
                }
            }
        }

        if ( $changed ) {
            OptionSetResolver::flush_cache();
        }

        return $changed;
    }

    /**
     * Read the current cart and total the units each managed option reserves,
     * keyed by {@see reservation_key}.
     *
     * @return array<string,int>
     */
    private function cart_reservations(): array {
        $reserved = array();

        if ( ! function_exists( 'WC' ) || null === WC()->cart ) {
            return $reserved;
        }

        foreach ( WC()->cart->get_cart() as $cart_item ) {
            if ( ! is_array( $cart_item ) || empty( $cart_item[ self::KEY ]['selections'] ) ) {
                continue;
            }
            $product = $cart_item['data'] ?? null;
            if ( ! $product instanceof WC_Product ) {
                continue;
            }
            $quantity = max( 1, (int) ( $cart_item['quantity'] ?? 1 ) );

            foreach ( self::managed_options_for( $product, (array) $cart_item[ self::KEY ]['selections'] ) as $option ) {
                $key              = self::reservation_key( $option['post_id'], $option['field_id'], $option['value'] );
                $reserved[ $key ] = ( $reserved[ $key ] ?? 0 ) + $quantity;
            }
        }

        return $reserved;
    }

    /**
     * Move a single option's stored stock by `$delta`, clamped at zero.
     *
     * @return bool True when the meta was written.
     */
    private function adjust_option_stock( int $post_id, string $field_id, string $value, int $delta ): bool {
        if ( 0 === $delta ) {
            return false;
        }

        $fields = get_post_meta( $post_id, self::META_FIELDS, true );
        if ( ! is_array( $fields ) ) {
            return false;
        }

        $mutated = false;
        foreach ( $fields as $f_index => $field ) {
            if ( ! is_array( $field ) || (string) ( $field['id'] ?? '' ) !== $field_id ) {
                continue;
            }
            $options = isset( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : array();
            foreach ( $options as $o_index => $option ) {
                if ( ! is_array( $option ) || null === self::option_stock( $option ) ) {
                    continue;
                }
                if ( self::option_value( $option, (string) $o_index ) !== $value ) {
                    continue;
                }
                $current = (int) $option['stock'];
                $next    = max( 0, $current + $delta );
                if ( $next === $current ) {
                    continue;
                }
                $fields[ $f_index ]['options'][ $o_index ]['stock'] = $next;
                $mutated = true;
            }
        }

        if ( $mutated ) {
            update_post_meta( $post_id, self::META_FIELDS, $fields );
        }

        return $mutated;
    }

    /**
     * @param array<string,mixed> $option
     */
    private static function option_stock( array $option ): ?int {
        return isset( $option['stock'] ) && is_numeric( $option['stock'] )
            ? max( 0, (int) $option['stock'] )
            : null;
    }

    /**
     * @param array<string,mixed> $option
     */
    private static function option_value( array $option, string $fallback ): string {
        $value = isset( $option['value'] ) ? (string) $option['value'] : '';
        if ( '' !== $value ) {
            return $value;
        }
        $id = isset( $option['id'] ) ? (string) $option['id'] : '';
        return '' !== $id ? $id : $fallback;
    }

    private static function reservation_key( int $post_id, string $field_id, string $value ): string {
        return $post_id . '|' . $field_id . '|' . $value;
    }
}
