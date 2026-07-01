<?php
/**
 * Core Automation Hub Bridge Engine
 *
 * @package    Automation_Hub
 * @subpackage Automation_Hub/core
 */

class Hub_Bridge {

	public static function init() {
		$instance = new self();
		
		// معرفی زمان‌بندی سفارشی برای کرون (رفع باگ ۳)
		add_filter( 'cron_schedules', array( $instance, 'add_cron_schedules' ) );

		// Triggers hooks
		add_action( 'woocommerce_order_status_changed', array( $instance, 'trigger_order_status' ), 10, 4 );
		
		// Phase 2 Triggers
		add_action( 'woocommerce_add_to_cart', array( $instance, 'track_cart_addition' ), 10, 6 );
		add_action( 'woocommerce_low_stock', array( $instance, 'trigger_low_stock' ) );
		add_action( 'woocommerce_no_stock', array( $instance, 'trigger_no_stock' ) );
		add_action( 'woocommerce_order_refunded', array( $instance, 'trigger_order_refunded' ), 10, 2 );
		add_action( 'transition_comment_status', array( $instance, 'trigger_product_review' ), 10, 3 );
		add_action( 'wp_login', array( $instance, 'update_user_last_login' ), 10, 2 );
		
		// Cron Actions
		add_action( 'hub_cron_fifteen_minute_event', array( $instance, 'check_abandoned_carts' ) );
		add_action( 'hub_cron_daily_event', array( $instance, 'check_winback_users' ) );
		add_action( 'hub_custom_scheduled_rule_trigger', array( $instance, 'execute_scheduled_rule' ), 10, 1 );

		// Delayed action execution handler from Action Scheduler
		add_action( 'hub_process_delayed_action', array( $instance, 'execute_delayed_action' ), 10, 4 );

		// Register crons if not exists
		if ( ! wp_next_scheduled( 'hub_cron_fifteen_minute_event' ) ) {
			wp_schedule_event( time(), 'quarter_hourly', 'hub_cron_fifteen_minute_event' );
		}
		if ( ! wp_next_scheduled( 'hub_cron_daily_event' ) ) {
			wp_schedule_event( time(), 'daily', 'hub_cron_daily_event' );
		}
	}

	public function add_cron_schedules( $schedules ) {
		if ( ! isset( $schedules['quarter_hourly'] ) ) {
			$schedules['quarter_hourly'] = array(
				'interval' => 15 * 60,
				'display'  => __( 'هر ۱۵ دقیقه', 'automation-hub' )
			);
		}
		return $schedules;
	}

	public function trigger_order_status( $order_id, $old_status, $new_status, $order ) {
		$this->process_rules( 'order_status', $new_status, $order, 'order' );
	}

	public function trigger_low_stock( $product ) {
		$this->process_rules( 'low_stock', 'low', $product, 'product' );
	}

	public function trigger_no_stock( $product ) {
		$this->process_rules( 'low_stock', 'out', $product, 'product' );
	}

	public function trigger_order_refunded( $order_id, $refund_id ) {
		$order = wc_get_order( $order_id );
		$this->process_rules( 'order_refunded', 'refund', $order, 'order' );
	}

	public function trigger_product_review( $new_status, $old_status, $comment ) {
		if ( 'approved' === $new_status && 'product' === get_post_type( $comment->comment_post_ID ) ) {
			$this->process_rules( 'product_review', 'approved', $comment, 'review' );
		}
	}

	public function update_user_last_login( $user_login, $user ) {
		update_user_meta( $user->ID, 'hub_last_login', current_time( 'mysql' ) );
	}

	public function track_cart_addition( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
		if ( ! function_exists( 'WC' ) ) return;
		global $wpdb;
		
		$current_user_id = get_current_user_id();
		$cart = WC()->cart->get_cart();
		$cart_contents = maybe_serialize( $cart );
		$session_cookie = WC()->session->get_customer_id();
		$table_name = $wpdb->prefix . 'hub_abandoned_carts';

		$wpdb->replace(
			$table_name,
			array(
				'cart_key'      => md5( $session_cookie ),
				'user_id'       => $current_user_id,
				'cart_contents' => $cart_contents,
				'status'        => 'active',
				'last_updated'  => current_time( 'mysql' )
			),
			array( '%s', '%d', '%s', '%s', '%s' )
		);
	}

