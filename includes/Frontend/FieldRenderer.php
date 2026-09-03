<?php
namespace Flexa\Extra\Frontend;

defined( 'ABSPATH' ) || exit;

use Flexa\Extra\Fields\FieldType;
use Flexa\Extra\Fields\OptionSetSchema;
use WC_Product;

/**
 * Renders a single option-set field to storefront HTML.
 *
 * Markup is intentionally the input contract that the cart layer (Pha 4) reads
 * back: every value posts under `flexa_extra[<field_id>]`. Prices shown here
 * are a server-side first paint / no-JS fallback; the storefront JS recomputes
 * the live subtotal from the JSON island, and the server recomputes it again
 * authoritatively on add-to-cart.
 */
class FieldRenderer {

    protected function __construct() {}

    /**
     * @param array<string,mixed>       $field
     * @param string|list<string>|null  $selected Pre-fill value when editing a
     *                                            cart line; null uses the field's
     *                                            configured default.
     */
    public static function render( array $field, WC_Product $product, $selected = null ): string {
        $type = isset( $field['type'] ) ? (string) $field['type'] : '';
        if ( ! FieldType::is_valid( $type ) ) {
            return '';
        }

        $id       = isset( $field['id'] ) ? (string) $field['id'] : '';
        $label    = isset( $field['label'] ) ? (string) $field['label'] : '';
        $required = ! empty( $field['required'] );
        $tooltip  = isset( $field['tooltip'] ) ? (string) $field['tooltip'] : '';

        // Radio/checkbox/swatch/button are multi-input groups → a <fieldset> with
        // a <legend> is the accessible label (a <label for> targets one control).
        // The single-control dropdown keeps a normal <label for>.
        $is_group = FieldType::is_choice( $type ) && FieldType::DROPDOWN !== $type;

        if ( FieldType::HEADING === $type ) {
            $body = self::render_heading( $field );
        } elseif ( FieldType::is_input( $type ) ) {
            $body = self::render_input( $type, $id, $field, $product, self::prefill_scalar( $selected ) );
        } elseif ( FieldType::is_choice( $type ) ) {
            $legend  = $is_group ? self::label_inner( $label, $required, $tooltip ) : '';
            $body    = self::render_choices( $type, $id, $field, $product, $legend, self::prefill_list( $selected ) );
            if ( '' !== $body && ! empty( $field['multiple'] ) ) {
                $body .= self::select_hint( $field );
            }
        } else {
            $body = '';
        }

        if ( '' === $body ) {
            return '';
        }

        $classes = 'flexa-extra-field flexa-extra-field--' . sanitize_html_class( $type );

        $label_html = '';
        if ( FieldType::HEADING !== $type && ! $is_group && '' !== $label ) {
            $label_html = sprintf(
                '<label class="flexa-extra-field__label" for="%1$s">%2$s</label>',
                esc_attr( self::input_id( $id ) ),
                self::label_inner( $label, $required, $tooltip )
            );
        }

        return sprintf(
            '<div class="%1$s" data-field-id="%2$s" data-field-type="%3$s"%4$s>%5$s%6$s</div>',
            esc_attr( $classes ),
            esc_attr( $id ),
            esc_attr( $type ),
            $required ? ' data-required="1"' : '',
            $label_html,
            $body
        );
    }

    /**
     * Inner markup shared by <label> and <legend>: text + required marker +
     * accessible tooltip.
     */
    private static function label_inner( string $label, bool $required, string $tooltip ): string {
        return esc_html( $label )
            . ( $required ? ' <abbr class="flexa-extra-required" title="' . esc_attr__( 'Required', 'flexa-extra' ) . '">*</abbr>' : '' )
            . ( '' !== $tooltip ? ' <span class="flexa-extra-tooltip" tabindex="0" role="note" title="' . esc_attr( $tooltip ) . '" aria-label="' . esc_attr( $tooltip ) . '">?</span>' : '' );
    }

