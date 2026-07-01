<?php

class Hub_Bridge {

	public static function init() {
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'handle_order_status' ), 10, 4 );
		add_action( 'woocommerce_new_order', array( __CLASS__, 'handle_new_order' ), 10, 1 );
		add_action( 'user_register', array( __CLASS__, 'handle_user_register' ), 10, 1 );
        add_action( 'hub_auth_request', array( __CLASS__, 'handle_auth_request' ), 10, 2 );
        add_action( 'wpcf7_mail_sent', array( __CLASS__, 'handle_cf7_submission' ), 10, 1 );
	}

    // --- هندلرهای تریگر ---

    public static function handle_cf7_submission( $contact_form ) {
        $submission = WPCF7_Submission::get_instance();
        if ( ! $submission ) return;

        $posted_data = $submission->get_posted_data();
        $uploaded_files = $submission->uploaded_files(); 
        
        $data = (object) [
            'form_id' => $contact_form->id(),
            'form_title' => $contact_form->title(),
            'fields' => $posted_data,
            'files' => $uploaded_files
        ];

        self::process_rules( 'cf7_submit', $data, (string)$contact_form->id() );
    }

public static function handle_order_status( $order_id, $from, $to, $order ) { 
        // حذف پیشوند wc- برای اطمینان از یکپارچگی با مقادیر ذخیره شده در دیتابیس
        $status_slug = str_replace('wc-', '', $to); 
        self::process_rules( 'order_status', $order, $status_slug ); 
    }
    
	public static function handle_new_order( $order_id ) { 
        $order = wc_get_order( $order_id ); 
        if($order) self::process_rules( 'order_created', $order ); 
    }
    
	public static function handle_user_register( $user_id ) { 
        $user = get_userdata( $user_id ); 
        if($user) self::process_rules( 'user_register', $user ); 
    }
    
    public static function handle_auth_request( $phone, $otp ) {
        $data = (object) [ 'phone' => $phone, 'otp' => $otp ];
        self::process_rules( 'auth_request', $data );
    }

    // --- موتور پردازش ---

	private static function process_rules( $trigger_type, $entity, $sub_trigger = null ) {
		$rules = get_option( 'hub_rules', [] );
		$webhooks = get_option( 'hub_webhooks', [] );
		$wh_map = []; foreach($webhooks as $wh) $wh_map[$wh['id']] = $wh;

        Hub_Logger::log("Trigger: $trigger_type" . ($sub_trigger ? " ($sub_trigger)" : ""), 'info', 'bridge_debug');

		foreach ( $rules as $rule ) {
			if ( $rule['trigger'] !== $trigger_type ) continue;
			
            if ( $trigger_type === 'order_status' ) {
                // پاک‌سازی پیشوند wc- از دیتای ذخیره شده قدیمی برای مقایسه کاملاً دقیق
                $saved_sub = str_replace('wc-', '', $rule['sub_trigger'] ?? '');
                $current_sub = str_replace('wc-', '', $sub_trigger ?? '');
                
                if ( !empty($saved_sub) && $saved_sub !== $current_sub ) continue;
            }
            if ( $trigger_type === 'cf7_submit' ) {
                if ( !empty($rule['cf7_form_id']) && (string)$rule['cf7_form_id'] !== (string)$sub_trigger ) continue;
            }

            Hub_Logger::log("Matched Rule: " . ($rule['name'] ?? 'Unnamed'), 'success', 'bridge');

            $msg_n8n_raw = $rule['message_n8n'] ?? '';
            $msg_sms_raw = $rule['message_sms'] ?? '';
            $msg_tg_raw  = $rule['message_tg'] ?? '';

            $msg_n8n_processed = self::parse_shortcodes( $msg_n8n_raw, $entity );
            $msg_sms_processed = !empty($rule['active_sms']) ? self::parse_shortcodes( $msg_sms_raw, $entity ) : '';
            $msg_tg_processed  = !empty($rule['active_tg']) ? self::parse_shortcodes( $msg_tg_raw, $entity ) : '';

            // --- 1. Telegram ---
            $tg_log_data = [];
            if ( !empty($rule['active_tg']) && !empty($rule['tg_bot_id']) && isset($wh_map[$rule['tg_bot_id']]) ) {
                $bot_token = $wh_map[$rule['tg_bot_id']]['url'];
                $chat_id = $rule['tg_chat_id'] ?? '';
                
                if ( !empty($chat_id) ) {
                    Hub_Sender::send_immediate( 'telegram.send', [ 'token' => $bot_token, 'chat_id' => $chat_id, 'message' => $msg_tg_processed ] );
                    $tg_log_data['status'] = 'sent';
                }
            }

            // --- 2. SMS ---
            $sms_log_data = [];
            if ( !empty($rule['active_sms']) ) {
                $target_num = '';
                
                if ( $trigger_type === 'auth_request' ) {
                    $target_num = $entity->phone;
                } elseif ( $trigger_type === 'cf7_submit' ) {
                    if ( !empty($rule['cf7_mobile_field']) && isset($entity->fields[$rule['cf7_mobile_field']]) ) {
                        $target_num = $entity->fields[$rule['cf7_mobile_field']];
                    }
                } else {
                    if ( ($rule['sms_target'] ?? 'customer') === 'customer' ) {
                        if ( is_a($entity, 'WC_Order') ) $target_num = $entity->get_billing_phone();
                        elseif ( is_a($entity, 'WP_User') ) $target_num = get_user_meta($entity->ID, 'billing_phone', true);
                    } else {
                        $target_num = $rule['sms_custom_num'] ?? '';
                    }
                }

                $target_num = self::normalize_number($target_num);

                if ( !empty($target_num) && !empty($rule['sms_provider_id']) && isset($wh_map[$rule['sms_provider_id']]) ) {
                     $provider = $wh_map[$rule['sms_provider_id']];
                     Hub_Sender::send_immediate( 'sms.send', [ 
                        'mobile' => $target_num, 'message' => $msg_sms_processed,
                        'user' => $provider['sms_user'], 'pass' => $provider['sms_pass'], 'from' => $provider['sms_from']
                    ]);
                    $sms_log_data['target'] = $target_num;
                }
            }

			// --- 3. n8n ---
			if ( !empty($rule['active_n8n']) ) {
                if ( !empty($rule['webhook_id']) && isset($wh_map[$rule['webhook_id']]) ) {
                    $payload = self::build_payload_n8n( $entity, $msg_n8n_processed );
                    $payload['meta'] = [
                        'rule_name' => $rule['name'],
                        'trigger' => $trigger_type,
                        'sms_sent' => !empty($sms_log_data),
                    ];
                    $payload['_webhook_url'] = $wh_map[$rule['webhook_id']]['url'];
                    
                    Hub_Sender::send_immediate( 'n8n.send', $payload );
                }
			}
		}
	}

    // --- ساخت Payload (اصلاح شده برای تبدیل مسیر فایل به لینک) ---

	public static function build_payload_n8n( $entity, $processed_msg ) {
        $data = [];
        
        if ( isset($entity->form_id) && isset($entity->fields) ) {
            $data = [
                'event' => 'cf7_submit',
                'form_id' => $entity->form_id,
                'form_title' => $entity->form_title,
                'fields' => $entity->fields,
                'files' => [] 
            ];
            
            // *** بخش مهم: تبدیل مسیر فایل به URL ***
            if(!empty($entity->files)) {
                $content_dir = wp_normalize_path( WP_CONTENT_DIR ); // مسیر فیزیکی مثلا /home/.../wp-content
                $content_url = content_url(); // آدرس اینترنتی مثلا https://.../wp-content

                foreach($entity->files as $key => $paths) {
                    // CF7 گاهی آرایه برمی‌گرداند حتی برای یک فایل
                    $path_array = is_array($paths) ? $paths : [$paths];
                    
                    foreach($path_array as $path) {
                        $normalized_path = wp_normalize_path($path);
                        // جایگزینی مسیر فیزیکی با URL
                        $file_url = str_replace($content_dir, $content_url, $normalized_path);
                        $data['files'][$key][] = $file_url;
                    }
                }
            }
        } 
        elseif ( isset($entity->otp) ) {
            $data = [ 'event' => 'auth_request', 'phone' => $entity->phone, 'otp' => $entity->otp ];
        } 
        elseif ( is_a( $entity, 'WC_Order' ) ) {
            $data = [ 
                'id' => $entity->get_id(), 
                'total' => $entity->get_total(), 
                'status' => $entity->get_status(), 
                'billing' => $entity->get_address('billing'),
                'meta_data' => $entity->get_meta_data()
            ];
            foreach($entity->get_items() as $item) {
                $data['items'][] = [ 'name' => $item->get_name(), 'qty' => $item->get_quantity(), 'total' => $item->get_total() ];
            }
        }

        return [ 'json_data' => $data, 'message' => $processed_msg ];
	}

    // --- پارسر شورت‌کد ---

    public static function parse_shortcodes($text, $entity) {
        if (empty($text)) return '';
        
        // 1. CF7
        if ( isset($entity->form_id) && isset($entity->fields) ) {
            $text = str_replace('{form_title}', $entity->form_title, $text);
            $text = str_replace('{form_id}', $entity->form_id, $text);

            return preg_replace_callback('/\{field:([^}]+)\}/', function($matches) use ($entity) {
                $field_name = $matches[1];
                if ( isset($entity->fields[$field_name]) ) {
                    $val = $entity->fields[$field_name];
                    return is_array($val) ? implode(', ', $val) : $val;
                }
                return ''; 
            }, $text);
        }

        // 2. Auth
        if ( isset($entity->otp) ) {
            return str_replace(['{otp}', '{phone}'], [$entity->otp, $entity->phone], $text);
        }

        // 3. WooCommerce
        if ( is_a( $entity, 'WC_Order' ) ) {
             return self::parse_wc_shortcodes($text, $entity);
        }
        // 4. User Register
        if ( is_a( $entity, 'WP_User' ) ) {
            $full_name = trim($entity->first_name . ' ' . $entity->last_name);
            if(empty($full_name)) $full_name = 'کاربر';
            
            $vars = [
                '{first_name}' => $entity->first_name,
                '{last_name}'  => $entity->last_name,
                '{full_name}'  => $full_name,
                '{email}'      => $entity->user_email,
                '{phone}'      => get_user_meta($entity->ID, 'billing_phone', true) ?: $entity->user_login,
            ];
            return str_replace(array_keys($vars), array_values($vars), $text);
        }
        return $text;
    }

    private static function parse_wc_shortcodes($text, $order) {
        $clean_price = function($html_price) { return trim(strip_tags(html_entity_decode($html_price))); };
        $date_created = $order->get_date_created();

        $vars = [
            '{order_id}' => $order->get_id(),
            '{status}' => wc_get_order_status_name($order->get_status()),
            '{date}' => $date_created ? $date_created->date_i18n('Y/m/d') : '',
            '{time}' => $date_created ? $date_created->date_i18n('H:i') : '',
            '{full_name}' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            '{phone}' => $order->get_billing_phone(),
            '{address}' => $order->get_billing_address_1() . ' ' . $order->get_billing_city(),
            '{customer_note}' => $order->get_customer_note(),
            '{shipping_method}' => $order->get_shipping_method(),
            '{shipping_cost}' => $clean_price(wc_price($order->get_shipping_total())),
            '{total}' => $clean_price(wc_price($order->get_total())),
        ];

        if (strpos($text, '{items_summary}') !== false) {
            $lines = [];
            foreach ($order->get_items() as $item) {
                $lines[] = "▪️ " . $item->get_name() . " (×" . $item->get_quantity() . ")";
            }
            $vars['{items_summary}'] = implode("\n", $lines);
        }

        if (strpos($text, '{items_detailed}') !== false) {
            $lines = [];
            foreach ($order->get_items() as $item) {
                $price_clean = $clean_price(wc_price($item->get_total()));
                $lines[] = "🛒 " . $item->get_name() . "\n   تعداد: " . $item->get_quantity() . " | قیمت: " . $price_clean;
            }
            $vars['{items_detailed}'] = implode("\n----------------\n", $lines);
        }

        if (strpos($text, '{_scrape_raw_result_}') !== false) {
            $scrape_list = "";
            foreach ($order->get_items() as $item) {
                $product = $item->get_product(); 
                if ($product) {
                    $price_display = "قیمت مبدا ندارد ❌";
                    $found_row = null;
                    $parent_id = $product->is_type('variation') ? $product->get_parent_id() : $product->get_id();
                    $raw_json = get_post_meta($parent_id, '_last_scrape_raw_result', true);
                    $scraped_rows = [];

                    if ($raw_json) {
                        $decoded = json_decode($raw_json, true);
                        if (!$decoded && is_string($raw_json)) $decoded = json_decode(stripslashes($raw_json), true);
                        if ($decoded) {
                            if (!isset($decoded[0])) $scraped_rows = [$decoded]; else $scraped_rows = $decoded;
                        }
                    }

                    if (!empty($scraped_rows) && is_array($scraped_rows)) {
                        if (!$product->is_type('variation')) {
                            $found_row = reset($scraped_rows);
                        } else {
                            foreach ($scraped_rows as $row) {
                                $is_match = true;
                                foreach ($row as $key => $val) {
                                    if (strpos($key, 'pa_') === 0) {
                                        $wc_attr_val = $product->get_attribute($key);
                                        if (trim((string)$wc_attr_val) !== trim((string)$val)) { $is_match = false; break; }
                                    }
                                }
                                if ($is_match) { $found_row = $row; break; }
                            }
                        }
                    }

                    if ($found_row) {
                        $p = null;
                        if (isset($found_row['price'])) $p = $found_row['price'];
                        elseif (isset($found_row['amount'])) $p = $found_row['amount'];
                        elseif (isset($found_row['lowest_price'])) $p = $found_row['lowest_price'];
                        if ($p) { $price_display = number_format((float)$p) . " تومان ✅"; }
                    }
                    
                    if ($price_display !== "قیمت مبدا ندارد ❌") {
                        $scrape_list .= "📦 " . $product->get_name() . "\n   💰 قیمت خرید: " . $price_display . "\n";
                    }
                }
            }
            if (empty($scrape_list)) $scrape_list = "هیچ داده قیمت خریدی یافت نشد.";
            $vars['{_scrape_raw_result_}'] = $scrape_list;
        }

        return str_replace(array_keys($vars), array_values($vars), $text);
    }

    public static function normalize_number($number) {
        if(empty($number)) return '';
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $number = str_replace($persian, $english, $number);
        $number = preg_replace('/[^0-9]/', '', $number);
        if (substr($number, 0, 3) === '989') $number = '0' . substr($number, 2);
        if (substr($number, 0, 1) === '9') $number = '0' . $number;
        return $number;
    }
}