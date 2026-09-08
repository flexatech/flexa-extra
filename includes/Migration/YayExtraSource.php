<?php
namespace Flexa\Extra\Migration;

defined( 'ABSPATH' ) || exit;

/**
 * Imports option sets from YayExtra (YayCommerce). YayExtra stores each set as a
 * `yaye_option_set` post with JSON meta: `_yaye_name`, `_yaye_status`,
 * `_yaye_options` (fields), `_yaye_actions`, `_yaye_products`.
 *
 * Field types, per-option prices, swatches and product assignment map cleanly.
 * Field-level conditional logic and the show/hide "actions" use YayExtra's own
 * option-id graph, which does not translate 1:1; those are reported as warnings
 * rather than guessed at.
 */
final class YayExtraSource extends AbstractMigrationSource {

    private const POST_TYPE = 'yaye_option_set';

    public function slug(): string {
        return 'yayextra';
    }

    public function label(): string {
        return 'YayExtra';
    }

    public function is_available(): bool {
        return post_type_exists( self::POST_TYPE ) || ! empty( $this->raw_posts() );
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function read_raw(): array {
        $sets = [];
        foreach ( $this->raw_posts() as $post ) {
            $sets[] = [
                'name'     => (string) get_post_meta( $post->ID, '_yaye_name', true ) ?: $post->post_title,
                'status'   => (int) get_post_meta( $post->ID, '_yaye_status', true ),
                'options'  => self::decode_meta( get_post_meta( $post->ID, '_yaye_options', true ) ),
                'actions'  => self::decode_meta( get_post_meta( $post->ID, '_yaye_actions', true ) ),
                'products' => self::decode_meta( get_post_meta( $post->ID, '_yaye_products', true ) ),
            ];
        }
        return $sets;
    }

    /**
     * YayExtra has stored these meta values as PHP-serialized arrays in some
     * versions and JSON strings in others. get_post_meta() already unserializes
     * the former to an array; the latter arrives as a string to decode.
     *
     * @param mixed $value
     * @return array<int|string,mixed>
     */
    private static function decode_meta( $value ): array {
        if ( is_array( $value ) ) {
            return $value;
        }
        if ( is_string( $value ) && '' !== $value ) {
            $decoded = json_decode( $value, true );
            return is_array( $decoded ) ? $decoded : [];
        }
        return [];
    }

    /**
     * @return array<int,\WP_Post>
     */
    private function raw_posts(): array {
        return get_posts(
            [
                'post_type'        => self::POST_TYPE,
                'post_status'      => [ 'publish', 'draft', 'pending', 'private' ],
                'numberposts'      => -1,
                'orderby'          => 'ID',
                'order'            => 'ASC',
            ]
        );
    }

    /**
     * @param array<string,mixed> $set
     * @return array{input:array<string,mixed>,warnings:list<string>}
     */
    public function convert( array $set ): array {
        $warnings = [];
        $fields   = [];
        $index    = 0;

        foreach ( self::arr( $set, 'options' ) as $raw_field ) {
            if ( ! is_array( $raw_field ) ) {
                continue;
            }
            $field = $this->convert_field( $raw_field, ++$index, $warnings );
            if ( null !== $field ) {
                $fields[] = $field;
            }
        }

        if ( ! empty( $set['actions'] ) && is_array( $set['actions'] ) ) {
            $warnings[] = __( 'Show/hide actions and price rules were not migrated; re-create them under Fees & discounts or field logic.', 'flexa-extra' );
        }

        $input = [
            'name'      => self::str( $set, 'name', __( 'Imported option set', 'flexa-extra' ) ),
            'status'    => (float) ( $set['status'] ?? 0 ) !== 0.0,
            'fields'    => $fields,
            'actions'   => [],
            'targeting' => $this->convert_targeting( self::arr( $set, 'products' ), $warnings ),
        ];

        return [ 'input' => $input, 'warnings' => $warnings ];
    }

    /**
     * @param array<string,mixed> $raw
     * @param list<string>        $warnings
     * @return array<string,mixed>|null
     */
    private function convert_field( array $raw, int $index, array &$warnings ): ?array {
        $source_type = self::str( $raw, 'fieldType' );
        $label       = self::str( $raw, 'name' );

        if ( 'file_upload' === $source_type ) {
            $warnings[] = sprintf(
                /* translators: %s: field label. */
                __( 'File upload field "%s" was skipped (not supported).', 'flexa-extra' ),
                $label
            );
            return null;
        }

        $type = self::map_type( $source_type, $raw );
        if ( '' === $type ) {
            $warnings[] = sprintf(
                /* translators: 1: field label, 2: source field type. */
                __( 'Field "%1$s" has an unsupported type (%2$s) and was skipped.', 'flexa-extra' ),
                $label,
                $source_type
            );
            return null;
        }

        if ( in_array( $source_type, [ 'time_picker' ], true ) ) {
            $warnings[] = sprintf(
                /* translators: %s: field label. */
                __( 'Time field "%s" was imported as a text field.', 'flexa-extra' ),
                $label
            );
        }

        if ( ! empty( $raw['logics'] ) && is_array( $raw['logics'] ) ) {
            $warnings[] = sprintf(
                /* translators: %s: field label. */
                __( 'Conditional logic on "%s" was not migrated; set it again under the field\'s Logic.', 'flexa-extra' ),
                $label
            );
        }

        $field = [
            'id'          => 'fld_' . $index,
            'type'        => $type,
            'label'       => $label,
            'name'        => self::str( $raw, 'fieldName' ),
            'required'    => self::bool( $raw, 'isRequired' ),
            'placeholder' => self::str( $raw, 'placeHolder', self::str( $raw, 'placeholderValue' ) ),
            'tooltip'     => '',
            'default'     => '',
            'logic'       => self::no_logic(),
        ];

        if ( 'text' === $type ) {
            $format               = self::str( self::arr( $raw, 'textFormat' ), 'value', 'normal' );
            $field['textFormat']  = in_array( $format, [ 'email', 'url' ], true ) ? $format : 'text';
            $field['regex']       = '';
        }

        if ( 'number' === $type ) {
            $field['min']  = self::num( $raw, 'minNumber' );
            $field['max']  = self::num( $raw, 'maxNumber' );
            $field['step'] = self::num( $raw, 'numberStep' );
        }

        if ( in_array( $type, [ 'checkbox', 'radio', 'dropdown', 'swatch', 'button' ], true ) ) {
            $field['multiple'] = in_array( $source_type, [ 'checkbox', 'button_multi', 'swatches_multi' ], true );
            $field['options']  = $this->convert_options( self::arr( $raw, 'optionValues' ), $index );
        }

        return $field;
    }

    /**
     * @param array<int|string,mixed> $option_values
     * @return list<array<string,mixed>>
     */
    private function convert_options( array $option_values, int $field_index ): array {
        $options = [];
        $i       = 0;
        foreach ( $option_values as $raw_option ) {
            if ( ! is_array( $raw_option ) ) {
                continue;
            }
            ++$i;
            $label = self::str( $raw_option, 'label', self::str( $raw_option, 'value' ) );
            $cost  = self::arr( $raw_option, 'additionalCost' );
            $price = self::price( 'none', 0.0 );
            if ( self::bool( $cost, 'isEnabled' ) ) {
                $cost_type = self::str( self::arr( $cost, 'costType' ), 'value' );
                $price     = self::price(
                    'percentage' === $cost_type ? 'percent' : 'fixed',
                    (float) ( self::num( $cost, 'value' ) ?? 0.0 )
                );
            }

            $options[] = [
                'id'      => 'opt_' . $field_index . '_' . $i,
                'label'   => $label,
                'value'   => self::str( $raw_option, 'value', $label ),
                'default' => self::bool( $raw_option, 'isDefault' ),
                'tooltip' => self::str( self::arr( $raw_option, 'additionalDescription' ), 'description' ),
                'color'   => self::str( $raw_option, 'swatchColor' ),
                'image'   => self::str( $raw_option, 'imageUrl' ),
                'price'   => $price,
            ];
        }
        return $options;
    }

    /**
     * @param array<int|string,mixed> $products
     * @param list<string>            $warnings
     * @return array<string,mixed>
     */
    private function convert_targeting( array $products, array &$warnings ): array {
        $type = (int) ( self::num( $products, 'product_filter_type' ) ?? 3 );

        if ( 1 === $type ) {
            $ids = array_values(
                array_filter(
                    array_map( 'intval', self::arr( $products, 'product_filter_one_by_one' ) )
                )
            );
            return [ 'mode' => 'manual', 'productIds' => $ids, 'match' => 'any', 'conditions' => [] ];
        }

        if ( 2 === $type ) {
            $warnings[] = __( 'Conditional product assignment was imported as "all products"; review Product assignment.', 'flexa-extra' );
        }

        return [ 'mode' => 'all', 'productIds' => [], 'match' => 'any', 'conditions' => [] ];
    }

    /**
     * Map a YayExtra field type to a Flexa Extra field type. Swatch-style fields
     * become a `swatch` when their options carry a colour or image, otherwise a
     * plain `button` group. Returns '' for types with no equivalent.
     *
     * @param array<string,mixed> $raw
     */
    private static function map_type( string $source_type, array $raw ): string {
        switch ( $source_type ) {
            case 'text':
            case 'time_picker':
                return 'text';
            case 'textarea':
                return 'textarea';
            case 'number':
                return 'number';
            case 'date_picker':
                return 'date_picker';
            case 'dropdown':
            case 'select':
                return 'dropdown';
            case 'radio':
                return 'radio';
            case 'checkbox':
                return 'checkbox';
            case 'label':
                return 'heading';
            case 'button':
            case 'button_multi':
            case 'swatches_multi':
                return self::has_swatch_media( self::arr( $raw, 'optionValues' ) ) ? 'swatch' : 'button';
            default:
                return '';
        }
    }

    /**
     * @param array<int|string,mixed> $option_values
     */
    private static function has_swatch_media( array $option_values ): bool {
        foreach ( $option_values as $option ) {
            if ( is_array( $option ) && ( '' !== self::str( $option, 'swatchColor' ) || '' !== self::str( $option, 'imageUrl' ) ) ) {
                return true;
            }
        }
        return false;
    }
}
