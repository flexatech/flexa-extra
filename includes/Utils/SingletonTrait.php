<?php
namespace Flexa\Extra\Utils;

defined( 'ABSPATH' ) || exit;

trait SingletonTrait {
    /** @var static|null */
    protected static $instance = null;

    protected function __construct() { }

    /**
     * @param mixed ...$args
     * @return static
     */
    public static function get_instance( ...$args ) {
        if ( ! isset( static::$instance ) ) {
            static::$instance = new static( ...$args );
        }

        return static::$instance;
    }

    /** Singletons should not be cloneable. */
    protected function __clone() { }

    /** Singletons should not be restorable from strings. */
    public function __wakeup() {
        throw new \Exception( 'Cannot unserialize a singleton.' );
    }
}
