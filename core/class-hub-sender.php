<?php
/**
 * Dispatcher core sending module layer
 *
 * @package    Automation_Hub
 * @subpackage Automation_Hub/core
 */

class Hub_Sender {

	public static function dispatch( $type, $args ) {
		switch ( $type ) {
			case 'sms':
				return self::send_sms( $args );
			case 'telegram':
				return self::send_telegram( $args );
			case 'n8n':
			case 'google_sheet':
				return self::send_webhook_payload( $args );
			case 'email':
				return self::send_html_email( $args );
			case 'whatsapp':
				return self::send_whatsapp_cloud( $args );
			case 'slack':
			case 'discord':
				return self::send_chat_webhook( $args, $type );
			case 'onesignal':
				return self::send_onesignal_push( $args );
			case 'order_note':
				return self::internal_add_order_note( $args );
			case 'order_status':
				return self::internal_change_order_status( $args );
            default:
                return array('success' => false, 'msg' => 'نوع اقدام نامعتبر است.');
		}
	}

	private static function send_sms( $args ) {
		$webhooks = get_option( 'hub_webhooks', array() );
		$conn_id = isset($args['connection_id']) ? $args['connection_id'] : '';
		
        if ( empty($conn_id) || ! isset( $webhooks[$conn_id] ) ) {
            return array('success' => false, 'msg' => 'کانال ارتباطی یافت نشد.');
        }

		$provider = $webhooks[$conn_id];
		$target_num = isset($args['target_value']) ? $args['target_value'] : '';
		
		if ( isset($args['target_mode']) && 'customer' === $args['target_mode'] && isset($args['entity']) && $args['entity'] instanceof WC_Order ) {
			$target_num = $args['entity']->get_billing_phone();
		}

		if ( empty( $target_num ) ) return array('success' => false, 'msg' => 'شماره موبایل گیرنده یافت نشد.');

		$response = wp_remote_post( 'https://api.melipayamak.com/json/Simple.ashx', array(
			'body' => array(
				'username' => $provider['username'],
				'password' => $provider['password'],
				'to'       => $target_num,
				'from'     => $provider['from_number'],
				'text'     => isset($args['message']) ? $args['message'] : ''
			)
		));

        if ( is_wp_error( $response ) ) {
            return array('success' => false, 'msg' => $response->get_error_message());
        }

        return array('success' => true, 'msg' => 'پیامک با موفقیت به ' . $target_num . ' ارسال شد.');
	}

	private static function send_telegram( $args ) {
		$webhooks = get_option( 'hub_webhooks', array() );
		$conn_id = isset($args['connection_id']) ? $args['connection_id'] : '';
		
        if ( empty($conn_id) || ! isset( $webhooks[$conn_id] ) ) {
            return array('success' => false, 'msg' => 'ربات تلگرام یافت نشد.');
        }

		$bot = $webhooks[$conn_id];
		$chat_id = ! empty( $args['target_value'] ) ? $args['target_value'] : $bot['chat_id'];

		if ( empty( $chat_id ) ) return array('success' => false, 'msg' => 'شناسه چت (Chat ID) یافت نشد.');

		$response = wp_remote_post( "https://api.telegram.org/bot" . $bot['token'] . "/sendMessage", array(
			'body' => array(
				'chat_id' => $chat_id,
				'text'    => isset($args['message']) ? $args['message'] : ''
			)
		));

        if ( is_wp_error( $response ) ) return array('success' => false, 'msg' => $response->get_error_message());
        return array('success' => true, 'msg' => 'پیام به تلگرام ارسال شد.');
	}

	private static function send_webhook_payload( $args ) {
		$webhooks = get_option( 'hub_webhooks', array() );
		$conn_id = isset($args['connection_id']) ? $args['connection_id'] : '';
		
        if ( empty($conn_id) || ! isset( $webhooks[$conn_id] ) ) {
            return array('success' => false, 'msg' => 'ارسال ناموفق: کانال ارتباطی انتخاب نشده یا یافت نشد.');
        }

		$url = $webhooks[$conn_id]['url'];
		
        // ایجاد پکیج حرفه‌ای و قدرتمند برای ارسال به n8n
        $payload = array(
            'message'     => isset($args['message']) ? $args['message'] : '',
            'entity_type' => isset($args['entity_type']) ? $args['entity_type'] : 'unknown',
            'timestamp'   => current_time( 'mysql' )
        );

        // اگر اکشن روی یک سفارش اتفاق می‌افتد، دیتاهای باارزش را ضمیمه کن
        if ( isset($args['entity_type']) && 'order' === $args['entity_type'] && isset($args['entity']) && $args['entity'] instanceof WC_Order ) {
            $order = $args['entity'];
            $payload['order_data'] = array(
                'id'            => $order->get_id(),
                'total'         => $order->get_total(),
                'status'        => $order->get_status(),
                'currency'      => $order->get_currency(),
                'first_name'    => $order->get_billing_first_name(),
                'last_name'     => $order->get_billing_last_name(),
                'phone'         => $order->get_billing_phone(),
                'email'         => $order->get_billing_email(),
                'payment_method'=> $order->get_payment_method_title(),
            );
        }

		$response = wp_remote_post( $url, array(
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => json_encode( $payload ),
            'timeout' => 15
		));

        if ( is_wp_error( $response ) ) {
            return array('success' => false, 'msg' => 'خطای سرور: ' . $response->get_error_message());
        }

        return array('success' => true, 'msg' => 'ارسال با موفقیت به وب‌هوک انجام شد (کد پاسخ سرور: ' . wp_remote_retrieve_response_code($response) . ')');
	}

	private static function send_html_email( $args ) {
		$to = isset($args['target_value']) ? $args['target_value'] : '';
		if ( isset($args['target_mode']) && 'customer' === $args['target_mode'] && isset($args['entity']) && $args['entity'] instanceof WC_Order ) {
			$to = $args['entity']->get_billing_email();
		}

		if ( empty( $to ) ) return array('success' => false, 'msg' => 'ایمیل گیرنده خالی است.');

		$subject = 'اطلاع‌رسانی سیستم اتوماسیون هاب';
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		wp_mail( $to, $subject, wpautop( $args['message'] ), $headers );
        return array('success' => true, 'msg' => 'ایمیل با موفقیت ارسال شد.');
	}

	private static function send_whatsapp_cloud( $args ) { return array('success'=>false, 'msg'=>'در حال توسعه'); }
	private static function send_chat_webhook( $args, $platform ) { return array('success'=>false, 'msg'=>'در حال توسعه'); }
	private static function send_onesignal_push( $args ) { return array('success'=>false, 'msg'=>'در حال توسعه'); }

	private static function internal_add_order_note( $args ) {
		if ( isset($args['entity_type']) && 'order' === $args['entity_type'] && isset($args['entity']) && $args['entity'] instanceof WC_Order ) {
			$args['entity']->add_order_note( $args['message'] );
            return array('success'=>true, 'msg'=>'یادداشت روی سفارش ثبت شد.');
		}
        return array('success'=>false, 'msg'=>'موجودیت سفارش نیست.');
	}

	private static function internal_change_order_status( $args ) {
		return array('success'=>false, 'msg'=>'موجودیت سفارش نیست.');
	}
}