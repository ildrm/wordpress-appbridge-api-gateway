<?php
namespace AppBridge\ApiGateway\Integrations;

use AppBridge\ApiGateway\Support\Response;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

final class WordPressIntegration implements IntegrationInterface {
	public function key(): string { return 'wordpress'; }
	public function name(): string { return 'WordPress'; }
	public function available(): bool { return true; }
	public function capabilities(): array { return array( 'content', 'taxonomies', 'comments', 'media', 'users' ); }
	public function health(): array { return array( 'ok' => true, 'version' => get_bloginfo( 'version' ) ); }
	public function register_routes(): void {
		register_rest_route( 'appbridge/v1', '/content', array(
			'methods' => 'GET', 'callback' => array( $this, 'content' ), 'permission_callback' => '__return_true',
			'args' => array(
				'type' => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_key', 'default' => 'post' ),
				'page' => array( 'type' => 'integer', 'minimum' => 1, 'default' => 1 ),
				'per_page' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ),
				'search' => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
			),
		) );
		register_rest_route( 'appbridge/v1', '/content/(?P<id>\d+)', array(
			'methods' => 'GET', 'callback' => array( $this, 'one' ), 'permission_callback' => '__return_true',
		) );
	}
	public function content( WP_REST_Request $request ) {
		$type = $request->get_param( 'type' );
		$obj = get_post_type_object( $type );
		if ( ! $obj || ! $obj->show_in_rest ) {
			return new \WP_Error( 'appbridge_invalid_type', __( 'Unsupported content type.', 'appbridge-api-gateway' ), array( 'status' => 400 ) );
		}
		$q = new \WP_Query( array(
			'post_type' => $type, 'post_status' => 'publish', 'paged' => $request['page'],
			'posts_per_page' => $request['per_page'], 's' => $request['search'] ?: '',
			'no_found_rows' => false, 'ignore_sticky_posts' => true,
		) );
		$items = array_map( array( $this, 'normalize_post' ), $q->posts );
		return Response::success( $items, 200, array( 'page' => (int) $request['page'], 'per_page' => (int) $request['per_page'], 'total' => (int) $q->found_posts, 'total_pages' => (int) $q->max_num_pages ) );
	}
	public function one( WP_REST_Request $request ) {
		$post = get_post( (int) $request['id'] );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return new \WP_Error( 'appbridge_not_found', __( 'Content not found.', 'appbridge-api-gateway' ), array( 'status' => 404 ) );
		}
		return Response::success( $this->normalize_post( $post ) );
	}
	private function normalize_post( $post ): array {
		$thumb = get_post_thumbnail_id( $post );
		return array(
			'id' => (int) $post->ID, 'type' => $post->post_type, 'slug' => $post->post_name,
			'title' => get_the_title( $post ), 'excerpt' => get_the_excerpt( $post ),
			'content' => apply_filters( 'the_content', $post->post_content ),
			'date' => get_post_datetime( $post )?->format( DATE_ATOM ),
			'modified' => get_post_datetime( $post, 'modified' )?->format( DATE_ATOM ),
			'link' => get_permalink( $post ),
			'featured_image' => $thumb ? array( 'id' => $thumb, 'url' => wp_get_attachment_image_url( $thumb, 'full' ), 'alt' => get_post_meta( $thumb, '_wp_attachment_image_alt', true ) ) : null,
		);
	}
}
