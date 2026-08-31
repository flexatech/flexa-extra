<?php
/**
 * Minimal WooCommerce class stubs.
 *
 * A `WC_Product` stand-in is enough for the resolver and the selection engine:
 * they only read id / price / parent / stock. Product taxonomy terms live in
 * the $GLOBALS['fx_product_terms'] registry the has_term() stub reads.
 */

declare(strict_types=1);

if ( ! class_exists( 'WC_Product' ) ) {
    class WC_Product {

        private $id;
        private $price;
        private $parent_id;
        private $in_stock;

        public function __construct( int $id, float $price = 0.0, int $parent_id = 0, bool $in_stock = true ) {
            $this->id        = $id;
            $this->price     = $price;
            $this->parent_id = $parent_id;
            $this->in_stock  = $in_stock;
        }

        public function get_id() {
            return $this->id;
        }

        public function get_price( $context = 'view' ) {
            unset( $context );
            return $this->price;
        }

        public function set_price( $price ) {
            $this->price = (float) $price;
        }

        public function get_parent_id() {
            return $this->parent_id;
        }

        public function is_in_stock() {
            return $this->in_stock;
        }
    }
}
