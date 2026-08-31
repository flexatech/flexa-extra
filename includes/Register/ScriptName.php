<?php
namespace Flexa\Extra\Register;

defined( 'ABSPATH' ) || exit;

/**
 * Registered script/style handles.
 */
class ScriptName {

    public const MODULE_PREFIX = 'flexa-extra/module/';

    public const STYLE_SETTINGS = self::MODULE_PREFIX . 'style-settings';

    public const PAGE_SETTINGS = self::MODULE_PREFIX . 'page-settings';

    // Storefront assets are plain (non-module) files shipped under assets/frontend.
    public const STYLE_FRONTEND = 'flexa-extra-frontend';

    public const PAGE_FRONTEND = 'flexa-extra-frontend';
}