    /**
     * @param array<string,mixed> $field
     */
    private static function render_heading( array $field ): string {
        $label       = isset( $field['label'] ) ? (string) $field['label'] : '';
        $description = isset( $field['default'] ) ? (string) $field['default'] : '';

        $html = '';
        if ( '' !== $label ) {
            $html .= '<div class="flexa-extra-heading__title">' . esc_html( $label ) . '</div>';
        }
        if ( '' !== $description ) {
            $html .= '<div class="flexa-extra-heading__desc">' . esc_html( $description ) . '</div>';
        }
        return $html;
    }

    /**
     * @param array<string,mixed> $field
     * @param string|null         $selected Pre-fill value when editing; overrides the default.
     */
    private static function render_input( string $type, string $id, array $field, WC_Product $product, ?string $selected = null ): string {
        $name        = self::input_name( $id );
        $input_id    = self::input_id( $id );
        $placeholder = isset( $field['placeholder'] ) ? (string) $field['placeholder'] : '';
        $default     = null !== $selected ? $selected : ( isset( $field['default'] ) ? (string) $field['default'] : '' );
        $required    = ! empty( $field['required'] );
        $hint        = self::price_hint( isset( $field['price'] ) && is_array( $field['price'] ) ? $field['price'] : array(), $product );

        $common = sprintf(
            'name="%1$s" id="%2$s" class="flexa-extra-control"%3$s%4$s',
            esc_attr( $name ),
            esc_attr( $input_id ),
            '' !== $placeholder ? ' placeholder="' . esc_attr( $placeholder ) . '"' : '',
            $required ? ' data-required="1" aria-required="true"' : ''
        );

        if ( FieldType::TEXTAREA === $type ) {
            $control = sprintf( '<textarea %1$s rows="3">%2$s</textarea>', $common, esc_textarea( $default ) );
        } elseif ( FieldType::NUMBER === $type ) {
            $attrs = '';
            foreach ( array( 'min', 'max', 'step' ) as $attr ) {
                if ( isset( $field[ $attr ] ) && '' !== $field[ $attr ] ) {
                    $attrs .= ' ' . $attr . '="' . esc_attr( (string) $field[ $attr ] ) . '"';
                }
            }
            $control = sprintf( '<input type="number" %1$s value="%2$s"%3$s />', $common, esc_attr( $default ), $attrs );
        } elseif ( FieldType::DATE_PICKER === $type ) {
            $control = sprintf( '<input type="date" %1$s value="%2$s" />', $common, esc_attr( $default ) );
        } elseif ( FieldType::COLOR_PICKER === $type ) {
            // Native color inputs must carry a valid hex value; fall back to black.
            $color   = sanitize_hex_color( $default );
            $control = sprintf( '<input type="color" %1$s value="%2$s" />', $common, esc_attr( null !== $color ? $color : '#000000' ) );
        } else {
            $input_type = self::text_input_type( isset( $field['textFormat'] ) ? (string) $field['textFormat'] : OptionSetSchema::TEXT_PLAIN );
            $control    = sprintf( '<input type="%1$s" %2$s value="%3$s" />', esc_attr( $input_type ), $common, esc_attr( $default ) );
        }

        return $control . $hint;
    }

