<?php
namespace Flexa\Extra\Cart;

defined( 'ABSPATH' ) || exit;

use Flexa\Extra\Fields\FieldType;
use Flexa\Extra\Fields\OptionSetSchema;
use Flexa\Extra\Frontend\OptionSetResolver;
use WC_Product;

/**
 * Authoritative server-side engine for extra-option selections.
 *
 * Given a product and the raw `flexa_extra[<field_id>]` POST payload it
 * resolves the applicable option sets, mirrors the storefront conditional
 * logic, validates every visible field, and recomputes the extra price from
 * the stored field definitions. The client price is never trusted — the
 * validator, cart handler and price calculator all route through here.
 */
class SelectionProcessor {

    protected function __construct() {}

    /**
     * @param array<string,mixed> $raw   Raw POST values keyed by field id.
     * @param float|null          $base  Base price to compute percentages against (defaults to product price).
     *
     * @return array{
     *     selections:array<string,mixed>,
     *     lines:list<array{field_id:string,label:string,type:string,display:string,amount:float,swatches:list<array{label:string,color:string,image:string}>}>,
     *     total:float,
     *     errors:list<string>
     * }
     */
    public static function process( WC_Product $product, array $raw, ?float $base = null ): array {
        $base   = null === $base ? (float) $product->get_price() : $base;
        $fields = self::applicable_fields( $product );

        // 1. Snapshot sanitized raw values for logic evaluation.
        $values = array();
        foreach ( $fields as $field ) {
            $values[ $field['id'] ] = self::read_value( $field, $raw );
        }

        $selections = array();
        $lines      = array();
        $errors     = array();
        $total      = 0.0;

        foreach ( $fields as $field ) {
            $id   = $field['id'];
            $type = $field['type'];

            if ( ! self::is_visible( $field, $values ) ) {
                continue;
            }

            $value = $values[ $id ];

            // Validation.
            $field_errors = self::validate_field( $field, $value );
            if ( ! empty( $field_errors ) ) {
                $errors = array_merge( $errors, $field_errors );
            }

            if ( ! self::has_value( $value ) ) {
                continue;
            }

            $selections[ $id ] = $value;

            $amount   = 0.0;
            $display  = '';
            $swatches = array();

            if ( FieldType::is_choice( $type ) ) {
                $selected = is_array( $value ) ? $value : array( $value );
                $labels   = array();
                foreach ( $selected as $selected_value ) {
                    $option = self::find_option( $field, (string) $selected_value );
                    if ( null === $option ) {
                        continue;
                    }

                    // Prefer the human label; fall back to the swatch colour (as a
                    // readable hex) before ever exposing the raw option value/id.
                    if ( '' !== $option['label'] ) {
                        $label = $option['label'];
                    } elseif ( '' !== $option['color'] ) {
                        $label = strtoupper( $option['color'] );
                    } else {
                        $label = (string) $selected_value;
                    }

                    $labels[] = $label;

                    if ( '' !== $option['color'] || '' !== $option['image'] ) {
                        $swatches[] = array(
                            'label' => $label,
                            'color' => $option['color'],
                            'image' => $option['image'],
                        );
                    }

                    $amount += self::price_amount( $option['price'], $base );
                }
                $display = implode( ', ', $labels );
            } else {
                $display = is_array( $value ) ? implode( ', ', $value ) : (string) $value;
                if ( isset( $field['price'] ) && is_array( $field['price'] ) ) {
                    $amount += self::price_amount( $field['price'], $base );
                }
            }

            $total += $amount;

            $lines[] = array(
                'field_id' => $id,
                'label'    => '' !== $field['label'] ? $field['label'] : $id,
                'type'     => $type,
                'display'  => $display,
                'amount'   => $amount,
                'swatches' => $swatches,
            );
        }

        // Set-level fee / discount actions, evaluated against the same value
        // snapshot the conditional-logic engine uses.
        foreach ( self::applicable_actions( $product ) as $action ) {
            if ( ! self::action_applies( $action, $values ) ) {
                continue;
            }

            $price     = isset( $action['price'] ) && is_array( $action['price'] ) ? $action['price'] : array();
            $magnitude = abs( self::price_amount( $price, $base ) );
            if ( 0.0 === $magnitude ) {
                continue;
            }

            $is_discount = OptionSetSchema::ACTION_DISCOUNT === ( $action['kind'] ?? OptionSetSchema::ACTION_FEE );
            $amount      = $is_discount ? -$magnitude : $magnitude;
            $label       = '' !== (string) ( $action['label'] ?? '' )
                ? (string) $action['label']
                : ( $is_discount ? __( 'Discount', 'flexa-extra' ) : __( 'Fee', 'flexa-extra' ) );

            $total += $amount;

            $lines[] = array(
                'field_id' => 'action:' . (string) ( $action['id'] ?? '' ),
                'label'    => $label,
                'type'     => 'action',
                'display'  => '',
                'amount'   => $amount,
                'swatches' => array(),
            );
        }

        return array(
            'selections' => $selections,
            'lines'      => $lines,
            'total'      => $total,
            'errors'     => $errors,
        );
    }

