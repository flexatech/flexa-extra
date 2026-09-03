<?php
namespace Flexa\Extra\Engine;

defined( 'ABSPATH' ) || exit;

/**
 * Activation / deactivation and WooCommerce compatibility hooks.
 */
class ActDeact {

    public static function install_woocommerce_admin_notice(): void {
        $woocommerce_url  = esc_url( admin_url( 'plugin-install.php?s=woocommerce&tab=search&type=term' ) );
        $woocommerce_link = '<a href="' . $woocommerce_url . '">' . esc_html( 'WooCommerce' ) . '</a>';
        $message          = sprintf(
            /* translators: %s: WooCommerce plugin installation link. */
            esc_html__( 'Flexa Extra is enabled but not effective. It requires %s in order to work', 'flexa-extra' ),
            $woocommerce_link
        );
        echo wp_kses(
            '<div class="error"><p><strong>' . $message . '</strong></p></div>',
            [
                'div'    => [ 'class' => true ],
                'p'      => [],
                'strong' => [],
                'a'      => [ 'href' => true ],
            ]
        );
    }

    public static function before_woocommerce_init(): void {
        if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', FLEXA_EXTRA_FILE, true );
        }
    }

    public static function activate(): void {
        if ( ! function_exists( 'WC' ) ) {
            return;
        }

        // Seed default settings on first activation.
        if ( false === get_option( 'flexa_extra_settings' ) ) {
            update_option( 'flexa_extra_settings', \Flexa\Extra\Helpers\Helper::get_default_settings() );
        }

        // Offer the quick-start guide on first activation. A short, user-scoped
        // transient is the safe way to trigger a one-time redirect: activation
        // runs sandboxed, so the redirect happens later on admin_init (see
        // ActivationRedirect). Skipped once the user has finished or dismissed
        // the guide, so re-activating a configured site never hijacks the admin.
        if ( ! \Flexa\Extra\Support\OnboardingState::is_finished() ) {
            set_transient(
                \Flexa\Extra\Engine\Admin\ActivationRedirect::REDIRECT_TRANSIENT,
                get_current_user_id(),
                MINUTE_IN_SECONDS
            );
        }
    }

    public static function deactivate(): void {
        if ( ! function_exists( 'WC' ) ) {
            return;
        }
    }
}
