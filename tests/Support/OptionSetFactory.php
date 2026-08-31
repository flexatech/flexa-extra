<?php
namespace Flexa\Extra\Tests\Support;

use Flexa\Extra\Fields\OptionSetSchema;
use Flexa\Extra\Frontend\OptionSetResolver;

/**
 * Registers option sets into the test "post meta" registry and builds fields.
 *
 * Everything goes through the real {@see OptionSetSchema::sanitize()} so tests
 * operate on exactly the normalized shape production stores — the same shape
 * the resolver and selection engine read back.
 */
final class OptionSetFactory {

    /** Reset the registry + resolver cache between tests. */
    public static function reset(): void {
        $GLOBALS['fx_sets']          = array();
        $GLOBALS['fx_product_terms'] = array();
        OptionSetResolver::flush_cache();
    }

    /**
     * @param array<string,mixed> $input Raw option-set body (pre-sanitize).
     */
    public static function register( int $id, array $input ): void {
        $data = OptionSetSchema::sanitize( $input );

        $GLOBALS['fx_sets'][ $id ] = array(
            'name'      => $data['name'],
            'status'    => $data['status'] ? 1 : 0,
            'fields'    => $data['fields'],
            'targeting' => $data['targeting'],
        );
    }

    /**
     * Assign taxonomy terms to a product for has_term()-based targeting.
     *
     * @param list<int> $term_ids
     */
    public static function assign_terms( int $product_id, string $taxonomy, array $term_ids ): void {
        $GLOBALS['fx_product_terms'][ $product_id ][ $taxonomy ] = $term_ids;
    }

    /**
     * Convenience: a single choice option definition.
     *
     * @return array<string,mixed>
     */
    public static function choice( string $id, string $label, ?array $price = null, array $extra = array() ): array {
        $option = array_merge(
            array(
                'id'    => $id,
                'label' => $label,
                'value' => $id,
            ),
            $extra
        );
        if ( null !== $price ) {
            $option['price'] = $price;
        }
        return $option;
    }

    /**
     * @return array{type:string,amount:float}
     */
    public static function price( string $type, float $amount ): array {
        return array( 'type' => $type, 'amount' => $amount );
    }
}
