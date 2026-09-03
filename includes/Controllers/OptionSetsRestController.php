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
    private const META_ACTIONS   = '_flexa_extra_actions';

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

        // Non-numeric segment: registered before the {id} route so `import` is never
        // captured by the `(?P<id>\d+)` pattern below.
        register_rest_route(
            $this->namespace,
            '/option-sets/import',
            [
                [
                    'methods'             => 'POST',
                    'callback'            => [ $this, 'import' ],
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

        register_rest_route(
            $this->namespace,
            '/option-sets/(?P<id>\d+)/duplicate',
            [
                [
                    'methods'             => 'POST',
                    'callback'            => [ $this, 'duplicate' ],
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
        $result = $this->insert_option_set( $this->get_json_params( $request ) );
        if ( null === $result ) {
            return $this->error( __( 'Could not create the option set.', 'flexa-extra' ), 500 );
        }
        return $this->success( $result, __( 'Option set created.', 'flexa-extra' ) );
    }

    public function duplicate( WP_REST_Request $request ): WP_REST_Response {
        $post = $this->get_option_set_post( (int) $request['id'] );
        if ( ! $post ) {
            return $this->error( __( 'Option set not found.', 'flexa-extra' ), 404 );
        }

        $source = $this->to_array( $post );
        /* translators: %s: name of the option set being copied. */
        $source['name']   = sprintf( __( '%s (copy)', 'flexa-extra' ), (string) $source['name'] );
        $source['status'] = false;

        $result = $this->insert_option_set( $source );
        if ( null === $result ) {
            return $this->error( __( 'Could not duplicate the option set.', 'flexa-extra' ), 500 );
        }
        return $this->success( $result, __( 'Option set duplicated.', 'flexa-extra' ) );
    }

    public function import( WP_REST_Request $request ): WP_REST_Response {
        $items = $this->extract_import_items( $this->get_json_params( $request ) );
        if ( empty( $items ) ) {
            return $this->error( __( 'No option sets were found in the imported file.', 'flexa-extra' ), 422 );
        }

        $created = [];
        foreach ( $items as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }
            $result = $this->insert_option_set( $item );
            if ( null !== $result ) {
                $created[] = $result;
            }
        }

        if ( empty( $created ) ) {
            return $this->error( __( 'None of the option sets could be imported.', 'flexa-extra' ), 422 );
        }

        $count = count( $created );
        return $this->success(
            [
                'items' => $created,
                'count' => $count,
            ],
            sprintf(
                /* translators: %d: number of option sets imported. */
                _n( 'Imported %d option set.', 'Imported %d option sets.', $count, 'flexa-extra' ),
                $count
            )
        );
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
     * Sanitize a raw option-set payload and persist it as a new post.
     *
     * @param array<string,mixed> $raw
     * @return array<string,mixed>|null The stored option set, or null on failure.
     */
    private function insert_option_set( array $raw ): ?array {
        $data = OptionSetSchema::sanitize( $raw );

        $post_id = wp_insert_post(
            [
                'post_type'   => CustomPostType::POST_TYPE,
                'post_title'  => $data['name'],
                'post_status' => 'publish',
            ],
            true
        );

        if ( is_wp_error( $post_id ) ) {
            return null;
        }

        $this->save_meta( $post_id, $data );

        return $this->to_array( get_post( $post_id ) );
    }

    /**
     * Normalize an import payload into a flat list of option-set objects. Accepts
     * our export envelope (`{ items: [...] }`), a single set object, or a bare list.
     *
     * @param array<string,mixed> $payload
     * @return array<int,mixed>
     */
    private function extract_import_items( array $payload ): array {
        if ( isset( $payload['items'] ) && is_array( $payload['items'] ) ) {
            return array_values( $payload['items'] );
        }
        // A single exported option set carries at least one of these keys.
        if ( isset( $payload['name'] ) || isset( $payload['fields'] ) || isset( $payload['targeting'] ) ) {
            return [ $payload ];
        }
        // Otherwise treat the payload itself as a list of sets.
        return array_values( $payload );
    }

    /**
     * @param array{name:string,status:bool,fields:list<array<string,mixed>>,targeting:array<string,mixed>,actions:list<array<string,mixed>>} $data
     */
    private function save_meta( int $post_id, array $data ): void {
        update_post_meta( $post_id, self::META_FIELDS, $data['fields'] );
        update_post_meta( $post_id, self::META_TARGETING, $data['targeting'] );
        update_post_meta( $post_id, self::META_STATUS, $data['status'] ? 1 : 0 );
        update_post_meta( $post_id, self::META_ACTIONS, $data['actions'] );

        do_action( 'flexa_extra/option_set/saved', $post_id, $data );
    }

    /**
     * @return array<string,mixed>
     */
    private function to_array( \WP_Post $post ): array {
        $targeting = get_post_meta( $post->ID, self::META_TARGETING, true );
        $actions   = get_post_meta( $post->ID, self::META_ACTIONS, true );

        return [
            'id'        => $post->ID,
            'name'      => $post->post_title,
            'status'    => (bool) (int) get_post_meta( $post->ID, self::META_STATUS, true ),
            'fields'    => (array) get_post_meta( $post->ID, self::META_FIELDS, true ),
            'targeting' => is_array( $targeting ) ? $targeting : [],
            'actions'   => is_array( $actions ) ? $actions : [],
        ];
    }
}
