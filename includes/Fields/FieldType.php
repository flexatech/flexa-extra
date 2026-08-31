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

    // Free field types.
    const TEXT     = 'text';
    const TEXTAREA = 'textarea';
    const NUMBER   = 'number';
    const CHECKBOX = 'checkbox';
    const RADIO    = 'radio';
    const DROPDOWN = 'dropdown';
    const SWATCH   = 'swatch';
    const BUTTON   = 'button';
    const HEADING  = 'heading';

    // Pro field types (declared so the schema knows them; render is gated in Pro).
    const DATE_PICKER  = 'date_picker';
    const FILE_UPLOAD  = 'file_upload';
    const IMAGE_UPLOAD = 'image_upload';
    const COLOR_PICKER = 'color_picker';

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
        return array( self::TEXT, self::TEXTAREA, self::NUMBER );
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
     * Pro-only field types. Free builds may store them but must not render them.
     *
     * @return list<string>
     */
    public static function pro_types(): array {
        return array( self::DATE_PICKER, self::FILE_UPLOAD, self::IMAGE_UPLOAD, self::COLOR_PICKER );
    }

    /**
     * All field types known to the plugin (free + pro).
     *
     * @return list<string>
     */
    public static function all(): array {
        $types = array_merge(
            self::input_types(),
            self::choice_types(),
            self::display_types(),
            self::pro_types()
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

    public static function is_pro( string $type ): bool {
        return in_array( $type, self::pro_types(), true );
    }

    /**
     * Descriptor list for the admin builder palette: type + label + which group
     * it belongs to. Kept here (not in JS) so the two never drift.
     *
     * @return list<array{type:string,label:string,group:string,pro:bool}>
     */
    public static function catalog(): array {
        $catalog = array(
            array( 'type' => self::TEXT, 'label' => __( 'Text', 'flexa-extra' ), 'group' => 'input', 'pro' => false ),
            array( 'type' => self::TEXTAREA, 'label' => __( 'Paragraph', 'flexa-extra' ), 'group' => 'input', 'pro' => false ),
            array( 'type' => self::NUMBER, 'label' => __( 'Number', 'flexa-extra' ), 'group' => 'input', 'pro' => false ),
            array( 'type' => self::CHECKBOX, 'label' => __( 'Checkboxes', 'flexa-extra' ), 'group' => 'choice', 'pro' => false ),
            array( 'type' => self::RADIO, 'label' => __( 'Radio buttons', 'flexa-extra' ), 'group' => 'choice', 'pro' => false ),
            array( 'type' => self::DROPDOWN, 'label' => __( 'Dropdown', 'flexa-extra' ), 'group' => 'choice', 'pro' => false ),
            array( 'type' => self::SWATCH, 'label' => __( 'Color / image swatch', 'flexa-extra' ), 'group' => 'choice', 'pro' => false ),
            array( 'type' => self::BUTTON, 'label' => __( 'Buttons', 'flexa-extra' ), 'group' => 'choice', 'pro' => false ),
            array( 'type' => self::HEADING, 'label' => __( 'Heading / description', 'flexa-extra' ), 'group' => 'display', 'pro' => false ),
            array( 'type' => self::DATE_PICKER, 'label' => __( 'Date picker', 'flexa-extra' ), 'group' => 'input', 'pro' => true ),
            array( 'type' => self::FILE_UPLOAD, 'label' => __( 'File upload', 'flexa-extra' ), 'group' => 'input', 'pro' => true ),
            array( 'type' => self::IMAGE_UPLOAD, 'label' => __( 'Image upload', 'flexa-extra' ), 'group' => 'input', 'pro' => true ),
            array( 'type' => self::COLOR_PICKER, 'label' => __( 'Color picker', 'flexa-extra' ), 'group' => 'input', 'pro' => true ),
        );

        return apply_filters( 'flexa_extra/field/catalog', $catalog );
    }
}
