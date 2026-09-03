<?php
namespace Flexa\Extra\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Sanitizer + shape definition for an option set payload.
 *
 * The REST controller delegates every write to `sanitize()` so a malformed or
 * hostile payload can never reach post meta. Prices, choices, conditional
 * logic and product targeting are all normalized here against the field
 * registry in {@see FieldType}. This is the single server-side contract; the
 * admin app mirrors it in `src/lib/schema/option-set.ts` (zod).
 */
class OptionSetSchema {

    const PRICE_NONE    = 'none';
    const PRICE_FIXED   = 'fixed';
    const PRICE_PERCENT = 'percent';

    const TEXT_PLAIN = 'text';
    const TEXT_EMAIL = 'email';
    const TEXT_URL   = 'url';
    const TEXT_REGEX = 'regex';

    const TARGET_ALL        = 'all';
    const TARGET_MANUAL     = 'manual';
    const TARGET_CONDITIONS = 'conditions';

    const ACTION_FEE      = 'fee';
    const ACTION_DISCOUNT = 'discount';

    protected function __construct() {}

    /**
     * Normalize a full option-set body.
     *
     * @param array<string,mixed> $input
     * @return array{name:string,status:bool,fields:list<array<string,mixed>>,targeting:array<string,mixed>,actions:list<array<string,mixed>>}
     */
    public static function sanitize( array $input ): array {
        $name = isset( $input['name'] ) ? sanitize_text_field( (string) $input['name'] ) : '';
        if ( '' === $name ) {
            $name = __( 'Untitled Option Set', 'flexa-extra' );
        }

        $raw_fields = isset( $input['fields'] ) && is_array( $input['fields'] ) ? $input['fields'] : array();
        $fields     = array();
        foreach ( $raw_fields as $raw_field ) {
            if ( is_array( $raw_field ) ) {
                $field = self::sanitize_field( $raw_field );
                if ( null !== $field ) {
                    $fields[] = $field;
                }
            }
        }

        $targeting = self::sanitize_targeting(
            isset( $input['targeting'] ) && is_array( $input['targeting'] ) ? $input['targeting'] : array()
        );

        $raw_actions = isset( $input['actions'] ) && is_array( $input['actions'] ) ? $input['actions'] : array();
        $actions     = array();
        foreach ( $raw_actions as $raw_action ) {
            if ( is_array( $raw_action ) ) {
                $actions[] = self::sanitize_action( $raw_action );
            }
        }

        $result = array(
            'name'      => $name,
            'status'    => isset( $input['status'] ) ? (bool) rest_sanitize_boolean( $input['status'] ) : false,
            'fields'    => $fields,
            'targeting' => $targeting,
            'actions'   => $actions,
        );

        return apply_filters( 'flexa_extra/option_set/sanitize', $result, $input );
    }

