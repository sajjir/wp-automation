<?php

/**
 * Handles API Key generation and request validation.
 */
class Hub_Security {

	const OPTION_NAME = 'hub_api_key';

	/**
	 * Get the current API Key or generate one if not exists.
	 *
	 * @return string The API Key.
	 */
	public static function get_api_key() {
		$key = get_option( self::OPTION_NAME );
		if ( ! $key ) {
			$key = self::generate_api_key();
		}
		return $key;
	}

	/**
	 * Generate a new secure API Key and save it.
	 *
	 * @return string The new key.
	 */
	public static function generate_api_key() {
		// تولید یک کلید رندوم ۳۲ کاراکتری امن
		$key = 'sk_' . bin2hex( random_bytes( 20 ) );
		update_option( self::OPTION_NAME, $key );
		
		Hub_Logger::log( 'New API Key generated', 'warning', 'security' );
		
		return $key;
	}

	/**
	 * Validate an incoming request.
	 * Checks for X-Hub-Api-Key header.
	 *
	 * @param WP_REST_Request $request
	 * @return boolean|WP_Error
	 */
	public static function validate_request( $request ) {
		$sent_key = $request->get_header( 'x_hub_api_key' ); // وردپرس هدرها را lowercase می‌کند
		$stored_key = self::get_api_key();

		if ( empty( $sent_key ) || $sent_key !== $stored_key ) {
			Hub_Logger::log( 'Unauthorized access attempt via REST API', 'error', 'security', array( 'ip' => $_SERVER['REMOTE_ADDR'] ) );
			return new WP_Error( 'rest_forbidden', __( 'Invalid API Key.', 'automation-hub' ), array( 'status' => 401 ) );
		}

		return true;
	}
}