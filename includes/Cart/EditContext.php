<?php
namespace Flexa\Extra\Cart;

defined( 'ABSPATH' ) || exit;

/**
 * Shared helpers for "edit options in cart": detecting when the single-product
 * page is being viewed to change an existing cart line, reading that line's
 * stored selections for pre-fill, and building the edit link.
 *
 * The edit key always refers to a line in the *current shopper's own* cart
 * session, so viewing the pre-filled page needs no nonce (a shopper can only
 * ever reach their own selections). The replace itself removes a cart line, so
 * that POST is nonce-guarded in {@see CartHandler}.
 */
final class EditContext {

    /** Cart-item data key that carries our stored selections. */
    private const KEY = 'flexa_extra';

    /** Query var / form field carrying the cart-item key being edited. */
    public const QUERY = 'flexa_edit';

    /** Nonce action + field name for the replace POST. */
    public const NONCE_ACTION = 'flexa_edit';
    public const NONCE_FIELD  = 'flexa_edit_nonce';

    /**
     * The cart-item key this product page is editing, or '' when not in edit
     * mode. Validated against the live cart so a stale or forged key is ignored.
     *
     * phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only
     * pre-fill of the shopper's own cart line; no state change, so no nonce.
     */
    public static function editing_key(): string {
        if ( ! isset( $_GET[ self::QUERY ] ) ) {
            return '';
        }

        $key = sanitize_text_field( wp_unslash( (string) $_GET[ self::QUERY ] ) );
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        return self::has_line( $key ) ? $key : '';
    }

    /**
     * Stored selections for a cart-item key, or an empty array when the key is
     * unknown or carries no extra-option data.
     *
     * @return array<string,mixed>
     */
    public static function selections_for( string $key ): array {
        $item = self::cart_item( $key );
        if ( ! isset( $item[ self::KEY ]['selections'] ) ) {
            return array();
        }

        return (array) $item[ self::KEY ]['selections'];
    }

    /**
     * Permalink to the product page that edits the given cart line.
     */
    public static function edit_url( string $key, int $product_id ): string {
        return add_query_arg( self::QUERY, rawurlencode( $key ), get_permalink( $product_id ) );
    }

    /**
     * True when the cart holds a line under $key that carries our data.
     */
    private static function has_line( string $key ): bool {
        return isset( self::cart_item( $key )[ self::KEY ] );
    }

    /**
     * The raw cart-item array for a key, or an empty array when the cart or line
     * is absent (WooCommerce returns `array()` for an unknown key).
     *
     * @return array<string,mixed>
     */
    private static function cart_item( string $key ): array {
        if ( '' === $key || ! function_exists( 'WC' ) || null === WC()->cart ) {
            return array();
        }

        return (array) WC()->cart->get_cart_item( $key );
    }
}