	public function check_abandoned_carts() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'hub_abandoned_carts';
		$delay_time = date( 'Y-m-d H:i:s', strtotime( '-30 minutes' ) );

		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $table_name WHERE status = 'active' AND last_updated <= %s", $delay_time
		) );

		foreach ( $results as $row ) {
			$wpdb->update( $table_name, array( 'status' => 'abandoned' ), array( 'id' => $row->id ) );
			$this->process_rules( 'abandoned_cart', 'abandoned', $row, 'cart' );
		}
	}

	public function check_winback_users() {
		$rules = get_option( 'hub_rules', array() );
		foreach ( $rules as $rule ) {
			if ( isset( $rule['trigger'] ) && 'winback_user' === $rule['trigger'] ) {
				$days = intval( $rule['sub_trigger'] );
				if ( $days <= 0 ) $days = 30;

				$target_date = date( 'Y-m-d H:i:s', strtotime( "-$days days" ) );
				
				$users = get_users( array(
					'meta_query' => array(
						'relation' => 'AND',
						array(
							'key'     => 'hub_last_login',
							'value'   => $target_date,
							'compare' => '<=',
							'type'    => 'DATETIME'
						)
					)
				) );

				foreach ( $users as $user ) {
					$this->process_rules( 'winback_user', $rule['sub_trigger'], $user, 'user' );
				}
			}
		}
	}

	public function execute_scheduled_rule( $rule_id ) {
		$rules = get_option( 'hub_rules', array() );
		if ( isset( $rules[$rule_id] ) ) {
			$this->process_rules( 'scheduled_cron', $rules[$rule_id]['sub_trigger'], null, 'none', $rule_id );
		}
	}

	/**
	 * Main Processing Engine for Engine Rules Architecture v2
	 */
	public function process_rules( $trigger, $sub_trigger, $entity, $entity_type, $forced_rule_id = null ) {
		$rules = get_option( 'hub_rules', array() );
		if ( empty( $rules ) ) return;

		foreach ( $rules as $rule_id => $rule ) {
			if ( null !== $forced_rule_id && $rule_id !== $forced_rule_id ) {
				continue;
			}

			if ( $rule['trigger'] !== $trigger ) continue;
			if ( ! empty( $rule['sub_trigger'] ) && $rule['sub_trigger'] !== $sub_trigger && 'winback_user' !== $trigger && 'scheduled_cron' !== $trigger ) {
				continue;
			}

			if ( ! $this->check_conditions( $rule, $entity, $entity_type ) ) {
				continue;
			}

			if ( ! isset( $rule['actions'] ) || ! is_array( $rule['actions'] ) ) {
				continue;
			}

			foreach ( $rule['actions'] as $action ) {
				$delay = isset( $action['delay'] ) ? $action['delay'] : array();
				
				if ( ! empty( $delay['enabled'] ) && intval( $delay['value'] ) > 0 ) {
					$delay_seconds = intval( $delay['value'] );
					if ( 'hours' === $delay['unit'] ) $delay_seconds *= 3600;
					if ( 'days' === $delay['unit'] ) $delay_seconds *= 86400;
					if ( 'minutes' === $delay['unit'] ) $delay_seconds *= 60;

					$entity_id = 0;
					if ( 'order' === $entity_type && is_object( $entity ) ) $entity_id = $entity->get_id();
					elseif ( 'product' === $entity_type && is_object( $entity ) ) $entity_id = $entity->get_id();
					elseif ( 'user' === $entity_type && is_object( $entity ) ) $entity_id = $entity->ID;
					elseif ( 'review' === $entity_type && is_object( $entity ) ) $entity_id = $entity->comment_ID;
					elseif ( 'cart' === $entity_type && is_object( $entity ) ) $entity_id = $entity->id;

					if ( function_exists( 'as_schedule_single_action' ) ) {
						as_schedule_single_action(
							time() + $delay_seconds,
							'hub_process_delayed_action',
							array(
								'rule_id'     => $rule_id,
								'action_id'   => $action['id'],
								'entity_id'   => $entity_id,
								'entity_type' => $entity_type
							)
						);
					}
				} else {
					$this->dispatch_direct_action( $rule, $action, $entity, $entity_type );
				}
			}
		}
	}

	public function execute_delayed_action( $rule_id, $action_id, $entity_id, $entity_type ) {
		$rules = get_option( 'hub_rules', array() );
		if ( ! isset( $rules[$rule_id] ) ) return;
		
		$rule = $rules[$rule_id];
		$target_action = null;

		foreach ( $rule['actions'] as $action ) {
			if ( $action['id'] === $action_id ) {
				$target_action = $action;
				break;
			}
		}

		if ( ! $target_action ) return;

		$entity = null;
		if ( 'order' === $entity_type ) $entity = wc_get_order( $entity_id );
		elseif ( 'product' === $entity_type ) $entity = wc_get_product( $entity_id );
		elseif ( 'user' === $entity_type ) $entity = get_user_by( 'id', $entity_id );
		elseif ( 'review' === $entity_type ) $entity = get_comment( $entity_id );
		elseif ( 'cart' === $entity_type ) {
			global $wpdb;
			$entity = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}hub_abandoned_carts WHERE id = %d", $entity_id ) );
		}

		if ( ! $this->check_conditions( $rule, $entity, $entity_type ) ) {
			return; 
		}

		$this->dispatch_direct_action( $rule, $target_action, $entity, $entity_type );
	}

	private function dispatch_direct_action( $rule, $action, $entity, $entity_type ) {
		$parsed_message = $this->parse_shortcodes( $action['message'], $entity, $entity_type );
		
		$args = array(
			'connection_id' => $action['connection_id'],
			'target_mode'   => isset($action['target_mode']) ? $action['target_mode'] : 'custom',
			'target_value'  => isset($action['target_value']) ? $action['target_value'] : '',
			'message'       => $parsed_message,
			'meta'          => isset( $action['meta'] ) ? $action['meta'] : array(),
			'entity'        => $entity,
			'entity_type'   => $entity_type
		);

		if ( class_exists( 'Hub_Sender' ) ) {
			Hub_Sender::dispatch( $action['type'], $args );
		}
	}

	public function check_conditions( $rule, $entity, $entity_type ) {
		if ( ! isset( $rule['conditions'] ) || empty( $rule['conditions'] ) ) {
			return true;
		}

		$logic = isset( $rule['condition_logic'] ) ? $rule['condition_logic'] : 'AND';
		$results = array();

		foreach ( $rule['conditions'] as $cond ) {
			$current_val = '';
			$field = $cond['field'];
			$op = $cond['operator'];
			$target_val = $cond['value'];

			if ( 'order' === $entity_type && $entity instanceof WC_Order ) {
				switch ( $field ) {
					case 'order_total': $current_val = $entity->get_total(); break;
					case 'billing_city': $current_val = $entity->get_billing_city(); break;
					case 'billing_state': $current_val = $entity->get_billing_state(); break;
					case 'payment_method': $current_val = $entity->get_payment_method(); break;
					case 'coupon_used': 
						$coupons = $entity->get_coupon_codes();
						$current_val = ! empty( $coupons ) ? implode( ',', $coupons ) : '';
						break;
				}
			} elseif ( 'user' === $entity_type && $entity instanceof WP_User ) {
				switch ( $field ) {
					case 'user_role': $current_val = ! empty( $entity->roles ) ? $entity->roles[0] : ''; break;
					case 'email_domain': 
						$parts = explode( '@', $entity->user_email );
						$current_val = isset( $parts[1] ) ? $parts[1] : '';
						break;
				}
			}

			$matched = false;
			switch ( $op ) {
				case 'equals': $matched = ( $current_val == $target_val ); break;
				case 'not_equals': $matched = ( $current_val != $target_val ); break;
				case 'greater_than': $matched = ( floatval( $current_val ) > floatval( $target_val ) ); break;
				case 'less_than': $matched = ( floatval( $current_val ) < floatval( $target_val ) ); break;
				case 'contains': $matched = ( strpos( $current_val, $target_val ) !== false ); break;
				case 'not_contains': $matched = ( strpos( $current_val, $target_val ) === false ); break;
				case 'is_empty': $matched = empty( $current_val ); break;
				case 'is_not_empty': $matched = ! empty( $current_val ); break;
			}

			$results[] = $matched;
		}

		if ( 'OR' === $logic ) {
			return in_array( true, $results, true );
		}
		return ! in_array( false, $results, true );
	}

	/**
	 * Parse dynamic placeholders syntax {} (رفع باگ ۴)
	 */
	public function parse_shortcodes( $message, $entity, $entity_type ) {
		if ( empty( $message ) ) return '';
		$replacements = array();
		
		if ( 'order' === $entity_type && $entity instanceof WC_Order ) {
			$items = array();
			foreach ( $entity->get_items() as $item ) {
				$items[] = $item->get_name() . ' × ' . $item->get_quantity();
			}
			$replacements = array(
				'{order_id}'       => $entity->get_id(),
				'{total}'          => $entity->get_total(),
				'{full_name}'      => trim( $entity->get_billing_first_name() . ' ' . $entity->get_billing_last_name() ),
				'{phone}'          => $entity->get_billing_phone(),
				'{address}'        => $entity->get_billing_address_1() . ' ' . $entity->get_billing_city(),
				'{date}'           => wp_date( 'Y/m/d H:i', $entity->get_date_created()->getTimestamp() ),
				'{items_detailed}' => implode( '، ', $items ),
			);
		} elseif ( 'user' === $entity_type && $entity instanceof WP_User ) {
			$replacements = array(
				'{user_id}'   => $entity->ID,
				'{full_name}' => trim( $entity->first_name . ' ' . $entity->last_name ),
				'{email}'     => $entity->user_email,
			);
		} elseif ( 'product' === $entity_type && $entity instanceof WC_Product ) {
			$replacements = array(
				'{product_id}'   => $entity->get_id(),
				'{product_name}' => $entity->get_name(),
				'{price}'        => $entity->get_price(),
				'{stock}'        => $entity->get_stock_quantity(),
			);
		} elseif ( 'review' === $entity_type && is_object($entity) ) {
			$replacements = array(
				'{review_author}'  => $entity->comment_author,
				'{review_content}' => $entity->comment_content,
			);
		} elseif ( 'cart' === $entity_type && is_object($entity) ) {
			$replacements = array(
				'{cart_id}'    => $entity->id,
				'{user_email}' => $entity->email,
				'{user_phone}' => $entity->phone,
			);
		}

		if ( ! empty( $replacements ) ) {
			$message = strtr( $message, $replacements );
		}
		
		return $message;
	}
}