<?php
namespace Flexa\Extra\Migration;

defined( 'ABSPATH' ) || exit;

/**
 * Imports option sets from YayExtra (YayCommerce). YayExtra stores each set as a
 * `yaye_option_set` post with JSON meta: `_yaye_name`, `_yaye_status`,
 * `_yaye_options` (fields), `_yaye_actions`, `_yaye_products`.
 *
 * Most settings are stored as `{ value, label }` pairs rather than plain
 * strings (the field type included), so they are read through {@see self::choice()}.
 *
 * Field types, per-option prices, swatches and product assignment map cleanly.
 * Field-level conditional logic and the show/hide "actions" use YayExtra's own
 * option-id graph, which does not translate 1:1; those are reported as warnings
 * rather than guessed at.
 */
final class YayExtraSource extends AbstractMigrationSource {

    private const POST_TYPE = 'yaye_option_set';

    /** YayExtra product-condition type => Flexa Extra targeting condition type. */
    private const CONDITION_TYPES = [
        'prod_category' => 'category',
        'prod_tag'      => 'tag',
    ];

    /** YayExtra product-condition type => WooCommerce taxonomy. */
    private const TAXONOMIES = [
        'prod_category' => 'product_cat',
        'prod_tag'      => 'product_tag',
    ];

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
                'products' => self::resolve_terms( self::decode_meta( get_post_meta( $post->ID, '_yaye_products', true ) ) ),
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
     * YayExtra matches product categories and tags by term *name*, while Flexa
     * Extra's targeting stores term IDs. Resolving that needs the database, so
     * it happens here and each condition value is annotated with a `termId` that
     * the pure {@see self::convert()} half can then read.
     *
     * @param array<int|string,mixed> $products
     * @return array<int|string,mixed>
     */
    private static function resolve_terms( array $products ): array {
        $filters = isset( $products['product_filter_by_conditions'] ) && is_array( $products['product_filter_by_conditions'] )
            ? $products['product_filter_by_conditions']
            : null;

        if ( null === $filters || ! isset( $filters['conditions'] ) || ! is_array( $filters['conditions'] ) ) {
            return $products;
        }

        foreach ( $filters['conditions'] as $i => $condition ) {
            if ( ! is_array( $condition ) || ! isset( $condition['value'] ) || ! is_array( $condition['value'] ) ) {
                continue;
            }

            $taxonomy = self::TAXONOMIES[ self::choice( $condition, 'type' ) ] ?? '';
            if ( '' === $taxonomy ) {
                continue;
            }

            foreach ( $condition['value'] as $j => $entry ) {
                if ( is_array( $entry ) ) {
                    $filters['conditions'][ $i ]['value'][ $j ]['termId'] = self::term_id( $entry, $taxonomy );
                }
            }
        }

        $products['product_filter_by_conditions'] = $filters;
        return $products;
    }

    /**
     * Resolve one YayExtra condition value to a term ID. The label carries the
     * term name; `value` holds the name too for tags, but a term ID for
     * categories, so both are tried by name, slug and finally as an ID.
     *
     * @param array<string,mixed> $entry
     */
    private static function term_id( array $entry, string $taxonomy ): int {
        foreach ( [ 'label', 'value' ] as $key ) {
            $needle = self::str( $entry, $key );
            if ( '' === $needle ) {
                continue;
            }
            foreach ( [ 'name', 'slug' ] as $field ) {
                $term = get_term_by( $field, $needle, $taxonomy );
                if ( $term instanceof \WP_Term ) {
                    return $term->term_id;
                }
            }
        }

        $id   = (int) ( self::num( $entry, 'value' ) ?? 0 );
        $term = $id > 0 ? get_term( $id, $taxonomy ) : null;
        return $term instanceof \WP_Term ? $term->term_id : 0;
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
     * Read a YayExtra select-style setting. These are saved as `{ value, label }`
     * pairs, though a few older versions wrote a plain string, so both are read.
     *
     * @param array<string,mixed> $raw
     */
    private static function choice( array $raw, string $key, string $default = '' ): string {
        $value = $raw[ $key ] ?? null;
        if ( is_array( $value ) ) {
            return self::str( $value, 'value', $default );
        }
        return is_scalar( $value ) && '' !== (string) $value ? (string) $value : $default;
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
        // The type lives in `type.value`; `fieldType` is the older flat shape.
        $source_type = self::choice( $raw, 'type', self::str( $raw, 'fieldType' ) );
        $label       = self::str( $raw, 'name' );

        if ( 'file_upload' === $source_type ) {
            $warnings[] = sprintf(
                /* translators: %s: field label. */
                __( 'File upload field "%s" was skipped (not supported).', 'flexa-extra' ),
                $label
            );
            return null;
        }

        $type = self::map_type( $source_type );
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
            'name'        => self::str( $raw, 'nameOnCart', self::str( $raw, 'fieldName' ) ),
            'required'    => self::bool( $raw, 'isRequired' ),
            'placeholder' => self::str( $raw, 'placeHolder', self::str( $raw, 'placeholderValue' ) ),
            'tooltip'     => '',
            'default'     => '',
            'logic'       => self::no_logic(),
        ];

        if ( 'text' === $type ) {
            $format               = self::choice( $raw, 'textFormat', 'normal' );
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
            $field['options']  = $this->convert_options( self::arr( $raw, 'optionValues' ), $index, 'swatch' === $type );
        }

        return $field;
    }