    /**
     * @param array<string,mixed> $raw
     * @return array<string,mixed>|null Null when the field type is unknown.
     */
    private static function sanitize_field( array $raw ): ?array {
        $type = isset( $raw['type'] ) ? sanitize_key( (string) $raw['type'] ) : '';
        if ( ! FieldType::is_valid( $type ) ) {
            return null;
        }

        $field = array(
            'id'          => self::sanitize_id( isset( $raw['id'] ) ? (string) $raw['id'] : '' ),
            'type'        => $type,
            'label'       => isset( $raw['label'] ) ? sanitize_text_field( (string) $raw['label'] ) : '',
            'name'        => self::sanitize_field_name( isset( $raw['name'] ) ? (string) $raw['name'] : '' ),
            'required'    => isset( $raw['required'] ) ? (bool) rest_sanitize_boolean( $raw['required'] ) : false,
            'placeholder' => isset( $raw['placeholder'] ) ? sanitize_text_field( (string) $raw['placeholder'] ) : '',
            'tooltip'     => isset( $raw['tooltip'] ) ? sanitize_text_field( (string) $raw['tooltip'] ) : '',
            'default'     => isset( $raw['default'] ) ? sanitize_text_field( (string) $raw['default'] ) : '',
            'logic'       => self::sanitize_logic( isset( $raw['logic'] ) && is_array( $raw['logic'] ) ? $raw['logic'] : array() ),
        );

        if ( FieldType::TEXT === $type ) {
            $format             = isset( $raw['textFormat'] ) ? sanitize_key( (string) $raw['textFormat'] ) : self::TEXT_PLAIN;
            $field['textFormat'] = in_array( $format, array( self::TEXT_PLAIN, self::TEXT_EMAIL, self::TEXT_URL, self::TEXT_REGEX ), true ) ? $format : self::TEXT_PLAIN;
            $field['regex']      = isset( $raw['regex'] ) ? sanitize_text_field( (string) $raw['regex'] ) : '';
        }

        if ( FieldType::NUMBER === $type ) {
            $field['min']  = self::sanitize_nullable_number( $raw['min'] ?? null );
            $field['max']  = self::sanitize_nullable_number( $raw['max'] ?? null );
            $field['step'] = self::sanitize_nullable_number( $raw['step'] ?? null );
        }

        // The default value carries the field's own format for the native inputs.
        if ( FieldType::COLOR_PICKER === $type ) {
            $field['default'] = isset( $raw['default'] ) ? ( sanitize_hex_color( (string) $raw['default'] ) ?? '' ) : '';
        }

        if ( FieldType::DATE_PICKER === $type ) {
            $field['default'] = self::sanitize_date( isset( $raw['default'] ) ? (string) $raw['default'] : '' );
        }

        // Input fields (text/textarea/number) can carry a flat price on the field itself.
        if ( FieldType::is_input( $type ) ) {
            $field['price'] = self::sanitize_price( isset( $raw['price'] ) && is_array( $raw['price'] ) ? $raw['price'] : array() );
        }

        // Choice fields carry a list of selectable options, each with its own price/stock/swatch.
        if ( FieldType::is_choice( $type ) ) {
            $field['multiple'] = FieldType::CHECKBOX === $type
                ? true
                : ( isset( $raw['multiple'] ) ? (bool) rest_sanitize_boolean( $raw['multiple'] ) : false );

            // How many options a shopper must / may pick. Null = no bound. Only
            // meaningful for multi-select; enforced server-side in SelectionProcessor.
            $field['minSelect'] = self::sanitize_count( $raw['minSelect'] ?? null );
            $field['maxSelect'] = self::sanitize_count( $raw['maxSelect'] ?? null );

            $raw_options       = isset( $raw['options'] ) && is_array( $raw['options'] ) ? $raw['options'] : array();
            $field['options']  = array();
            foreach ( $raw_options as $raw_option ) {
                if ( is_array( $raw_option ) ) {
                    $field['options'][] = self::sanitize_choice( $raw_option );
                }
            }
        }

        return $field;
    }

    /**
     * @param array<string,mixed> $raw
     * @return array<string,mixed>
     */
    private static function sanitize_choice( array $raw ): array {
        return array(
            'id'      => self::sanitize_id( isset( $raw['id'] ) ? (string) $raw['id'] : '' ),
            'label'   => isset( $raw['label'] ) ? sanitize_text_field( (string) $raw['label'] ) : '',
            'value'   => isset( $raw['value'] ) ? sanitize_text_field( (string) $raw['value'] ) : '',
            'default' => isset( $raw['default'] ) ? (bool) rest_sanitize_boolean( $raw['default'] ) : false,
            'tooltip' => isset( $raw['tooltip'] ) ? sanitize_text_field( (string) $raw['tooltip'] ) : '',
            'color'   => isset( $raw['color'] ) ? sanitize_hex_color( (string) $raw['color'] ) ?? '' : '',
            'image'   => isset( $raw['image'] ) ? esc_url_raw( (string) $raw['image'] ) : '',
            'price'   => self::sanitize_price( isset( $raw['price'] ) && is_array( $raw['price'] ) ? $raw['price'] : array() ),
            'stock'   => self::sanitize_stock( $raw['stock'] ?? null ),
        );
    }

