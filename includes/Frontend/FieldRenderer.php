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
     * @param array<string,mixed> $field
     */
    public static function render( array $field, WC_Product $product ): string {
        $type = isset( $field['type'] ) ? (string) $field['type'] : '';
        if ( ! FieldType::is_valid( $type ) || FieldType::is_pro( $type ) ) {
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
            $body = self::render_input( $type, $id, $field, $product );
        } elseif ( FieldType::is_choice( $type ) ) {
            $legend  = $is_group ? self::label_inner( $label, $required, $tooltip ) : '';
            $body    = self::render_choices( $type, $id, $field, $product, $legend );
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
     */
    private static function render_input( string $type, string $id, array $field, WC_Product $product ): string {
        $name        = self::input_name( $id );
        $input_id    = self::input_id( $id );
        $placeholder = isset( $field['placeholder'] ) ? (string) $field['placeholder'] : '';
        $default     = isset( $field['default'] ) ? (string) $field['default'] : '';
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
        } else {
            $input_type = self::text_input_type( isset( $field['textFormat'] ) ? (string) $field['textFormat'] : OptionSetSchema::TEXT_PLAIN );
            $control    = sprintf( '<input type="%1$s" %2$s value="%3$s" />', esc_attr( $input_type ), $common, esc_attr( $default ) );
        }

        return $control . $hint;
    }

    /**
     * @param array<string,mixed> $field
     */
    private static function render_choices( string $type, string $id, array $field, WC_Product $product, string $legend = '' ): string {
        $options  = isset( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : array();
        $multiple = ! empty( $field['multiple'] ) || FieldType::CHECKBOX === $type;
        $required = ! empty( $field['required'] );

        if ( empty( $options ) ) {
            return '';
        }

        if ( FieldType::DROPDOWN === $type ) {
            return self::render_dropdown( $id, $options, $field, $product, $multiple );
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
            $checked   = ! empty( $option['default'] );
            $hint      = self::price_hint( isset( $option['price'] ) && is_array( $option['price'] ) ? $option['price'] : array(), $product );
            $field_uid = self::input_id( $id ) . '-' . sanitize_html_class( $option_id );

            $input = sprintf(
                '<input type="%1$s" name="%2$s" id="%3$s" value="%4$s"%5$s%6$s data-price-option="%7$s" />',
                $multiple ? 'checkbox' : 'radio',
                esc_attr( $name ),
                esc_attr( $field_uid ),
                esc_attr( $value ),
                $checked ? ' checked' : '',
                $required ? ' aria-required="true"' : '',
                esc_attr( $option_id )
            );

            if ( FieldType::SWATCH === $type ) {
                $rows .= self::render_swatch_row( $field_uid, $input, $option, $label, $hint );
            } elseif ( FieldType::BUTTON === $type ) {
                $rows .= sprintf(
                    '<label class="flexa-extra-button" for="%1$s">%2$s<span class="flexa-extra-button__label">%3$s</span>%4$s</label>',
                    esc_attr( $field_uid ),
                    $input,
                    esc_html( $label ),
                    $hint
                );
            } else {
                $rows .= sprintf(
                    '<label class="flexa-extra-choice" for="%1$s">%2$s<span class="flexa-extra-choice__label">%3$s</span>%4$s</label>',
                    esc_attr( $field_uid ),
                    $input,
                    esc_html( $label ),
                    $hint
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
    private static function render_swatch_row( string $field_uid, string $input, array $option, string $label, string $hint ): string {
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
            '<label class="flexa-extra-swatch" for="%1$s" title="%2$s">%3$s%4$s<span class="flexa-extra-swatch__label">%2$s</span>%5$s</label>',
            esc_attr( $field_uid ),
            esc_attr( $label ),
            $input,
            $swatch,
            $hint
        );
    }

    /**
     * @param list<mixed>         $options
     * @param array<string,mixed> $field
     */
    private static function render_dropdown( string $id, array $options, array $field, WC_Product $product, bool $multiple ): string {
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
            $selected  = ! empty( $option['default'] );
            $hint      = self::price_hint_text( isset( $option['price'] ) && is_array( $option['price'] ) ? $option['price'] : array(), $product );

            $opts .= sprintf(
                '<option value="%1$s"%2$s data-price-option="%3$s">%4$s%5$s</option>',
                esc_attr( $value ),
                $selected ? ' selected' : '',
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
     * @param array<string,mixed> $option
     */
    private static function choice_value( array $option, string $fallback ): string {
        $value = isset( $option['value'] ) ? (string) $option['value'] : '';
        return '' !== $value ? $value : $fallback;
    }

    private static function input_name( string $id ): string {
        return 'flexa_extra[' . $id . ']';
    }

    private static function input_id( string $id ): string {
        return 'flexa-extra-' . sanitize_html_class( $id );
    }
}
