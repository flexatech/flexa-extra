<?php
namespace Flexa\Extra\Tests\Integration;

use Flexa\Extra\Variations\AttributeType;
use Flexa\Extra\Variations\SwatchRenderer;
use Flexa\Extra\Variations\TermMeta;

/**
 * The variation-swatch overlay against a real attribute taxonomy: the native
 * <select> is kept but hidden, and a swatch list is rendered with one item per
 * option, the stored color / image, and the selected term marked.
 */
final class VariationSwatchRenderTest extends IntegrationTestCase {

	private string $taxonomy = 'pa_color';
	private int $red;
	private int $blue;

	protected function setUp(): void {
		parent::setUp();

		wc_create_attribute( array( 'name' => 'Color', 'slug' => 'color' ) );
		register_taxonomy( $this->taxonomy, array( 'product' ), array( 'hierarchical' => false ) );
		$this->red  = (int) wp_insert_term( 'Red', $this->taxonomy, array( 'slug' => 'red' ) )['term_id'];
		$this->blue = (int) wp_insert_term( 'Blue', $this->taxonomy, array( 'slug' => 'blue' ) )['term_id'];

		update_option( AttributeType::OPTION_KEY, array() );
	}

	protected function tearDown(): void {
		delete_option( AttributeType::OPTION_KEY );
		parent::tearDown();
	}

	/**
	 * @param array<string,mixed> $extra
	 * @return array<string,mixed>
	 */
	private function args( array $extra = array() ): array {
		return array_merge(
			array(
				'attribute' => $this->taxonomy,
				'options'   => array( 'red', 'blue' ),
				'selected'  => 'red',
			),
			$extra
		);
	}

	private function select_html(): string {
		return '<select id="pa_color" class="" name="attribute_pa_color" data-attribute_name="attribute_pa_color"><option value="">Choose</option><option value="red">Red</option><option value="blue">Blue</option></select>';
	}

	public function test_off_attribute_is_returned_unchanged(): void {
		$renderer = SwatchRenderer::get_instance();
		$html     = $this->select_html();

		$this->assertSame( $html, $renderer->render_dropdown( $html, $this->args() ) );
	}

	public function test_color_type_renders_hidden_select_and_swatch_list(): void {
		AttributeType::set_type( $this->taxonomy, 'color' );
		TermMeta::set_color( $this->red, '#ff0000' );

		$out = SwatchRenderer::get_instance()->render_dropdown( $this->select_html(), $this->args() );

		// Original select kept but hidden.
		$this->assertStringContainsString( 'data-flexa-raw="1"', $out );
		$this->assertStringContainsString( 'display:none', $out );
		// Swatch list of the right type with both options.
		$this->assertStringContainsString( 'flexa-extra-vswatch--color', $out );
		$this->assertStringContainsString( 'data-value="red"', $out );
		$this->assertStringContainsString( 'data-value="blue"', $out );
		// Stored color applied, selected term marked.
		$this->assertStringContainsString( 'background-color:#ff0000', $out );
		$this->assertStringContainsString( 'aria-checked="true"', $out );
		$this->assertStringContainsString( 'aria-checked="false"', $out );
	}

	public function test_button_type_uses_term_labels(): void {
		AttributeType::set_type( $this->taxonomy, 'button' );

		$out = SwatchRenderer::get_instance()->render_dropdown( $this->select_html(), $this->args() );

		$this->assertStringContainsString( 'flexa-extra-vswatch--button', $out );
		$this->assertStringContainsString( 'flexa-extra-vswatch__label">Red<', $out );
		$this->assertStringContainsString( 'flexa-extra-vswatch__label">Blue<', $out );
	}

	public function test_image_type_falls_back_to_label_without_image(): void {
		AttributeType::set_type( $this->taxonomy, 'image' );

		$out = SwatchRenderer::get_instance()->render_dropdown( $this->select_html(), $this->args() );

		$this->assertStringContainsString( 'flexa-extra-vswatch--image', $out );
		// Red has no image assigned -> label fallback, not an <img>.
		$this->assertStringContainsString( 'flexa-extra-vswatch__label">Red<', $out );
	}

	public function test_item_html_filter_can_override_inner_markup(): void {
		AttributeType::set_type( $this->taxonomy, 'button' );

		add_filter(
			'flexa_extra/variation_swatches/item_html',
			static fn( $inner, $term ) => '<span class="x">' . $term->slug . '</span>',
			10,
			2
		);

		$out = SwatchRenderer::get_instance()->render_dropdown( $this->select_html(), $this->args() );

		remove_all_filters( 'flexa_extra/variation_swatches/item_html' );

		$this->assertStringContainsString( '<span class="x">red</span>', $out );
	}

	public function test_defer_guard_returns_html_unchanged(): void {
		AttributeType::set_type( $this->taxonomy, 'color' );
		add_filter( 'flexa_extra/variation_swatches/defer_to_wvs', '__return_true' );

		$renderer = SwatchRenderer::get_instance();
		$html     = $this->select_html();
		$out      = $renderer->render_dropdown( $html, $this->args() );

		$this->assertTrue( $renderer->should_defer() );
		$this->assertSame( $html, $out );

		remove_all_filters( 'flexa_extra/variation_swatches/defer_to_wvs' );
	}

	public function test_defer_state_is_advertised_to_admin_config(): void {
		add_filter( 'flexa_extra/variation_swatches/defer_to_wvs', '__return_true' );

		$config = SwatchRenderer::get_instance()->advertise_defer_state( array() );

		$this->assertTrue( $config['variation_swatches_deferred'] );

		remove_all_filters( 'flexa_extra/variation_swatches/defer_to_wvs' );
	}

	public function test_disabled_module_returns_html_unchanged(): void {
		AttributeType::set_type( $this->taxonomy, 'color' );
		add_filter( 'flexa_extra/variation_swatches/enabled', '__return_false' );

		$html = $this->select_html();
		$out  = SwatchRenderer::get_instance()->render_dropdown( $html, $this->args() );

		remove_all_filters( 'flexa_extra/variation_swatches/enabled' );

		$this->assertSame( $html, $out );
	}
}
