<?php
namespace Flexa\Extra\Migration;

defined( 'ABSPATH' ) || exit;

use Flexa\Extra\Fields\FieldType;

/**
 * Imports forms from **Acowebs "Custom Product Addons for WooCommerce"** (WCPA,
 * free).
 *
 * WCPA keeps each form as a `wcpa_pt_forms` post whose fields live in the
 * `_wcpa_fb_json_data` post meta: a JSON object keyed by section id, each section
 * holding an `extra` block (its name) and a `fields` list of rows, where every
 * row is itself a list of field objects. Choices sit under a field's `values`
 * key, each with a label / value and an optional per-option `price`, `image` and
 * `color`.
 *
 * A form is assigned to products either directly (the product's
 * `_wcpa_product_meta` holds a list of form ids) or by category (the form post's
 * own `product_cat` terms). Both are resolved while reading so the pure
 * {@see self::convert()} half only maps already-shaped data.
 */
final class AcowebsWcpaSource extends AbstractMigrationSource {

    private const POST_TYPE       = 'wcpa_pt_forms';
    private const FORM_META       = '_wcpa_fb_json_data';
    private const PRODUCT_META    = '_wcpa_product_meta';

    public function slug(): string {
        return 'acowebs-wcpa';
    }

    public function label(): string {
        return 'Custom Product Add-Ons (Acowebs)';
    }

    public function is_available(): bool {
        return post_type_exists( self::POST_TYPE ) || ! empty( $this->form_posts() );
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function read_raw(): array {
        $sets = [];
        foreach ( $this->form_posts() as $post ) {
            $json = json_decode( (string) get_post_meta( $post->ID, self::FORM_META, true ), true );
            if ( ! is_array( $json ) || [] === $json ) {
                continue;
            }
            $sets[] = [
                'name'     => $post->post_title !== '' ? $post->post_title : __( 'Imported form', 'flexa-extra' ),
                'sections' => $json,
                'scope'    => $this->form_scope( $post->ID ),
            ];
        }
        return $sets;
    }

    /**
     * @return array<int,\WP_Post>
     */
    private function form_posts(): array {
        if ( ! post_type_exists( self::POST_TYPE ) ) {
            // The plugin registers the post type, so when it is inactive there is
            // nothing readable even if rows linger; keep this defensive.
            $exists = get_posts(
                [
                    'post_type'   => self::POST_TYPE,
                    'post_status' => 'any',
                    'numberposts' => 1,
                    'fields'      => 'ids',
                ]
            );
            if ( empty( $exists ) ) {
                return [];
            }
        }

        return get_posts(
            [
                'post_type'   => self::POST_TYPE,
                'post_status' => [ 'publish', 'draft', 'pending', 'private' ],
                'numberposts' => -1,
                'orderby'     => 'ID',
                'order'       => 'ASC',
            ]
        );
    }

    /**
     * Which products / categories a form applies to. Products point at the form
     * through their `_wcpa_product_meta` list; the form itself may also be scoped
     * to product categories through its own `product_cat` terms.
     *
     * @return array{mode:string,productIds:list<int>,categoryIds:list<int>}
     */
    private function form_scope( int $form_id ): array {
        $product_ids = get_posts(
            [
                'post_type'   => 'product',
                'post_status' => [ 'publish', 'draft', 'pending', 'private' ],
                'numberposts' => -1,
                'fields'      => 'ids',
                'meta_query'  => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- One-off migration read, not a hot path.
                    [
                        'key'     => self::PRODUCT_META,
                        'value'   => ':' . $form_id . ';',
                        'compare' => 'LIKE',
                    ],
                ],
            ]
        );
        $product_ids = array_values( array_filter( array_map( 'intval', (array) $product_ids ) ) );

        $category_ids = [];
        $terms        = get_the_terms( $form_id, 'product_cat' );
        if ( is_array( $terms ) ) {
            foreach ( $terms as $term ) {
                $category_ids[] = (int) $term->term_id;
            }
        }

        if ( [] !== $product_ids ) {
            return [ 'mode' => 'manual', 'productIds' => $product_ids, 'categoryIds' => $category_ids ];
        }
        if ( [] !== $category_ids ) {
            return [ 'mode' => 'conditions', 'productIds' => [], 'categoryIds' => $category_ids ];
        }
        return [ 'mode' => 'all', 'productIds' => [], 'categoryIds' => [] ];
    }

