<?php
namespace AppBridge\ApiGateway\Auth;

use AppBridge\ApiGateway\Services\AuditService;
use AppBridge\ApiGateway\Support\Settings;
use WP_Error;

defined( 'ABSPATH' ) || exit;

final class TokenService {
	public function issue_pair( int $user_id, array $scopes = array(), string $client_id = 'default', ?string $family_id = null ): array|WP_Error {
		$family_id = $family_id ?: wp_generate_uuid4();
		$access = $this->issue( $user_id, 'access', (int) Settings::get( 'access_token_ttl', 3600 ), $scopes, $client_id, $family_id );
		$refresh = $this->issue( $user_id, 'refresh', (int) Settings::get( 'refresh_token_ttl', 2592000 ), $scopes, $client_id, $family_id );
		if ( is_wp_error( $access ) || is_wp_error( $refresh ) ) {
			return new WP_Error( 'appbridge_token_error', __( 'Unable to issue session tokens.', 'appbridge-api-gateway' ), array( 'status' => 500 ) );
		}
		AuditService::log( 'token_issued', 'user:' . $user_id, array( 'client_id' => $client_id ), $user_id );
		return array(
			'token_type'    => 'Bearer',
			'access_token'  => $access,
			'expires_in'    => (int) Settings::get( 'access_token_ttl', 3600 ),
			'refresh_token' => $refresh,
			'refresh_expires_in' => (int) Settings::get( 'refresh_token_ttl', 2592000 ),
			'scope'         => implode( ' ', $scopes ),
		);
	}

	private function issue( int $user_id, string $type, int $ttl, array $scopes, string $client_id, string $family_id ): string|WP_Error {
		global $wpdb;
		$plain = rtrim( strtr( base64_encode( random_bytes( 48 ) ), '+/', '-_' ), '=' );
		$ok = $wpdb->insert(
			$wpdb->prefix . 'appbridge_tokens',
			array(
				'user_id'    => $user_id,
				'token_hash' => hash( 'sha256', $plain ),
				'type'       => $type,
				'family_id'  => $family_id,
				'client_id'  => sanitize_text_field( $client_id ),
				'scopes'     => wp_json_encode( array_values( array_unique( array_map( 'sanitize_key', $scopes ) ) ) ),
				'expires_at' => gmdate( 'Y-m-d H:i:s', time() + max( 60, $ttl ) ),
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		return false === $ok ? new WP_Error( 'appbridge_db_error' ) : $plain;
	}

	public function validate( string $plain, string $type = 'access' ): array|WP_Error {
		global $wpdb;
		$hash = hash( 'sha256', $plain );
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}appbridge_tokens WHERE token_hash = %s AND type = %s LIMIT 1",
				$hash,
				$type
			),
			ARRAY_A
		);
		if ( ! $row || ! empty( $row['revoked_at'] ) || strtotime( $row['expires_at'] . ' UTC' ) <= time() ) {
			return new WP_Error( 'appbridge_invalid_token', __( 'The access token is invalid or expired.', 'appbridge-api-gateway' ), array( 'status' => 401 ) );
		}
		$wpdb->update( $wpdb->prefix . 'appbridge_tokens', array( 'last_used_at' => current_time( 'mysql', true ) ), array( 'id' => (int) $row['id'] ), array( '%s' ), array( '%d' ) );
		$row['scopes'] = json_decode( (string) $row['scopes'], true ) ?: array();
		return $row;
	}

	public function rotate_refresh( string $plain ): array|WP_Error {
		$row = $this->validate( $plain, 'refresh' );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		$this->revoke_family( (string) $row['family_id'] );
		return $this->issue_pair( (int) $row['user_id'], (array) $row['scopes'], (string) $row['client_id'] );
	}

	public function revoke_family( string $family_id ): void {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}appbridge_tokens SET revoked_at = %s WHERE family_id = %s AND revoked_at IS NULL",
				current_time( 'mysql', true ),
				$family_id
			)
		);
	}

	public function revoke_user( int $user_id ): void {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}appbridge_tokens SET revoked_at = %s WHERE user_id = %d AND revoked_at IS NULL",
				current_time( 'mysql', true ),
				$user_id
			)
		);
	}
}
