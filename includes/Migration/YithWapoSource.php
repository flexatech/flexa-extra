<?php
namespace Flexa\Extra\Migration;

defined( 'ABSPATH' ) || exit;

/**
 * Imports add-ons from the free **YITH WooCommerce Product Add-Ons & Extra
 * Options** (YITH WAPO).
 *
 * Unlike the other sources, YITH WAPO does not use posts or options: it keeps
 * its data in its own tables, `{prefix}yith_wapo_blocks` (groups) and
 * `{prefix}yith_wapo_addons` (fields, linked by `block_id`). Each add-on row has
 * a serialized `settings` array (type, title, required) and a serialized
 * `options` array that is column-oriented — parallel lists keyed by
 * `label`, `price`, `price_method`, `price_type`, `color`, and so on. One block
 * becomes one Flexa Extra option set.
 *
 * Reading those tables is the only database-dependent step; the column-oriented
 * options are transposed into per-option rows inside the pure
 * {@see self::convert()} half so the mapping stays unit-testable.
 */
final class YithWapoSource extends AbstractMigrationSource {

    private const BLOCKS_TABLE = 'yith_wapo_blocks';
    private const ADDONS_TABLE  = 'yith_wapo_addons';

    public function slug(): string {
        return 'yith-wapo';
    }

    public function label(): string {
        return 'YITH WooCommerce Product Add-Ons';
    }