    /**
     * @param array<string,mixed> $set
     * @return array{input:array<string,mixed>,warnings:list<string>}
     */
    public function convert( array $set ): array {
        $warnings = [];
        $fields   = [];
        $index    = 0;

        foreach ( self::arr( $set, 'sections' ) as $section ) {
            if ( ! is_array( $section ) ) {
                continue;
            }

            $name = self::str( self::arr( $section, 'extra' ), 'name' );
            if ( '' !== $name && 'Default' !== $name ) {
                $fields[] = [
                    'id'    => 'fld_' . ( ++$index ),
                    'type'  => 'heading',
                    'label' => $name,
                    'name'  => '',
                    'logic' => self::no_logic(),
                ];
            }

            // `fields` is a list of rows; each row is a list of field objects.
            foreach ( self::arr( $section, 'fields' ) as $row ) {
                if ( ! is_array( $row ) ) {
                    continue;
                }
                foreach ( $row as $raw_field ) {
                    if ( ! is_array( $raw_field ) ) {
                        continue;
                    }
                    $field = $this->convert_field( $raw_field, ++$index, $warnings );
                    if ( null !== $field ) {
                        $fields[] = $field;
                    }
                }
            }
        }

        $input = [
            'name'      => self::str( $set, 'name', __( 'Imported form', 'flexa-extra' ) ),
            'status'    => true,
            'fields'    => $fields,
            'actions'   => [],
            'targeting' => $this->convert_targeting( self::arr( $set, 'scope' ), $warnings ),
        ];

        return [ 'input' => $input, 'warnings' => $warnings ];
    }

