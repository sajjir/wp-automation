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
			case 'apply_coupon':
				return self::internal_generate_coupon( $args );
            default:
                return array('success' => false, 'msg' => 'نوع اقدام نامعتبر است.');
		}
	}

	private static function send_sms( $args ) {
		$webhooks = get_option( 'hub_webhooks', array() );
		$conn_id = $args['connection_id'];
		
        if ( empty($conn_id) || ! isset( $webhooks[$conn_id] ) ) {
            return array('success' => false, 'msg' => 'کانال ارتباطی یافت نشد.');
        }

		$provider = $webhooks[$conn_id];
		$target_num = $args['target_value'];
		
		if ( 'customer' === $args['target_mode'] && $args['entity'] instanceof WC_Order ) {
			$target_num = $args['entity']->get_billing_phone();
		}

		if ( empty( $target_num ) ) return array('success' => false, 'msg' => 'شماره موبایل گیرنده یافت نشد.');

		$response = wp_remote_post( '[https://api.melipayamak.com/json/Simple.ashx](https://api.melipayamak.com/json/Simple.ashx)', array(
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

        return array('success' => true, 'msg' => 'پیامک با موفقیت به ' . $target_num . ' ارسال شد.');
	}

	private static function send_telegram( $args ) {
		$webhooks = get_option( 'hub_webhooks', array() );
		$conn_id = $args['connection_id'];
		
        if ( empty($conn_id) || ! isset( $webhooks[$conn_id] ) ) {
            return array('success' => false, 'msg' => 'ربات تلگرام یافت نشد.');
        }

		$bot = $webhooks[$conn_id];
		$chat_id = ! empty( $args['target_value'] ) ? $args['target_value'] : $bot['chat_id'];

		if ( empty( $chat_id ) ) return array('success' => false, 'msg' => 'شناسه چت (Chat ID) یافت نشد.');

		$response = wp_remote_post( "[https://api.telegram.org/bot](https://api.telegram.org/bot)" . $bot['token'] . "/sendMessage", array(
			'body' => array(
				'chat_id' => $chat_id,
				'text'    => $args['message']
			)
		));

        if ( is_wp_error( $response ) ) {
            return array('success' => false, 'msg' => $response->get_error_message());
        }
        return array('success' => true, 'msg' => 'پیام به تلگرام ارسال شد.');
	}

	private static function send_webhook_payload( $args ) {
		$webhooks = get_option( 'hub_webhooks', array() );
		$conn_id = $args['connection_id'];
		
        if ( empty($conn_id) || ! isset( $webhooks[$conn_id] ) ) {
            return array('success' => false, 'msg' => 'ارسال ناموفق: کانال ارتباطی انتخاب نشده یا یافت نشد.');
        }

		$url = $webhooks[$conn_id]['url'];
		
        // ایجاد پکیج حرفه‌ای و قدرتمند برای ارسال به n8n
        $payload = array(
            'message'     => $args['message'],
            'target'      => $args['target_value'],
            'entity_type' => $args['entity_type'],
            'timestamp'   => current_time( 'mysql' )
        );

        // اگر اکشن روی یک سفارش اتفاق می‌افتد، دیتاهای باارزش را ضمیمه کن
        if ( 'order' === $args['entity_type'] && $args['entity'] instanceof WC_Order ) {
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
		$to = $args['target_value'];
		if ( 'customer' === $args['target_mode'] && $args['entity'] instanceof WC_Order ) {
			$to = $args['entity']->get_billing_email();
		}

		if ( empty( $to ) ) return array('success' => false, 'msg' => 'ایمیل گیرنده خالی است.');

		$subject = isset( $args['meta']['subject'] ) ? $args['meta']['subject'] : 'اطلاع‌رسانی اتوماسیون هاب';
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		wp_mail( $to, $subject, wpautop( $args['message'] ), $headers );
        return array('success' => true, 'msg' => 'ایمیل با موفقیت ارسال شد.');
	}

	private static function send_whatsapp_cloud( $args ) {
		$webhooks = get_option( 'hub_webhooks', array() );
		if ( ! isset( $webhooks[$args['connection_id']] ) ) return array('success'=>false, 'msg'=>'کانال واتساپ یافت نشد.');
		$config = $webhooks[$args['connection_id']];

		$to = $args['target_value'];
		if ( 'customer' === $args['target_mode'] && $args['entity'] instanceof WC_Order ) {
			$to = $args['entity']->get_billing_phone();
		}

		if ( empty( $to ) ) return array('success'=>false, 'msg'=>'شماره خالی است.');

		$response = wp_remote_post( "[https://graph.facebook.com/v19.0/](https://graph.facebook.com/v19.0/)" . $config['phone_number_id'] . "/messages", array(
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
        if ( is_wp_error( $response ) ) return array('success'=>false, 'msg'=>$response->get_error_message());
        return array('success'=>true, 'msg'=>'پیام واتساپ ارسال شد.');
	}

	private static function send_chat_webhook( $args, $platform ) {
		$webhooks = get_option( 'hub_webhooks', array() );
		if ( ! isset( $webhooks[$args['connection_id']] ) ) return array('success'=>false, 'msg'=>'وب‌هوک یافت نشد.');
		$url = $webhooks[$args['connection_id']]['url'];

		$key = ( 'slack' === $platform ) ? 'text' : 'content';

		$response = wp_remote_post( $url, array(
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => json_encode( array( $key => $args['message'] ) )
		));
        if ( is_wp_error( $response ) ) return array('success'=>false, 'msg'=>$response->get_error_message());
        return array('success'=>true, 'msg'=>'پیام چت ارسال شد.');
	}

	private static function send_onesignal_push( $args ) {
		$webhooks = get_option( 'hub_webhooks', array() );
		if ( ! isset( $webhooks[$args['connection_id']] ) ) return array('success'=>false, 'msg'=>'اکانت یافت نشد.');
		$config = $webhooks[$args['connection_id']];

		$response = wp_remote_post( '[https://onesignal.com/api/v1/notifications](https://onesignal.com/api/v1/notifications)', array(
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
        if ( is_wp_error( $response ) ) return array('success'=>false, 'msg'=>$response->get_error_message());
        return array('success'=>true, 'msg'=>'پوش‌نوتیفیکیشن ارسال شد.');
	}

	private static function internal_add_order_note( $args ) {
		if ( 'order' === $args['entity_type'] && $args['entity'] instanceof WC_Order ) {
			$args['entity']->add_order_note( $args['message'] );
            return array('success'=>true, 'msg'=>'یادداشت روی سفارش ثبت شد.');
		}
        return array('success'=>false, 'msg'=>'موجودیت سفارش نیست.');
	}

	private static function internal_change_order_status( $args ) {
		if ( 'order' === $args['entity_type'] && $args['entity'] instanceof WC_Order ) {
			$target_status = isset( $args['meta']['target_status'] ) ? $args['meta']['target_status'] : '';
			if ( ! empty( $target_status ) ) {
				$args['entity']->update_status( $target_status, 'تغییر وضعیت خودکار توسط اتوماسیون هاب.' );
                return array('success'=>true, 'msg'=>'وضعیت سفارش آپدیت شد.');
			}
            return array('success'=>false, 'msg'=>'وضعیت مقصد تنظیم نشده است.');
		}
        return array('success'=>false, 'msg'=>'موجودیت سفارش نیست.');
	}

	private static function internal_generate_coupon( $args ) {
		if ( ! class_exists( 'WC_Coupon' ) ) return array('success'=>false, 'msg'=>'ووکامرس فعال نیست.');
		
		$amount = isset( $args['meta']['coupon_amount'] ) ? floatval( $args['meta']['coupon_amount'] ) : 10;
		$expiry = isset( $args['meta']['coupon_expiry_days'] ) ? intval( $args['meta']['coupon_expiry_days'] ) : 7;
		
		$coupon_code = 'HUB-' . strtoupper( wp_generate_password( 6, false ) );
		$coupon = new WC_Coupon();
		$coupon->set_code( $coupon_code );
		$coupon->set_discount_type( 'percent' );
		$coupon->set_amount( $amount );
		$coupon->set_date_expires( time() + ( $expiry * DAY_IN_SECONDS ) ); 
		$coupon->set_individual_use( true );
		$coupon->save();

		if ( 'order' === $args['entity_type'] && $args['entity'] instanceof WC_Order ) {
			$args['entity']->add_order_note( sprintf( 'کد تخفیف اختصاصی %s ساخته و فعال شد.', $coupon_code ) );
            return array('success'=>true, 'msg'=>'کوپن تخفیف با موفقیت ساخته شد.');
		}
        return array('success'=>false, 'msg'=>'موجودیت سفارش نیست.');
	}
}