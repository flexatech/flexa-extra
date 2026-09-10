<?php
namespace Flexa\Extra\Engine\Admin;

defined( 'ABSPATH' ) || exit;

use Flexa\Extra\Utils\SingletonTrait;
use Flexa\Extra\Register\ScriptName;
use Flexa\Extra\Helpers\Helper;

/**
 * Registers the admin menu page and mounts the React app.
 */
final class Settings {
    use SingletonTrait;

    /**
     * Shared top-level slug for the whole Flexa ecosystem. Every Flexa plugin
     * hangs its pages under this one parent, so they group together instead of
     * scattering across wp-admin. The first plugin to boot creates it; the rest
     * detect it (see the guard in admin_menu) and only add their submenus.
     */
    private const PARENT_SLUG = 'flexa';

    /** This plugin's main page: the option-set builder. */
    private const PAGE_SLUG = 'flexa-extra';

    /** Deep-link submenu that opens the variation-swatches screen directly. */
    private const SWATCHES_SLUG = 'flexa-extra-variation-swatches';

    /**
     * Admin-page hook suffixes we own, filled in admin_menu. Used to enqueue the
     * app only on our screens.
     *
     * @var array<int,string>
     */
    private array $hook_suffixes = [];

    /**
     * Hook suffix of the "Flexa" hub page, set when this plugin owns the parent
     * menu. Kept apart from $hook_suffixes because the hub loads its own small
     * inline CSS/JS, not the React app bundle.
     */
    private string $hub_hook = '';

