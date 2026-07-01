<?php

/**
 * Handles logging to the custom database table.
 */
class Hub_Logger {

	/**
	 * Log a message to the database.
	 *
	 * @param string $message The message to log.
	 * @param string $type    Type of log (info, error, warning, success).
	 * @param string $source  Where the log is coming from (e.g., 'queue', 'n8n_bridge').
	 * @param mixed  $context Optional array/object data to store as JSON.
	 */
	public static function log( $message, $type = 'info', $source = 'system', $context = null ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'hub_logs';

		// تبدیل کانتکست به JSON اگر آرایه یا آبجکت باشد
		if ( ! is_string( $context ) && ! is_null( $context ) ) {
			$context = wp_json_encode( $context, JSON_UNESCAPED_UNICODE );
		}

		$wpdb->insert(
			$table_name,
			array(
				'log_type'   => $type,
				'source'     => $source,
				'message'    => $message,
				'context'    => $context,
				'created_at' => current_time( 'mysql' ),
			),
			array(
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
			)
		);
	}
}