<?php
namespace Flexa\Extra\Migration;

defined( 'ABSPATH' ) || exit;

/**
 * Imports add-ons from the official **WooCommerce Product Add-Ons** plugin.
 *
 * That plugin stores its fields as a PHP-serialized array under the
 * `_product_addons` post meta, on individual products and on `global_product_addon`
 * posts (add-on groups that apply to all products or to selected categories).
 * `get_post_meta()` unserializes the value to an array, which is what
 * {@see self::convert()} expects.
 *
 * Each product with its own add-ons becomes one option set assigned to that
 * product; each global group becomes one option set assigned to all products or
 * to its categories. Field types, per-option prices and swatch images map where
 * an equivalent exists. Attachment IDs on image options are resolved to URLs
 * while reading (a database step), so the pure {@see self::convert()} half only
 * ever sees a URL string.
 */
final class WooProductAddonsSource extends AbstractMigrationSource {

    private const META_KEY   = '_product_addons';
    private const GLOBAL_CPT  = 'global_product_addon';
    private const META_ALL    = '_all_products';
    private const META_CATS   = '_product_addon_categories';

    public function slug(): string {
        return 'woo-product-addons';
    }

    public function label(): string {
        return 'WooCommerce Product Add-Ons';
    }

    public function is_available(): bool {
        if ( ! empty( $this->global_posts() ) ) {
            return true;
        }
        return ! empty( $this->product_ids_with_addons() );
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function read_raw(): array {
        $sets = [];

        foreach ( $this->global_posts() as $post ) {
            $addons = get_post_meta( $post->ID, self::META_KEY, true );
            if ( ! is_array( $addons ) || [] === $addons ) {
                continue;
            }
            $sets[] = [
                'name'   => $post->post_title !== '' ? $post->post_title : __( 'Global add-ons', 'flexa-extra' ),
                'addons' => $this->resolve_images( $addons ),
                'scope'  => $this->global_scope( $post->ID ),
            ];
        }

        foreach ( $this->product_ids_with_addons() as $product_id ) {
            $addons = get_post_meta( $product_id, self::META_KEY, true );
            if ( ! is_array( $addons ) || [] === $addons ) {
                continue;
            }
            $sets[] = [
                'name'   => sprintf(
                    /* translators: %s: the product name. */
                    __( 'Add-ons: %s', 'flexa-extra' ),
                    get_the_title( $product_id )
                ),
                'addons' => $this->resolve_images( $addons ),
                'scope'  => [ 'mode' => 'manual', 'productIds' => [ $product_id ], 'categoryIds' => [] ],
            ];
        }

        return $sets;
    }

    /**
     * @return array<int,\WP_Post>
     */
    private function global_posts(): array {
        if ( ! post_type_exists( self::GLOBAL_CPT ) ) {
            return [];
        }
        return get_posts(
            [
                'post_type'   => self::GLOBAL_CPT,
                'post_status' => [ 'publish', 'draft', 'pending', 'private' ],
                'numberposts' => -1,
                'orderby'     => 'ID',
                'order'       => 'ASC',
            ]
        );
    }

    /**
     * IDs of products that carry a non-empty `_product_addons` meta.
     *
     * @return list<int>
     */
    private function product_ids_with_addons(): array {
        $ids = get_posts(
            [
                'post_type'   => 'product',
                'post_status' => [ 'publish', 'draft', 'pending', 'private' ],
                'numberposts' => -1,
                'orderby'     => 'ID',
                'order'       => 'ASC',
                'fields'      => 'ids',
                'meta_query'  => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- One-off migration read, not a hot path.
                    [
                        'key'     => self::META_KEY,
                        'compare' => 'EXISTS',
                    ],
                ],
            ]
        );

        return array_values( array_filter( array_map( 'intval', (array) $ids ) ) );
    }

    /**
     * Resolve each image option's attachment ID to a URL so convert() stays pure.
     *
     * @param array<int|string,mixed> $addons
     * @return array<int|string,mixed>
     */
    private function resolve_images( array $addons ): array {
        foreach ( $addons as $i => $addon ) {
            if ( ! is_array( $addon ) || ! isset( $addon['options'] ) || ! is_array( $addon['options'] ) ) {
                continue;
            }
            foreach ( $addon['options'] as $j => $option ) {
                if ( is_array( $option ) && isset( $option['image'] ) && is_numeric( $option['image'] ) ) {
                    $url = wp_get_attachment_image_url( (int) $option['image'], 'full' );
                    $addons[ $i ]['options'][ $j ]['image'] = is_string( $url ) ? $url : '';
                }
            }
        }
        return $addons;
    }