    /**
     * @param array<string,mixed> $raw
     * @param list<string>        $warnings
     * @return array<string,mixed>|null
     */
    private function convert_field( array $raw, int $index, array &$warnings ): ?array {
        // A field switched off in the builder is not rendered.
        if ( array_key_exists( 'active', $raw ) && ! self::bool( $raw, 'active', true ) ) {
            return null;
        }

        $source_type = self::str( $raw, 'type' );
        $subtype     = self::str( $raw, 'subtype' );
        $label       = self::str( $raw, 'label' );

        if ( 'file' === $source_type ) {
            $warnings[] = sprintf(
                /* translators: %s: field label. */
                __( 'File upload field "%s" was skipped (not supported).', 'flexa-extra' ),
                $label
            );
            return null;
        }

        $type = self::map_type( $source_type, $subtype );
        if ( '' === $type ) {
            if ( in_array( $source_type, [ 'hidden', 'separator' ], true ) ) {
                return null; // Silent: no shopper-facing equivalent.
            }
            $warnings[] = sprintf(
                /* translators: 1: field label, 2: source field type. */
                __( 'Field "%1$s" has an unsupported type (%2$s) and was skipped.', 'flexa-extra' ),
                $label,
                $source_type
            );
            return null;
        }

        if ( in_array( $source_type, [ 'time', 'datetime-local' ], true ) ) {
            $warnings[] = sprintf(
                /* translators: 1: field label, 2: source field type. */
                __( 'Field "%1$s" (%2$s) was imported as the closest Flexa Extra type.', 'flexa-extra' ),
                $label,
                $source_type
            );
        }

        $is_content = in_array( $source_type, [ 'content', 'header' ], true );

        $field = [
            'id'          => 'fld_' . $index,
            'type'        => $type,
            'label'       => $is_content && '' === $label ? self::str( $raw, 'value', self::str( $raw, 'description' ) ) : $label,
            'name'        => '',
            'required'    => self::bool( $raw, 'required' ),
            'placeholder' => self::str( $raw, 'placeholder' ),
            'tooltip'     => self::str( $raw, 'description' ),
            'default'     => 'heading' === $type ? '' : self::str( $raw, 'value' ),
            'logic'       => self::no_logic(),
        ];

        if ( 'text' === $type ) {
            $field['textFormat'] = in_array( $subtype, [ 'email', 'url' ], true ) ? $subtype
                : ( in_array( $source_type, [ 'email', 'url' ], true ) ? $source_type : 'text' );
            $field['regex']      = '';
        }

        if ( 'number' === $type ) {
            $field['min']  = self::num( $raw, 'min', self::num( $raw, 'minValue' ) );
            $field['max']  = self::num( $raw, 'max', self::num( $raw, 'maxValue' ) );
            $field['step'] = self::num( $raw, 'step' );
        }

        if ( 'color_picker' === $type ) {
            $field['default'] = '';
        }

        // A dropdown / radio whose options carry a colour or image is closer to a
        // swatch, which is the only Flexa type that renders those per option.
        if ( in_array( $type, [ 'dropdown', 'radio' ], true ) && $this->options_have_swatch( $raw ) ) {
            $type          = 'swatch';
            $field['type'] = 'swatch';
        }

        // A field-level price on an input (text / textarea / number / etc.).
        if ( FieldType::is_input( $type ) && self::bool( $raw, 'enablePrice' ) ) {
            $field['price'] = $this->field_price( $raw, $warnings, $label );
        }

        if ( in_array( $type, [ 'checkbox', 'radio', 'dropdown', 'swatch' ], true ) ) {
            $field['multiple'] = 'checkbox' === $type;
            $field['options']  = $this->convert_options( $raw, $index, $source_type, 'swatch' === $type );
        }

        return $field;
    }