    /**
     * @param array<int|string,mixed> $option_values
     * @return list<array<string,mixed>>
     */
    private function convert_options( array $option_values, int $field_index, bool $is_swatch ): array {
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
                $cost_type = self::choice( $cost, 'costType' );
                $price     = self::price(
                    'percentage' === $cost_type ? 'percent' : 'fixed',
                    (float) ( self::num( $cost, 'value' ) ?? 0.0 )
                );
            }

            // YayExtra seeds every option with a default swatch colour, but only
            // the swatches field types ever render one, and each option picks
            // either the colour or the image through `swatchesType`.
            $swatch_type = $is_swatch ? self::str( $raw_option, 'swatchesType', 'color' ) : '';

            $options[] = [
                'id'      => 'opt_' . $field_index . '_' . $i,
                'label'   => $label,
                'value'   => self::str( $raw_option, 'value', $label ),
                'default' => self::bool( $raw_option, 'isDefault' ),
                'tooltip' => self::str( self::arr( $raw_option, 'additionalDescription' ), 'description' ),
                'color'   => 'color' === $swatch_type ? self::str( $raw_option, 'swatchColor' ) : '',
                'image'   => 'image' === $swatch_type ? self::str( $raw_option, 'imageUrl' ) : '',
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
            return $this->convert_conditions( self::arr( $products, 'product_filter_by_conditions' ), $warnings );
        }

        return self::all_products();
    }

    /**
     * Map YayExtra's product conditions onto Flexa Extra's targeting rules.
     *
     * YayExtra holds one rule per condition with a *list* of terms ("is one of");
     * Flexa Extra holds one term per rule, so each list is expanded. That is
     * faithful while the set's match mode agrees with the operator ("is one of"
     * with Any, "is none of" with All) and is flagged when it does not.
     *
     * @param array<int|string,mixed> $raw
     * @param list<string>            $warnings
     * @return array<string,mixed>
     */
    private function convert_conditions( array $raw, array &$warnings ): array {
        $match      = 'all' === self::choice( $raw, 'match_type', 'any' ) ? 'all' : 'any';
        $conditions = [];

        foreach ( self::arr( $raw, 'conditions' ) as $condition ) {
            if ( ! is_array( $condition ) ) {
                continue;
            }

            $source_type = self::choice( $condition, 'type' );
            $mapped      = self::CONDITION_TYPES[ $source_type ] ?? '';
            if ( '' === $mapped ) {
                $warnings[] = sprintf(
                    /* translators: %s: the source condition type, e.g. "prod_name". */
                    __( 'Product condition "%s" has no equivalent and was not migrated; review Product assignment.', 'flexa-extra' ),
                    $source_type
                );
                continue;
            }

            $operator = 'is_one_of' === self::choice( $condition, 'comparation', 'is_one_of' ) ? 'is' : 'is_not';
            $values   = self::arr( $condition, 'value' );

            // "is one of" reads as OR, "is none of" as AND. Expanding a multi-term
            // rule only keeps its meaning when the set matches the same way.
            if ( count( $values ) > 1 && ( 'is' === $operator ? 'any' : 'all' ) !== $match ) {
                $warnings[] = __( 'A product condition covering several terms was split into one rule per term, which changes how it matches; review Product assignment.', 'flexa-extra' );
            }

            foreach ( $values as $entry ) {
                if ( ! is_array( $entry ) ) {
                    continue;
                }

                $term_id = (int) ( self::num( $entry, 'termId' ) ?? 0 );
                if ( $term_id <= 0 ) {
                    $warnings[] = sprintf(
                        /* translators: %s: the category or tag name. */
                        __( 'Product condition term "%s" was not found on this site and was skipped.', 'flexa-extra' ),
                        self::str( $entry, 'label', self::str( $entry, 'value' ) )
                    );
                    continue;
                }

                $conditions[] = [
                    'type'     => $mapped,
                    'operator' => $operator,
                    'value'    => (string) $term_id,
                ];
            }
        }

        if ( [] === $conditions ) {
            $warnings[] = __( 'Conditional product assignment could not be migrated and was imported as "all products"; review Product assignment.', 'flexa-extra' );
            return self::all_products();
        }

        return [ 'mode' => 'conditions', 'productIds' => [], 'match' => $match, 'conditions' => $conditions ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function all_products(): array {
        return [ 'mode' => 'all', 'productIds' => [], 'match' => 'any', 'conditions' => [] ];
    }

    /**
     * Map a YayExtra field type to a Flexa Extra field type. Returns '' for
     * types with no equivalent.
     */
    private static function map_type( string $source_type ): string {
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
            case 'swatches':
            case 'swatches_multi':
                return 'swatch';
            case 'button':
            case 'button_multi':
                return 'button';
            default:
                return '';
        }
    }
}