    /**
     * Flat list of every field across the product's applicable option sets.
     *
     * @return list<array<string,mixed>>
     */
    private static function applicable_fields( WC_Product $product ): array {
        $fields = array();
        foreach ( OptionSetResolver::for_product( $product ) as $set ) {
            foreach ( $set['fields'] as $field ) {
                if ( is_array( $field ) && isset( $field['id'], $field['type'] ) ) {
                    $fields[] = $field;
                }
            }
        }
        return $fields;
    }

    /**
     * Flat list of every set-level action across the product's option sets.
     *
     * @return list<array<string,mixed>>
     */
    private static function applicable_actions( WC_Product $product ): array {
        $actions = array();
        foreach ( OptionSetResolver::for_product( $product ) as $set ) {
            foreach ( $set['actions'] as $action ) {
                if ( is_array( $action ) && isset( $action['id'] ) ) {
                    $actions[] = $action;
                }
            }
        }
        return $actions;
    }

    /**
     * True when an action's condition rules match. Empty rules always apply.
     *
     * @param array<string,mixed> $action
     * @param array<string,mixed> $values
     */
    private static function action_applies( array $action, array $values ): bool {
        $rules = isset( $action['rules'] ) && is_array( $action['rules'] ) ? $action['rules'] : array();
        if ( empty( $rules ) ) {
            return true;
        }

        $results = array();
        foreach ( $rules as $rule ) {
            if ( is_array( $rule ) ) {
                $results[] = self::rule_passes( $rule, $values );
            }
        }

        $match_all = isset( $action['match'] ) && 'all' === $action['match'];
        return $match_all ? ! in_array( false, $results, true ) : in_array( true, $results, true );
    }

    /**
     * @param array<string,mixed> $field
     * @param array<string,mixed> $raw
     * @return string|list<string>
     */
    private static function read_value( array $field, array $raw ) {
        $id  = $field['id'];
        $val = $raw[ $id ] ?? '';

        if ( is_array( $val ) ) {
            return array_values( array_map( 'sanitize_text_field', array_map( 'strval', $val ) ) );
        }

        $val = sanitize_text_field( (string) $val );

        // Multi-select choice fields always resolve to a list.
        if ( FieldType::is_choice( $field['type'] ) && ! empty( $field['multiple'] ) ) {
            return '' === $val ? array() : array( $val );
        }

        return $val;
    }

