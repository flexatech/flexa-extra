<?php
namespace Flexa\Extra\Controllers;

defined( 'ABSPATH' ) || exit;

use Flexa\Extra\Utils\SingletonTrait;
use Flexa\Extra\Support\OnboardingState;
use WP_REST_Request;
use WP_REST_Response;

/**
 * GET/POST /flexa-extra/v1/onboarding
 *
 * Drives the first-run quick-start guide. Both routes are store-admin only
 * (`manage_options`); the guide proposes global configuration. POST accepts a
 * partial `{ status }` payload and echoes the full coerced state back.
 */
final class OnboardingRestController extends BaseRestController {
	use SingletonTrait;

	protected function __construct() {
		register_rest_route(
			$this->namespace,
			'/onboarding',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'get_state' ],
					'permission_callback' => [ $this, 'permission_callback' ],
				],
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'update_state' ],
					'permission_callback' => [ $this, 'permission_callback' ],
				],
			]
		);
	}

	public function get_state( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );
		return $this->success( OnboardingState::all() );
	}

	public function update_state( WP_REST_Request $request ): WP_REST_Response {
		$incoming = $this->get_json_params( $request );
		return $this->success( OnboardingState::update( $incoming ) );
	}
}
