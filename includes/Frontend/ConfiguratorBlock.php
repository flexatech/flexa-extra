<?php
namespace Flexa\Extra\Frontend;

defined( 'ABSPATH' ) || exit;

use Flexa\Extra\Utils\SingletonTrait;
use Flexa\Extra\Register\ScriptName;

/**
 * Registers the `flexa-extra/configurator` dynamic block.
 *
 * The block renders one option set as an interactive, price-calculating
 * configurator anywhere in the block editor (pages, posts, templates). It is
 * display only: its inputs sit outside the WooCommerce add-to-cart form, so
 * selections are not submitted to the cart. For cart-integrated options, use the
 * automatic product-page placement ({@see ProductRenderer}). Front-end markup is
 * produced server-side so option definitions never ship to unauthenticated
 * editors, and pricing stays consistent with the product page.
 */
final class ConfiguratorBlock {
    use SingletonTrait;

    protected function __construct() {
        add_action( 'init', [ $this, 'register' ] );
    }

    public function register(): void {
        if ( ! function_exists( 'register_block_type' ) ) {
            return;
        }

        wp_register_script(
            ScriptName::BLOCK_EDITOR,
            FLEXA_EXTRA_PLUGIN_URL . 'assets/blocks/configurator/index.js',
            [ 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-api-fetch' ],
            FLEXA_EXTRA_VERSION,
            true
        );

        register_block_type(
            FLEXA_EXTRA_PLUGIN_DIR . 'assets/blocks/configurator',
            [ 'render_callback' => [ $this, 'render' ] ]
        );
    }

    /**
     * Server render callback.
     *
     * @param array<string,mixed> $attributes
     */
    public function render( array $attributes ): string {
        $set_id     = isset( $attributes['optionSetId'] ) ? (int) $attributes['optionSetId'] : 0;
        $product_id = isset( $attributes['productId'] ) ? (int) $attributes['productId'] : 0;

        if ( $set_id <= 0 ) {
            return '';
        }

        return ProductRenderer::get_instance()->render_configurator( $set_id, $product_id );
    }
}
