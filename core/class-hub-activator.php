<?php
/**
 * Fired during plugin activation
 *
 * @package    Automation_Hub
 * @subpackage Automation_Hub/core
 */

class Hub_Activator {

	/**
	 * Activation runner.
	 */
	public static function activate() {
		self::create_abandoned_cart_table();
		self::migrate_rules_to_v2();
	}

	/**
	 * Create database table for tracking abandoned carts
	 */
	private static function create_abandoned_cart_table() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'hub_abandoned_carts';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			cart_key varchar(64) NOT NULL,
			user_id bigint(20) DEFAULT 0,
			email varchar(100) DEFAULT '',
			phone varchar(20) DEFAULT '',
			cart_contents longtext NOT NULL,
			last_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			status varchar(20) DEFAULT 'abandoned',
			PRIMARY KEY  (id),
			UNIQUE KEY cart_key (cart_key)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Migrates old flat rules architecture to Phase 2 multi-action grid structure
	 */
	public static function migrate_rules_to_v2() {
		$rules = get_option( 'hub_rules', array() );
		if ( ! is_array( $rules ) || empty( $rules ) ) {
			return;
		}

		$is_updated = false;

		foreach ( $rules as $key => $rule ) {
			// If already migrated to actions architecture, skip
			if ( isset( $rule['actions'] ) && is_array( $rule['actions'] ) ) {
				continue;
			}

			$new_actions = array();

			// Migrate old n8n block
			if ( ! empty( $rule['active_n8n'] ) ) {
				$new_actions[] = array(
					'id'            => 'act_' . uniqid(),
					'type'          => 'n8n',
					'connection_id' => isset( $rule['webhook_id'] ) ? $rule['webhook_id'] : '',
					'target_mode'   => 'custom',
					'target_value'  => '',
					'message'       => isset( $rule['message_n8n'] ) ? $rule['message_n8n'] : '',
					'delay'         => array( 'enabled' => false, 'value' => 0, 'unit' => 'minutes' ),
					'meta'          => array()
				);
			}

			// Migrate old SMS block
			if ( ! empty( $rule['active_sms'] ) ) {
				$new_actions[] = array(
					'id'            => 'act_' . uniqid(),
					'type'          => 'sms',
					'connection_id' => isset( $rule['sms_provider_id'] ) ? $rule['sms_provider_id'] : '',
					'target_mode'   => isset( $rule['sms_target'] ) ? $rule['sms_target'] : 'customer',
					'target_value'  => isset( $rule['sms_custom_num'] ) ? $rule['sms_custom_num'] : '',
					'message'       => isset( $rule['message_sms'] ) ? $rule['message_sms'] : '',
					'delay'         => array( 'enabled' => false, 'value' => 0, 'unit' => 'minutes' ),
					'meta'          => array()
				);
			}

			// Migrate old Telegram block
			if ( ! empty( $rule['active_tg'] ) ) {
				$new_actions[] = array(
					'id'            => 'act_' . uniqid(),
					'type'          => 'telegram',
					'connection_id' => isset( $rule['tg_bot_id'] ) ? $rule['tg_bot_id'] : '',
					'target_mode'   => 'custom',
					'target_value'  => isset( $rule['tg_chat_id'] ) ? $rule['tg_chat_id'] : '',
					'message'       => isset( $rule['message_tg'] ) ? $rule['message_tg'] : '',
					'delay'         => array( 'enabled' => false, 'value' => 0, 'unit' => 'minutes' ),
					'meta'          => array()
				);
			}

			// Clean up old properties and inject new components
			unset( $rules[$key]['active_n8n'], $rules[$key]['webhook_id'], $rules[$key]['message_n8n'] );
			unset( $rules[$key]['active_sms'], $rules[$key]['sms_provider_id'], $rules[$key]['sms_target'], $rules[$key]['sms_custom_num'], $rules[$key]['message_sms'] );
			unset( $rules[$key]['active_tg'], $rules[$key]['tg_bot_id'], $rules[$key]['tg_chat_id'], $rules[$key]['message_tg'] );

			$rules[$key]['condition_logic'] = 'AND';
			$rules[$key]['conditions']      = array();
			$rules[$key]['actions']         = $new_actions;

			$is_updated = true;
		}

		if ( $is_updated ) {
			update_option( 'hub_rules', $rules );
		}
	}
}