<?php
namespace AppBridge\ApiGateway\Database;

defined( 'ABSPATH' ) || exit;

final class Schema {
	public const VERSION = '1.0.0';

	public static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$p = $wpdb->prefix;

		$sql = array();
		$sql[] = "CREATE TABLE {$p}appbridge_tokens (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			token_hash char(64) NOT NULL,
			type varchar(16) NOT NULL,
			family_id char(36) NOT NULL,
			client_id varchar(100) NOT NULL DEFAULT 'default',
			scopes text NULL,
			expires_at datetime NOT NULL,
			last_used_at datetime NULL,
			revoked_at datetime NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY token_hash (token_hash),
			KEY user_type (user_id,type),
			KEY family_id (family_id)
		) {$charset};";
		$sql[] = "CREATE TABLE {$p}appbridge_devices (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			device_uuid varchar(191) NOT NULL,
			platform varchar(30) NOT NULL,
			push_token varchar(255) NULL,
			app_version varchar(50) NULL,
			device_name varchar(100) NULL,
			locale varchar(20) NULL,
			last_ip varchar(45) NULL,
			last_seen_at datetime NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY user_device (user_id,device_uuid)
		) {$charset};";
		$sql[] = "CREATE TABLE {$p}appbridge_audit (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NULL,
			action varchar(100) NOT NULL,
			resource varchar(191) NULL,
			request_id varchar(64) NULL,
			ip varchar(45) NULL,
			client_id varchar(100) NULL,
			context_json longtext NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY action_created (action,created_at),
			KEY user_created (user_id,created_at)
		) {$charset};";
		$sql[] = "CREATE TABLE {$p}appbridge_notifications (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			type varchar(80) NOT NULL,
			title varchar(191) NOT NULL,
			body text NULL,
			data_json longtext NULL,
			read_at datetime NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY user_read_created (user_id,read_at,created_at)
		) {$charset};";
		$sql[] = "CREATE TABLE {$p}appbridge_idempotency (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			idempotency_key varchar(191) NOT NULL,
			user_id bigint(20) unsigned NULL,
			route varchar(191) NOT NULL,
			request_hash char(64) NOT NULL,
			status_code smallint unsigned NULL,
			response_json longtext NULL,
			expires_at datetime NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY route_key (route,idempotency_key),
			KEY expires_at (expires_at)
		) {$charset};";
		$sql[] = "CREATE TABLE {$p}appbridge_webhooks (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(191) NOT NULL,
			url text NOT NULL,
			secret_hash char(64) NOT NULL,
			secret_cipher longtext NULL,
			events text NOT NULL,
			enabled tinyint(1) NOT NULL DEFAULT 1,
			created_by bigint(20) unsigned NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY enabled (enabled)
		) {$charset};";
		$sql[] = "CREATE TABLE {$p}appbridge_webhook_deliveries (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			webhook_id bigint(20) unsigned NOT NULL,
			event varchar(100) NOT NULL,
			event_id char(36) NOT NULL,
			attempt smallint unsigned NOT NULL DEFAULT 1,
			status_code smallint unsigned NULL,
			response_excerpt text NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			next_attempt_at datetime NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY webhook_status (webhook_id,status),
			KEY event_id (event_id)
		) {$charset};";

		foreach ( $sql as $query ) {
			dbDelta( $query );
		}
		update_option( 'appbridge_db_version', self::VERSION, false );
	}
}