    /**
     * Product/category scope of a global add-on group.
     *
     * @return array{mode:string,productIds:list<int>,categoryIds:list<int>}
     */
    private function global_scope( int $post_id ): array {
        if ( '' !== (string) get_post_meta( $post_id, self::META_ALL, true )
            && '0' !== (string) get_post_meta( $post_id, self::META_ALL, true ) ) {
            return [ 'mode' => 'all', 'productIds' => [], 'categoryIds' => [] ];
        }

        // Categories are stored either as a meta list of term IDs or as the post's
        // own product_cat terms, depending on the version; read both.
        $cats = get_post_meta( $post_id, self::META_CATS, true );
        $ids  = is_array( $cats ) ? array_map( 'intval', $cats ) : [];

        if ( [] === $ids ) {
            $terms = get_the_terms( $post_id, 'product_cat' );
            if ( is_array( $terms ) ) {
                foreach ( $terms as $term ) {
                    $ids[] = (int) $term->term_id;
                }
            }
        }

        $ids = array_values( array_filter( $ids ) );
        if ( [] === $ids ) {
            return [ 'mode' => 'all', 'productIds' => [], 'categoryIds' => [] ];
        }

        return [ 'mode' => 'conditions', 'productIds' => [], 'categoryIds' => $ids ];
    }

