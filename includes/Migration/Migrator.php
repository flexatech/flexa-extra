<?php
namespace Flexa\Extra\Migration;

defined( 'ABSPATH' ) || exit;

use Flexa\Extra\Engine\Admin\CustomPostType;
use Flexa\Extra\Fields\OptionSetSchema;

/**
 * Runs an import from another options plugin into Flexa Extra option sets.
 *
 * Every imported set is routed through {@see OptionSetSchema::sanitize()} (the
 * same gate the builder uses) and created switched off (published post, inactive
 * status), exactly like a duplicated set: it shows in the Option Sets list for
 * review but the storefront skips it until you turn it on.
 */
final class Migrator {

    private const META_FIELDS    = '_flexa_extra_fields';
    private const META_TARGETING = '_flexa_extra_targeting';
    private const META_STATUS    = '_flexa_extra_status';
    private const META_ACTIONS   = '_flexa_extra_actions';

    /**
     * @return list<AbstractMigrationSource>
     */
    public function sources(): array {
        /**
         * Filter the list of migration sources.
         *
         * @param list<AbstractMigrationSource> $sources
         */
        return apply_filters(
            'flexa_extra/migration/sources',
            [ new YayExtraSource(), new ThemeHighSource() ]
        );
    }

    public function source( string $slug ): ?AbstractMigrationSource {
        foreach ( $this->sources() as $source ) {
            if ( $source->slug() === $slug ) {
                return $source;
            }
        }
        return null;
    }

    /**
     * A summary of every source and how many sets it would import.
     *
     * @return list<array{slug:string,label:string,available:bool,count:int}>
     */
    public function report(): array {
        $report = [];
        foreach ( $this->sources() as $source ) {
            $available = $source->is_available();
            $report[]  = [
                'slug'      => $source->slug(),
                'label'     => $source->label(),
                'available' => $available,
                'count'     => $available ? $source->count() : 0,
            ];
        }
        return $report;
    }

    /**
     * Import every option set from one source.
     *
     * @return array{source:string,label:string,imported:list<array{id:int,name:string,warnings:list<string>}>,skipped:int}
     */
    public function import( AbstractMigrationSource $source ): array {
        $imported = [];
        $skipped  = 0;

        foreach ( $source->read_raw() as $raw_set ) {
            $converted = $source->convert( $raw_set );
            $input     = $converted['input'];

            // A set with no importable fields is not worth creating.
            if ( empty( $input['fields'] ) ) {
                ++$skipped;
                continue;
            }

            $post_id = $this->create_inactive( $input );
            if ( 0 === $post_id ) {
                ++$skipped;
                continue;
            }

            $imported[] = [
                'id'       => $post_id,
                'name'     => (string) get_the_title( $post_id ),
                'warnings' => array_values( array_unique( $converted['warnings'] ) ),
            ];
        }

        return [
            'source'   => $source->slug(),
            'label'    => $source->label(),
            'imported' => $imported,
            'skipped'  => $skipped,
        ];
    }

    /**
     * @param array<string,mixed> $input
     */
    private function create_inactive( array $input ): int {
        $data = OptionSetSchema::sanitize( $input );

        // Published so it appears in the Option Sets list, but with the active
        // flag off so the resolver (publish + status 1) skips it on the storefront
        // until the merchant reviews and turns it on. Mirrors the Duplicate flow.
        $post_id = wp_insert_post(
            [
                'post_type'   => CustomPostType::POST_TYPE,
                'post_title'  => $data['name'],
                'post_status' => 'publish',
            ],
            true
        );

        if ( is_wp_error( $post_id ) ) {
            return 0;
        }

        update_post_meta( $post_id, self::META_FIELDS, $data['fields'] );
        update_post_meta( $post_id, self::META_TARGETING, $data['targeting'] );
        update_post_meta( $post_id, self::META_STATUS, 0 );
        update_post_meta( $post_id, self::META_ACTIONS, $data['actions'] );

        do_action( 'flexa_extra/migration/imported', $post_id, $data );

        return (int) $post_id;
    }
}