    protected function __construct() {
        add_filter( 'admin_body_class', [ $this, 'admin_body_class' ] );
        add_action( 'admin_menu', [ $this, 'admin_menu' ] );
        add_filter( 'plugin_action_links_' . FLEXA_EXTRA_BASE_NAME, [ $this, 'add_action_links' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
    }

    public function admin_body_class( string $classes ): string {
        if ( strpos( $classes, 'flexa-extra-ui' ) === false ) {
            $classes .= ' flexa-extra-ui';
        }
        return $classes;
    }

    /**
     * @param array<int,string> $links
     * @return array<int,string>
     */
    public function add_action_links( $links ): array {
        return array_merge(
            [
                '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) . '">' . esc_html__( 'Settings', 'flexa-extra' ) . '</a>',
            ],
            $links
        );
    }

    public function admin_menu(): void {
        $owns_parent = $this->register_parent_menu();

        if ( $owns_parent ) {
            // Give the parent a first submenu that shares its own slug. Core's
            // menu processor redirects a top-level menu to its first submenu
            // whenever the two slugs differ (see wp-admin/includes/menu.php),
            // so matching them keeps "Flexa" on its own hub page. Registering
            // it here also relabels that first row to "Home" instead of
            // repeating "Flexa".
            add_submenu_page(
                self::PARENT_SLUG,
                __( 'Flexa', 'flexa-extra' ),
                __( 'Home', 'flexa-extra' ),
                'manage_options',
                self::PARENT_SLUG,
                [ $this, 'render_hub' ]
            );
        }

        $this->hook_suffixes[] = (string) add_submenu_page(
            self::PARENT_SLUG,
            __( 'Product Options', 'flexa-extra' ),
            __( 'Product Options', 'flexa-extra' ),
            'manage_options',
            self::PAGE_SLUG,
            [ $this, 'render_page' ]
        );

        $this->hook_suffixes[] = (string) add_submenu_page(
            self::PARENT_SLUG,
            __( 'Variation Swatches', 'flexa-extra' ),
            __( 'Variation Swatches', 'flexa-extra' ),
            'manage_options',
            self::SWATCHES_SLUG,
            [ $this, 'render_swatches_page' ]
        );
    }

    /**
     * Create the shared "Flexa" parent menu once. Other Flexa plugins call the
     * same guarded pattern, so whichever loads first wins and the rest just add
     * their submenus under it. The parent renders its own hub page (a grid of
     * cards, one per registered submenu). Returns true when this call actually
     * created the menu, so the caller knows it should register the hub's own
     * first submenu.
     */
    private function register_parent_menu(): bool {
        global $admin_page_hooks;

        if ( isset( $admin_page_hooks[ self::PARENT_SLUG ] ) ) {
            return false;
        }

        $this->hub_hook = (string) add_menu_page(
            __( 'Flexa', 'flexa-extra' ),
            __( 'Flexa', 'flexa-extra' ),
            'manage_options',
            self::PARENT_SLUG,
            [ $this, 'render_hub' ],
            'dashicons-screenoptions',
            81
        );

        return true;
    }

    /**
     * The Flexa module catalog. Each entry is one card on the hub. This seeds the
     * flexatech ecosystem so modules from plugins that are not installed yet still
     * show up as install suggestions. Any Flexa plugin can register or override an
     * entry through the `flexa/dashboard/modules` filter; keys are module slugs.
     *
     * Recognised keys per entry: title, desc, category, icon (dashicon), accent
     * (hex), premium (bool), page (internal admin-page slug when the module lives
     * inside an active plugin), plugin (plugin file for install/active detection),
     * url (learn-more / get link), popularity (int, for the Popular sort).
     *
     * @return array<string,array<string,mixed>>
     */
    private function hub_modules(): array {
        $wporg = 'https://wordpress.org/plugins/';

        $modules = [
            // Modules that ship inside this plugin (always active here).
            'product-options'      => [
                'title'      => __( 'Product Options', 'flexa-extra' ),
                'desc'       => __( 'Custom option sets, fields, add-on pricing and conditional logic for your products.', 'flexa-extra' ),
                'category'   => 'store',
                'icon'       => 'dashicons-screenoptions',
                'accent'     => '#007bea',
                'page'       => self::PAGE_SLUG,
                'popularity' => 100,
            ],
            'variation-swatches'   => [
                'title'      => __( 'Variation Swatches', 'flexa-extra' ),
                'desc'       => __( 'Color, image and button swatches for variable product attributes.', 'flexa-extra' ),
                'category'   => 'store',
                'icon'       => 'dashicons-art',
                'accent'     => '#8b5cf6',
                'page'       => self::SWATCHES_SLUG,
                'popularity' => 98,
            ],
        ];

        /**
         * Whether to list the wider flexatech ecosystem (our other plugins) on the
         * hub as install suggestions. Temporarily off: until the pricing, reviews
         * and discount add-on modules ship, the hub shows only the modules that
         * live in this plugin, so store owners are not confused by suggestions for
         * things we have not released yet. Return true from the filter to restore.
         */
        if ( ! apply_filters( 'flexa_extra/hub/show_ecosystem', false ) ) {
            return (array) apply_filters( 'flexa/dashboard/modules', $modules );
        }

        // Other flexatech plugins on wordpress.org. Shown as suggestions when missing.
        $modules += [
            'flexa-block'          => [
                'title'      => __( 'Flexa Block', 'flexa-extra' ),
                'desc'       => __( 'A library of lightweight Gutenberg blocks for building modern pages.', 'flexa-extra' ),
                'category'   => 'cx',
                'icon'       => 'dashicons-layout',
                'accent'     => '#4f46e5',
                'premium'    => true,
                'plugin'     => 'flexa-block/flexa-block.php',
                'url'        => $wporg . 'flexa-block/',
                'popularity' => 90,
            ],
            'flexa-seo-aeo'        => [
                'title'      => __( 'Flexa SEO & AEO', 'flexa-extra' ),
                'desc'       => __( 'SEO plus an Answer Engine readiness score and a health dashboard for AI search.', 'flexa-extra' ),
                'category'   => 'marketing',
                'icon'       => 'dashicons-search',
                'accent'     => '#10b981',
                'plugin'     => 'flexa-seo-aeo/flexa-seo-aeo.php',
                'url'        => $wporg . 'flexa-seo-aeo/',
                'popularity' => 82,
            ],
            'flexa-wishlist'       => [
                'title'      => __( 'Flexa Wishlist', 'flexa-extra' ),
                'desc'       => __( 'A fast, cache-safe wishlist for WooCommerce: one-tap save, guest persistence and sharing.', 'flexa-extra' ),
                'category'   => 'cx',
                'icon'       => 'dashicons-heart',
                'accent'     => '#ef4444',
                'plugin'     => 'flexa-wishlist-for-woocommerce/flexa-wishlist-for-woocommerce.php',
                'url'        => $wporg . 'flexa-wishlist-for-woocommerce/',
                'popularity' => 78,
            ],
            'retailers-management' => [
                'title'      => __( 'Retailers Management', 'flexa-extra' ),
                'desc'       => __( 'Manage retailers, assign them to products and show retailer info on product pages.', 'flexa-extra' ),
                'category'   => 'store',
                'icon'       => 'dashicons-store',
                'accent'     => '#a855f7',
                'plugin'     => 'retailers-management-for-woocommerce/retailers-management-for-woocommerce.php',
                'url'        => $wporg . 'retailers-management-for-woocommerce/',
                'popularity' => 74,
            ],
            'flexa-media-folders'  => [
                'title'      => __( 'Flexa Media Folders', 'flexa-extra' ),
                'desc'       => __( 'Organize the Media Library into a drag-and-drop folder tree.', 'flexa-extra' ),
                'category'   => 'content',
                'icon'       => 'dashicons-portfolio',
                'accent'     => '#7c3aed',
                'plugin'     => 'flexa-media-folders/flexa-media-folders.php',
                'url'        => $wporg . 'flexa-media-folders/',
                'popularity' => 70,
            ],
            'flexa-cache'          => [
                'title'      => __( 'Flexa Cache', 'flexa-extra' ),
                'desc'       => __( 'Page and object caching to speed up your storefront.', 'flexa-extra' ),
                'category'   => 'performance',
                'icon'       => 'dashicons-performance',
                'accent'     => '#0ea5e9',
                'premium'    => true,
                'plugin'     => 'flexa-cache/flexa-cache.php',
                'url'        => $wporg . 'flexa-cache/',
                'popularity' => 66,
            ],
            'flexa-site-migrator'  => [
                'title'      => __( 'Flexa Site Migrator', 'flexa-extra' ),
                'desc'       => __( 'Package files and database to migrate or stage your site anywhere.', 'flexa-extra' ),
                'category'   => 'performance',
                'icon'       => 'dashicons-migrate',
                'accent'     => '#2563eb',
                'plugin'     => 'flexa-site-migrator/flexa-site-migrator.php',
                'url'        => $wporg . 'flexa-site-migrator/',
                'popularity' => 60,
            ],
            'flexa-unsubscribe'    => [
                'title'      => __( 'Flexa Unsubscribe', 'flexa-extra' ),
                'desc'       => __( 'One-click unsubscribe management with secure tokens and CSV tools.', 'flexa-extra' ),
                'category'   => 'marketing',
                'icon'       => 'dashicons-dismiss',
                'accent'     => '#64748b',
                'plugin'     => 'flexa-unsubscribe/flexa-unsubscribe.php',
                'url'        => $wporg . 'flexa-unsubscribe/',
                'popularity' => 54,
            ],
            'flex-explorer'        => [
                'title'      => __( 'Flex Explorer', 'flexa-extra' ),
                'desc'       => __( 'A lightweight, read-only file browser for wp-content, right in the admin.', 'flexa-extra' ),
                'category'   => 'content',
                'icon'       => 'dashicons-media-default',
                'accent'     => '#f59e0b',
                'plugin'     => 'flex-explorer-lite/flex-explorer.php',
                'url'        => $wporg . 'flex-explorer-lite/',
                'popularity' => 48,
            ],
        ];

        /**
         * Filter the Flexa module catalog shown on the hub. Add or override entries
         * keyed by module slug. See `hub_modules()` for the recognised fields.
         *
         * @param array<string,array<string,mixed>> $modules
         */
        return (array) apply_filters( 'flexa/dashboard/modules', $modules );
    }

    /**
     * Category tab labels, in display order. Only tabs with at least one module
     * are rendered.
     *
     * @return array<string,string>
     */
    private function hub_categories(): array {
        return [
            'store'       => __( 'Store & Product', 'flexa-extra' ),
            'booking'     => __( 'Booking & Hotel', 'flexa-extra' ),
            'marketing'   => __( 'Marketing', 'flexa-extra' ),
            'reviews'     => __( 'Reviews & Trust', 'flexa-extra' ),
            'analytics'   => __( 'Analytics', 'flexa-extra' ),
            'cx'          => __( 'Customer Experience', 'flexa-extra' ),
            'performance' => __( 'Performance', 'flexa-extra' ),
        ];
    }

    /**
     * Resolve the call-to-action for a module from its install / active state.
     *
     * @param array<string,mixed> $m
     * @return array{label:string,url:string,variant:string,external:bool}
     */
    private function hub_module_state( array $m ): array {
        $page = isset( $m['page'] ) ? (string) $m['page'] : '';
        if ( '' !== $page ) {
            $url = (string) menu_page_url( $page, false );
            if ( '' !== $url ) {
                return [ 'label' => __( 'Open', 'flexa-extra' ), 'url' => $url, 'variant' => 'open', 'external' => false ];
            }
        }

        $file = isset( $m['plugin'] ) ? (string) $m['plugin'] : '';
        if ( '' !== $file ) {
            if ( ! function_exists( 'is_plugin_active' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            if ( is_plugin_active( $file ) ) {
                $url = '' !== $page ? (string) menu_page_url( $page, false ) : '';
                if ( '' === $url ) {
                    $url = isset( $m['url'] ) && '' !== (string) $m['url'] ? (string) $m['url'] : admin_url( 'plugins.php' );
                }
                return [ 'label' => __( 'Open', 'flexa-extra' ), 'url' => $url, 'variant' => 'open', 'external' => false ];
            }

            $installed = isset( get_plugins()[ $file ] );
            if ( $installed && current_user_can( 'activate_plugins' ) ) {
                $url = wp_nonce_url(
                    self_admin_url( 'plugins.php?action=activate&plugin=' . rawurlencode( $file ) . '&plugin_status=all' ),
                    'activate-plugin_' . $file
                );
                return [ 'label' => __( 'Activate', 'flexa-extra' ), 'url' => $url, 'variant' => 'activate', 'external' => false ];
            }
        }

        // Not installed (or the current user cannot activate): promote it.
        $url = isset( $m['url'] ) && '' !== (string) $m['url'] ? (string) $m['url'] : 'https://flexatech.com/';
        return [ 'label' => __( 'Get it', 'flexa-extra' ), 'url' => $url, 'variant' => 'get', 'external' => true ];
    }

    /**
     * Render the "Flexa" landing page: a filterable grid of ecosystem modules.
     * Modules come from the catalog (`hub_modules`), plus any extra submenus other
     * Flexa plugins registered under the parent that the catalog does not cover.
     */
    public function render_hub(): void {
        $modules = $this->hub_modules();
        $modules = $this->hub_merge_submenu_modules( $modules );

        // Which category tabs actually have modules.
        $present = [];
        foreach ( $modules as $m ) {
            $cat = isset( $m['category'] ) ? (string) $m['category'] : '';
            if ( '' !== $cat ) {
                $present[ $cat ] = true;
            }
        }

        echo '<div id="flexa-hub" class="wrap flexa-hub">';

        printf(
            '<div class="flexa-hub__head"><h1>%1$s</h1><p>%2$s</p></div>',
            esc_html__( 'Flexa modules', 'flexa-extra' ),
            esc_html__( 'Everything from the Flexa ecosystem in one place. Open an active module, or discover more.', 'flexa-extra' )
        );

        // Toolbar: category tabs on the left, sort on the right.
        echo '<div class="flexa-hub__bar"><div class="flexa-hub__tabs">';
        printf(
            '<button type="button" class="flexa-hub__tab is-active" data-tab="all">%s</button>',
            esc_html__( 'All Modules', 'flexa-extra' )
        );
        foreach ( $this->hub_categories() as $key => $label ) {
            if ( empty( $present[ $key ] ) ) {
                continue;
            }
            printf(
                '<button type="button" class="flexa-hub__tab" data-tab="%1$s">%2$s</button>',
                esc_attr( $key ),
                esc_html( $label )
            );
        }
        echo '</div>';
        printf(
            '<select class="flexa-hub__sort"><option value="popular">%1$s</option><option value="name">%2$s</option></select>',
            esc_html__( 'Popular', 'flexa-extra' ),
            esc_html__( 'Name (A to Z)', 'flexa-extra' )
        );
        echo '</div>';

        // Sort by popularity for the default view.
        uasort(
            $modules,
            static function ( array $a, array $b ): int {
                return ( (int) ( $b['popularity'] ?? 0 ) ) <=> ( (int) ( $a['popularity'] ?? 0 ) );
            }
        );

        echo '<div class="flexa-hub__grid">';
        foreach ( $modules as $m ) {
            echo $this->hub_render_card( $m ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
        }
        echo '</div></div>';
    }

    /**
     * Append cards for any submenu registered under the Flexa parent that the
     * catalog does not already cover (so an active sibling module is never hidden,
     * even if it did not register itself through the filter).
     *
     * @param array<string,array<string,mixed>> $modules
     * @return array<string,array<string,mixed>>
     */
    private function hub_merge_submenu_modules( array $modules ): array {
        global $submenu;

        $known = [];
        foreach ( $modules as $m ) {
            if ( ! empty( $m['page'] ) ) {
                $known[ (string) $m['page'] ] = true;
            }
        }

        $subs = isset( $submenu[ self::PARENT_SLUG ] ) ? (array) $submenu[ self::PARENT_SLUG ] : [];
        foreach ( $subs as $item ) {
            $slug = isset( $item[2] ) ? (string) $item[2] : '';
            $cap  = isset( $item[1] ) ? (string) $item[1] : 'manage_options';

            if ( '' === $slug || self::PARENT_SLUG === $slug || isset( $known[ $slug ] ) || ! current_user_can( $cap ) ) {
                continue;
            }

            $modules[ $slug ] = [
                'title'      => isset( $item[0] ) ? wp_strip_all_tags( (string) $item[0] ) : $slug,
                'desc'       => '',
                'category'   => '',
                'icon'       => 'dashicons-admin-generic',
                'accent'     => '#007bea',
                'page'       => $slug,
                'popularity' => 10,
            ];
        }

        return $modules;
    }

    /**
     * Build the markup for one module card.
     *
     * @param array<string,mixed> $m
     */
    private function hub_render_card( array $m ): string {
        $title   = isset( $m['title'] ) ? (string) $m['title'] : '';
        $desc    = isset( $m['desc'] ) ? (string) $m['desc'] : '';
        $cat     = isset( $m['category'] ) ? (string) $m['category'] : '';
        $icon    = isset( $m['icon'] ) ? (string) $m['icon'] : 'dashicons-admin-generic';
        // One shared Flexa blue for every icon tile: per-module colours read as
        // noise on a dense grid. The `accent` field is kept for future use.
        $accent  = '#007bea';
        $premium = ! empty( $m['premium'] );
        $pop     = (int) ( $m['popularity'] ?? 0 );
        $state   = $this->hub_module_state( $m );

        $badge = $premium
            ? '<span class="flexa-hub__badge"><span class="dashicons dashicons-star-filled"></span>' . esc_html__( 'Premium', 'flexa-extra' ) . '</span>'
            : '';

        $learn = ( isset( $m['url'] ) && '' !== (string) $m['url'] && 'get' !== $state['variant'] )
            ? sprintf(
                '<a class="flexa-hub__learn" href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
                esc_url( (string) $m['url'] ),
                esc_html__( 'Learn more', 'flexa-extra' )
            )
            : '<span></span>';

        // Dim modules that are not installed yet (variant "get") so the store owner
        // can focus on what is actually active. The card lights back up on hover.
        $card_class = 'flexa-hub__card flexa-hub__card--' . $state['variant'];

        return sprintf(
            '<div class="' . esc_attr( $card_class ) . '" data-category="%1$s" data-title="%2$s" data-pop="%3$d">'
                . '<span class="flexa-hub__icon" style="background:%4$s;color:%5$s"><span class="dashicons %6$s"></span></span>'
                . '<div class="flexa-hub__content">'
                    . '<div class="flexa-hub__head-row"><h3 class="flexa-hub__title">%7$s</h3>%8$s</div>'
                    . '%9$s'
                    . '<div class="flexa-hub__foot">%10$s<a class="flexa-hub__btn flexa-hub__btn--%11$s" href="%12$s"%13$s>%14$s</a></div>'
                . '</div>'
            . '</div>',
            esc_attr( '' !== $cat ? $cat : 'all' ),
            esc_attr( $title ),
            $pop,
            esc_attr( self::hex_to_rgba( $accent, 0.12 ) ),
            esc_attr( $accent ),
            esc_attr( $icon ),
            esc_html( $title ),
            $badge,
            '' !== $desc ? '<p class="flexa-hub__desc">' . esc_html( $desc ) . '</p>' : '',
            $learn,
            esc_attr( $state['variant'] ),
            esc_url( $state['url'] ),
            $state['external'] ? ' target="_blank" rel="noopener noreferrer"' : '',
            esc_html( $state['label'] )
        );
    }

    /**
     * Convert a #rrggbb (or #rgb) hex colour to an rgba() string. Falls back to
     * the Flexa blue tint on a malformed value.
     */
    private static function hex_to_rgba( string $hex, float $alpha ): string {
        $hex = ltrim( $hex, '#' );
        if ( 3 === strlen( $hex ) ) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
            return 'rgba(0,123,234,' . $alpha . ')';
        }

        return sprintf(
            'rgba(%d,%d,%d,%s)',
            hexdec( substr( $hex, 0, 2 ) ),
            hexdec( substr( $hex, 2, 2 ) ),
            hexdec( substr( $hex, 4, 2 ) ),
            rtrim( rtrim( number_format( $alpha, 3, '.', '' ), '0' ), '.' )
        );
    }

    /**
     * Self-contained styles for the hub page, attached via wp_add_inline_style()
     * so no build step or separate stylesheet is needed (dashicons already ship
     * with wp-admin). Returns the raw CSS; WordPress prints the <style> wrapper.
     */
    private function hub_css(): string {
        return '
        .flexa-hub { max-width: 1180px; }
        .flexa-hub__head { margin: 22px 0 20px; }
        .flexa-hub__head h1 { font-size: 28px; font-weight: 700; margin: 0 0 6px; line-height: 1.2; }
        .flexa-hub__head p { margin: 0; color: #6b7280; font-size: 14px; }
        .flexa-hub__bar { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 18px; }
        .flexa-hub__tabs { display: flex; flex-wrap: wrap; gap: 4px; }
        .flexa-hub__tab { border: 0; background: transparent; cursor: pointer; padding: 7px 14px; border-radius: 999px; font-size: 13px; font-weight: 600; color: #4b5563; line-height: 1.2; transition: background .15s ease, color .15s ease; }
        .flexa-hub__tab:hover { color: #007bea; }
        .flexa-hub__tab.is-active { background: #ebf7ff; color: #007bea; }
        .flexa-hub__sort { height: 36px; border: 1px solid #d1d5db; border-radius: 8px; padding: 0 30px 0 12px; font-size: 13px; color: #374151; background-color: #fff; }
        .flexa-hub__grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 18px; }
        .flexa-hub__card { display: flex; gap: 16px; padding: 20px; background: #fff; border: 1px solid #e5e7eb; border-radius: 14px; box-shadow: 0 1px 2px rgba(16,24,40,.04); transition: border-color .15s ease, box-shadow .15s ease, transform .15s ease; }
        .flexa-hub__card:hover { border-color: #cfebff; box-shadow: 0 8px 24px rgba(16,24,40,.08); transform: translateY(-2px); }
        .flexa-hub__icon { flex: none; width: 52px; height: 52px; border-radius: 12px; display: flex; align-items: center; justify-content: center; }
        .flexa-hub__icon .dashicons { width: 26px; height: 26px; font-size: 26px; }
        .flexa-hub__content { flex: 1 1 auto; min-width: 0; display: flex; flex-direction: column; }
        .flexa-hub__head-row { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; }
        .flexa-hub__title { font-size: 16px; font-weight: 700; color: #111827; margin: 2px 0 0; line-height: 1.3; }
        .flexa-hub__badge { flex: none; display: inline-flex; align-items: center; gap: 3px; background: #fef3c7; color: #92600a; font-size: 11px; font-weight: 600; padding: 3px 8px; border-radius: 999px; white-space: nowrap; }
        .flexa-hub__badge .dashicons { width: 13px; height: 13px; font-size: 13px; }
        .flexa-hub__desc { margin: 8px 0 0; font-size: 13px; line-height: 1.5; color: #6b7280; }
        .flexa-hub__foot { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-top: auto; padding-top: 16px; }
        .flexa-hub__learn { font-size: 13px; font-weight: 600; color: #007bea; text-decoration: none; }
        .flexa-hub__learn:hover { text-decoration: underline; }
        .flexa-hub__learn::after { content: " \2192"; }
        .flexa-hub__btn { display: inline-flex; align-items: center; justify-content: center; height: 34px; padding: 0 16px; border-radius: 8px; font-size: 13px; font-weight: 600; text-decoration: none; white-space: nowrap; transition: background .15s ease, box-shadow .15s ease, opacity .15s ease; }
        .flexa-hub__btn--open { background: #007bea; color: #fff; }
        .flexa-hub__btn--open:hover { background: #0061ce; color: #fff; }
        .flexa-hub__btn--activate { background: #10b981; color: #fff; }
        .flexa-hub__btn--activate:hover { background: #059669; color: #fff; }
        .flexa-hub__btn--get { background: #eef2f7; color: #1f2937; }
        .flexa-hub__btn--get:hover { background: #e2e8f0; color: #1f2937; }
        .flexa-hub__card--get { background: #fbfcfd; border-color: #eef1f5; box-shadow: none; }
        .flexa-hub__card--get .flexa-hub__icon { filter: grayscale(1); opacity: .5; }
        .flexa-hub__card--get .flexa-hub__title { color: #6b7280; }
        .flexa-hub__card--get .flexa-hub__desc { color: #9ca3af; }
        .flexa-hub__card--get .flexa-hub__badge { opacity: .6; }
        .flexa-hub__card--get:hover, .flexa-hub__card--get:focus-within { background: #fff; }
        .flexa-hub__card--get:hover .flexa-hub__icon, .flexa-hub__card--get:focus-within .flexa-hub__icon { filter: none; opacity: 1; }
        .flexa-hub__card--get:hover .flexa-hub__title, .flexa-hub__card--get:focus-within .flexa-hub__title { color: #111827; }
        .flexa-hub__card--get:hover .flexa-hub__desc, .flexa-hub__card--get:focus-within .flexa-hub__desc { color: #6b7280; }
        .flexa-hub__card--get:hover .flexa-hub__badge, .flexa-hub__card--get:focus-within .flexa-hub__badge { opacity: 1; }
        @media (prefers-reduced-motion: reduce) { .flexa-hub__card:hover { transform: none; } }
        ';
    }

    /**
     * Vanilla JS for the hub: category filtering and the Popular / Name sort.
     * Attached via wp_add_inline_script(); returns the raw JS (no <script> tag).
     */
    private function hub_js(): string {
        return "
        (function(){
            var root = document.getElementById('flexa-hub');
            if (!root) { return; }
            var tabs = root.querySelectorAll('.flexa-hub__tab');
            var grid = root.querySelector('.flexa-hub__grid');
            var sort = root.querySelector('.flexa-hub__sort');
            var cards = grid ? grid.querySelectorAll('.flexa-hub__card') : [];

            function filter(cat) {
                for (var i = 0; i < cards.length; i++) {
                    var show = cat === 'all' || cards[i].getAttribute('data-category') === cat;
                    cards[i].style.display = show ? '' : 'none';
                }
            }

            for (var t = 0; t < tabs.length; t++) {
                tabs[t].addEventListener('click', function () {
                    for (var j = 0; j < tabs.length; j++) { tabs[j].classList.remove('is-active'); }
                    this.classList.add('is-active');
                    filter(this.getAttribute('data-tab'));
                });
            }

            if (sort && grid) {
                sort.addEventListener('change', function () {
                    var mode = sort.value;
                    var arr = Array.prototype.slice.call(grid.children);
                    arr.sort(function (a, b) {
                        if (mode === 'name') {
                            return (a.getAttribute('data-title') || '').localeCompare(b.getAttribute('data-title') || '');
                        }
                        return (parseInt(b.getAttribute('data-pop'), 10) || 0) - (parseInt(a.getAttribute('data-pop'), 10) || 0);
                    });
                    for (var k = 0; k < arr.length; k++) { grid.appendChild(arr[k]); }
                });
            }
        })();
        ";
    }

    public function render_page(): void {
        $this->render_root( '' );
    }

    public function render_swatches_page(): void {
        $this->render_root( '/variation-swatches' );
    }

    /**
     * Mount point for the React app. An optional initial route lets a submenu
     * open the SPA on a specific screen (the app reads data-initial-route on
     * boot when the URL has no hash yet).
     */
    private function render_root( string $route ): void {
        $attr = '' === $route ? '' : sprintf( ' data-initial-route="%s"', esc_attr( $route ) );

        echo wp_kses(
            '<div id="flexa-extra-admin-root"' . $attr . '></div>',
            [ 'div' => [ 'id' => true, 'data-initial-route' => true ] ]
        );
    }

    public function admin_enqueue_scripts( string $hook_suffix ): void {
        // The hub is a plain PHP page: register empty style/script handles and
        // attach its small CSS/JS inline (WordPress prints the wrapping tags).
        if ( '' !== $this->hub_hook && $hook_suffix === $this->hub_hook ) {
            wp_register_style( 'flexa-extra-hub', false, [], FLEXA_EXTRA_VERSION );
            wp_enqueue_style( 'flexa-extra-hub' );
            wp_add_inline_style( 'flexa-extra-hub', $this->hub_css() );

            wp_register_script( 'flexa-extra-hub', false, [], FLEXA_EXTRA_VERSION, true );
            wp_enqueue_script( 'flexa-extra-hub' );
            wp_add_inline_script( 'flexa-extra-hub', $this->hub_js() );
            return;
        }

        if ( ! in_array( $hook_suffix, $this->hook_suffixes, true ) ) {
            return;
        }

        wp_localize_script( ScriptName::PAGE_SETTINGS, 'flexaExtra', Helper::get_js_config() );
        wp_enqueue_media();
        wp_enqueue_script( ScriptName::PAGE_SETTINGS );
        wp_enqueue_style( ScriptName::STYLE_SETTINGS );
        // Storefront CSS powers the builder's live preview so it matches the
        // real product page 1:1 (scoped to .flexa-extra-* selectors).
        wp_enqueue_style( ScriptName::STYLE_FRONTEND );
    }
}
