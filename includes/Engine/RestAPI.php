<?php
namespace Flexa\Extra\Engine;

defined( 'ABSPATH' ) || exit;

use Flexa\Extra\Utils\SingletonTrait;
use Flexa\Extra\Controllers\SettingsRestController;
use Flexa\Extra\Controllers\OptionSetsRestController;
use Flexa\Extra\Controllers\ResourcesRestController;
use Flexa\Extra\Controllers\OnboardingRestController;

/**
 * Boots every REST controller on rest_api_init.
 *
 * Register every controller here — a controller that is not instantiated here
 * is silently dead.
 */
final class RestAPI {
    use SingletonTrait;

    protected function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_endpoints' ] );
    }

    public function register_endpoints(): void {
        SettingsRestController::get_instance();
        OptionSetsRestController::get_instance();
        ResourcesRestController::get_instance();
        OnboardingRestController::get_instance();

        do_action( 'flexa_extra/rest/register_routes' );
    }
}