    /**
     * @param array<string,mixed>   $field
     * @param string|list<string>   $value
     * @return list<string>
     */
    private static function validate_field( array $field, $value ): array {
        $errors = array();
        $label  = '' !== $field['label'] ? $field['label'] : $field['id'];

        if ( ! empty( $field['required'] ) && ! self::has_value( $value ) ) {
            /* translators: %s: field label. */
            $errors[] = sprintf( __( '"%s" is required.', 'flexa-extra' ), $label );
            return $errors;
        }

        if ( ! self::has_value( $value ) ) {
            return $errors;
        }

        // Min / max number of options for a multi-select choice field. Enforced
        // only once at least one option is chosen (an untouched optional field
        // returned above); a required field's emptiness is caught by the guard.
        if ( FieldType::is_choice( $field['type'] ) && ! empty( $field['multiple'] ) ) {
            $count = is_array( $value ) ? count( $value ) : 1;
            $min   = self::count_constraint( $field, 'minSelect' );
            $max   = self::count_constraint( $field, 'maxSelect' );

            if ( null !== $min && $count < $min ) {
                $errors[] = sprintf(
                    /* translators: 1: field label, 2: minimum number of options. */
                    _n( 'Please choose at least %2$d option for "%1$s".', 'Please choose at least %2$d options for "%1$s".', $min, 'flexa-extra' ),
                    $label,
                    $min
                );
            }
            if ( null !== $max && $count > $max ) {
                $errors[] = sprintf(
                    /* translators: 1: field label, 2: maximum number of options. */
                    _n( 'Please choose at most %2$d option for "%1$s".', 'Please choose at most %2$d options for "%1$s".', $max, 'flexa-extra' ),
                    $label,
                    $max
                );
            }
        }

        if ( FieldType::TEXT === $field['type'] && is_string( $value ) ) {
            $format = isset( $field['textFormat'] ) ? (string) $field['textFormat'] : OptionSetSchema::TEXT_PLAIN;

            if ( OptionSetSchema::TEXT_EMAIL === $format && ! is_email( $value ) ) {
                /* translators: %s: field label. */
                $errors[] = sprintf( __( '"%s" must be a valid email address.', 'flexa-extra' ), $label );
            } elseif ( OptionSetSchema::TEXT_URL === $format && ! wc_is_valid_url( $value ) ) {
                /* translators: %s: field label. */
                $errors[] = sprintf( __( '"%s" must be a valid URL.', 'flexa-extra' ), $label );
            } elseif ( OptionSetSchema::TEXT_REGEX === $format && ! self::regex_matches( isset( $field['regex'] ) ? (string) $field['regex'] : '', $value ) ) {
                /* translators: %s: field label. */
                $errors[] = sprintf( __( '"%s" is not in the expected format.', 'flexa-extra' ), $label );
            }
        }

        if ( FieldType::NUMBER === $field['type'] && is_string( $value ) && is_numeric( $value ) ) {
            $number = (float) $value;
            if ( isset( $field['min'] ) && $number < (float) $field['min'] ) {
                /* translators: 1: field label, 2: minimum value. */
                $errors[] = sprintf( __( '"%1$s" must be at least %2$s.', 'flexa-extra' ), $label, $field['min'] );
            }
            if ( isset( $field['max'] ) && $number > (float) $field['max'] ) {
                /* translators: 1: field label, 2: maximum value. */
                $errors[] = sprintf( __( '"%1$s" must be at most %2$s.', 'flexa-extra' ), $label, $field['max'] );
            }
        }

        return $errors;
    }

    /**
     * A selection-count bound from the field, or null when unset.
     *
     * @param array<string,mixed> $field
     */
    private static function count_constraint( array $field, string $key ): ?int {
        return isset( $field[ $key ] ) && is_numeric( $field[ $key ] ) ? max( 0, (int) $field[ $key ] ) : null;
    }

