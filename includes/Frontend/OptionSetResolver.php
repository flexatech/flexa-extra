<?php
namespace Flexa\Extra\Frontend;

defined( 'ABSPATH' ) || exit;

use Flexa\Extra\Engine\Admin\CustomPostType;
use WC_Product;

/**
 * Resolves which option sets apply to a given product.
 *
 * An option set applies when it is published, marked active, and its targeting
 * rule matches the product (all / manual / conditions). Results are cached per
 * product for the duration of the request so the renderer and the (later)
 * cart validator/price calculator never re-query.
 */
class OptionSetResolver {

    private const META_FIELDS    = '_flexa_extra_fields';
    private const META_TARGETING = '_flexa_extra_targeting';
    private const META_STATUS    = '_flexa_extra_status';
    private const META_ACTIONS   = '_flexa_extra_actions';

    /**
     * Per-request cache keyed by product id.
     *
     * @var array<int,list<array<string,mixed>>>
     */
    private static $cache = array();

    /**
     * Clear the per-request cache. Useful after a bulk data change and in tests.
     */
    public static function flush_cache(): void {
        self::$cache = array();
    }

    /**
     * Option sets that apply to a product.
     *
     * @return list<array{id:int,name:string,fields:list<mixed>,actions:list<mixed>}>
     */
    public static function for_product( WC_Product $product ): array {
        $product_id = $product->get_id();

        if ( isset( self::$cache[ $product_id ] ) ) {
            return self::$cache[ $product_id ];
        }

        $applicable = array();

        foreach ( self::published_sets() as $post_id ) {
            $targeting = get_post_meta( $post_id, self::META_TARGETING, true );
            $targeting = is_array( $targeting ) ? $targeting : array();

            if ( ! self::matches( $product, $targeting ) ) {
                continue;
            }

            $fields = get_post_meta( $post_id, self::META_FIELDS, true );
            $fields = is_array( $fields ) ? $fields : array();

            if ( empty( $fields ) ) {
                continue;
            }

            $actions = get_post_meta( $post_id, self::META_ACTIONS, true );
            $actions = is_array( $actions ) ? $actions : array();

            $applicable[] = array(
                'id'      => $post_id,
                'name'    => get_the_title( $post_id ),
                'fields'  => array_values( $fields ),
                'actions' => array_values( $actions ),
            );
        }

        /**
         * Filter the option sets applied to a product before rendering.
         *
         * @param list<array{id:int,name:string,fields:list<mixed>,actions:list<mixed>}> $applicable
         * @param WC_Product                                                              $product
         */
        $applicable = apply_filters( 'flexa_extra/resolver/applicable_sets', $applicable, $product );

        self::$cache[ $product_id ] = $applicable;

        return $applicable;
    }

    /**
     * Ids of every active, published option set.
     *
     * @return list<int>
     */
    private static function published_sets(): array {
        $posts = get_posts(
            array(
                'post_type'   => CustomPostType::POST_TYPE,
                'post_status' => 'publish',
                'numberposts' => -1,
                'fields'      => 'ids',
                // Option sets are a small, admin-managed set; this runs once per
                // request and the result is memoized in self::$cache.
                'meta_key'    => self::META_STATUS, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Small admin-managed dataset, cached per request.
                'meta_value'  => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Small admin-managed dataset, cached per request.
            )
        );

        return array_map( 'intval', $posts );
    }

    /**
     * @param array<string,mixed> $targeting
     */
    private static function matches( WC_Product $product, array $targeting ): bool {
        $mode = isset( $targeting['mode'] ) ? (string) $targeting['mode'] : 'all';

        if ( 'all' === $mode ) {
            return true;
        }

        if ( 'manual' === $mode ) {
            $ids = isset( $targeting['productIds'] ) && is_array( $targeting['productIds'] )
                ? array_map( 'intval', $targeting['productIds'] )
                : array();

            return in_array( $product->get_id(), $ids, true )
                || in_array( (int) $product->get_parent_id(), $ids, true );
        }

        if ( 'conditions' === $mode ) {
            $conditions = isset( $targeting['conditions'] ) && is_array( $targeting['conditions'] )
                ? $targeting['conditions']
                : array();

            if ( empty( $conditions ) ) {
                return false;
            }

            $match_all = isset( $targeting['match'] ) && 'all' === $targeting['match'];

            foreach ( $conditions as $condition ) {
                if ( ! is_array( $condition ) ) {
                    continue;
                }
                $result = self::condition_matches( $product, $condition );

                if ( $match_all && ! $result ) {
                    return false;
                }
                if ( ! $match_all && $result ) {
                    return true;
                }
            }

            return $match_all;
        }

        return false;
    }

    /**
     * @param array<string,mixed> $condition
     */
    private static function condition_matches( WC_Product $product, array $condition ): bool {
        $type     = isset( $condition['type'] ) ? (string) $condition['type'] : 'category';
        $operator = isset( $condition['operator'] ) ? (string) $condition['operator'] : 'is';
        $value    = isset( $condition['value'] ) ? (string) $condition['value'] : '';

        $product_id = $product->get_id();

        switch ( $type ) {
            case 'category':
                $has = has_term( (int) $value, 'product_cat', $product_id );
                return 'is_not' === $operator ? ! $has : $has;

            case 'tag':
                $has = has_term( (int) $value, 'product_tag', $product_id );
                return 'is_not' === $operator ? ! $has : $has;

            case 'product':
                $is = (int) $value === $product_id || (int) $value === (int) $product->get_parent_id();
                return 'is_not' === $operator ? ! $is : $is;

            case 'price':
                $price     = (float) $product->get_price();
                $threshold = (float) $value;
                return 'lt' === $operator ? $price < $threshold : $price > $threshold;

            case 'stock':
                $in_stock = $product->is_in_stock();
                return 'out_of_stock' === $operator ? ! $in_stock : $in_stock;
        }

        return false;
    }
}