    /**
     * @param array<string,mixed> $set
     * @return array{input:array<string,mixed>,warnings:list<string>}
     */
    public function convert( array $set ): array {
        $warnings = [];
        $fields   = [];
        $index    = 0;

        $addons = self::arr( $set, 'addons' );
        // Respect the plugin's own display order.
        usort(
            $addons,
            static function ( $a, $b ): int {
                $pa = is_array( $a ) && isset( $a['position'] ) && is_numeric( $a['position'] ) ? (int) $a['position'] : 0;
                $pb = is_array( $b ) && isset( $b['position'] ) && is_numeric( $b['position'] ) ? (int) $b['position'] : 0;
                return $pa <=> $pb;
            }
        );

        foreach ( $addons as $raw_field ) {
            if ( ! is_array( $raw_field ) ) {
                continue;
            }
            $field = $this->convert_field( $raw_field, ++$index, $warnings );
            if ( null !== $field ) {
                $fields[] = $field;
            }
        }

        $input = [
            'name'      => self::str( $set, 'name', __( 'Imported add-ons', 'flexa-extra' ) ),
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
        $source_type = self::str( $raw, 'type' );
        $display     = self::str( $raw, 'display' );
        $label       = self::str( $raw, 'name' );

        if ( 'file_upload' === $source_type ) {
            $warnings[] = sprintf(
                /* translators: %s: field label. */
                __( 'File upload field "%s" was skipped (not supported).', 'flexa-extra' ),
                $label
            );
            return null;
        }

        $type = self::map_type( $source_type, $display );
        if ( '' === $type ) {
            $warnings[] = sprintf(
                /* translators: 1: field label, 2: source field type. */
                __( 'Field "%1$s" has an unsupported type (%2$s) and was skipped.', 'flexa-extra' ),
                $label,
                $source_type
            );
            return null;
        }

        if ( in_array( $source_type, [ 'custom_price', 'input_multiplier' ], true ) ) {
            $warnings[] = sprintf(
                /* translators: 1: field label, 2: source field type. */
                __( 'Field "%1$s" (%2$s) was imported as a plain number field; its price behaviour was not carried over.', 'flexa-extra' ),
                $label,
                $source_type
            );
        }

        $field = [
            'id'          => 'fld_' . $index,
            'type'        => $type,
            'label'       => $label,
            'name'        => '',
            'required'    => self::bool( $raw, 'required' ),
            'placeholder' => self::str( $raw, 'placeholder' ),
            'tooltip'     => '',
            'default'     => '',
            'logic'       => self::no_logic(),
        ];

        if ( 'text' === $type ) {
            $field['textFormat'] = 'email' === self::str( $raw, 'restrictions_type' ) ? 'email' : 'text';
            $field['regex']      = '';
        }

        if ( 'number' === $type ) {
            $field['min']  = self::num( $raw, 'min' );
            $field['max']  = self::num( $raw, 'max' );
            $field['step'] = null;
        }

        // Text / textarea / number can carry a single price on the add-on itself.
        if ( in_array( $type, [ 'text', 'textarea', 'number' ], true ) && self::bool( $raw, 'adjust_price' ) ) {
            $field['price'] = $this->map_price(
                self::str( $raw, 'price_type', 'flat_fee' ),
                (float) ( self::num( $raw, 'price' ) ?? 0.0 ),
                $warnings
            );
        }

        if ( in_array( $type, [ 'checkbox', 'radio', 'dropdown', 'swatch' ], true ) ) {
            $field['multiple'] = 'checkbox' === $type;
            $field['options']  = $this->convert_options( self::arr( $raw, 'options' ), $index, 'swatch' === $type, $warnings );
        }

        return $field;
    }

    /**
     * @param array<int|string,mixed> $options
     * @param list<string>            $warnings
     * @return list<array<string,mixed>>
     */
    private function convert_options( array $options, int $field_index, bool $is_swatch, array &$warnings ): array {
        $out = [];
        $i   = 0;
        foreach ( $options as $raw_option ) {
            if ( ! is_array( $raw_option ) ) {
                continue;
            }
            ++$i;
            $label = self::str( $raw_option, 'label' );
            $price = self::price( 'none', 0.0 );
            if ( null !== self::num( $raw_option, 'price' ) && 0.0 !== self::num( $raw_option, 'price' ) ) {
                $price = $this->map_price(
                    self::str( $raw_option, 'price_type', 'flat_fee' ),
                    (float) self::num( $raw_option, 'price' ),
                    $warnings
                );
            }

            $image = $is_swatch ? self::str( $raw_option, 'image' ) : '';

            $out[] = [
                'id'      => 'opt_' . $field_index . '_' . $i,
                'label'   => $label,
                'value'   => $label,
                'default' => false,
                'tooltip' => '',
                'color'   => '',
                'image'   => $image,
                'price'   => $price,
            ];
        }
        return $out;
    }

    /**
     * Map a WooCommerce Product Add-Ons price type onto Flexa Extra's price shape.
     *
     * `quantity_based` is a per-unit charge, which is exactly how Flexa Extra
     * treats a fixed surcharge (WooCommerce multiplies it by the line quantity).
     * `flat_fee` is charged once per line regardless of quantity, which has no
     * equivalent here, so it becomes a per-unit fixed amount and is flagged.
     *
     * @param list<string> $warnings
     * @return array{type:string,amount:float}
     */
    private function map_price( string $price_type, float $amount, array &$warnings ): array {
        if ( 'percentage_based' === $price_type ) {
            return self::price( 'percent', $amount );
        }
        if ( 'flat_fee' === $price_type ) {
            $warnings[] = __( 'A one-time "flat fee" price was imported as a per-unit amount; review it if you sell that product in quantities above one.', 'flexa-extra' );
        }
        return self::price( 'fixed', $amount );
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

        return [ 'mode' => 'all', 'productIds' => [], 'match' => 'any', 'conditions' => [] ];
    }

    /**
     * Map a Product Add-Ons field type to a Flexa Extra type. `multiple_choice`
     * carries its widget in `display` (select / radiobutton / images); older
     * versions used the widget name as the type directly. Returns '' when there
     * is no equivalent.
     */
    private static function map_type( string $source_type, string $display ): string {
        switch ( $source_type ) {
            case 'multiple_choice':
                if ( 'radiobutton' === $display ) {
                    return 'radio';
                }
                if ( 'images' === $display ) {
                    return 'swatch';
                }
                return 'dropdown';
            case 'select':
                return 'dropdown';
            case 'radiobutton':
                return 'radio';
            case 'images':
                return 'swatch';
            case 'checkbox':
                return 'checkbox';
            case 'custom_text':
                return 'text';
            case 'custom_textarea':
                return 'textarea';
            case 'custom_price':
            case 'input_multiplier':
                return 'number';
            case 'datepicker':
                return 'date_picker';
            case 'heading':
                return 'heading';
            default:
                return '';
        }
    }
}
