<?php
/** Uninstall AppBridge API Gateway. */
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( ! defined( 'APPBRIDGE_REMOVE_DATA' ) || ! APPBRIDGE_REMOVE_DATA ) {
	return;
}

global $wpdb;
$tables = array(
	$wpdb->prefix . 'appbridge_tokens',
	$wpdb->prefix . 'appbridge_devices',
	$wpdb->prefix . 'appbridge_audit',
	$wpdb->prefix . 'appbridge_notifications',
	$wpdb->prefix . 'appbridge_idempotency',
	$wpdb->prefix . 'appbridge_webhooks',
	$wpdb->prefix . 'appbridge_webhook_deliveries',
);
foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}
delete_option( 'appbridge_settings' );
delete_option( 'appbridge_db_version' );
