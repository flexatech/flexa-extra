<?php
namespace Flexa\Extra;

defined( 'ABSPATH' ) || exit;

use Flexa\Extra\Utils\SingletonTrait;

/**
 * Flexa Extra plugin initializer. Boots the engine classes once WooCommerce is
 * available.
 */
final class Initialize {

    use SingletonTrait;

    protected function __construct() {
        \Flexa\Extra\Engine\Admin\Settings::get_instance();
        \Flexa\Extra\Engine\Admin\CustomPostType::get_instance();
        \Flexa\Extra\Register\RegisterFacade::get_instance();
        \Flexa\Extra\Engine\RestAPI::get_instance();
        \Flexa\Extra\Frontend\ProductRenderer::get_instance();
        \Flexa\Extra\Frontend\Validator::get_instance();
        \Flexa\Extra\Cart\CartHandler::get_instance();
        \Flexa\Extra\Cart\PriceCalculator::get_instance();
    }
}
