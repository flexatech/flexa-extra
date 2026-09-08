<?php
namespace Flexa\Extra\Controllers;

defined( 'ABSPATH' ) || exit;

use Flexa\Extra\Migration\Migrator;
use Flexa\Extra\Utils\SingletonTrait;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Admin-only endpoints for importing option sets from other plugins.
 *
 *   GET  /flexa-extra/v1/migration/sources
 *   POST /flexa-extra/v1/migration/import   { "source": "yayextra" }
 */
final class MigrationRestController extends BaseRestController {
    use SingletonTrait;

    protected function __construct() {
        register_rest_route(
            $this->namespace,
            '/migration/sources',
            [
                [
                    'methods'             => 'GET',
                    'callback'            => [ $this, 'sources' ],
                    'permission_callback' => [ $this, 'permission_callback' ],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/migration/import',
            [
                [
                    'methods'             => 'POST',
                    'callback'            => [ $this, 'import' ],
                    'permission_callback' => [ $this, 'permission_callback' ],
                ],
            ]
        );
    }

    public function sources( WP_REST_Request $request ): WP_REST_Response {
        unset( $request );
        return $this->success( [ 'sources' => ( new Migrator() )->report() ] );
    }

    public function import( WP_REST_Request $request ): WP_REST_Response {
        $slug     = sanitize_key( (string) ( $this->get_json_params( $request )['source'] ?? '' ) );
        $migrator = new Migrator();
        $source   = $migrator->source( $slug );

        if ( null === $source ) {
            return $this->error( __( 'Unknown import source.', 'flexa-extra' ), 400 );
        }
        if ( ! $source->is_available() ) {
            return $this->error( __( 'No data was found for that plugin.', 'flexa-extra' ), 404 );
        }

        $result = $migrator->import( $source );
        $count  = count( $result['imported'] );

        return $this->success(
            $result,
            sprintf(
                /* translators: %d: number of option sets imported. */
                _n( 'Imported %d option set (switched off for review).', 'Imported %d option sets (switched off for review).', $count, 'flexa-extra' ),
                $count
            )
        );
    }
}
