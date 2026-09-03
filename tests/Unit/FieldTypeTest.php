<?php
namespace Flexa\Extra\Tests\Unit;

use Flexa\Extra\Fields\FieldType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( FieldType::class )]
final class FieldTypeTest extends TestCase {

    public function test_known_free_types_are_valid(): void {
        foreach ( array( 'text', 'textarea', 'number', 'date_picker', 'color_picker', 'checkbox', 'radio', 'dropdown', 'swatch', 'button', 'heading' ) as $type ) {
            $this->assertTrue( FieldType::is_valid( $type ), "$type should be valid" );
        }
    }

    public function test_removed_pro_types_are_no_longer_valid(): void {
        $this->assertFalse( FieldType::is_valid( 'file_upload' ) );
        $this->assertFalse( FieldType::is_valid( 'image_upload' ) );
    }

    public function test_unknown_type_is_invalid(): void {
        $this->assertFalse( FieldType::is_valid( 'wysiwyg' ) );
        $this->assertFalse( FieldType::is_valid( '' ) );
    }

    public function test_choice_input_display_partitions_are_disjoint(): void {
        $this->assertTrue( FieldType::is_choice( 'radio' ) );
        $this->assertFalse( FieldType::is_choice( 'text' ) );

        $this->assertTrue( FieldType::is_input( 'number' ) );
        $this->assertTrue( FieldType::is_input( 'date_picker' ) );
        $this->assertTrue( FieldType::is_input( 'color_picker' ) );
        $this->assertFalse( FieldType::is_input( 'radio' ) );

        $this->assertTrue( FieldType::is_display( 'heading' ) );
        $this->assertFalse( FieldType::is_display( 'number' ) );
    }

    public function test_catalog_entries_have_required_shape(): void {
        foreach ( FieldType::catalog() as $entry ) {
            $this->assertArrayHasKey( 'type', $entry );
            $this->assertArrayHasKey( 'label', $entry );
            $this->assertArrayHasKey( 'group', $entry );
            $this->assertContains( $entry['group'], array( 'input', 'choice', 'display' ) );
            $this->assertTrue( FieldType::is_valid( $entry['type'] ) );
        }
    }
}
