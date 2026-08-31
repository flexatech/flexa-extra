<?php
namespace Flexa\Extra\Tests\Integration;

use Flexa\Extra\Controllers\OptionSetsRestController;
use Flexa\Extra\Controllers\ResourcesRestController;
use Flexa\Extra\Controllers\SettingsRestController;
use ReflectionClass;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Exercises the option-sets REST controller against the real REST server:
 * routing, permissions, schema-sanitized persistence and CRUD.
 */
final class RestOptionSetsTest extends IntegrationTestCase {

    private WP_REST_Server $server;

    protected function setUp(): void {
        parent::setUp();

        // Controllers register routes in their (singleton) constructors, so a
        // fresh REST server needs the singletons reset to re-register onto it.
        foreach ( array( SettingsRestController::class, OptionSetsRestController::class, ResourcesRestController::class ) as $controller ) {
            $ref  = new ReflectionClass( $controller );
            $prop = $ref->getProperty( 'instance' );
            $prop->setValue( null, null );
        }

        global $wp_rest_server;
        $this->server   = new WP_REST_Server();
        $wp_rest_server = $this->server;
        do_action( 'rest_api_init' );

        wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
    }

    protected function tearDown(): void {
        global $wp_rest_server;
        $wp_rest_server = null;
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
        $this->assertArrayHasKey( '/flexa-extra/v1/option-sets', $routes );
        $this->assertArrayHasKey( '/flexa-extra/v1/option-sets/(?P<id>\d+)', $routes );
    }

    public function test_create_persists_sanitized_option_set(): void {
        $response = $this->server->dispatch(
            $this->json_request(
                'POST',
                '/flexa-extra/v1/option-sets',
                array(
                    'name'   => 'Gift options',
                    'status' => true,
                    'fields' => array(
                        array( 'type' => 'text', 'id' => 'msg', 'label' => 'Message' ),
                        array( 'type' => 'malware', 'id' => 'x', 'label' => 'X' ), // must be dropped
                    ),
                    'targeting' => array( 'mode' => 'all' ),
                )
            )
        );

        $this->assertSame( 200, $response->get_status() );
        $data = $response->get_data()['data'];

        $this->assertNotEmpty( $data['id'] );
        $this->assertSame( 'Gift options', $data['name'] );
        $this->assertTrue( $data['status'] );
        $this->assertCount( 1, $data['fields'] ); // unknown type dropped by the sanitizer
        $this->assertSame( 'text', $data['fields'][0]['type'] );
    }

    public function test_index_returns_created_sets(): void {
        $this->register_option_set(
            array( 'name' => 'A', 'status' => true, 'targeting' => array( 'mode' => 'all' ), 'fields' => array( array( 'type' => 'text', 'id' => 'a', 'label' => 'A' ) ) )
        );

        $response = $this->server->dispatch( new WP_REST_Request( 'GET', '/flexa-extra/v1/option-sets' ) );

        $this->assertSame( 200, $response->get_status() );
        $items = $response->get_data()['data']['items'];
        $this->assertNotEmpty( $items );
    }

    public function test_update_changes_name_and_fields(): void {
        $id = $this->register_option_set(
            array( 'name' => 'Before', 'status' => true, 'targeting' => array( 'mode' => 'all' ), 'fields' => array( array( 'type' => 'text', 'id' => 'a', 'label' => 'A' ) ) )
        );

        $response = $this->server->dispatch(
            $this->json_request(
                'PUT',
                '/flexa-extra/v1/option-sets/' . $id,
                array( 'name' => 'After', 'status' => false, 'fields' => array(), 'targeting' => array( 'mode' => 'all' ) )
            )
        );

        $this->assertSame( 200, $response->get_status() );
        $this->assertSame( 'After', get_the_title( $id ) );
        $this->assertFalse( (bool) (int) get_post_meta( $id, '_flexa_extra_status', true ) );
    }

    public function test_delete_removes_the_set(): void {
        $id = $this->register_option_set(
            array( 'name' => 'Temp', 'status' => true, 'targeting' => array( 'mode' => 'all' ), 'fields' => array( array( 'type' => 'text', 'id' => 'a', 'label' => 'A' ) ) )
        );

        $response = $this->server->dispatch( new WP_REST_Request( 'DELETE', '/flexa-extra/v1/option-sets/' . $id ) );

        $this->assertSame( 200, $response->get_status() );
        $this->assertNull( get_post( $id ) );
    }

    public function test_requires_manage_options(): void {
        wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

        $response = $this->server->dispatch( new WP_REST_Request( 'GET', '/flexa-extra/v1/option-sets' ) );

        $this->assertSame( 403, $response->get_status() );
    }
}
