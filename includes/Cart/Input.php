<?php
namespace Flexa\Extra\Cart;

defined( 'ABSPATH' ) || exit;

/**
 * Reads the raw `flexa_extra` payload off the add-to-cart request.
 *
 * Values are unslashed here; per-field sanitization happens in
 * {@see SelectionProcessor} against the field definitions.
 */
class Input {

    protected function __construct() {}

    /**
     * @return array<string,mixed>
     */
    public static function read(): array {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce add-to-cart is a form action without our nonce; values are sanitized per-field against the schema in SelectionProcessor.
        if ( ! isset( $_POST['flexa_extra'] ) || ! is_array( $_POST['flexa_extra'] ) ) {
            return array();
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized downstream per field type in SelectionProcessor::read_value().
        return (array) wp_unslash( $_POST['flexa_extra'] );
    }
}
