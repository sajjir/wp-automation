<?php
/**
 * Dispatcher core sending module layer
 *
 * @package    Automation_Hub
 * @subpackage Automation_Hub/core
 */

class Hub_Sender {

	/**
	 * Central Action Router Switching Framework
	 */
	public static function dispatch( $type, $args ) {
		switch ( $type ) {
			case 'sms':
				self::send_sms( $args );
				break;
			case 'telegram':
				self::send_telegram( $args );
				break;
			case 'n8n':
			case 'google_sheet':
				self::send_webhook_payload( $args );
				break;
			case 'email':
				self::send_html_email( $args );
				break;
			case 'whatsapp':
				self::send_whatsapp_cloud( $args );
				break;
			case 'slack':
			case 'discord':
				self::send_chat_webhook( $args, $type );
				break;
			case 'onesignal':
				self::send_onesignal_push( $args );
				break;
			case 'order_note':
				self::internal_add_order_note( $args );
				break;
			case 'order_status':
				self::internal_change_order_status( $args );
				break;
			case 'apply_coupon':
				self::internal_generate_coupon( $args );
				break;
		}
	}

	private static function send_sms( $args ) {
		$webhooks = get_option( 'hub_webhooks', array() );
		$conn_id = $args['connection_id'];
		if ( ! isset( $webhooks[$conn_id] ) ) return;

		$provider = $webhooks[$conn_id];
		$target_num = $args['target_value'];
		
		if ( 'customer' === $args['target_mode'] && $args['entity'] instanceof WC_Order ) {
			$target_num = $args['entity']->get_billing_phone();
		}

		if ( empty( $target_num ) ) return;

		// Call outward API via standard remote post execution patterns...
		wp_remote_post( 'https://api.melipayamak.com/json/Simple.ashx', array(
			'body' => array(
				'username' => $provider['username'],
				'password' => $provider['password'],
				'to'       => $target_num,
				'from'     => $provider['from_number'],
				'text'     => $args['message']
			)
		));
	}

	private static function send_telegram( $args ) {
		$webhooks = get_option( 'hub_webhooks', array() );
		$conn_id = $args['connection_id'];
		if ( ! isset( $webhooks[$conn_id] ) ) return;

		$bot = $webhooks[$conn_id];
		$chat_id = ! empty( $args['target_value'] ) ? $args['target_value'] : $bot['chat_id'];

		if ( empty( $chat_id ) ) return;

		wp_remote_post( "https://api.telegram.org/bot" . $bot['token'] . "/sendMessage", array(
			'body' => array(
				'chat_id' => $chat_id,
				'text'    => $args['message']
			)
		));
	}

	private static function send_webhook_payload( $args ) {
		$webhooks = get_option( 'hub_webhooks', array() );
		$conn_id = $args['connection_id'];
		if ( ! isset( $webhooks[$conn_id] ) ) return;

		$url = $webhooks[$conn_id]['url'];
		
		wp_remote_post( $url, array(
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => json_encode( array(
				'message'     => $args['message'],
				'entity_type' => $args['entity_type'],
				'timestamp'   => current_time( 'timestamp' )
			) )
		));
	}

	private static function send_html_email( $args ) {
		$to = $args['target_value'];
		if ( 'customer' === $args['target_mode'] && $args['entity'] instanceof WC_Order ) {
			$to = $args['entity']->get_billing_email();
		}

		if ( empty( $to ) ) return;

		$subject = isset( $args['meta']['subject'] ) ? $args['meta']['subject'] : 'اطلاع‌رسانی اتوماسیون هاب';
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		wp_mail( $to, $subject, wpautop( $args['message'] ), $headers );
	}

	private static function send_whatsapp_cloud( $args ) {
		$webhooks = get_option( 'hub_webhooks', array() );
		if ( ! isset( $webhooks[$args['connection_id']] ) ) return;
		$config = $webhooks[$args['connection_id']];

		$to = $args['target_value'];
		if ( 'customer' === $args['target_mode'] && $args['entity'] instanceof WC_Order ) {
			$to = $args['entity']->get_billing_phone();
		}

		if ( empty( $to ) ) return;

		wp_remote_post( "https://graph.facebook.com/v19.0/" . $config['phone_number_id'] . "/messages", array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $config['access_token'],
				'Content-Type'  => 'application/json'
			),
			'body' => json_encode( array(
				'messaging_product' => 'whatsapp',
				'to'                => $to,
				'type'              => 'text',
				'text'              => array( 'body' => $args['message'] )
			))
		));
	}

	private static function send_chat_webhook( $args, $platform ) {
		$webhooks = get_option( 'hub_webhooks', array() );
		if ( ! isset( $webhooks[$args['connection_id']] ) ) return;
		$url = $webhooks[$args['connection_id']]['url'];

		$key = ( 'slack' === $platform ) ? 'text' : 'content';

		wp_remote_post( $url, array(
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => json_encode( array( $key => $args['message'] ) )
		));
	}

	private static function send_onesignal_push( $args ) {
		$webhooks = get_option( 'hub_webhooks', array() );
		if ( ! isset( $webhooks[$args['connection_id']] ) ) return;
		$config = $webhooks[$args['connection_id']];

		wp_remote_post( 'https://onesignal.com/api/v1/notifications', array(
			'headers' => array(
				'Authorization' => 'Basic ' . $config['rest_api_key'],
				'Content-Type'  => 'application/json'
			),
			'body' => json_encode( array(
				'app_id'   => $config['app_id'],
				'contents' => array( 'en' => $args['message'] ),
				'included_segments' => array( 'Subscribed Users' )
			))
		));
	}

	private static function internal_add_order_note( $args ) {
		if ( 'order' === $args['entity_type'] && $args['entity'] instanceof WC_Order ) {
			$args['entity']->add_order_note( $args['message'] );
		}
	}

	private static function internal_change_order_status( $args ) {
		if ( 'order' === $args['entity_type'] && $args['entity'] instanceof WC_Order ) {
			$target_status = isset( $args['meta']['target_status'] ) ? $args['meta']['target_status'] : '';
			if ( ! empty( $target_status ) ) {
				$args['entity']->update_status( $target_status, 'تغییر وضعیت خودکار توسط اتوماسیون هاب.' );
			}
		}
	}

	private static function internal_generate_coupon( $args ) {
		if ( ! class_exists( 'WC_Coupon' ) ) return;
		
		$amount = isset( $args['meta']['coupon_amount'] ) ? floatval( $args['meta']['coupon_amount'] ) : 10;
		$expiry = isset( $args['meta']['coupon_expiry_days'] ) ? intval( $args['meta']['coupon_expiry_days'] ) : 7;
		
		$coupon_code = 'HUB-' . strtoupper( wp_generate_password( 6, false ) );
		$coupon = new WC_Coupon();
		$coupon->set_code( $coupon_code );
		$coupon->set_discount_type( 'percent' );
		$coupon->set_amount( $amount );
		$coupon->set_date_expires( time() + ( $expiry * 81640 ) );
		$coupon->set_individual_use( true );
		$coupon->save();

		if ( 'order' === $args['entity_type'] && $args['entity'] instanceof WC_Order ) {
			$args['entity']->add_order_note( sprintf( 'کد تخفیف اختصاصی %s ساخته و فعال شد.', $coupon_code ) );
		}
	}
}