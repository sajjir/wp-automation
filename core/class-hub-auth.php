<?php

class Hub_Auth {

	public static function init() {
		add_shortcode( 'hub_login_form', array( __CLASS__, 'render_login_form' ) );

		add_action( 'wp_ajax_hub_send_otp', array( __CLASS__, 'handle_send_otp' ) );
		add_action( 'wp_ajax_nopriv_hub_send_otp', array( __CLASS__, 'handle_send_otp' ) );

		add_action( 'wp_ajax_hub_verify_otp', array( __CLASS__, 'handle_verify_otp' ) );
		add_action( 'wp_ajax_nopriv_hub_verify_otp', array( __CLASS__, 'handle_verify_otp' ) );

        // هوک جدید برای تکمیل ثبت‌نام
        add_action( 'wp_ajax_hub_complete_register', array( __CLASS__, 'handle_complete_register' ) );
		add_action( 'wp_ajax_nopriv_hub_complete_register', array( __CLASS__, 'handle_complete_register' ) );
        
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

        $settings = get_option('hub_auth_settings');
        if ( !empty($settings['active']) ) {
            if ( !empty($settings['unified_login']) ) {
                add_action( 'woocommerce_before_customer_login_form', array( __CLASS__, 'render_my_account_login' ) );
            }
            add_action( 'woocommerce_before_checkout_form', array( __CLASS__, 'manage_checkout_auth' ), 5 );
        }
	}

