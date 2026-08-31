<?php
namespace Flexa\Extra\Engine\Admin;

defined( 'ABSPATH' ) || exit;

use Flexa\Extra\Utils\SingletonTrait;

/**
 * Registers the hidden custom post type that stores each group of extra product
 * options. UI is hidden — the React app is the only editor.
 *
 * NOTE: the post-type key must be <= 20 characters or WordPress refuses to
 * register it (register_post_type returns a WP_Error and the type never exists).
 */
final class CustomPostType {
    use SingletonTrait;

    public const POST_TYPE = 'flexa_extra_optset';

    protected function __construct() {
        add_action( 'init', [ $this, 'register_post_type' ] );
    }

    public function register_post_type(): void {
        register_post_type(
            self::POST_TYPE,
            [
                'labels'              => [
                    'name'          => __( 'Option Sets', 'flexa-extra' ),
                    'singular_name' => __( 'Option Set', 'flexa-extra' ),
                ],
                'public'              => false,
                'show_ui'             => false,
                'show_in_menu'        => false,
                'query_var'           => false,
                'rewrite'             => false,
                'hierarchical'        => false,
                'exclude_from_search' => true,
                'publicly_queryable'  => false,
                'capability_type'     => 'post',
                'supports'            => [ 'title', 'author' ],
            ]
        );
    }
}
