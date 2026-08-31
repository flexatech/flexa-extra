<?php
namespace Flexa\Extra\Tests\Unit;

use Flexa\Extra\Helpers\Helper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Covers the `style` settings group added in Pha 5: enum clamping for swatch
 * size/shape, hex validation for the button colors (empty = theme default),
 * and that unknown/hostile values fall back to defaults.
 */
#[CoversClass( Helper::class )]
final class SettingsStyleTest extends TestCase {

    public function test_defaults_include_style_group(): void {
        $defaults = Helper::get_default_settings();

        $this->assertArrayHasKey( 'style', $defaults );
        $this->assertSame( 'md', $defaults['style']['swatchSize'] );
        $this->assertSame( 'circle', $defaults['style']['swatchShape'] );
        $this->assertTrue( $defaults['style']['showTooltips'] );
        $this->assertSame( '', $defaults['style']['buttonBg'] );
        $this->assertSame( '', $defaults['style']['buttonActiveText'] );
    }

    public function test_valid_style_values_pass_through(): void {
        $out = Helper::sanitize_settings(
            array(
                'style' => array(
                    'swatchSize'       => 'lg',
                    'swatchShape'      => 'square',
                    'showTooltips'     => false,
                    'buttonBg'         => '#ff0000',
                    'buttonText'       => '#FFFFFF',
                    'buttonActiveBg'   => '#123abc',
                    'buttonActiveText' => '#000',
                ),
            )
        );

        $this->assertSame( 'lg', $out['style']['swatchSize'] );
        $this->assertSame( 'square', $out['style']['swatchShape'] );
        $this->assertFalse( $out['style']['showTooltips'] );
        $this->assertSame( '#ff0000', $out['style']['buttonBg'] );
        $this->assertSame( '#FFFFFF', $out['style']['buttonText'] );
        $this->assertSame( '#123abc', $out['style']['buttonActiveBg'] );
        $this->assertSame( '#000', $out['style']['buttonActiveText'] );
    }

    public function test_invalid_enums_fall_back_to_defaults(): void {
        $out = Helper::sanitize_settings(
            array(
                'style' => array(
                    'swatchSize'  => 'gigantic',
                    'swatchShape' => 'triangle',
                ),
            )
        );

        $this->assertSame( 'md', $out['style']['swatchSize'] );
        $this->assertSame( 'circle', $out['style']['swatchShape'] );
    }

    public function test_invalid_or_empty_hex_normalizes_to_empty_string(): void {
        $out = Helper::sanitize_settings(
            array(
                'style' => array(
                    'buttonBg'       => 'red',            // not a hex
                    'buttonText'     => '',               // explicitly empty
                    'buttonActiveBg' => '#zzzzzz',        // invalid hex
                    'buttonActiveText' => 'javascript:1', // hostile
                ),
            )
        );

        $this->assertSame( '', $out['style']['buttonBg'] );
        $this->assertSame( '', $out['style']['buttonText'] );
        $this->assertSame( '', $out['style']['buttonActiveBg'] );
        $this->assertSame( '', $out['style']['buttonActiveText'] );
    }

    public function test_missing_style_group_uses_all_defaults(): void {
        $out = Helper::sanitize_settings( array( 'general' => array( 'enabled' => true ) ) );

        $this->assertSame( Helper::get_default_settings()['style'], $out['style'] );
    }
}