    public static function enqueue_assets() {
        wp_register_script( 'hub-auth-js', HUB_PLUGIN_URL . 'js/hub-auth.js', array('jquery'), HUB_VERSION, true );
        wp_register_style( 'hub-auth-css', HUB_PLUGIN_URL . 'css/hub-auth.css', array(), HUB_VERSION );
        
        wp_localize_script( 'hub-auth-js', 'hubAuth', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'hub_auth_nonce' ),
            'timer_limit' => 120
        ));
    }

    public static function render_my_account_login() {
        if ( is_user_logged_in() ) return;
        echo '<div class="hub-myaccount-overlay">';
        echo do_shortcode('[hub_login_form]'); 
        echo '</div>';
        echo '<style>#customer_login, .u-columns.col2-set, .woocommerce-form-login, .woocommerce-form-register, .col-1, .col-2, .account-login-inner { display: none !important; }</style>';
    }

    public static function manage_checkout_auth() {
        if ( is_user_logged_in() ) return;
        echo '<div class="hub-checkout-overlay">';
        echo '<h3>ورود / ثبت‌نام جهت تکمیل خرید</h3>';
        echo do_shortcode('[hub_login_form redirect="current"]'); 
        echo '</div>';
        echo '<style>form.checkout.woocommerce-checkout, .woocommerce-info, .woocommerce-form-login-toggle { display: none !important; }</style>';
    }

	public static function render_login_form( $atts ) {
        if ( is_user_logged_in() ) {
            $u = wp_get_current_user();
            return "<div class='hub-logged-in-msg'>✅ {$u->display_name} عزیز، وارد شده‌اید.</div>";
        }

        $atts = shortcode_atts( array( 'redirect' => '' ), $atts );
        $redirect_url = $atts['redirect'];
        if ( $redirect_url === 'current' ) {
            global $wp;
            $redirect_url = home_url( add_query_arg( array(), $wp->request ) );
        }

        wp_enqueue_script( 'hub-auth-js' );
        wp_enqueue_style( 'hub-auth-css' );

		ob_start();
		?>
		<div class="hub-auth-wrapper">
            <input type="hidden" id="hub-redirect-to" value="<?php echo esc_url($redirect_url); ?>">
            
            <div id="hub-step-phone" class="hub-step active">
				<p class="hub-title">ورود | ثبت‌نام</p>
				<div class="hub-input-row">
                    <input type="tel" id="hub-phone" placeholder="شماره موبایل (09xxxxxxxxx)" dir="ltr" maxlength="11" pattern="[0-9]*" inputmode="numeric">
                </div>
                <button type="button" id="hub-btn-send" class="hub-btn">ارسال کد تایید</button>
			</div>

            <div id="hub-step-verify" class="hub-step" style="display:none;">
                <p class="hub-subtitle">کد تایید به <span id="hub-phone-display"></span> ارسال شد</p>
                <div class="hub-otp-row">
                    <input type="text" id="hub-otp" placeholder="- - - -" maxlength="4" autocomplete="one-time-code" inputmode="numeric">
                </div>
                <div class="hub-timer-row">
                    <span id="hub-timer">02:00</span>
                    <a href="#" id="hub-btn-resend" class="hub-link-btn disabled">ارسال مجدد کد</a>
                </div>
                <div class="hub-footer-row">
				    <button type="button" id="hub-btn-verify" class="hub-btn">بررسی کد</button>
                    <a href="#" id="hub-btn-edit" class="hub-link-back">اصلاح شماره</a>
                </div>
			</div>

            <div id="hub-step-register" class="hub-step" style="display:none;">
                <p class="hub-title">تکمیل ثبت‌نام</p>
                <p class="hub-subtitle">شماره شما تایید شد. لطفاً مشخصات خود را وارد کنید:</p>
                
                <div class="hub-input-group">
                    <input type="text" id="hub-fname" placeholder="نام" class="hub-input-half">
                    <input type="text" id="hub-lname" placeholder="نام خانوادگی" class="hub-input-half">
                </div>
                <div class="hub-input-row">
                    <input type="email" id="hub-email" placeholder="ایمیل (example@gmail.com)" dir="ltr">
                </div>

<button type="button" id="hub-btn-register" class="hub-btn">ثبت اطلاعات و ورود</button>
                <div class="hub-footer-row">
                    <a href="#" id="hub-btn-cancel-register" class="hub-link-back">انصراف و تغییر شماره</a>
                </div>
            </div>

            <div id="hub-message" class="hub-message"></div>
		</div>
		<?php
		return ob_get_clean();
	}

    // --- هندلر ۱: ارسال کد ---
    public static function handle_send_otp() {
		if ( ! check_ajax_referer( 'hub_auth_nonce', 'nonce', false ) ) wp_send_json_error( 'خطای امنیتی.' );
        
        $phone_raw = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        $phone = self::normalize_number( $phone_raw );

        if ( ! preg_match( '/^09[0-9]{9}$/', $phone ) ) wp_send_json_error( 'شماره موبایل معتبر نیست.' );

        $rate_limit = get_option('hub_auth_settings')['rate_limit'] ?? 120;
        $last_sent = get_transient( 'hub_otp_time_' . $phone );
        if ( $last_sent ) {
            $remain = $rate_limit - (time() - $last_sent);
            if($remain > 0) wp_send_json_error( "لطفاً $remain ثانیه صبر کنید." );
        }

        $otp = rand( 1000, 9999 );
        set_transient( 'hub_otp_code_' . $phone, $otp, 5 * 60 );
        set_transient( 'hub_otp_time_' . $phone, time(), $rate_limit );

        do_action( 'hub_auth_request', $phone, $otp );
		wp_send_json_success( 'کد تایید ارسال شد.' );
	}

    // --- هندلر ۲: بررسی کد ---
	public static function handle_verify_otp() {
		if ( ! check_ajax_referer( 'hub_auth_nonce', 'nonce', false ) ) wp_send_json_error( 'خطای امنیتی.' );
		
        $phone_raw = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        $phone = self::normalize_number( $phone_raw );
        $otp_user = sanitize_text_field( $_POST['otp'] );
        $client_redirect = isset($_POST['redirect_to']) ? esc_url_raw($_POST['redirect_to']) : '';

        $cached_otp = get_transient( 'hub_otp_code_' . $phone );
        if ( empty($cached_otp) || $cached_otp != $otp_user ) wp_send_json_error( 'کد تایید اشتباه است.' );

        // جستجوی کاربر
        $user = self::get_user_by_phone( $phone );

        if ( $user ) {
            // کاربر وجود دارد -> لاگین کن
            self::login_user( $user, $client_redirect );
        } else {
            // کاربر وجود ندارد -> ثبت نام لازم است
            // شماره را به عنوان "وریفای شده" علامت می‌زنیم تا در مرحله بعد استفاده شود
            set_transient( 'hub_verified_phone_' . $phone, true, 10 * 60 ); // ۱۰ دقیقه اعتبار
            delete_transient( 'hub_otp_code_' . $phone ); // کد مصرف شد

            wp_send_json_success( array( 
                'action' => 'register_required',
                'msg' => 'شماره تایید شد. لطفاً اطلاعات خود را تکمیل کنید.' 
            ) );
        }
	}

    // --- هندلر ۳: تکمیل ثبت‌نام (جدید) ---
    public static function handle_complete_register() {
        if ( ! check_ajax_referer( 'hub_auth_nonce', 'nonce', false ) ) wp_send_json_error( 'خطای امنیتی.' );

        $phone_raw = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        $phone = self::normalize_number( $phone_raw );
        
        $fname = sanitize_text_field($_POST['fname']);
        $lname = sanitize_text_field($_POST['lname']);
        $email = sanitize_email($_POST['email']);
        $client_redirect = isset($_POST['redirect_to']) ? esc_url_raw($_POST['redirect_to']) : '';

        // اعتبار سنجی
        if ( empty($fname) || empty($lname) ) wp_send_json_error( 'نام و نام خانوادگی الزامی است.' );
        if ( !is_email($email) ) wp_send_json_error( 'ایمیل وارد شده معتبر نیست.' );
        if ( email_exists($email) ) wp_send_json_error( 'این ایمیل قبلاً ثبت شده است.' );

        // بررسی اینکه آیا این شماره واقعاً وریفای شده است؟
        if ( ! get_transient( 'hub_verified_phone_' . $phone ) ) {
            wp_send_json_error( 'نشست شما منقضی شده است. لطفاً مجدد تلاش کنید.' );
        }

        // ساخت کاربر
        $user_id = wp_create_user( $phone, wp_generate_password(), $email );
        
        if ( is_wp_error( $user_id ) ) wp_send_json_error( $user_id->get_error_message() );

        // ذخیره مشخصات
        $user = new WP_User( $user_id );
        $user->set_role( 'customer' );
        
        update_user_meta( $user_id, 'billing_phone', $phone );
        update_user_meta( $user_id, 'first_name', $fname );
        update_user_meta( $user_id, 'last_name', $lname );
        update_user_meta( $user_id, 'billing_first_name', $fname );
        update_user_meta( $user_id, 'billing_last_name', $lname );
        update_user_meta( $user_id, 'billing_email', $email );

        do_action( 'user_register', $user_id );
        
        // پاک کردن ترنزینت و لاگین
        delete_transient( 'hub_verified_phone_' . $phone );
        self::login_user( $user, $client_redirect );
    }

    // --- توابع کمکی ---

    private static function login_user( $user, $client_redirect ) {
        wp_clear_auth_cookie();
        wp_set_current_user( $user->ID );
        wp_set_auth_cookie( $user->ID, true );
        
        // سینک سفارشات مهمان
        $phone = get_user_meta($user->ID, 'billing_phone', true);
        if($phone) self::sync_guest_orders($user->ID, $phone);

        $final_redirect = home_url();
        $settings = get_option('hub_auth_settings');
        
        if ( !empty($client_redirect) ) $final_redirect = $client_redirect;
        elseif ( !empty($settings['redirect_url']) ) $final_redirect = $settings['redirect_url'];

        wp_send_json_success( array( 
            'action' => 'login_success',
            'redirect' => $final_redirect, 
            'msg' => 'خوش آمدید!' 
        ) );
    }

    private static function get_user_by_phone( $phone ) {
        // جستجو بر اساس متای billing_phone
        $users = get_users( array('meta_key' => 'billing_phone', 'meta_value' => $phone, 'number' => 1) );
        if ( ! empty( $users ) ) return $users[0];
        
        // جستجو بر اساس نام کاربری (اگر شماره موبایل نام کاربری باشد)
        if ( username_exists( $phone ) ) {
            return get_user_by( 'login', $phone );
        }
        return false;
    }

    private static function normalize_number($number) {
        if(empty($number)) return '';
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabic  = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $number = str_replace($persian, $english, $number);
        $number = str_replace($arabic, $english, $number);
        $number = preg_replace('/[^0-9]/', '', $number);
        if (substr($number, 0, 3) === '989') $number = '0' . substr($number, 2);
        if (substr($number, 0, 1) === '9') $number = '0' . $number;
        return $number;
    }

    private static function sync_guest_orders( $user_id, $phone ) {
        if(function_exists('wc_get_orders')) {
            $orders = wc_get_orders( array('limit' => -1, 'meta_key' => '_billing_phone', 'meta_value' => $phone, 'customer_id' => 0, 'return' => 'ids') );
            if ( ! empty( $orders ) ) {
                foreach ( $orders as $order_id ) {
                    $order = wc_get_order( $order_id );
                    $order->set_customer_id( $user_id );
                    $order->save();
                }
            }
        }
    }
}