    /**
     * @param array<string,mixed> $field
     * @param list<string>|null   $selected Selected values when editing; null uses option defaults.
     */
    private static function render_choices( string $type, string $id, array $field, WC_Product $product, string $legend = '', ?array $selected = null ): string {
        $options  = isset( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : array();
        $multiple = ! empty( $field['multiple'] ) || FieldType::CHECKBOX === $type;
        $required = ! empty( $field['required'] );

        if ( empty( $options ) ) {
            return '';
        }

        if ( FieldType::DROPDOWN === $type ) {
            return self::render_dropdown( $id, $options, $field, $product, $multiple, $selected );
        }

        $name = $multiple ? self::input_name( $id ) . '[]' : self::input_name( $id );
        $rows = '';

        foreach ( $options as $index => $option ) {
            if ( ! is_array( $option ) ) {
                continue;
            }
            $option_id = isset( $option['id'] ) ? (string) $option['id'] : (string) $index;
            $value     = self::choice_value( $option, $option_id );
            $label     = isset( $option['label'] ) ? (string) $option['label'] : $value;
            $stock     = self::option_stock( $option );
            $out       = null !== $stock && $stock <= 0;
            $checked   = self::is_selected( $value, $option, $selected ) && ! $out;
            $hint      = self::price_hint( isset( $option['price'] ) && is_array( $option['price'] ) ? $option['price'] : array(), $product );
            $hint     .= self::stock_note( $out );
            $field_uid = self::input_id( $id ) . '-' . sanitize_html_class( $option_id );
            $out_class = $out ? ' is-out-of-stock' : '';

            $input = sprintf(
                '<input type="%1$s" name="%2$s" id="%3$s" value="%4$s"%5$s%6$s%7$s data-price-option="%8$s" />',
                $multiple ? 'checkbox' : 'radio',
                esc_attr( $name ),
                esc_attr( $field_uid ),
                esc_attr( $value ),
                $checked ? ' checked' : '',
                $out ? ' disabled' : '',
                $required ? ' aria-required="true"' : '',
                esc_attr( $option_id )
            );

            if ( FieldType::SWATCH === $type ) {
                $rows .= self::render_swatch_row( $field_uid, $input, $option, $label, $hint, $out_class );
            } elseif ( FieldType::BUTTON === $type ) {
                $rows .= sprintf(
                    '<label class="flexa-extra-button%5$s" for="%1$s">%2$s<span class="flexa-extra-button__label">%3$s</span>%4$s</label>',
                    esc_attr( $field_uid ),
                    $input,
                    esc_html( $label ),
                    $hint,
                    esc_attr( $out_class )
                );
            } else {
                $rows .= sprintf(
                    '<label class="flexa-extra-choice%5$s" for="%1$s">%2$s<span class="flexa-extra-choice__label">%3$s</span>%4$s</label>',
                    esc_attr( $field_uid ),
                    $input,
                    esc_html( $label ),
                    $hint,
                    esc_attr( $out_class )
                );
            }
        }

        $wrap_class  = 'flexa-extra-choices flexa-extra-choices--' . sanitize_html_class( $type );
        $legend_html = '' !== $legend ? '<legend class="flexa-extra-field__label">' . $legend . '</legend>' : '';

        return sprintf(
            '<fieldset class="%1$s"%2$s>%3$s%4$s</fieldset>',
            esc_attr( $wrap_class ),
            $required ? ' aria-required="true"' : '',
            $legend_html,
            $rows
        );
    }

    /**
     * @param array<string,mixed> $option
     */
    private static function render_swatch_row( string $field_uid, string $input, array $option, string $label, string $hint, string $out_class = '' ): string {
        $color = isset( $option['color'] ) ? (string) $option['color'] : '';
        $image = isset( $option['image'] ) ? (string) $option['image'] : '';

        $swatch = '';
        if ( '' !== $image ) {
            $swatch = '<span class="flexa-extra-swatch__chip" style="background-image:url(' . esc_url( $image ) . ')"></span>';
        } elseif ( '' !== $color ) {
            $swatch = '<span class="flexa-extra-swatch__chip" style="background-color:' . esc_attr( $color ) . '"></span>';
        } else {
            $swatch = '<span class="flexa-extra-swatch__chip"></span>';
        }

        return sprintf(
            '<label class="flexa-extra-swatch%6$s" for="%1$s" title="%2$s">%3$s%4$s<span class="flexa-extra-swatch__label">%2$s</span>%5$s</label>',
            esc_attr( $field_uid ),
            esc_attr( $label ),
            $input,
            $swatch,
            $hint,
            esc_attr( $out_class )
        );
    }

    /**
     * @param list<mixed>         $options
     * @param array<string,mixed> $field
     * @param list<string>|null   $selected Selected values when editing; null uses option defaults.
     */
    private static function render_dropdown( string $id, array $options, array $field, WC_Product $product, bool $multiple, ?array $selected = null ): string {
        $name        = $multiple ? self::input_name( $id ) . '[]' : self::input_name( $id );
        $input_id    = self::input_id( $id );
        $required    = ! empty( $field['required'] );
        $placeholder = isset( $field['placeholder'] ) ? (string) $field['placeholder'] : __( 'Choose an option', 'flexa-extra' );

        $opts = $multiple ? '' : '<option value="">' . esc_html( $placeholder ) . '</option>';

        foreach ( $options as $index => $option ) {
            if ( ! is_array( $option ) ) {
                continue;
            }
            $option_id = isset( $option['id'] ) ? (string) $option['id'] : (string) $index;
            $value     = self::choice_value( $option, $option_id );
            $label     = isset( $option['label'] ) ? (string) $option['label'] : $value;
            $stock     = self::option_stock( $option );
            $out       = null !== $stock && $stock <= 0;
            $is_sel    = self::is_selected( $value, $option, $selected ) && ! $out;
            $hint      = self::price_hint_text( isset( $option['price'] ) && is_array( $option['price'] ) ? $option['price'] : array(), $product );
            if ( $out ) {
                /* translators: appended to a sold-out dropdown option. */
                $hint = trim( $hint . ' ' . __( '(out of stock)', 'flexa-extra' ) );
            }

            $opts .= sprintf(
                '<option value="%1$s"%2$s%3$s data-price-option="%4$s">%5$s%6$s</option>',
                esc_attr( $value ),
                $is_sel ? ' selected' : '',
                $out ? ' disabled' : '',
                esc_attr( $option_id ),
                esc_html( $label ),
                '' !== $hint ? ' ' . esc_html( $hint ) : ''
            );
        }

        return sprintf(
            '<select name="%1$s" id="%2$s" class="flexa-extra-control"%3$s%4$s>%5$s</select>',
            esc_attr( $name ),
            esc_attr( $input_id ),
            $multiple ? ' multiple' : '',
            $required ? ' data-required="1" aria-required="true"' : '',
            $opts
        );
    }

    /**
     * Formatted price badge (HTML) for first paint / no-JS.
     *
     * @param array<string,mixed> $price
     */
    private static function price_hint( array $price, WC_Product $product ): string {
        $text = self::price_hint_text( $price, $product );
        if ( '' === $text ) {
            return '';
        }
        return '<span class="flexa-extra-price">' . esc_html( $text ) . '</span>';
    }

    /**
     * @param array<string,mixed> $price
     */
    private static function price_hint_text( array $price, WC_Product $product ): string {
        $type   = isset( $price['type'] ) ? (string) $price['type'] : OptionSetSchema::PRICE_NONE;
        $amount = isset( $price['amount'] ) ? (float) $price['amount'] : 0.0;

        if ( OptionSetSchema::PRICE_NONE === $type || 0.0 === $amount ) {
            return '';
        }

        $sign = $amount < 0 ? '-' : '+';

        if ( OptionSetSchema::PRICE_PERCENT === $type ) {
            return $sign . abs( $amount ) . '%';
        }

        $formatted = html_entity_decode( wp_strip_all_tags( wc_price( abs( $amount ) ) ), ENT_COMPAT, 'UTF-8' );
        return $sign . $formatted;
    }

    private static function text_input_type( string $format ): string {
        switch ( $format ) {
            case OptionSetSchema::TEXT_EMAIL:
                return 'email';
            case OptionSetSchema::TEXT_URL:
                return 'url';
            default:
                return 'text';
        }
    }

    /**
     * Normalize a stored selection into a scalar pre-fill value for input fields.
     * Returns null when there is nothing to pre-fill (not in edit mode).
     *
     * @param string|list<string>|null $selected
     */
    private static function prefill_scalar( $selected ): ?string {
        if ( null === $selected ) {
            return null;
        }
        if ( is_array( $selected ) ) {
            $first = reset( $selected );
            return false === $first ? '' : (string) $first;
        }
        return (string) $selected;
    }

    /**
     * Normalize a stored selection into a list of selected values for choice
     * fields. Returns null when not in edit mode (so option defaults apply).
     *
     * @param string|list<string>|null $selected
     * @return list<string>|null
     */
    private static function prefill_list( $selected ): ?array {
        if ( null === $selected ) {
            return null;
        }
        if ( is_array( $selected ) ) {
            return array_map( 'strval', $selected );
        }
        return '' === (string) $selected ? array() : array( (string) $selected );
    }

    /**
     * Whether an option should be pre-selected: its stored value is in the edit
     * selection, or (outside edit mode) it carries the configured default flag.
     *
     * @param array<string,mixed> $option
     * @param list<string>|null   $selected
     */
    private static function is_selected( string $value, array $option, ?array $selected ): bool {
        if ( null === $selected ) {
            return ! empty( $option['default'] );
        }
        return in_array( $value, $selected, true );
    }

    /**
     * @param array<string,mixed> $option
     */
    private static function choice_value( array $option, string $fallback ): string {
        $value = isset( $option['value'] ) ? (string) $option['value'] : '';
        return '' !== $value ? $value : $fallback;
    }

    /**
     * Managed stock for a choice option, or null when unlimited.
     *
     * @param array<string,mixed> $option
     */
    private static function option_stock( array $option ): ?int {
        return isset( $option['stock'] ) && is_numeric( $option['stock'] )
            ? max( 0, (int) $option['stock'] )
            : null;
    }

    /**
     * A small helper line telling the shopper how many options to pick, when a
     * min and/or max selection bound is configured on a multi-select field.
     *
     * @param array<string,mixed> $field
     */
    private static function select_hint( array $field ): string {
        $min = isset( $field['minSelect'] ) && is_numeric( $field['minSelect'] ) ? max( 0, (int) $field['minSelect'] ) : null;
        $max = isset( $field['maxSelect'] ) && is_numeric( $field['maxSelect'] ) ? max( 0, (int) $field['maxSelect'] ) : null;

        $text = '';
        if ( null !== $min && $min > 0 && null !== $max && $max > 0 ) {
            if ( $min === $max ) {
                /* translators: %d: exact number of options to choose. */
                $text = sprintf( _n( 'Choose exactly %d option.', 'Choose exactly %d options.', $max, 'flexa-extra' ), $max );
            } else {
                /* translators: 1: minimum, 2: maximum number of options. */
                $text = sprintf( __( 'Choose between %1$d and %2$d options.', 'flexa-extra' ), $min, $max );
            }
        } elseif ( null !== $min && $min > 0 ) {
            /* translators: %d: minimum number of options to choose. */
            $text = sprintf( _n( 'Choose at least %d option.', 'Choose at least %d options.', $min, 'flexa-extra' ), $min );
        } elseif ( null !== $max && $max > 0 ) {
            /* translators: %d: maximum number of options to choose. */
            $text = sprintf( _n( 'Choose up to %d option.', 'Choose up to %d options.', $max, 'flexa-extra' ), $max );
        }

        if ( '' === $text ) {
            return '';
        }

        return '<span class="flexa-extra-select-hint">' . esc_html( $text ) . '</span>';
    }

    /**
     * "Out of stock" badge for a sold-out radio/checkbox/swatch/button option.
     */
    private static function stock_note( bool $out ): string {
        if ( ! $out ) {
            return '';
        }
        return '<span class="flexa-extra-stock flexa-extra-stock--out">' . esc_html__( 'Out of stock', 'flexa-extra' ) . '</span>';
    }

    private static function input_name( string $id ): string {
        return 'flexa_extra[' . $id . ']';
    }

    private static function input_id( string $id ): string {
        return 'flexa-extra-' . sanitize_html_class( $id );
    }
}