    /**
     * Per-option inventory. Null means the option is not stock-managed (unlimited);
     * a non-negative integer is the remaining quantity.
     *
     * @param mixed $value
     */
    private static function sanitize_stock( $value ): ?int {
        if ( null === $value || '' === $value || ! is_numeric( $value ) ) {
            return null;
        }
        return max( 0, (int) $value );
    }

    /**
     * A non-negative selection bound (min/max chosen options). Null = no bound.
     *
     * @param mixed $value
     */
    private static function sanitize_count( $value ): ?int {
        if ( null === $value || '' === $value || ! is_numeric( $value ) ) {
            return null;
        }
        return max( 0, (int) $value );
    }

    /**
     * @param array<string,mixed> $raw
     * @return array{type:string,amount:float}
     */
    private static function sanitize_price( array $raw ): array {
        $type = isset( $raw['type'] ) ? sanitize_key( (string) $raw['type'] ) : self::PRICE_NONE;
        if ( ! in_array( $type, array( self::PRICE_NONE, self::PRICE_FIXED, self::PRICE_PERCENT ), true ) ) {
            $type = self::PRICE_NONE;
        }

        $amount = isset( $raw['amount'] ) ? (float) $raw['amount'] : 0.0;

        return array(
            'type'   => $type,
            'amount' => self::PRICE_NONE === $type ? 0.0 : $amount,
        );
    }

    /**
     * @param array<string,mixed> $raw
     * @return array{enabled:bool,action:string,match:string,rules:list<array<string,string>>}
     */
    private static function sanitize_logic( array $raw ): array {
        $action = isset( $raw['action'] ) && 'hide' === $raw['action'] ? 'hide' : 'show';
        $match  = isset( $raw['match'] ) && 'all' === $raw['match'] ? 'all' : 'any';

        return array(
            'enabled' => isset( $raw['enabled'] ) ? (bool) rest_sanitize_boolean( $raw['enabled'] ) : false,
            'action'  => $action,
            'match'   => $match,
            'rules'   => self::sanitize_rules( isset( $raw['rules'] ) && is_array( $raw['rules'] ) ? $raw['rules'] : array() ),
        );
    }

    /**
     * Normalize a list of condition rules shared by conditional logic and actions.
     *
     * @param array<int,mixed> $raw_rules
     * @return list<array{field:string,operator:string,value:string}>
     */
    private static function sanitize_rules( array $raw_rules ): array {
        $rules = array();
        foreach ( $raw_rules as $raw_rule ) {
            if ( ! is_array( $raw_rule ) ) {
                continue;
            }
            $operator = isset( $raw_rule['operator'] ) ? sanitize_key( (string) $raw_rule['operator'] ) : 'is';
            if ( ! in_array( $operator, array( 'is', 'is_not', 'empty', 'not_empty' ), true ) ) {
                $operator = 'is';
            }
            $rules[] = array(
                'field'    => self::sanitize_id( isset( $raw_rule['field'] ) ? (string) $raw_rule['field'] : '' ),
                'operator' => $operator,
                'value'    => isset( $raw_rule['value'] ) ? sanitize_text_field( (string) $raw_rule['value'] ) : '',
            );
        }
        return $rules;
    }