    public function is_available(): bool {
        global $wpdb;

        $table = $wpdb->prefix . self::BLOCKS_TABLE;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Detecting a third-party plugin's own table; no WP API exists for it.
        $found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
        if ( $found !== $table ) {
            return false;
        }

        $count = $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Third-party plugin's own table; name from $wpdb->prefix, no user input.
        return is_numeric( $count ) && (int) $count > 0;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function read_raw(): array {
        global $wpdb;

        $blocks_table = $wpdb->prefix . self::BLOCKS_TABLE;
        $addons_table = $wpdb->prefix . self::ADDONS_TABLE;

        $blocks = $wpdb->get_results( "SELECT * FROM `{$blocks_table}` ORDER BY id ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Third-party plugin's own table; name from $wpdb->prefix, no user input.
        if ( ! is_array( $blocks ) ) {
            return [];
        }

        $sets = [];
        foreach ( $blocks as $block ) {
            if ( ! is_array( $block ) ) {
                continue;
            }

            $block_id = isset( $block['id'] ) ? (int) $block['id'] : 0;
            $settings = maybe_unserialize( $block['settings'] ?? '' );
            $settings = is_array( $settings ) ? $settings : [];

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Third-party plugin's own table read during a one-off migration.
            $rows = $wpdb->get_results(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own table name from $wpdb->prefix; block_id is bound with prepare().
                $wpdb->prepare( "SELECT * FROM `{$addons_table}` WHERE block_id = %d ORDER BY priority ASC", $block_id ),
                ARRAY_A
            );

            $addons = [];
            foreach ( is_array( $rows ) ? $rows : [] as $row ) {
                if ( ! is_array( $row ) ) {
                    continue;
                }
                $addons[] = [
                    'settings' => is_array( maybe_unserialize( $row['settings'] ?? '' ) ) ? maybe_unserialize( $row['settings'] ?? '' ) : [],
                    'options'  => is_array( maybe_unserialize( $row['options'] ?? '' ) ) ? maybe_unserialize( $row['options'] ?? '' ) : [],
                ];
            }

            $sets[] = [
                'name'   => self::str( $block, 'name', self::str( $settings, 'title', __( 'Imported block', 'flexa-extra' ) ) ),
                'addons' => $addons,
                'scope'  => $this->block_scope( $block, $settings ),
            ];
        }

        return $sets;
    }

    /**
     * Read a block's product scope. YITH keeps it both on a `product_association`
     * column and inside the serialized `rules`; the rules carry the actual IDs.
     *
     * @param array<string,mixed> $block
     * @param array<string,mixed> $settings
     * @return array{mode:string,productIds:list<int>,categoryIds:list<int>}
     */
    private function block_scope( array $block, array $settings ): array {
        $rules = self::arr( $settings, 'rules' );
        $show  = self::str( $rules, 'show_in', self::str( $block, 'product_association', 'all' ) );

        if ( 'products' === $show ) {
            $ids = array_values( array_filter( array_map( 'intval', self::arr( $rules, 'show_in_products' ) ) ) );
            if ( [] !== $ids ) {
                return [ 'mode' => 'manual', 'productIds' => $ids, 'categoryIds' => [] ];
            }
        }

        if ( 'categories' === $show ) {
            $ids = array_values( array_filter( array_map( 'intval', self::arr( $rules, 'show_in_categories' ) ) ) );
            if ( [] !== $ids ) {
                return [ 'mode' => 'conditions', 'productIds' => [], 'categoryIds' => $ids ];
            }
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

        foreach ( self::arr( $set, 'addons' ) as $raw_field ) {
            if ( ! is_array( $raw_field ) ) {
                continue;
            }
            $field = $this->convert_field( $raw_field, ++$index, $warnings );
            if ( null !== $field ) {
                $fields[] = $field;
            }
        }

        $input = [
            'name'      => self::str( $set, 'name', __( 'Imported block', 'flexa-extra' ) ),
            'status'    => true,
            'fields'    => $fields,
            'actions'   => [],
            'targeting' => $this->convert_targeting( self::arr( $set, 'scope' ), $warnings ),
        ];

        return [ 'input' => $input, 'warnings' => $warnings ];
    }

    /**
     * @param array<string,mixed> $raw   One add-on: { settings, options }.
     * @param list<string>        $warnings
     * @return array<string,mixed>|null
     */
    private function convert_field( array $raw, int $index, array &$warnings ): ?array {
        $settings    = self::arr( $raw, 'settings' );
        $source_type = self::str( $settings, 'type' );
        $label       = self::str( $settings, 'title' );

        if ( in_array( $source_type, [ 'file', 'product' ], true ) ) {
            $warnings[] = sprintf(
                /* translators: 1: field label, 2: source field type. */
                __( 'Field "%1$s" (%2$s) has no equivalent and was skipped.', 'flexa-extra' ),
                $label,
                $source_type
            );
            return null;
        }

        $type = self::map_type( $source_type );
        if ( '' === $type ) {
            if ( 'html_separator' === $source_type ) {
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

        // HTML blocks carry their copy in dedicated keys rather than the title.
        if ( 'heading' === $type && '' === $label ) {
            $label = self::str( $settings, 'heading_text', self::str( $settings, 'text_content' ) );
        }

        $field = [
            'id'          => 'fld_' . $index,
            'type'        => $type,
            'label'       => $label,
            'name'        => '',
            'required'    => self::bool( $settings, 'required' ),
            'placeholder' => self::str( $settings, 'placeholder' ),
            'tooltip'     => self::str( $settings, 'description' ),
            'default'     => '',
            'logic'       => self::no_logic(),
        ];

        if ( 'text' === $type ) {
            $field['textFormat'] = 'text';
            $field['regex']      = '';
        }

        if ( 'number' === $type ) {
            $field['min']  = self::num( $settings, 'numbers_min' );
            $field['max']  = self::num( $settings, 'numbers_max' );
            $field['step'] = null;
        }

        if ( 'color_picker' === $type ) {
            $field['default'] = '';
        }

        if ( in_array( $type, [ 'checkbox', 'radio', 'dropdown', 'swatch' ], true ) ) {
            $field['multiple'] = 'checkbox' === $type;
            $field['options']  = $this->convert_options( self::arr( $raw, 'options' ), $index, 'swatch' === $type );
        }

        return $field;
    }

    /**
     * Transpose YITH's column-oriented options ({ label:[...], price:[...] }) into
     * per-option rows and map each to Flexa Extra's option shape. A list of
     * per-option arrays (as some versions store) is accepted as-is.
     *
     * @param array<int|string,mixed> $options
     * @return list<array<string,mixed>>
     */
    private function convert_options( array $options, int $field_index, bool $is_swatch ): array {
        $rows = $this->option_rows( $options );

        $out = [];
        $i   = 0;
        foreach ( $rows as $row ) {
            ++$i;
            $label  = self::str( $row, 'label' );
            $method = self::str( $row, 'price_method', 'free' );
            $price  = self::price( 'none', 0.0 );

            if ( 'free' !== $method ) {
                $amount = (float) ( self::num( $row, 'price' ) ?? 0.0 );
                if ( 'decrease' === $method ) {
                    $amount = -$amount;
                }
                $ptype = self::str( $row, 'price_type', 'fixed' );
                $price = self::price( 'percentage' === $ptype ? 'percent' : 'fixed', $amount );
            }

            $out[] = [
                'id'      => 'opt_' . $field_index . '_' . $i,
                'label'   => $label,
                'value'   => $label,
                'default' => self::bool( $row, 'default' ),
                'tooltip' => self::str( $row, 'tooltip', self::str( $row, 'description' ) ),
                'color'   => $is_swatch ? self::str( $row, 'color' ) : '',
                'image'   => '',
                'price'   => $price,
            ];
        }
        return $out;
    }

    /**
     * Normalize YITH options into a list of per-option associative arrays. YITH
     * stores them column-oriented (each key is a parallel list); older data may
     * already be a list of arrays.
     *
     * @param array<int|string,mixed> $options
     * @return list<array<string,mixed>>
     */
    private function option_rows( array $options ): array {
        // Already a list of per-option arrays.
        if ( isset( $options[0] ) && is_array( $options[0] ) ) {
            $rows = [];
            foreach ( $options as $row ) {
                if ( is_array( $row ) ) {
                    $rows[] = $row;
                }
            }
            return $rows;
        }

        // Column-oriented: { label: [...], price: [...], ... }. The row count is
        // the length of the labels list.
        $labels = self::arr( $options, 'label' );
        $count  = count( $labels );
        if ( 0 === $count ) {
            return [];
        }

        $rows = [];
        for ( $i = 0; $i < $count; $i++ ) {
            $row = [];
            foreach ( $options as $key => $values ) {
                if ( is_array( $values ) && array_key_exists( $i, $values ) ) {
                    $row[ $key ] = $values[ $i ];
                }
            }
            $rows[] = $row;
        }
        return $rows;
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
     * Map a YITH WAPO add-on type to a Flexa Extra type. Returns '' when there is
     * no equivalent.
     */
    private static function map_type( string $source_type ): string {
        switch ( $source_type ) {
            case 'text':
                return 'text';
            case 'textarea':
                return 'textarea';
            case 'number':
                return 'number';
            case 'select':
                return 'dropdown';
            case 'radio':
                return 'radio';
            case 'checkbox':
                return 'checkbox';
            case 'color':
                return 'swatch';
            case 'colorpicker':
                return 'color_picker';
            case 'date':
                return 'date_picker';
            case 'label':
            case 'html_heading':
            case 'html_text':
                return 'heading';
            default:
                return '';
        }
    }
}
