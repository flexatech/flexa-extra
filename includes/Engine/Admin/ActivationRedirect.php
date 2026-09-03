<?php
namespace Flexa\Extra\Engine\Admin;

defined( 'ABSPATH' ) || exit;

use Flexa\Extra\Utils\SingletonTrait;
use Flexa\Extra\Support\OnboardingState;

/**
 * First-run entry into the quick-start guide.
 *
 * A single-plugin activation drops a short-lived, user-scoped transient (see
 * {@see \Flexa\Extra\Engine\ActDeact::activate}); the next admin page load for
 * that same user redirects once to the Flexa Extra screen, where the guide
 * shows itself while the status is still `pending`. Every path the redirect
 * cannot safely cover (bulk activation, WP-CLI, another user) falls back to a
 * dismissible notice on the Plugins screen. Both self-suppress once the guide
 * is completed or dismissed.
 */
final class ActivationRedirect {
	use SingletonTrait;

	/** Top-level admin page slug the guide lives on. */
	private const PAGE_SLUG = 'flexa-extra';

	/** One-shot, user-scoped transient set on activation. */
	public const REDIRECT_TRANSIENT = 'flexa_extra_activation_redirect';

	private const DISMISS_ARG   = 'flexa-extra-dismiss-setup';
	private const DISMISS_NONCE = 'flexa_extra_dismiss_setup_notice';

	protected function __construct() {
		add_action( 'admin_init', [ $this, 'maybe_redirect' ] );
		add_action( 'admin_init', [ $this, 'maybe_dismiss_notice' ] );
		add_action( 'admin_notices', [ $this, 'maybe_render_notice' ] );
	}

	/**
	 * Consume the activation transient and, when it is safe, send the activating
	 * admin to the plugin screen exactly once.
	 */
	public function maybe_redirect(): void {
		$user = get_transient( self::REDIRECT_TRANSIENT );
		if ( false === $user ) {
			return;
		}

		// One shot: delete before any guard can bail, so a blocked redirect never
		// re-fires on the next request.
		delete_transient( self::REDIRECT_TRANSIENT );

		if ( wp_doing_ajax() || is_network_admin() ) {
			return;
		}
		// Bulk activation lands on plugins.php with this flag; never hijack it.
		if ( isset( $_GET['activate-multi'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( get_current_user_id() !== (int) $user ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( OnboardingState::is_finished() ) {
			return;
		}

		wp_safe_redirect( $this->page_url() );
		exit;
	}

	/**
	 * Persist a dismissal of the plugins-screen notice (nonce-checked) and bounce
	 * back to a clean Plugins screen.
	 */
	public function maybe_dismiss_notice(): void {
		if ( ! isset( $_GET[ self::DISMISS_ARG ] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::DISMISS_NONCE ) ) {
			return;
		}

		OnboardingState::update( [ 'status' => 'dismissed' ] );

		wp_safe_redirect( admin_url( 'plugins.php' ) );
		exit;
	}

	/**
	 * Render the fallback notice: Plugins screen only, settings-capable users
	 * only, and only while the guide is still pending.
	 */
	public function maybe_render_notice(): void {
		$screen = get_current_screen();
		if ( null === $screen || 'plugins' !== $screen->id ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( 'pending' !== OnboardingState::get( 'status' ) ) {
			return;
		}

		$dismiss_url = wp_nonce_url(
			add_query_arg( self::DISMISS_ARG, '1', admin_url( 'plugins.php' ) ),
			self::DISMISS_NONCE
		);

		printf(
			'<div class="notice notice-info"><p>%s</p><p><a class="button button-primary" href="%s">%s</a> <a href="%s">%s</a></p></div>',
			esc_html__( 'Flexa Extra is active. Build your first option set in a couple of minutes with the quick-start guide.', 'flexa-extra' ),
			esc_url( $this->page_url() ),
			esc_html__( 'Start the guide', 'flexa-extra' ),
			esc_url( $dismiss_url ),
			esc_html__( 'Dismiss', 'flexa-extra' )
		);
	}

	private function page_url(): string {
		return admin_url( 'admin.php?page=' . self::PAGE_SLUG );
	}
}
