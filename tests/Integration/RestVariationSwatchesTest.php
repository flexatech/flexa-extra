<?php
namespace Flexa\Extra\Tests\Integration;

use Flexa\Extra\Controllers\VariationSwatchesRestController;
use Flexa\Extra\Variations\AttributeType;
use Flexa\Extra\Variations\TermMeta;
use ReflectionClass;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Exercises the variation-swatches REST controller against the real REST
 * server: listing global attributes, setting a swatch type, writing per-term
 * color / image, and the GET -> POST -> GET round-trip.
 */
final class RestVariationSwatchesTest extends IntegrationTestCase {

	private WP_REST_Server $server;
	private string $taxonomy = 'pa_color';
	private int $term_id;

	protected function setUp(): void {
		parent::setUp();

		$ref  = new ReflectionClass( VariationSwatchesRestController::class );
		$prop = $ref->getProperty( 'instance' );
		$prop->setValue( null, null );

		global $wp_rest_server;
		$this->server   = new WP_REST_Server();
		$wp_rest_server = $this->server;
		do_action( 'rest_api_init' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		// A real global attribute + taxonomy + one term.
		wc_create_attribute( array( 'name' => 'Color', 'slug' => 'color' ) );
		register_taxonomy( $this->taxonomy, array( 'product' ), array( 'hierarchical' => false ) );
		$term          = wp_insert_term( 'Red', $this->taxonomy );
		$this->term_id = (int) $term['term_id'];

		update_option( AttributeType::OPTION_KEY, array() );
	}

	protected function tearDown(): void {
		global $wp_rest_server;
		$wp_rest_server = null;
		delete_option( AttributeType::OPTION_KEY );
		parent::tearDown();
	}

	/**
	 * @param array<string,mixed> $body
	 */
	private function json_request( string $method, string $route, array $body = array() ): WP_REST_Request {
		$request = new WP_REST_Request( $method, $route );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $body ) );
		return $request;
	}

	public function test_routes_are_registered(): void {
		$routes = $this->server->get_routes();
		$this->assertArrayHasKey( '/flexa-extra/v1/variation-swatches/attributes', $routes );
		$this->assertArrayHasKey( '/flexa-extra/v1/variation-swatches/attribute', $routes );
		$this->assertArrayHasKey( '/flexa-extra/v1/variation-swatches/term', $routes );
	}

	public function test_get_lists_attribute_with_terms_and_default_off(): void {
		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/flexa-extra/v1/variation-swatches/attributes' ) );

		$this->assertSame( 200, $response->get_status() );
		$attributes = $response->get_data()['data']['attributes'];

		$mine = array_values( array_filter( $attributes, fn( $a ) => $this->taxonomy === $a['taxonomy'] ) );
		$this->assertCount( 1, $mine );
		$this->assertSame( AttributeType::TYPE_OFF, $mine[0]['type'] );

		$terms = array_values( array_filter( $mine[0]['terms'], fn( $t ) => $this->term_id === $t['id'] ) );
		$this->assertCount( 1, $terms );
		$this->assertSame( 'Red', $terms[0]['name'] );
		$this->assertSame( '', $terms[0]['color'] );
	}

	public function test_set_attribute_type_persists_and_round_trips(): void {
		$post = $this->server->dispatch(
			$this->json_request( 'POST', '/flexa-extra/v1/variation-swatches/attribute', array( 'taxonomy' => $this->taxonomy, 'type' => 'color' ) )
		);

		$this->assertSame( 200, $post->get_status() );
		$this->assertSame( 'color', $post->get_data()['data']['type'] );
		$this->assertSame( 'color', AttributeType::get_type( $this->taxonomy ) );

		$get  = $this->server->dispatch( new WP_REST_Request( 'GET', '/flexa-extra/v1/variation-swatches/attributes' ) );
		$mine = array_values( array_filter( $get->get_data()['data']['attributes'], fn( $a ) => $this->taxonomy === $a['taxonomy'] ) );
		$this->assertSame( 'color', $mine[0]['type'] );
	}

	public function test_set_attribute_off_clears_mapping(): void {
		AttributeType::set_type( $this->taxonomy, 'button' );

		$response = $this->server->dispatch(
			$this->json_request( 'POST', '/flexa-extra/v1/variation-swatches/attribute', array( 'taxonomy' => $this->taxonomy, 'type' => 'off' ) )
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( AttributeType::TYPE_OFF, AttributeType::get_type( $this->taxonomy ) );
		$this->assertArrayNotHasKey( $this->taxonomy, AttributeType::all() );
	}

	public function test_unknown_attribute_is_400(): void {
		$response = $this->server->dispatch(
			$this->json_request( 'POST', '/flexa-extra/v1/variation-swatches/attribute', array( 'taxonomy' => 'pa_nope', 'type' => 'color' ) )
		);
		$this->assertSame( 400, $response->get_status() );
	}

	public function test_set_term_writes_color(): void {
		$response = $this->server->dispatch(
			$this->json_request( 'POST', '/flexa-extra/v1/variation-swatches/term', array( 'term_id' => $this->term_id, 'color' => '#ff0000' ) )
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( '#ff0000', $response->get_data()['data']['color'] );
		$this->assertSame( '#ff0000', TermMeta::get_color( $this->term_id ) );
	}

	public function test_set_term_empty_color_clears_it(): void {
		TermMeta::set_color( $this->term_id, '#abcdef' );

		$response = $this->server->dispatch(
			$this->json_request( 'POST', '/flexa-extra/v1/variation-swatches/term', array( 'term_id' => $this->term_id, 'color' => '' ) )
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( '', TermMeta::get_color( $this->term_id ) );
	}

	public function test_set_term_unknown_id_is_400(): void {
		$response = $this->server->dispatch(
			$this->json_request( 'POST', '/flexa-extra/v1/variation-swatches/term', array( 'term_id' => 99999999, 'color' => '#ff0000' ) )
		);
		$this->assertSame( 400, $response->get_status() );
	}

	public function test_requires_manage_woocommerce(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/flexa-extra/v1/variation-swatches/attributes' ) );
		$this->assertSame( 403, $response->get_status() );
	}
}
