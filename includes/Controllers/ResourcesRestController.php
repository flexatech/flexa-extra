<?php
namespace Flexa\Extra\Controllers;

defined( 'ABSPATH' ) || exit;

use Flexa\Extra\Utils\SingletonTrait;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Read-only lookups the builder needs to assign an option set to products.
 *
 *   GET /flexa-extra/v1/search?type=product|category|tag&q=...
 *   GET /flexa-extra/v1/resolve?type=product&ids=1,2,3
 *
 * `search` powers the async pickers; `resolve` turns stored ids back into
 * labels when an existing option set is reopened.
 */
final class ResourcesRestController extends BaseRestController {
    use SingletonTrait;

    protected function __construct() {
        register_rest_route(
            $this->namespace,
            '/search',
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'search' ],
                'permission_callback' => [ $this, 'permission_callback' ],
                'args'                => [
                    'type' => [ 'sanitize_callback' => 'sanitize_key' ],
                    'q'    => [ 'sanitize_callback' => 'sanitize_text_field' ],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/resolve',
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'resolve' ],
                'permission_callback' => [ $this, 'permission_callback' ],
                'args'                => [
                    'type' => [ 'sanitize_callback' => 'sanitize_key' ],
                    'ids'  => [ 'sanitize_callback' => 'sanitize_text_field' ],
                ],
            ]
        );
    }

    public function search( WP_REST_Request $request ): WP_REST_Response {
        $type = (string) $request->get_param( 'type' );
        $term = (string) $request->get_param( 'q' );

        if ( 'product' === $type ) {
            return $this->success( [ 'items' => $this->search_products( $term ) ] );
        }

        $taxonomy = $this->taxonomy_for( $type );
        if ( $taxonomy ) {
            return $this->success( [ 'items' => $this->search_terms( $taxonomy, $term ) ] );
        }

        return $this->error( __( 'Unknown search type.', 'flexa-extra' ), 400 );
    }

    public function resolve( WP_REST_Request $request ): WP_REST_Response {
        $type = (string) $request->get_param( 'type' );
        $ids  = array_filter( array_map( 'absint', explode( ',', (string) $request->get_param( 'ids' ) ) ) );

        if ( empty( $ids ) ) {
            return $this->success( [ 'items' => [] ] );
        }

        if ( 'product' === $type ) {
            $items = [];
            foreach ( $ids as $id ) {
                $product = function_exists( 'wc_get_product' ) ? wc_get_product( $id ) : null;
                if ( $product ) {
                    $items[] = [ 'id' => $id, 'label' => $product->get_name() ];
                }
            }
            return $this->success( [ 'items' => $items ] );
        }

        $taxonomy = $this->taxonomy_for( $type );
        if ( $taxonomy ) {
            $items = [];
            foreach ( $ids as $id ) {
                $term = get_term( $id, $taxonomy );
                if ( $term && ! is_wp_error( $term ) ) {
                    $items[] = [ 'id' => $id, 'label' => $term->name ];
                }
            }
            return $this->success( [ 'items' => $items ] );
        }

        return $this->error( __( 'Unknown resolve type.', 'flexa-extra' ), 400 );
    }

    /**
     * @return list<array{id:int,label:string}>
     */
    private function search_products( string $term ): array {
        $query = new \WP_Query(
            [
                'post_type'      => 'product',
                'post_status'    => 'publish',
                's'              => $term,
                'posts_per_page' => 20,
                'orderby'        => 'title',
                'order'          => 'ASC',
                'fields'         => 'ids',
            ]
        );

        $items = [];
        foreach ( $query->posts as $id ) {
            $items[] = [ 'id' => (int) $id, 'label' => get_the_title( $id ) ];
        }
        return $items;
    }

    /**
     * @return list<array{id:int,label:string}>
     */
    private function search_terms( string $taxonomy, string $term ): array {
        $terms = get_terms(
            [
                'taxonomy'   => $taxonomy,
                'hide_empty' => false,
                'search'     => $term,
                'number'     => 20,
            ]
        );

        if ( is_wp_error( $terms ) ) {
            return [];
        }

        $items = [];
        foreach ( $terms as $t ) {
            $items[] = [ 'id' => (int) $t->term_id, 'label' => $t->name ];
        }
        return $items;
    }

    private function taxonomy_for( string $type ): ?string {
        $map = [
            'category' => 'product_cat',
            'tag'      => 'product_tag',
        ];
        return $map[ $type ] ?? null;
    }
}
