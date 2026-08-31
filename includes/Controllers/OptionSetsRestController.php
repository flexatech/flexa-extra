<?php
namespace Flexa\Extra\Controllers;

defined( 'ABSPATH' ) || exit;

use Flexa\Extra\Utils\SingletonTrait;
use Flexa\Extra\Engine\Admin\CustomPostType;
use Flexa\Extra\Fields\OptionSetSchema;
use WP_REST_Request;
use WP_REST_Response;

/**
 * CRUD for option sets.
 *
 *   GET    /flexa-extra/v1/option-sets
 *   POST   /flexa-extra/v1/option-sets
 *   GET    /flexa-extra/v1/option-sets/{id}
 *   PUT    /flexa-extra/v1/option-sets/{id}
 *   DELETE /flexa-extra/v1/option-sets/{id}
 *
 * Each option set stores its normalized `fields` array and `targeting` rule in
 * post meta. Every write is routed through {@see OptionSetSchema::sanitize()}
 * so post meta can never hold an unvalidated payload.
 */
final class OptionSetsRestController extends BaseRestController {
    use SingletonTrait;

    private const META_FIELDS    = '_flexa_extra_fields';
    private const META_TARGETING = '_flexa_extra_targeting';
    private const META_STATUS    = '_flexa_extra_status';

    protected function __construct() {
        register_rest_route(
            $this->namespace,
            '/option-sets',
            [
                [
                    'methods'             => 'GET',
                    'callback'            => [ $this, 'index' ],
                    'permission_callback' => [ $this, 'permission_callback' ],
                ],
                [
                    'methods'             => 'POST',
                    'callback'            => [ $this, 'create' ],
                    'permission_callback' => [ $this, 'permission_callback' ],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/option-sets/(?P<id>\d+)',
            [
                [
                    'methods'             => 'GET',
                    'callback'            => [ $this, 'show' ],
                    'permission_callback' => [ $this, 'permission_callback' ],
                    'args'                => [ 'id' => [ 'sanitize_callback' => 'absint' ] ],
                ],
                [
                    'methods'             => 'PUT, PATCH',
                    'callback'            => [ $this, 'update' ],
                    'permission_callback' => [ $this, 'permission_callback' ],
                    'args'                => [ 'id' => [ 'sanitize_callback' => 'absint' ] ],
                ],
                [
                    'methods'             => 'DELETE',
                    'callback'            => [ $this, 'destroy' ],
                    'permission_callback' => [ $this, 'permission_callback' ],
                    'args'                => [ 'id' => [ 'sanitize_callback' => 'absint' ] ],
                ],
            ]
        );
    }

    public function index( WP_REST_Request $request ): WP_REST_Response {
        unset( $request );

        $posts = get_posts(
            [
                'post_type'   => CustomPostType::POST_TYPE,
                'post_status' => 'publish',
                'numberposts' => -1,
                'orderby'     => 'date',
                'order'       => 'DESC',
            ]
        );

        $items = array_map( [ $this, 'to_array' ], $posts );

        return $this->success( [ 'items' => $items ] );
    }

    public function show( WP_REST_Request $request ): WP_REST_Response {
        $post = $this->get_option_set_post( (int) $request['id'] );
        if ( ! $post ) {
            return $this->error( __( 'Option set not found.', 'flexa-extra' ), 404 );
        }
        return $this->success( $this->to_array( $post ) );
    }

    public function create( WP_REST_Request $request ): WP_REST_Response {
        $data = OptionSetSchema::sanitize( $this->get_json_params( $request ) );

        $post_id = wp_insert_post(
            [
                'post_type'   => CustomPostType::POST_TYPE,
                'post_title'  => $data['name'],
                'post_status' => 'publish',
            ],
            true
        );

        if ( is_wp_error( $post_id ) ) {
            return $this->error( $post_id->get_error_message(), 500 );
        }

        $this->save_meta( $post_id, $data );

        return $this->success( $this->to_array( get_post( $post_id ) ), __( 'Option set created.', 'flexa-extra' ) );
    }

    public function update( WP_REST_Request $request ): WP_REST_Response {
        $post = $this->get_option_set_post( (int) $request['id'] );
        if ( ! $post ) {
            return $this->error( __( 'Option set not found.', 'flexa-extra' ), 404 );
        }

        $data = OptionSetSchema::sanitize( $this->get_json_params( $request ) );

        wp_update_post(
            [
                'ID'         => $post->ID,
                'post_title' => $data['name'],
            ]
        );

        $this->save_meta( $post->ID, $data );

        return $this->success( $this->to_array( get_post( $post->ID ) ), __( 'Option set updated.', 'flexa-extra' ) );
    }

    public function destroy( WP_REST_Request $request ): WP_REST_Response {
        $post = $this->get_option_set_post( (int) $request['id'] );
        if ( ! $post ) {
            return $this->error( __( 'Option set not found.', 'flexa-extra' ), 404 );
        }

        wp_delete_post( $post->ID, true );

        return $this->success( [ 'id' => $post->ID ], __( 'Option set deleted.', 'flexa-extra' ) );
    }

    private function get_option_set_post( int $id ): ?\WP_Post {
        $post = $id ? get_post( $id ) : null;
        if ( ! $post || CustomPostType::POST_TYPE !== $post->post_type ) {
            return null;
        }
        return $post;
    }

    /**
     * @param array{name:string,status:bool,fields:list<array<string,mixed>>,targeting:array<string,mixed>} $data
     */
    private function save_meta( int $post_id, array $data ): void {
        update_post_meta( $post_id, self::META_FIELDS, $data['fields'] );
        update_post_meta( $post_id, self::META_TARGETING, $data['targeting'] );
        update_post_meta( $post_id, self::META_STATUS, $data['status'] ? 1 : 0 );

        do_action( 'flexa_extra/option_set/saved', $post_id, $data );
    }

    /**
     * @return array<string,mixed>
     */
    private function to_array( \WP_Post $post ): array {
        $targeting = get_post_meta( $post->ID, self::META_TARGETING, true );

        return [
            'id'        => $post->ID,
            'name'      => $post->post_title,
            'status'    => (bool) (int) get_post_meta( $post->ID, self::META_STATUS, true ),
            'fields'    => (array) get_post_meta( $post->ID, self::META_FIELDS, true ),
            'targeting' => is_array( $targeting ) ? $targeting : [],
        ];
    }
}
