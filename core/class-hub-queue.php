<?php

/**
 * Manages the event queue in the database.
 */
class Hub_Queue {

	/**
	 * Add an event to the queue and trigger immediate processing via Action Scheduler.
	 *
	 * @param string $event_type The type of event (e.g., order.created).
	 * @param array  $payload    The data to send to n8n.
	 * @param int    $priority   Priority (lower number = higher priority).
	 * @return int|false The inserted ID or false on error.
	 */
	public static function push( $event_type, $payload, $priority = 10 ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'hub_queue';
		$json_payload = wp_json_encode( $payload, JSON_UNESCAPED_UNICODE );

		$result = $wpdb->insert(
			$table_name,
			array(
				'event_type' => $event_type,
				'payload'    => $json_payload,
				'status'     => 'pending',
				'priority'   => $priority,
				'created_at' => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( $result ) {
			$insert_id = $wpdb->insert_id;

			// --- تغییر مهم: ایجاد اکشن آنی برای پردازش همین آیتم ---
			if ( function_exists( 'as_schedule_single_action' ) ) {
				as_schedule_single_action( time(), 'hub_process_queue_item', array( 'id' => $insert_id ), 'hub_queue' );
			}

			Hub_Logger::log( "Event queued: $event_type (ID: $insert_id)", 'info', 'queue', $payload );
			return $insert_id;
		} else {
			Hub_Logger::log( "Failed to queue event: $event_type", 'error', 'queue', $wpdb->last_error );
			return false;
		}
	}

	/**
	 * Fetch pending items from the queue.
	 *
	 * @param int $limit Number of items to fetch.
	 * @return array Objects from the database.
	 */
	public static function fetch_batch( $limit = 5 ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'hub_queue';

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $table_name 
			 WHERE status = 'pending' 
			 OR (status = 'failed' AND attempts < 3)
			 ORDER BY priority ASC, id ASC 
			 LIMIT %d",
			$limit
		) );
	}

	/**
	 * Update the status of a queue item.
	 *
	 * @param int    $id     The queue ID.
	 * @param string $status new status (processing, completed, failed).
	 */
	public static function update_status( $id, $status ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'hub_queue';

		$data = array(
			'status'     => $status,
			'updated_at' => current_time( 'mysql' ),
		);

		if ( $status === 'failed' ) {
			$wpdb->query( $wpdb->prepare( "UPDATE $table_name SET attempts = attempts + 1 WHERE id = %d", $id ) );
		}

		$wpdb->update(
			$table_name,
			$data,
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}
}