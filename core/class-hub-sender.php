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
		
		if ( 'customer' === $args['target_mode'] && $args['entity'] instanceof WC_Order ) {
			$target_num = $args['entity']->get_billing_phone();
		}

		if ( empty( $target_num ) ) return array('success' => false, 'msg' => 'شماره موبایل گیرنده یافت نشد.');

		$response = wp_remote_post( 'https://api.melipayamak.com/json/Simple.ashx', array(
			'body' => array(
				'username' => $provider['username'],
				'password' => $provider['password'],
				'to'       => $target_num,
				'from'     => $provider['from_number'],
				'text'     => $args['message']
			)
		));

        if ( is_wp_error( $response ) ) {
            return array('success' => false, 'msg' => $response->get_error_message());
        }

        return array('success' => true, 'msg' => 'پیامک با موفقیت ارسال شد.');
	}

	private static function send_telegram( $args ) {
		$webhooks = get_option( 'hub_webhooks', array() );
		$conn_id = isset($args['connection_id']) ? $args['connection_id'] : '';
		
        if ( empty($conn_id) || ! isset( $webhooks[$conn_id] ) ) {
            return array('success' => false, 'msg' => 'ربات تلگرام یافت نشد.');
        }

		$bot = $webhooks[$conn_id];
		$chat_id = ! empty( $args['target_value'] ) ? $args['target_value'] : $bot['chat_id'];

		if ( empty( $chat_id ) ) return array('success' => false, 'msg' => 'شناسه چت یافت نشد.');

		$response = wp_remote_post( "https://api.telegram.org/bot" . $bot['token'] . "/sendMessage", array(
			'body' => array(
				'chat_id' => $chat_id,
				'text'    => $args['message']
			)
		));

        if ( is_wp_error( $response ) ) return array('success' => false, 'msg' => $response->get_error_message());
        return array('success' => true, 'msg' => 'پیام ارسال شد.');
	}

	private static function send_webhook_payload( $args ) {
		$webhooks = get_option( 'hub_webhooks', array() );
		$conn_id = isset($args['connection_id']) ? $args['connection_id'] : '';
		
        if ( empty($conn_id) || ! isset( $webhooks[$conn_id] ) ) {
            return array('success' => false, 'msg' => 'ارسال ناموفق: کانال ارتباطی (وب‌هوک) یافت نشد.');
        }

		$url = $webhooks[$conn_id]['url'];
		if(empty($url)) return array('success' => false, 'msg' => 'آدرس URL برای این کانال خالی است.');
		
        // ایجاد پکیج حرفه‌ای شامل تمام دیتای سفارش برای n8n
        $payload = array(
            'message'     => $args['message'],
            'event_type'  => $args['entity_type'],
            'timestamp'   => current_time( 'mysql' )
        );

        // ارسال دیتای غنی سفارش به n8n
        if ( 'order' === $args['entity_type'] && $args['entity'] instanceof WC_Order ) {
            $order = $args['entity'];
            $payload['order'] = array(
                'id'            => $order->get_id(),
                'total'         => $order->get_total(),
                'status'        => $order->get_status(),
                'currency'      => $order->get_currency(),
                'payment_method'=> $order->get_payment_method_title(),
                'customer'      => array(
                    'first_name' => $order->get_billing_first_name(),
                    'last_name'  => $order->get_billing_last_name(),
                    'phone'      => $order->get_billing_phone(),
                    'email'      => $order->get_billing_email(),
                ),
                'items'         => array()
            );
            
            foreach( $order->get_items() as $item ) {
                $payload['order']['items'][] = array(
                    'name' => $item->get_name(),
                    'qty'  => $item->get_quantity(),
                    'total'=> $item->get_total()
                );
            }
        }

		$response = wp_remote_post( $url, array(
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => json_encode( $payload ),
            'timeout' => 15
		));

        if ( is_wp_error( $response ) ) return array('success' => false, 'msg' => $response->get_error_message());
        
        $code = wp_remote_retrieve_response_code($response);
        if($code >= 200 && $code < 300) {
            return array('success' => true, 'msg' => 'ارسال با موفقیت به وب‌هوک n8n انجام شد.');
        } else {
            return array('success' => false, 'msg' => 'خطای سرور n8n با کد: ' . $code);
        }
	}

	private static function send_html_email( $args ) {
		$to = $args['target_value'];
		if ( 'customer' === $args['target_mode'] && $args['entity'] instanceof WC_Order ) {
			$to = $args['entity']->get_billing_email();
		}
		if ( empty( $to ) ) return array('success' => false, 'msg' => 'ایمیل گیرنده خالی است.');

		$subject = 'اطلاع‌رسانی فروشگاه';
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		wp_mail( $to, $subject, wpautop( $args['message'] ), $headers );
        return array('success' => true, 'msg' => 'ایمیل ارسال شد.');
	}

	private static function internal_add_order_note( $args ) {
		if ( 'order' === $args['entity_type'] && $args['entity'] instanceof WC_Order ) {
			$args['entity']->add_order_note( $args['message'] );
            return array('success'=>true, 'msg'=>'یادداشت درج شد.');
		}
        return array('success'=>false, 'msg'=>'موجودیت سفارش نیست.');
	}

	private static function internal_change_order_status( $args ) {
		// منطق ساده شده
        return array('success'=>false, 'msg'=>'موجودیت سفارش نیست.');
	}
}