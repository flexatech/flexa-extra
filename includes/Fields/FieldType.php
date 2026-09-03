<?php
namespace Flexa\Extra\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Single source of truth for the extra-option field types.
 *
 * Every consumer — the admin builder (via localized config), the REST
 * sanitizer, and the frontend renderer — reads its list of valid types from
 * here. Never hardcode a type string anywhere else.
 */
class FieldType {

    const TEXT         = 'text';
    const TEXTAREA     = 'textarea';
    const NUMBER       = 'number';
    const DATE_PICKER  = 'date_picker';
    const COLOR_PICKER = 'color_picker';
    const CHECKBOX     = 'checkbox';
    const RADIO        = 'radio';
    const DROPDOWN     = 'dropdown';
    const SWATCH       = 'swatch';
    const BUTTON       = 'button';
    const HEADING      = 'heading';

    protected function __construct() {}

    /**
     * Field types that let the shopper pick from a list of choices (each choice
     * can carry its own price / stock / swatch metadata).
     *
     * @return list<string>
     */
    public static function choice_types(): array {
        return array( self::CHECKBOX, self::RADIO, self::DROPDOWN, self::SWATCH, self::BUTTON );
    }

    /**
     * Field types that accept a single free-form input value.
     *
     * @return list<string>
     */
    public static function input_types(): array {
        return array( self::TEXT, self::TEXTAREA, self::NUMBER, self::DATE_PICKER, self::COLOR_PICKER );
    }

    /**
     * Display-only field types (no value, no price).
     *
     * @return list<string>
     */
    public static function display_types(): array {
        return array( self::HEADING );
    }

    /**
     * All field types known to the plugin.
     *
     * @return list<string>
     */
    public static function all(): array {
        $types = array_merge(
            self::input_types(),
            self::choice_types(),
            self::display_types()
        );

        return apply_filters( 'flexa_extra/field/types', $types );
    }

    public static function is_valid( string $type ): bool {
        return in_array( $type, self::all(), true );
    }

    public static function is_choice( string $type ): bool {
        return in_array( $type, self::choice_types(), true );
    }

    public static function is_input( string $type ): bool {
        return in_array( $type, self::input_types(), true );
    }

    public static function is_display( string $type ): bool {
        return in_array( $type, self::display_types(), true );
    }

    /**
     * Descriptor list for the admin builder palette: type + label + which group
     * it belongs to. Kept here (not in JS) so the two never drift.
     *
     * @return list<array{type:string,label:string,group:string}>
     */
    public static function catalog(): array {
        $catalog = array(
            array( 'type' => self::TEXT, 'label' => __( 'Text', 'flexa-extra' ), 'group' => 'input' ),
            array( 'type' => self::TEXTAREA, 'label' => __( 'Paragraph', 'flexa-extra' ), 'group' => 'input' ),
            array( 'type' => self::NUMBER, 'label' => __( 'Number', 'flexa-extra' ), 'group' => 'input' ),
            array( 'type' => self::DATE_PICKER, 'label' => __( 'Date picker', 'flexa-extra' ), 'group' => 'input' ),
            array( 'type' => self::COLOR_PICKER, 'label' => __( 'Color picker', 'flexa-extra' ), 'group' => 'input' ),
            array( 'type' => self::CHECKBOX, 'label' => __( 'Checkboxes', 'flexa-extra' ), 'group' => 'choice' ),
            array( 'type' => self::RADIO, 'label' => __( 'Radio buttons', 'flexa-extra' ), 'group' => 'choice' ),
            array( 'type' => self::DROPDOWN, 'label' => __( 'Dropdown', 'flexa-extra' ), 'group' => 'choice' ),
            array( 'type' => self::SWATCH, 'label' => __( 'Color / image swatch', 'flexa-extra' ), 'group' => 'choice' ),
            array( 'type' => self::BUTTON, 'label' => __( 'Buttons', 'flexa-extra' ), 'group' => 'choice' ),
            array( 'type' => self::HEADING, 'label' => __( 'Heading / description', 'flexa-extra' ), 'group' => 'display' ),
        );

        return apply_filters( 'flexa_extra/field/catalog', $catalog );
    }
}