    /**
     * A set-level fee or discount applied when its condition rules match. Empty
     * rules mean the action always applies.
     *
     * @param array<string,mixed> $raw
     * @return array{id:string,label:string,kind:string,price:array{type:string,amount:float},match:string,rules:list<array{field:string,operator:string,value:string}>}
     */
    private static function sanitize_action( array $raw ): array {
        $kind  = isset( $raw['kind'] ) && self::ACTION_DISCOUNT === $raw['kind'] ? self::ACTION_DISCOUNT : self::ACTION_FEE;
        $match = isset( $raw['match'] ) && 'all' === $raw['match'] ? 'all' : 'any';

        return array(
            'id'    => self::sanitize_id( isset( $raw['id'] ) ? (string) $raw['id'] : '' ),
            'label' => isset( $raw['label'] ) ? sanitize_text_field( (string) $raw['label'] ) : '',
            'kind'  => $kind,
            'price' => self::sanitize_price( isset( $raw['price'] ) && is_array( $raw['price'] ) ? $raw['price'] : array() ),
            'match' => $match,
            'rules' => self::sanitize_rules( isset( $raw['rules'] ) && is_array( $raw['rules'] ) ? $raw['rules'] : array() ),
        );
    }

    /**
     * @param array<string,mixed> $raw
     * @return array<string,mixed>
     */
    private static function sanitize_targeting( array $raw ): array {
        $mode = isset( $raw['mode'] ) ? sanitize_key( (string) $raw['mode'] ) : self::TARGET_ALL;
        if ( ! in_array( $mode, array( self::TARGET_ALL, self::TARGET_MANUAL, self::TARGET_CONDITIONS ), true ) ) {
            $mode = self::TARGET_ALL;
        }

        $product_ids = array();
        if ( isset( $raw['productIds'] ) && is_array( $raw['productIds'] ) ) {
            $product_ids = array_values( array_filter( array_map( 'absint', $raw['productIds'] ) ) );
        }

        $match      = isset( $raw['match'] ) && 'all' === $raw['match'] ? 'all' : 'any';
        $conditions = array();
        $raw_conds  = isset( $raw['conditions'] ) && is_array( $raw['conditions'] ) ? $raw['conditions'] : array();
        foreach ( $raw_conds as $raw_cond ) {
            if ( is_array( $raw_cond ) ) {
                $conditions[] = self::sanitize_condition( $raw_cond );
            }
        }

        return array(
            'mode'       => $mode,
            'productIds' => $product_ids,
            'match'      => $match,
            'conditions' => $conditions,
        );
    }

    /**
     * @param array<string,mixed> $raw
     * @return array{type:string,operator:string,value:string}
     */
    private static function sanitize_condition( array $raw ): array {
        $type = isset( $raw['type'] ) ? sanitize_key( (string) $raw['type'] ) : 'category';
        if ( ! in_array( $type, array( 'category', 'tag', 'price', 'stock', 'product' ), true ) ) {
            $type = 'category';
        }

        $operator = isset( $raw['operator'] ) ? sanitize_key( (string) $raw['operator'] ) : 'is';
        if ( ! in_array( $operator, array( 'is', 'is_not', 'gt', 'lt', 'in_stock', 'out_of_stock' ), true ) ) {
            $operator = 'is';
        }

        return array(
            'type'     => $type,
            'operator' => $operator,
            'value'    => isset( $raw['value'] ) ? sanitize_text_field( (string) $raw['value'] ) : '',
        );
    }

    private static function sanitize_id( string $id ): string {
        $id = preg_replace( '/[^a-zA-Z0-9_\-]/', '', $id );
        return is_string( $id ) ? $id : '';
    }

    private static function sanitize_field_name( string $name ): string {
        $name = sanitize_title( $name );
        return str_replace( '-', '_', $name );
    }

    /**
     * @param mixed $value
     */
    private static function sanitize_nullable_number( $value ): ?float {
        if ( null === $value || '' === $value ) {
            return null;
        }
        return is_numeric( $value ) ? (float) $value : null;
    }

    /**
     * Accepts an ISO date (YYYY-MM-DD) as the native date input emits it; anything
     * else collapses to an empty default.
     */
    private static function sanitize_date( string $value ): string {
        $value = trim( $value );
        if ( '' === $value || 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
            return '';
        }
        $parts = array_map( 'intval', explode( '-', $value ) );
        return checkdate( $parts[1], $parts[2], $parts[0] ) ? $value : '';
    }
}
