<?php
namespace AppBridge\ApiGateway\Services;

defined( 'ABSPATH' ) || exit;
final class WebhookService {
	public function register_events(): void {
		add_action( 'appbridge_deliver_webhook', array( $this, 'process_delivery' ), 10, 4 );
		add_action( 'save_post', function( $post_id, $post, $update ) {
			if ( wp_is_post_revision( $post_id ) || 'auto-draft' === $post->post_status ) { return; }
			$this->dispatch( $update ? 'post.updated' : 'post.created', array( 'id' => (int) $post_id, 'type' => $post->post_type ) );
		}, 10, 3 );
		add_action( 'user_register', function( $user_id ) { $this->dispatch( 'user.created', array( 'id' => (int) $user_id ) ); } );
		add_action( 'woocommerce_order_status_changed', function( $order_id, $from, $to ) { $this->dispatch( 'order.updated', array( 'id' => (int) $order_id, 'from' => $from, 'to' => $to ) ); }, 10, 3 );
		add_action( 'gform_after_submission', function( $entry, $form ) { $this->dispatch( 'form.submitted', array( 'form_id' => (int) $form['id'], 'entry_id' => (int) $entry['id'] ) ); }, 10, 2 );
	}
	public function dispatch( string $event, array $data ): void {
		global $wpdb;
		$hooks = $wpdb->get_results( "SELECT id,events FROM {$wpdb->prefix}appbridge_webhooks WHERE enabled=1", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( $hooks as $hook ) {
			$events = array_filter( array_map( 'trim', explode( ',', (string) $hook['events'] ) ) );
			if ( ! in_array( '*', $events, true ) && ! in_array( $event, $events, true ) ) { continue; }
			$this->enqueue( (int) $hook['id'], $event, $data, wp_generate_uuid4(), 1, 0 );
		}
	}
	private function enqueue( int $webhook_id, string $event, array $data, string $event_id, int $attempt, int $delay ): void {
		$args = array( $webhook_id, $event, $data, $event_id, $attempt );
		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action( time() + $delay, 'appbridge_deliver_webhook', $args, 'appbridge' );
		} else {
			wp_schedule_single_event( time() + max( 1, $delay ), 'appbridge_deliver_webhook', $args );
		}
	}
	public function process_delivery( int $webhook_id, string $event, array $data, string $event_id, int $attempt = 1 ): void {
		global $wpdb;
		$hook = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}appbridge_webhooks WHERE id=%d AND enabled=1 LIMIT 1", $webhook_id ), ARRAY_A );
		if ( ! $hook ) { return; }
		$secret = $this->decrypt( (string) $hook['secret_cipher'] );
		if ( '' === $secret ) { return; }
		$payload = wp_json_encode( array( 'id' => $event_id, 'event' => $event, 'created_at' => gmdate( DATE_ATOM ), 'data' => $data ) );
		$signature = hash_hmac( 'sha256', $payload, $secret );
		$response = wp_safe_remote_post( $hook['url'], array( 'timeout' => 10, 'redirection' => 0, 'headers' => array( 'Content-Type' => 'application/json', 'X-AppBridge-Event' => $event, 'X-AppBridge-Event-ID' => $event_id, 'X-AppBridge-Signature' => 'sha256=' . $signature ), 'body' => $payload ) );
		$code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
		$excerpt = is_wp_error( $response ) ? $response->get_error_message() : substr( (string) wp_remote_retrieve_body( $response ), 0, 1000 );
		$complete = $code >= 200 && $code < 300;
		$wpdb->insert( $wpdb->prefix . 'appbridge_webhook_deliveries', array( 'webhook_id' => $webhook_id, 'event' => $event, 'event_id' => $event_id, 'attempt' => $attempt, 'status_code' => $code ?: null, 'response_excerpt' => $excerpt, 'status' => $complete ? 'complete' : ( $attempt >= 4 ? 'failed' : 'retry' ), 'next_attempt_at' => $complete || $attempt >= 4 ? null : gmdate( 'Y-m-d H:i:s', time() + ( 30 * ( 2 ** ( $attempt - 1 ) ) ) ), 'created_at' => current_time( 'mysql', true ) ) );
		if ( ! $complete && $attempt < 4 ) {
			$this->enqueue( $webhook_id, $event, $data, $event_id, $attempt + 1, 30 * ( 2 ** ( $attempt - 1 ) ) );
		}
	}
	public static function encrypt( string $plain ): string {
		if ( ! function_exists( 'openssl_encrypt' ) ) { return ''; }
		$key = hash( 'sha256', wp_salt( 'auth' ), true ); $iv = random_bytes( 12 ); $tag = '';
		$cipher = openssl_encrypt( $plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
		return is_string( $cipher ) ? base64_encode( $iv . $tag . $cipher ) : '';
	}
	private function decrypt( string $encoded ): string {
		if ( ! function_exists( 'openssl_decrypt' ) ) { return ''; }
		$raw = base64_decode( $encoded, true ); if ( false === $raw || strlen( $raw ) < 29 ) { return ''; }
		$key = hash( 'sha256', wp_salt( 'auth' ), true ); $iv = substr( $raw, 0, 12 ); $tag = substr( $raw, 12, 16 ); $cipher = substr( $raw, 28 );
		$plain = openssl_decrypt( $cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag ); return is_string( $plain ) ? $plain : '';
	}
}
