<?php
namespace Flexa\Extra\Controllers;

defined( 'ABSPATH' ) || exit;

use Flexa\Extra\Utils\SingletonTrait;
use Flexa\Extra\Variations\AttributeType;
use Flexa\Extra\Variations\TermMeta;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Read/write the variation-swatch configuration for the admin UI.
 *
 *   GET  /flexa-extra/v1/variation-swatches/attributes
 *       Lists every global product attribute with its current swatch type and
 *       its terms (each carrying the stored color / image).
 *   POST /flexa-extra/v1/variation-swatches/attribute  { taxonomy, type }
 *       Sets the swatch type for one attribute (`off` clears it).
 *   POST /flexa-extra/v1/variation-swatches/term       { term_id, color?, image_id? }
 *       Writes the per-term color / image, using woo-variation-swatches keys.
 *
 * Store-admin only (`manage_woocommerce`); these settings affect the storefront.
 */
final class VariationSwatchesRestController extends BaseRestController {
	use SingletonTrait;

	protected function __construct() {
		register_rest_route(
			$this->namespace,
			'/variation-swatches/attributes',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_attributes' ],
				'permission_callback' => [ $this, 'permission_callback' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/variation-swatches/attribute',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'set_attribute_type' ],
				'permission_callback' => [ $this, 'permission_callback' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/variation-swatches/term',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'set_term' ],
				'permission_callback' => [ $this, 'permission_callback' ],
			]
		);
	}

	/**
	 * Variation swatches are store-level configuration: gate on WooCommerce
	 * management rather than the generic `manage_options`.
	 *
	 * @return bool|\WP_Error
	 */
	public function permission_callback( WP_REST_Request $request ) {
		unset( $request );

		return current_user_can( 'manage_woocommerce' )
			? true
			: new \WP_Error(
				'rest_forbidden',
				__( 'You are not allowed to manage Flexa Extra.', 'flexa-extra' ),
				[ 'status' => 403 ]
			);
	}

	public function get_attributes( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );

		if ( ! function_exists( 'wc_get_attribute_taxonomies' ) ) {
			return $this->success( [ 'attributes' => [] ] );
		}

		$attributes = [];
		foreach ( wc_get_attribute_taxonomies() as $attribute ) {
			$taxonomy = wc_attribute_taxonomy_name( $attribute->attribute_name );

			$attributes[] = [
				'taxonomy' => $taxonomy,
				'label'    => (string) $attribute->attribute_label,
				'type'     => AttributeType::get_type( $taxonomy ),
				'terms'    => $this->terms_for( $taxonomy ),
			];
		}

		return $this->success( [ 'attributes' => $attributes ] );
	}

	public function set_attribute_type( WP_REST_Request $request ): WP_REST_Response {
		$params   = $this->get_json_params( $request );
		$taxonomy = sanitize_key( (string) ( $params['taxonomy'] ?? '' ) );
		$type     = sanitize_key( (string) ( $params['type'] ?? '' ) );

		if ( '' === $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
			return $this->error( __( 'Unknown attribute.', 'flexa-extra' ), 400 );
		}

		AttributeType::set_type( $taxonomy, $type );

		return $this->success( [ 'type' => AttributeType::get_type( $taxonomy ) ] );
	}

	public function set_term( WP_REST_Request $request ): WP_REST_Response {
		$params  = $this->get_json_params( $request );
		$term_id = absint( $params['term_id'] ?? 0 );

		if ( $term_id <= 0 || ! get_term( $term_id ) || is_wp_error( get_term( $term_id ) ) ) {
			return $this->error( __( 'Unknown term.', 'flexa-extra' ), 400 );
		}

		if ( array_key_exists( 'color', $params ) ) {
			TermMeta::set_color( $term_id, (string) $params['color'] );
		}

		if ( array_key_exists( 'image_id', $params ) ) {
			TermMeta::set_image_id( $term_id, absint( $params['image_id'] ) );
		}

		return $this->success(
			[
				'id'    => $term_id,
				'color' => TermMeta::get_color( $term_id ),
				'image' => $this->image_payload( $term_id ),
			]
		);
	}

	/**
	 * @return list<array{id:int,name:string,color:string,image:array{id:int,url:string}}>
	 */
	private function terms_for( string $taxonomy ): array {
		$terms = get_terms(
			[
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			]
		);

		if ( is_wp_error( $terms ) ) {
			return [];
		}

		$items = [];
		foreach ( $terms as $term ) {
			$id      = (int) $term->term_id;
			$items[] = [
				'id'    => $id,
				'name'  => (string) $term->name,
				'color' => TermMeta::get_color( $id ),
				'image' => $this->image_payload( $id ),
			];
		}

		return $items;
	}

	/**
	 * @return array{id:int,url:string}
	 */
	private function image_payload( int $term_id ): array {
		return [
			'id'  => TermMeta::get_image_id( $term_id ),
			'url' => TermMeta::get_image_url( $term_id ),
		];
	}
}