    private static function regex_matches( string $pattern, string $value ): bool {
        if ( '' === $pattern ) {
            return true;
        }
        $delimited = '/' . str_replace( '/', '\\/', $pattern ) . '/';
        $result    = @preg_match( $delimited, $value ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- User-supplied pattern may be invalid; treat that as non-blocking.
        // An invalid pattern (false) is treated as a pass so a bad admin regex never blocks checkout.
        return false === $result ? true : 1 === $result;
    }

    /**
     * Server-side mirror of the storefront conditional-logic engine.
     *
     * @param array<string,mixed> $field
     * @param array<string,mixed> $values
     */
    private static function is_visible( array $field, array $values ): bool {
        $logic = isset( $field['logic'] ) && is_array( $field['logic'] ) ? $field['logic'] : array();

        if ( empty( $logic['enabled'] ) || empty( $logic['rules'] ) || ! is_array( $logic['rules'] ) ) {
            return true;
        }

        $results = array();
        foreach ( $logic['rules'] as $rule ) {
            if ( is_array( $rule ) ) {
                $results[] = self::rule_passes( $rule, $values );
            }
        }

        $match_all = isset( $logic['match'] ) && 'all' === $logic['match'];
        $combined  = $match_all ? ! in_array( false, $results, true ) : in_array( true, $results, true );

        return isset( $logic['action'] ) && 'hide' === $logic['action'] ? ! $combined : $combined;
    }

    /**
     * @param array<string,mixed> $rule
     * @param array<string,mixed> $values
     */
    private static function rule_passes( array $rule, array $values ): bool {
        $target   = $values[ $rule['field'] ] ?? '';
        $operator = isset( $rule['operator'] ) ? (string) $rule['operator'] : 'is';
        $expected = isset( $rule['value'] ) ? (string) $rule['value'] : '';

        switch ( $operator ) {
            case 'empty':
                return ! self::has_value( $target );
            case 'not_empty':
                return self::has_value( $target );
            case 'is_not':
                return ! self::value_matches( $target, $expected );
            case 'is':
            default:
                return self::value_matches( $target, $expected );
        }
    }

    /**
     * @param string|list<string> $value
     */
    private static function has_value( $value ): bool {
        if ( is_array( $value ) ) {
            return ! empty( $value );
        }
        return '' !== $value;
    }

    /**
     * @param string|list<string> $value
     */
    private static function value_matches( $value, string $target ): bool {
        if ( is_array( $value ) ) {
            return in_array( $target, $value, true );
        }
        return (string) $value === $target;
    }

    /**
     * @param array<string,mixed> $field
     * @return array{id:string,label:string,value:string,color:string,image:string,price:array{type:string,amount:float}}|null
     */
    private static function find_option( array $field, string $selected_value ): ?array {
        $options = isset( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : array();
        foreach ( $options as $index => $option ) {
            if ( ! is_array( $option ) ) {
                continue;
            }
            $option_value = isset( $option['value'] ) && '' !== $option['value']
                ? (string) $option['value']
                : (string) ( $option['id'] ?? $index );

            if ( $option_value === $selected_value ) {
                return array(
                    'id'    => (string) ( $option['id'] ?? $index ),
                    'label' => isset( $option['label'] ) ? (string) $option['label'] : '',
                    'value' => $option_value,
                    'color' => isset( $option['color'] ) ? (string) $option['color'] : '',
                    'image' => isset( $option['image'] ) ? (string) $option['image'] : '',
                    'price' => isset( $option['price'] ) && is_array( $option['price'] ) ? $option['price'] : array(),
                );
            }
        }
        return null;
    }

    /**
     * @param array<string,mixed> $price
     */
    private static function price_amount( array $price, float $base ): float {
        $type   = isset( $price['type'] ) ? (string) $price['type'] : OptionSetSchema::PRICE_NONE;
        $amount = isset( $price['amount'] ) ? (float) $price['amount'] : 0.0;

        if ( OptionSetSchema::PRICE_NONE === $type || 0.0 === $amount ) {
            return 0.0;
        }

        if ( OptionSetSchema::PRICE_PERCENT === $type ) {
            return ( $base * $amount ) / 100;
        }

        return $amount;
    }
}
