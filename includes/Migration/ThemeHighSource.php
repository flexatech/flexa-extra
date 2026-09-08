<?php
namespace Flexa\Extra\Migration;

defined( 'ABSPATH' ) || exit;

/**
 * Imports sections from ThemeHigh "Extra Product Options for WooCommerce"
 * (free), which stores its data as serialized section objects in the
 * `thwepof_custom_sections` option. Each section has a title and a keyed list of
 * field objects.
 *
 * The free version carries no per-option pricing, so imported choices come over
 * as labels only. A section title becomes a heading field. Product/category/tag
 * conditions are flattened into Flexa Extra's targeting where they map cleanly.
 *
 * The source plugin should be active (or at least its classes loadable) during
 * the import so the stored objects deserialize; otherwise there is nothing to
 * read.
 */
final class ThemeHighSource extends AbstractMigrationSource {

    private const OPTION_KEY = 'thwepof_custom_sections';

    public function slug(): string {
        return 'themehigh';
    }

    public function label(): string {
        return 'Extra Product Options (ThemeHigh)';
    }

    public function is_available(): bool {
        $sections = get_option( self::OPTION_KEY );
        return is_array( $sections ) && ! empty( $sections );
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function read_raw(): array {
        $sections = get_option( self::OPTION_KEY );
        if ( ! is_array( $sections ) ) {
            return [];
        }

        // The stored value is a keyed array of section objects (with nested field
        // objects). Round-tripping through JSON flattens the public properties to
        // plain arrays, which is what convert() expects.
        $normalized = json_decode( (string) wp_json_encode( $sections ), true );
        if ( ! is_array( $normalized ) ) {
            return [];
        }

        $sets = [];
        foreach ( $normalized as $section ) {
            if ( is_array( $section ) ) {
                $sets[] = $section;
            }
        }
        return $sets;
    }

    /**
     * @param array<string,mixed> $set
     * @return array{input:array<string,mixed>,warnings:list<string>}
     */
    public function convert( array $set ): array {
        $warnings = [];
        $fields   = [];
        $index    = 0;

        $title = self::str( $set, 'title', self::str( $set, 'name' ) );
        if ( '' !== $title && self::bool( $set, 'show_title', true ) ) {
            $fields[] = [
                'id'    => 'fld_' . ( ++$index ),
                'type'  => 'heading',
                'label' => $title,
                'name'  => '',
                'logic' => self::no_logic(),
            ];
        }

        foreach ( self::arr( $set, 'fields' ) as $raw_field ) {
            if ( ! is_array( $raw_field ) ) {
                continue;
            }
            if ( ! self::bool( $raw_field, 'enabled', true ) ) {
                continue;
            }
            $field = $this->convert_field( $raw_field, ++$index, $warnings );
            if ( null !== $field ) {
                $fields[] = $field;
            }
        }

        $input = [
            'name'      => '' !== $title ? $title : self::str( $set, 'name', __( 'Imported section', 'flexa-extra' ) ),
            'status'    => true,
            'fields'    => $fields,
            'actions'   => [],
            'targeting' => $this->convert_targeting( $set, $warnings ),
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
        $label       = self::str( $raw, 'title' );

        $type = self::map_type( $source_type );
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

        if ( in_array( $source_type, [ 'tel', 'password', 'range', 'timepicker', 'paragraph' ], true ) ) {
            $warnings[] = sprintf(
                /* translators: 1: field label, 2: source field type. */
                __( 'Field "%1$s" (%2$s) was imported as the closest Flexa Extra type.', 'flexa-extra' ),
                $label,
                $source_type
            );
        }

        $field = [
            'id'          => 'fld_' . $index,
            'type'        => $type,
            'label'       => 'paragraph' === $source_type ? self::str( $raw, 'value', $label ) : $label,
            'name'        => self::str( $raw, 'name' ),
            'required'    => self::bool( $raw, 'required' ),
            'placeholder' => self::str( $raw, 'placeholder' ),
            'tooltip'     => '',
            'default'     => self::str( $raw, 'value' ),
            'logic'       => self::no_logic(),
        ];

        if ( 'text' === $type ) {
            $validator           = self::str( $raw, 'validator' );
            $field['textFormat'] = 'email' === $validator ? 'email' : ( 'url' === $source_type ? 'url' : 'text' );
            $field['regex']      = '';
        }

        if ( 'number' === $type ) {
            $field['min']  = null;
            $field['max']  = null;
            $field['step'] = null;
        }

        if ( in_array( $type, [ 'checkbox', 'radio', 'dropdown' ], true ) ) {
            $field['multiple'] = 'checkbox' === $type;
            $field['options']  = $this->convert_options( $raw, $index, $source_type, $label );
        }

        return $field;
    }

    /**
     * ThemeHigh stores choices as a plain list of label strings (no price). A
     * single checkbox/switch has no list, so it becomes one on/off option.
     *
     * @param array<string,mixed> $raw
     * @return list<array<string,mixed>>
     */
    private function convert_options( array $raw, int $field_index, string $source_type, string $label ): array {
        if ( in_array( $source_type, [ 'checkbox', 'switch' ], true ) ) {
            return [ $this->option( $field_index, 1, '' !== $label ? $label : __( 'Yes', 'flexa-extra' ), 'yes' ) ];
        }

        $options = [];
        $i       = 0;
        foreach ( self::arr( $raw, 'options' ) as $opt ) {
            if ( ! is_scalar( $opt ) ) {
                continue;
            }
            ++$i;
            $text      = (string) $opt;
            $options[] = $this->option( $field_index, $i, $text, $text );
        }
        return $options;
    }

    /**
     * @return array<string,mixed>
     */
    private function option( int $field_index, int $i, string $label, string $value ): array {
        return [
            'id'      => 'opt_' . $field_index . '_' . $i,
            'label'   => $label,
            'value'   => $value,
            'default' => false,
            'tooltip' => '',
            'color'   => '',
            'image'   => '',
            'price'   => self::price( 'none', 0.0 ),
        ];
    }

    /**
     * Best-effort mapping of ThemeHigh conditional rules to Flexa targeting. The
     * source nests rules for AND/OR; we flatten to a single any/all list, keeping
     * only product/category/tag equality conditions.
     *
     * @param array<string,mixed> $set
     * @param list<string>        $warnings
     * @return array<string,mixed>
     */
    private function convert_targeting( array $set, array &$warnings ): array {
        $json = self::str( $set, 'conditional_rules_json' );
        $rules = '' !== $json ? json_decode( $json, true ) : self::arr( $set, 'conditional_rules' );

        $conditions = [];
        if ( is_array( $rules ) ) {
            $this->collect_conditions( $rules, $conditions );
        }

        if ( empty( $conditions ) ) {
            return [ 'mode' => 'all', 'productIds' => [], 'match' => 'any', 'conditions' => [] ];
        }

        $warnings[] = __( 'Product conditions were flattened into a single "match any" list; review Product assignment.', 'flexa-extra' );

        return [ 'mode' => 'conditions', 'productIds' => [], 'match' => 'any', 'conditions' => $conditions ];
    }

    /**
     * Recursively pull leaf {subject, comparison, cvalue} rules out of the nested
     * ThemeHigh rule tree, expanding each cvalue into its own Flexa condition.
     *
     * @param array<int|string,mixed> $node
     * @param list<array<string,string>> $out
     */
    private function collect_conditions( array $node, array &$out ): void {
        if ( isset( $node['subject'] ) ) {
            $type = self::map_condition_subject( self::str( $node, 'subject' ) );
            if ( '' !== $type ) {
                $operator = 'not_equals' === self::str( $node, 'comparison' ) ? 'is_not' : 'is';
                $values   = self::arr( $node, 'cvalue' );
                if ( empty( $values ) && isset( $node['cvalue'] ) && is_scalar( $node['cvalue'] ) ) {
                    $values = [ $node['cvalue'] ];
                }
                foreach ( $values as $value ) {
                    if ( is_scalar( $value ) ) {
                        $out[] = [ 'type' => $type, 'operator' => $operator, 'value' => (string) $value ];
                    }
                }
            }
            return;
        }

        foreach ( $node as $child ) {
            if ( is_array( $child ) ) {
                $this->collect_conditions( $child, $out );
            }
        }
    }

    private static function map_condition_subject( string $subject ): string {
        switch ( $subject ) {
            case 'product':
                return 'product';
            case 'category':
                return 'category';
            case 'tag':
                return 'tag';
            default:
                return '';
        }
    }

    private static function map_type( string $source_type ): string {
        switch ( $source_type ) {
            case 'inputtext':
            case 'tel':
            case 'password':
            case 'email':
            case 'url':
            case 'timepicker':
                return 'text';
            case 'textarea':
                return 'textarea';
            case 'number':
            case 'range':
                return 'number';
            case 'select':
                return 'dropdown';
            case 'radio':
                return 'radio';
            case 'checkbox':
            case 'switch':
            case 'checkboxgroup':
                return 'checkbox';
            case 'datepicker':
                return 'date_picker';
            case 'colorpicker':
                return 'color_picker';
            case 'heading':
            case 'paragraph':
                return 'heading';
            default:
                return '';
        }
    }
}
