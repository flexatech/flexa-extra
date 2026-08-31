<?php
namespace Flexa\Extra\Cart;

defined( 'ABSPATH' ) || exit;

use Flexa\Extra\Register\ScriptName;
use Flexa\Extra\Utils\SingletonTrait;

/**
 * Captures extra-option selections onto the cart item, restores them from the
 * session, renders them in the cart/checkout, and persists them to the order.
 *
 * Only the raw (sanitized) selection and the base price are stored on the cart
 * item. The extra price and the human-readable lines are always recomputed from
 * the current field definitions via {@see SelectionProcessor} so a stale or
 * tampered value can never determine what the shopper pays.
 */
final class CartHandler {
    use SingletonTrait;

    private const KEY = 'flexa_extra';

    protected function __construct() {
        add_filter( 'woocommerce_add_cart_item_data', [ $this, 'add_cart_item_data' ], 10, 3 );
        add_filter( 'woocommerce_get_cart_item_from_session', [ $this, 'get_cart_item_from_session' ], 10, 2 );
        add_filter( 'woocommerce_get_item_data', [ $this, 'display_in_cart' ], 10, 2 );
        add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'add_order_line_item_meta' ], 10, 3 );
        add_action( 'wp_enqueue_scripts', [ $this, 'maybe_enqueue_cart_style' ] );
    }

    /**
     * The storefront stylesheet also carries the cart/checkout line-item styling,
     * so pull it in on those pages even though the option fields don't render there.
     *
     * The block cart (Store API) strips inline `style` from item meta but keeps
     * `class`, so swatch colours are delivered as per-colour classes backed by
     * inline CSS built here from the current cart contents.
     */
    public function maybe_enqueue_cart_style(): void {
        if ( ! function_exists( 'is_cart' ) ) {
            return;
        }

        $is_cart_context = is_cart() || is_checkout()
            || ( function_exists( 'has_block' ) && ( has_block( 'woocommerce/cart' ) || has_block( 'woocommerce/checkout' ) ) );

        if ( ! $is_cart_context ) {
            return;
        }

        wp_enqueue_style( ScriptName::STYLE_FRONTEND );

        $css = $this->swatch_inline_css();
        if ( '' !== $css ) {
            wp_add_inline_style( ScriptName::STYLE_FRONTEND, $css );
        }
    }

    /**
     * Per-swatch background rules for every colour/image currently in the cart,
     * keyed by the same class {@see swatch_class()} stamps on the chip so the
     * block cart (which drops inline styles) can still paint the swatch.
     */
    private function swatch_inline_css(): string {
        if ( ! function_exists( 'WC' ) || null === WC()->cart ) {
            return '';
        }

        $rules = [];
        foreach ( WC()->cart->get_cart() as $cart_item ) {
            if ( ! is_array( $cart_item ) ) {
                continue;
            }
            foreach ( $this->recompute_lines( $cart_item ) as $line ) {
                foreach ( $line['swatches'] as $swatch ) {
                    $class = $this->swatch_class( $swatch );
                    if ( '' === $class || isset( $rules[ $class ] ) ) {
                        continue;
                    }

                    if ( '' !== $swatch['image'] ) {
                        $rules[ $class ] = '.' . $class . '{background-image:url(' . esc_url( $swatch['image'] ) . ')}';
                    } else {
                        $color = sanitize_hex_color( $swatch['color'] );
                        if ( null !== $color ) {
                            $rules[ $class ] = '.' . $class . '{background-color:' . $color . '}';
                        }
                    }
                }
            }
        }

        return implode( "\n", $rules );
    }

    /**
     * @param array<string,mixed> $cart_item_data
     * @param int                 $product_id
     * @param int                 $variation_id
     * @return array<string,mixed>
     */
    public function add_cart_item_data( $cart_item_data, $product_id, $variation_id = 0 ): array {
        // Fields resolve against the (parent) product; the percent base is the priced entity.
        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return $cart_item_data;
        }

        $priced = $variation_id ? wc_get_product( $variation_id ) : $product;
        $base   = $priced ? (float) $priced->get_price() : (float) $product->get_price();

        $raw    = Input::read();
        $result = SelectionProcessor::process( $product, $raw, $base );

        if ( empty( $result['selections'] ) ) {
            return $cart_item_data;
        }

        $cart_item_data[ self::KEY ] = [
            'selections' => $result['selections'],
            'base'       => $base,
        ];

        // Distinct selections become distinct cart lines; identical ones stack.
        $cart_item_data['flexa_extra_hash'] = md5( wp_json_encode( $result['selections'] ) );

        return $cart_item_data;
    }

    /**
     * @param array<string,mixed> $cart_item
     * @param array<string,mixed> $values
     * @return array<string,mixed>
     */
    public function get_cart_item_from_session( $cart_item, $values ): array {
        if ( isset( $values[ self::KEY ] ) ) {
            $cart_item[ self::KEY ] = $values[ self::KEY ];
        }
        return $cart_item;
    }

    /**
     * @param list<array{key:string,value:string,display:string}> $item_data
     * @param array<string,mixed>                                 $cart_item
     * @return list<array{key:string,value:string,display:string}>
     */
    public function display_in_cart( $item_data, $cart_item ): array {
        $lines = $this->recompute_lines( $cart_item );

        foreach ( $lines as $line ) {
            $item_data[] = [
                'key'     => $line['label'],
                'value'   => $this->format_line_value( $line ),
                'display' => '',
            ];
        }

        return $item_data;
    }

    /**
     * @param \WC_Order_Item_Product $item
     * @param string                 $cart_item_key
     * @param array<string,mixed>    $values
     */
    public function add_order_line_item_meta( $item, $cart_item_key, $values ): void {
        unset( $cart_item_key );

        if ( empty( $values[ self::KEY ]['selections'] ) ) {
            return;
        }

        $lines = $this->recompute_lines( $values );

        foreach ( $lines as $line ) {
            $item->add_meta_data( $line['label'], $this->format_line_value( $line ), false );
        }

        // Hidden machine-readable copy for integrations.
        $item->add_meta_data( '_flexa_extra', $values[ self::KEY ]['selections'], true );
    }

    /**
     * Recompute display lines from the stored selections against current defs.
     *
     * @param array<string,mixed> $cart_item
     * @return list<array{field_id:string,label:string,type:string,display:string,amount:float,swatches:list<array{label:string,color:string,image:string}>}>
     */
    private function recompute_lines( array $cart_item ): array {
        if ( empty( $cart_item[ self::KEY ]['selections'] ) ) {
            return [];
        }

        $product = isset( $cart_item['data'] ) && $cart_item['data'] instanceof \WC_Product
            ? $cart_item['data']
            : wc_get_product( $cart_item['product_id'] ?? 0 );

        if ( ! $product ) {
            return [];
        }

        $base   = isset( $cart_item[ self::KEY ]['base'] ) ? (float) $cart_item[ self::KEY ]['base'] : null;
        $result = SelectionProcessor::process( $product, (array) $cart_item[ self::KEY ]['selections'], $base );

        return $result['lines'];
    }

    /**
     * Build the displayed value for one selection: an optional swatch chip, the
     * human-readable value, and a subtle surcharge pill.
     *
     * The markup is limited to tags/attributes that survive both `wp_kses_post`
     * (classic cart, order emails) and the block cart's client sanitiser, which
     * keeps `span`/`class`/`title` but drops inline `style`.
     *
     * @param array{display:string,amount:float,swatches:list<array{label:string,color:string,image:string}>} $line
     */
    private function format_line_value( array $line ): string {
        $chips = '';
        foreach ( $line['swatches'] as $swatch ) {
            $chips .= $this->render_swatch_chip( $swatch );
        }

        $value  = '<span class="flexa-extra-cart-item">';
        $value .= $chips;

        // An unlabelled colour swatch is fully conveyed by its chip, so don't
        // repeat the raw hex as text — the chip's title still exposes it.
        if ( ! $this->swatches_are_unlabelled( $line ) ) {
            $text   = '' !== $line['display'] ? wp_kses_post( $line['display'] ) : '&mdash;';
            $value .= '<span class="flexa-extra-cart-text">' . $text . '</span>';
        }

        if ( 0.0 !== $line['amount'] ) {
            $sign   = $line['amount'] < 0 ? '-' : '+';
            $value .= '<span class="flexa-extra-cart-fee">' . esc_html( $sign ) . wp_kses_post( wc_price( abs( $line['amount'] ) ) ) . '</span>';
        }

        $value .= '</span>';

        return $value;
    }

    /**
     * A small colour dot or image thumbnail for a selected swatch option.
     *
     * The colour is carried both as an inline `style` (for the classic cart and
     * order emails) and as a per-colour `class` (for the block cart, which
     * strips inline styles) — {@see swatch_inline_css()} backs the class.
     *
     * @param array{label:string,color:string,image:string} $swatch
     */
    private function render_swatch_chip( array $swatch ): string {
        $class = $this->swatch_class( $swatch );
        if ( '' === $class ) {
            return '';
        }

        if ( '' !== $swatch['image'] ) {
            $style = 'background-image:url(' . esc_url( $swatch['image'] ) . ')';
            $title = '' !== $swatch['label'] ? $swatch['label'] : $swatch['image'];
        } else {
            $style = 'background-color:' . $swatch['color'];
            $title = '' !== $swatch['label'] ? $swatch['label'] : $swatch['color'];
        }

        return '<span class="flexa-extra-cart-swatch ' . esc_attr( $class ) . '"'
            . ' style="' . esc_attr( $style ) . '"'
            . ' title="' . esc_attr( $title ) . '"></span>';
    }

    /**
     * Stable class for a swatch, derived from its colour/image so the chip and
     * the inline CSS rule agree.
     *
     * @param array{label:string,color:string,image:string} $swatch
     */
    private function swatch_class( array $swatch ): string {
        if ( '' !== $swatch['image'] ) {
            return 'fxsw-i-' . substr( md5( $swatch['image'] ), 0, 10 );
        }

        if ( '' !== $swatch['color'] ) {
            return 'fxsw-c-' . strtolower( ltrim( $swatch['color'], '#' ) );
        }

        return '';
    }

    /**
     * True when every swatch on the line is an unlabelled colour (its label is
     * the hex fallback), i.e. the chip alone should represent it.
     *
     * @param array{display:string,swatches:list<array{label:string,color:string,image:string}>} $line
     */
    private function swatches_are_unlabelled( array $line ): bool {
        if ( [] === $line['swatches'] ) {
            return false;
        }

        $labels = [];
        foreach ( $line['swatches'] as $swatch ) {
            if ( '' === $swatch['label'] || '#' !== $swatch['label'][0] ) {
                return false;
            }
            $labels[] = $swatch['label'];
        }

        return implode( ', ', $labels ) === $line['display'];
    }
}