    /**
     * Whether any of a field's options carries a colour or image swatch.
     *
     * @param array<string,mixed> $raw
     */
    private function options_have_swatch( array $raw ): bool {
        foreach ( self::arr( $raw, 'values' ) as $opt ) {
            if ( is_array( $opt ) && ( '' !== self::str( $opt, 'color' ) || '' !== self::str( $opt, 'image' ) ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * A single checkbox has no `values` list; it becomes one on/off option using
     * its `check_value`. Grouped choices read their `values` list.
     *
     * @param array<string,mixed> $raw
     * @return list<array<string,mixed>>
     */
    private function convert_options( array $raw, int $field_index, string $source_type, bool $is_swatch ): array {
        if ( 'checkbox' === $source_type ) {
            $label = self::str( $raw, 'label' );
            $value = self::str( $raw, 'check_value', __( 'Yes', 'flexa-extra' ) );
            return [ $this->option( $field_index, 1, '' !== $label ? $label : $value, $value, self::price( 'none', 0.0 ), '', '' ) ];
        }

        $out = [];
        $i   = 0;
        foreach ( self::arr( $raw, 'values' ) as $opt ) {
            if ( ! is_array( $opt ) ) {
                continue;
            }
            ++$i;
            $label = self::str( $opt, 'label', self::str( $opt, 'value' ) );
            $value = self::str( $opt, 'value', $label );

            $price = self::price( 'none', 0.0 );
            $cost  = self::num( $opt, 'price' );
            if ( null !== $cost && 0.0 !== $cost ) {
                $ptype = self::str( $opt, 'priceType', self::str( $opt, 'price_type' ) );
                $price = self::price( in_array( $ptype, [ 'percent', 'percentage' ], true ) ? 'percent' : 'fixed', (float) $cost );
            }

            $color = $is_swatch ? self::str( $opt, 'color' ) : '';
            $image = $is_swatch ? self::str( $opt, 'image' ) : '';

            $out[] = $this->option( $field_index, $i, $label, $value, $price, $color, $image );
        }
        return $out;
    }

    /**
     * @param array{type:string,amount:float} $price
     * @return array<string,mixed>
     */
    private function option( int $field_index, int $i, string $label, string $value, array $price, string $color, string $image ): array {
        return [
            'id'      => 'opt_' . $field_index . '_' . $i,
            'label'   => $label,
            'value'   => $value,
            'default' => false,
            'tooltip' => '',
            'color'   => $color,
            'image'   => $image,
            'price'   => $price,
        ];
    }

    /**
     * Field-level price. WCPA "custom" pricing is a formula in its own syntax,
     * which does not port across, so it is flagged rather than guessed at.
     *
     * @param array<string,mixed> $raw
     * @param list<string>        $warnings
     * @return array{type:string,amount:float}
     */
    private function field_price( array $raw, array &$warnings, string $label ): array {
        $pricing = self::str( $raw, 'pricingType' );
        if ( 'custom' === $pricing || 'formula' === $pricing ) {
            $warnings[] = sprintf(
                /* translators: %s: field label. */
                __( 'The custom price formula on "%s" was not migrated; set it again under the field price.', 'flexa-extra' ),
                $label
            );
            return self::price( 'none', 0.0 );
        }

        $amount = self::num( $raw, 'price' );
        if ( null === $amount || 0.0 === $amount ) {
            return self::price( 'none', 0.0 );
        }
        return self::price( in_array( $pricing, [ 'percent', 'percentage' ], true ) ? 'percent' : 'fixed', (float) $amount );
    }

    /**
     * @param array<string,mixed> $scope
     * @param list<string>        $warnings
     * @return array<string,mixed>
     */
    private function convert_targeting( array $scope, array &$warnings ): array {
        $mode = self::str( $scope, 'mode', 'all' );

        if ( 'manual' === $mode ) {
            $ids = array_values( array_filter( array_map( 'intval', self::arr( $scope, 'productIds' ) ) ) );
            return [ 'mode' => 'manual', 'productIds' => $ids, 'match' => 'any', 'conditions' => [] ];
        }

        if ( 'conditions' === $mode ) {
            $conditions = [];
            foreach ( self::arr( $scope, 'categoryIds' ) as $term_id ) {
                if ( is_numeric( $term_id ) && (int) $term_id > 0 ) {
                    $conditions[] = [ 'type' => 'category', 'operator' => 'is', 'value' => (string) (int) $term_id ];
                }
            }
            if ( [] !== $conditions ) {
                $warnings[] = __( 'Category assignment was imported as product conditions; review Product assignment.', 'flexa-extra' );
                return [ 'mode' => 'conditions', 'productIds' => [], 'match' => 'any', 'conditions' => $conditions ];
            }
        }

        $warnings[] = __( 'No product assignment was found for this form, so it was imported as "all products"; review Product assignment.', 'flexa-extra' );
        return [ 'mode' => 'all', 'productIds' => [], 'match' => 'any', 'conditions' => [] ];
    }

    /**
     * Map a WCPA field type to a Flexa Extra type. `select` / `radio-group` become
     * a swatch when their options carry a colour or image (handled by the caller);
     * returns '' when there is no equivalent.
     */
    private static function map_type( string $source_type, string $subtype ): string {
        switch ( $source_type ) {
            case 'text':
                return 'text';
            case 'email':
            case 'url':
            case 'tel':
            case 'time':
                return 'text';
            case 'textarea':
                return 'textarea';
            case 'number':
                return 'number';
            case 'date':
            case 'datetime-local':
                return 'date_picker';
            case 'color':
                return 'color_picker';
            case 'select':
                return 'dropdown';
            case 'radio-group':
                return 'radio';
            case 'checkbox-group':
            case 'checkbox':
                return 'checkbox';
            case 'header':
            case 'content':
                return 'heading';
            default:
                unset( $subtype );
                return '';
        }
    }
}
