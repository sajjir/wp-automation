<?php
/**
 * Admin Panel Management Console Configuration
 *
 * @package    Automation_Hub
 * @subpackage Automation_Hub/admin
 */

class Hub_Admin {

	public function init() {
		add_action( 'admin_menu', array( $this, 'add_plugin_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'save_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	public function enqueue_admin_assets( $hook ) {
		if ( 'toplevel_page_automation-hub' !== $hook ) {
			return;
		}
		
		// لود فایل‌های استایل و جاوااسکریپت (دقت کنید پوشه css مستقیما داخل admin باشد)
		wp_enqueue_style( 'hub-admin-css', HUB_PLUGIN_URL . 'admin/css/hub-admin.css', array(), HUB_VERSION );
		wp_enqueue_script( 'hub-admin-js', HUB_PLUGIN_URL . 'admin/js/hub-admin.js', array( 'jquery' ), HUB_VERSION, true );
	}

	public function add_plugin_admin_menu() {
		add_menu_page(
			'اتوماسیون هاب',
			'اتوماسیون هاب',
			'manage_options',
			'automation-hub',
			array( $this, 'display_plugin_admin_page' ),
			'dashicons-network-asset',
			58
		);
	}

	public function display_plugin_admin_page() {
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'campaigns';
		?>
		<div class="wrap hub-wrap rtl">
			<div class="hub-header-main">
				<h1>⚙️ اتوماسیون هاب <span class="hub-badge">پرو ۲.۰</span></h1>
				<p class="hub-subtitle">سیستم هوشمند اتوماسیون و اطلاع‌رسانی چندکاناله وردپرس</p>
			</div>

			<h2 class="nav-tab-wrapper hub-nav-tabs">
				<a href="?page=automation-hub&tab=campaigns" class="nav-tab <?php echo $active_tab == 'campaigns' ? 'nav-tab-active' : ''; ?>">📋 سناریوهای اتوماسیون</a>
				<a href="?page=automation-hub&tab=connections" class="nav-tab <?php echo $active_tab == 'connections' ? 'nav-tab-active' : ''; ?>">🔌 مدیریت کانال‌ها</a>
			</h2>

			<form method="POST" action="" class="hub-form-container">
				<?php
				wp_nonce_field( 'hub_save_nonce', 'hub_nonce' );
				if ( 'campaigns' === $active_tab ) {
					$this->render_campaigns_tab();
				} else {
					$this->render_connections_tab();
				}
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * ==========================================
	 * تب اول: سناریوهای اتوماسیون (Rules)
	 * ==========================================
	 */
	private function render_campaigns_tab() {
		$rules = get_option( 'hub_rules', array() );
		$webhooks = get_option( 'hub_webhooks', array() );
		?>
		<div class="hub-tab-content">
			<div class="hub-actions-top">
				<button type="button" id="btn-add-new-rule" class="hub-btn hub-btn-primary">➕ افزودن سناریوی جدید</button>
				<button type="submit" name="hub_save_rules" class="hub-btn hub-btn-success">💾 ذخیره سناریوها</button>
			</div>

			<div id="rules-repeater-container">
				<?php 
				if ( ! empty( $rules ) && is_array( $rules ) ) {
					foreach ( $rules as $index => $rule ) {
						$this->render_rule_row( $rule, $index, $webhooks );
					}
				} else {
					echo '<div class="hub-empty-state"><p>هیچ سناریویی یافت نشد. برای شروع، روی دکمه "افزودن سناریوی جدید" کلیک کنید.</p></div>';
				}
				?>
			</div>
		</div>

		<script type="text/template" id="rule-template">
			<?php $this->render_rule_row( array(), '{{RULE_INDEX}}', $webhooks, true ); ?>
		</script>
		<?php
	}

	private function render_rule_row( $rule = array(), $index = 0, $webhooks = array(), $is_template = false ) {
		$name = isset( $rule['name'] ) ? $rule['name'] : '';
		$trigger = isset( $rule['trigger'] ) ? $rule['trigger'] : 'order_status';
		$sub_trigger = isset( $rule['sub_trigger'] ) ? $rule['sub_trigger'] : '';
		$condition_logic = isset( $rule['condition_logic'] ) ? $rule['condition_logic'] : 'AND';
		$conditions = isset( $rule['conditions'] ) ? $rule['conditions'] : array();
		$actions = isset( $rule['actions'] ) ? $rule['actions'] : array();
		
		$row_class = $is_template ? 'hub-card rule-row template-hidden' : 'hub-card rule-row';
		$attr_index = $is_template ? 'data-index="{{RULE_INDEX}}"' : 'data-index="' . $index . '"';
		$input_prefix = "rules[$index]";
		?>
		<div class="<?php echo $row_class; ?>" <?php echo $attr_index; ?>>
			<div class="hub-card-header">
				<div class="hub-card-title">
					<span class="dashicons dashicons-controls-repeat"></span>
					<input type="text" name="<?php echo $input_prefix; ?>[name]" value="<?php echo esc_attr($name); ?>" class="hub-input-title txt-rule-name-input" placeholder="عنوان سناریو (مثلا: پیگیری سبد خرید)" required />
				</div>
				<button type="button" class="hub-btn-icon remove-row" title="حذف سناریو"><span class="dashicons dashicons-trash"></span></button>
			</div>
			
			<div class="hub-card-body">
				<!-- بخش رویداد (Trigger) -->
				<div class="hub-section">
					<h4 class="hub-section-title">۱. رویداد شروع (Trigger)</h4>
					<div class="hub-grid-2">
						<div class="hub-form-group">
							<label>نوع رویداد</label>
							<select name="<?php echo $input_prefix; ?>[trigger]" class="hub-select trigger-selector">
								<option value="order_status" <?php selected($trigger, 'order_status'); ?>>تغییر وضعیت سفارش (ووکامرس)</option>
								<option value="abandoned_cart" <?php selected($trigger, 'abandoned_cart'); ?>>سبد خرید رها شده (ووکامرس)</option>
								<option value="low_stock" <?php selected($trigger, 'low_stock'); ?>>موجودی انبار رو به اتمام (ووکامرس)</option>
								<option value="order_refunded" <?php selected($trigger, 'order_refunded'); ?>>ثبت مرجوعی سفارش (ووکامرس)</option>
								<option value="product_review" <?php selected($trigger, 'product_review'); ?>>ثبت دیدگاه محصول (ووکامرس)</option>
								<option value="winback_user" <?php selected($trigger, 'winback_user'); ?>>کاربر غیرفعال / Win-back (کاربران)</option>
							</select>
						</div>
						<div class="hub-form-group">
							<label>جزئیات رویداد</label>
							<div class="cond-box cond-order_status <?php echo $trigger == 'order_status' ? '' : 'hidden-box'; ?>">
								<select name="<?php echo $input_prefix; ?>[sub_trigger_order]" class="hub-select">
									<option value="pending" <?php selected($sub_trigger, 'pending'); ?>>در انتظار پرداخت</option>
									<option value="processing" <?php selected($sub_trigger, 'processing'); ?>>در حال پردازش</option>
									<option value="completed" <?php selected($sub_trigger, 'completed'); ?>>تکمیل شده</option>
									<option value="cancelled" <?php selected($sub_trigger, 'cancelled'); ?>>لغو شده</option>
									<option value="refunded" <?php selected($sub_trigger, 'refunded'); ?>>مرجوع شده</option>
								</select>
							</div>
							<div class="cond-box cond-winback_user <?php echo $trigger == 'winback_user' ? '' : 'hidden-box'; ?>">
								<div class="hub-input-group">
									<span>پس از</span>
									<input type="number" name="<?php echo $input_prefix; ?>[sub_trigger_winback]" value="<?php echo esc_attr($sub_trigger); ?>" class="hub-input-small" />
									<span>روز عدم ورود</span>
								</div>
							</div>
						</div>
					</div>
				</div>

				<!-- بخش شروط (Conditions) -->
				<div class="hub-section hub-bg-light">
					<h4 class="hub-section-title">۲. شروط هوشمند (Conditions)</h4>
					<div class="hub-form-group">
						<label class="hub-inline-label">
							اجرای اقدامات در صورتی که: 
							<select name="<?php echo $input_prefix; ?>[condition_logic]" class="hub-select-small">
								<option value="AND" <?php selected($condition_logic, 'AND'); ?>>همه شروط زیر برقرار باشند (AND)</option>
								<option value="OR" <?php selected($condition_logic, 'OR'); ?>>حداقل یکی از شروط زیر برقرار باشد (OR)</option>
							</select>
						</label>
					</div>
					
					<div class="conditions-container-rows">
						<?php 
						if(is_array($conditions) && !empty($conditions)){
							foreach($conditions as $c_idx => $cond){
								?>
								<div class="hub-condition-row">
									<select name="<?php echo $input_prefix; ?>[conditions][<?php echo $c_idx; ?>][field]" class="hub-select">
										<option value="order_total" <?php selected($cond['field'], 'order_total'); ?>>جمع کل سفارش</option>
										<option value="billing_city" <?php selected($cond['field'], 'billing_city'); ?>>شهر صورتحساب</option>
										<option value="user_role" <?php selected($cond['field'], 'user_role'); ?>>نقش کاربری</option>
									</select>
									<select name="<?php echo $input_prefix; ?>[conditions][<?php echo $c_idx; ?>][operator]" class="hub-select">
										<option value="equals" <?php selected($cond['operator'], 'equals'); ?>>برابر با</option>
										<option value="not_equals" <?php selected($cond['operator'], 'not_equals'); ?>>مخالف با</option>
										<option value="greater_than" <?php selected($cond['operator'], 'greater_than'); ?>>بزرگتر از</option>
										<option value="contains" <?php selected($cond['operator'], 'contains'); ?>>شامل عبارت</option>
									</select>
									<input type="text" name="<?php echo $input_prefix; ?>[conditions][<?php echo $c_idx; ?>][value]" value="<?php echo esc_attr($cond['value']); ?>" class="hub-input" placeholder="مقدار ارزیابی..." />
									<button type="button" class="hub-btn-icon-danger btn-remove-condition"><span class="dashicons dashicons-no"></span></button>
								</div>
								<?php
							}
						}
						?>
					</div>
					<button type="button" class="hub-btn hub-btn-outline btn-add-condition" style="margin-top: 10px;">+ افزودن شرط</button>
				</div>

				<!-- بخش اقدامات (Actions) -->
				<div class="hub-section">
					<h4 class="hub-section-title">۳. اقدامات اجرایی (Actions)</h4>
					<div class="actions-holder-rows">
						<?php 
						if(is_array($actions) && !empty($actions)){
							foreach($actions as $a_idx => $act){
								$act_id = $act['id'];
								$act_prefix = $input_prefix . "[actions][$a_idx]";
								$conn_id_val = isset($act['connection_id']) ? $act['connection_id'] : '';
								?>
								<div class="hub-action-card">
									<div class="hub-action-header">
										<strong>اقدام #<?php echo esc_html($act_id); ?></strong>
										<button type="button" class="hub-btn-icon-danger btn-delete-action-node" title="حذف اقدام"><span class="dashicons dashicons-no"></span></button>
									</div>
									<input type="hidden" name="<?php echo $act_prefix; ?>[id]" value="<?php echo esc_attr($act_id); ?>" />
									
									<div class="hub-grid-2">
										<div class="hub-form-group">
											<label>نوع اقدام</label>
											<select name="<?php echo $act_prefix; ?>[type]" class="hub-select action-type-selector">
												<option value="sms" <?php selected($act['type'], 'sms'); ?>>ارسال پیامک (SMS)</option>
												<option value="telegram" <?php selected($act['type'], 'telegram'); ?>>ارسال به تلگرام</option>
												<option value="n8n" <?php selected($act['type'], 'n8n'); ?>>ارسال به n8n (وب‌هوک)</option>
												<option value="email" <?php selected($act['type'], 'email'); ?>>ارسال ایمیل (SMTP)</option>
												<option value="order_note" <?php selected($act['type'], 'order_note'); ?>>داخلی - ثبت یادداشت سفارش</option>
												<option value="order_status" <?php selected($act['type'], 'order_status'); ?>>داخلی - تغییر وضعیت سفارش</option>
											</select>
										</div>
										<div class="hub-form-group">
											<label>انتخاب کانال ارتباطی</label>
											<select name="<?php echo $act_prefix; ?>[connection_id]" class="hub-select">
												<option value="">-- کانال پیشفرض --</option>
												<?php foreach($webhooks as $w_key => $w_val): ?>
													<option value="<?php echo esc_attr($w_key); ?>" <?php selected($conn_id_val, $w_key); ?>><?php echo esc_html($w_val['name']); ?></option>
												<?php endforeach; ?>
											</select>
										</div>
									</div>
									
									<div class="hub-form-group">
										<label>محتوای پیام</label>
										<textarea name="<?php echo $act_prefix; ?>[message]" class="hub-textarea" rows="3"><?php echo esc_textarea($act['message']); ?></textarea>
										<p class="hub-help-text">متغیرهای مجاز: <code>{order_id}</code>, <code>{total}</code>, <code>{full_name}</code>, <code>{phone}</code></p>
									</div>

									<div class="hub-delay-box">
										<label class="hub-checkbox-label">
											<input type="checkbox" name="<?php echo $act_prefix; ?>[delay][enabled]" value="1" <?php checked(!empty($act['delay']['enabled']), true); ?> class="chk-delay-toggle" />
											اجرای با تاخیر (زمان‌بندی شده)
										</label>
										<div class="delay-values-wrapper <?php echo !empty($act['delay']['enabled']) ? '' : 'hidden-box'; ?>">
											<input type="number" name="<?php echo $act_prefix; ?>[delay][value]" value="<?php echo esc_attr(isset($act['delay']['value'])?$act['delay']['value']:0); ?>" class="hub-input-small" />
											<select name="<?php echo $act_prefix; ?>[delay][unit]" class="hub-select-small">
												<option value="minutes" <?php selected(isset($act['delay']['unit'])?$act['delay']['unit']:'', 'minutes'); ?>>دقیقه</option>
												<option value="hours" <?php selected(isset($act['delay']['unit'])?$act['delay']['unit']:'', 'hours'); ?>>ساعت</option>
												<option value="days" <?php selected(isset($act['delay']['unit'])?$act['delay']['unit']:'', 'days'); ?>>روز</option>
											</select>
											<span>پس از وقوع رویداد</span>
										</div>
									</div>
								</div>
								<?php
							}
						}
						?>
					</div>
					<button type="button" class="hub-btn hub-btn-secondary btn-add-action-node" style="margin-top:10px;">+ افزودن اقدام اجرایی</button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * ==========================================
	 * تب دوم: مدیریت کانال‌ها (Webhooks)
	 * ==========================================
	 */
	private function render_connections_tab() {
		$webhooks = get_option( 'hub_webhooks', array() );
		?>
		<div class="hub-tab-content">
			<div class="hub-actions-top">
				<button type="button" id="btn-add-new-webhook" class="hub-btn hub-btn-primary">➕ افزودن کانال جدید</button>
				<button type="submit" name="hub_save_webhooks" class="hub-btn hub-btn-success">💾 ذخیره کانال‌ها</button>
			</div>

			<div id="webhooks-repeater-container">
				<?php 
				if ( ! empty( $webhooks ) && is_array( $webhooks ) ) {
					$index = 0;
					foreach ( $webhooks as $key => $webhook ) {
						$this->render_webhook_row( $webhook, $index );
						$index++;
					}
				} else {
					echo '<div class="hub-empty-state"><p>هیچ کانال ارتباطی تنظیم نشده است. هم‌اکنون اولین کانال خود را بسازید.</p></div>';
				}
				?>
			</div>
		</div>

		<script type="text/template" id="webhook-template">
			<?php $this->render_webhook_row( array(), '{{WH_INDEX}}', true ); ?>
		</script>
		<?php
	}

	private function render_webhook_row( $webhook = array(), $index = 0, $is_template = false ) {
		$name            = isset( $webhook['name'] ) ? $webhook['name'] : '';
		$type            = isset( $webhook['type'] ) ? $webhook['type'] : 'melipayamak';
		$url             = isset( $webhook['url'] ) ? $webhook['url'] : '';
		$username        = isset( $webhook['username'] ) ? $webhook['username'] : '';
		$password        = isset( $webhook['password'] ) ? $webhook['password'] : '';
		$from_number     = isset( $webhook['from_number'] ) ? $webhook['from_number'] : '';
		$token           = isset( $webhook['token'] ) ? $webhook['token'] : '';
		$chat_id         = isset( $webhook['chat_id'] ) ? $webhook['chat_id'] : '';
		$phone_number_id = isset( $webhook['phone_number_id'] ) ? $webhook['phone_number_id'] : '';
		$access_token    = isset( $webhook['access_token'] ) ? $webhook['access_token'] : '';
		$app_id          = isset( $webhook['app_id'] ) ? $webhook['app_id'] : '';
		$rest_api_key    = isset( $webhook['rest_api_key'] ) ? $webhook['rest_api_key'] : '';

		$row_class = $is_template ? 'hub-card webhook-row template-hidden' : 'hub-card webhook-row';
		$attr_index = $is_template ? 'data-index="{{WH_INDEX}}"' : 'data-index="' . $index . '"';
		$input_prefix = "webhooks[$index]";
		?>
		<div class="<?php echo $row_class; ?>" <?php echo $attr_index; ?>>
			<div class="hub-card-header">
				<div class="hub-card-title">
					<span class="dashicons dashicons-admin-network"></span>
					<strong>تنظیمات اتصال</strong>
				</div>
				<button type="button" class="hub-btn-icon remove-webhook-row" title="حذف کانال"><span class="dashicons dashicons-trash"></span></button>
			</div>
			
			<div class="hub-card-body">
				<div class="hub-grid-2">
					<div class="hub-form-group">
						<label>نام کانال (برای تشخیص)</label>
						<input type="text" name="<?php echo $input_prefix; ?>[name]" value="<?php echo esc_attr($name); ?>" class="hub-input" placeholder="مثلا: پنل ملی پیامک شماره ۱" required />
					</div>
					<div class="hub-form-group">
						<label>ارائه‌دهنده (Provider)</label>
						<select name="<?php echo $input_prefix; ?>[type]" class="hub-select webhook-type-selector">
							<option value="melipayamak" <?php selected($type, 'melipayamak'); ?>>ملی‌پیامک (SMS)</option>
							<option value="telegram" <?php selected($type, 'telegram'); ?>>تلگرام (Telegram Bot)</option>
							<option value="n8n" <?php selected($type, 'n8n'); ?>>n8n (Webhook)</option>
							<option value="google_sheet" <?php selected($type, 'google_sheet'); ?>>گوگل شیت (Webhook)</option>
							<option value="email" <?php selected($type, 'email'); ?>>ایمیل (SMTP وردپرس)</option>
							<option value="whatsapp" <?php selected($type, 'whatsapp'); ?>>واتساپ (Cloud API)</option>
							<option value="slack" <?php selected($type, 'slack'); ?>>اسلک (Slack Webhook)</option>
							<option value="discord" <?php selected($type, 'discord'); ?>>دیسکورد (Discord Webhook)</option>
							<option value="onesignal" <?php selected($type, 'onesignal'); ?>>پوش‌نوتیفیکیشن (OneSignal)</option>
						</select>
					</div>
				</div>

				<div class="hub-dynamic-fields">
					<!-- MeliPayamak Fields -->
					<div class="wh-field wh-melipayamak" style="display:none;">
						<div class="hub-grid-3">
							<div class="hub-form-group">
								<label>نام کاربری پنل</label>
								<input type="text" name="<?php echo $input_prefix; ?>[username]" value="<?php echo esc_attr($username); ?>" class="hub-input" dir="ltr" />
							</div>
							<div class="hub-form-group">
								<label>رمز عبور پنل</label>
								<input type="password" name="<?php echo $input_prefix; ?>[password]" value="<?php echo esc_attr($password); ?>" class="hub-input" dir="ltr" />
							</div>
							<div class="hub-form-group">
								<label>شماره خط فرستنده</label>
								<input type="text" name="<?php echo $input_prefix; ?>[from_number]" value="<?php echo esc_attr($from_number); ?>" class="hub-input" dir="ltr" />
							</div>
						</div>
					</div>

					<!-- Telegram Fields -->
					<div class="wh-field wh-telegram" style="display:none;">
						<div class="hub-grid-2">
							<div class="hub-form-group">
								<label>توکن ربات (Bot Token)</label>
								<input type="text" name="<?php echo $input_prefix; ?>[token]" value="<?php echo esc_attr($token); ?>" class="hub-input" dir="ltr" />
							</div>
							<div class="hub-form-group">
								<label>شناسه چت دیفالت (Chat ID)</label>
								<input type="text" name="<?php echo $input_prefix; ?>[chat_id]" value="<?php echo esc_attr($chat_id); ?>" class="hub-input" dir="ltr" />
							</div>
						</div>
					</div>

					<!-- Webhook URLs (n8n, Google Sheet, Slack, Discord) -->
					<div class="wh-field wh-webhook-url" style="display:none;">
						<div class="hub-form-group">
							<label>آدرس وب‌هوک (Webhook URL)</label>
							<input type="url" name="<?php echo $input_prefix; ?>[url]" value="<?php echo esc_attr($url); ?>" class="hub-input" dir="ltr" placeholder="https://..." />
						</div>
					</div>

					<!-- Email Field -->
					<div class="wh-field wh-email" style="display:none;">
						<div class="hub-info-box">
							<span class="dashicons dashicons-email"></span>
							<p>برای ایمیل نیاز به تنظیمات اضافه‌ای نیست و از سیستم ایمیل داخلی سایت شما استفاده می‌شود.</p>
						</div>
					</div>

					<!-- WhatsApp Fields -->
					<div class="wh-field wh-whatsapp" style="display:none;">
						<div class="hub-form-group">
							<label>شناسه شماره تلفن (Phone Number ID)</label>
							<input type="text" name="<?php echo $input_prefix; ?>[phone_number_id]" value="<?php echo esc_attr($phone_number_id); ?>" class="hub-input" dir="ltr" />
						</div>
						<div class="hub-form-group">
							<label>توکن دسترسی دائم (Access Token)</label>
							<input type="text" name="<?php echo $input_prefix; ?>[access_token]" value="<?php echo esc_attr($access_token); ?>" class="hub-input" dir="ltr" />
						</div>
					</div>

					<!-- OneSignal Fields -->
					<div class="wh-field wh-onesignal" style="display:none;">
						<div class="hub-grid-2">
							<div class="hub-form-group">
								<label>شناسه اپلیکیشن (App ID)</label>
								<input type="text" name="<?php echo $input_prefix; ?>[app_id]" value="<?php echo esc_attr($app_id); ?>" class="hub-input" dir="ltr" />
							</div>
							<div class="hub-form-group">
								<label>کلید اتصال (REST API Key)</label>
								<input type="text" name="<?php echo $input_prefix; ?>[rest_api_key]" value="<?php echo esc_attr($rest_api_key); ?>" class="hub-input" dir="ltr" />
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * ==========================================
	 * پردازش و ذخیره‌سازی مجزای فرم‌ها (رفع باگ ذخیره)
	 * ==========================================
	 */
	public function save_settings() {
		// اگر ادمین نیست یا فرم سابمیت نشده کلا خارج شو
		if ( ! current_user_can( 'manage_options' ) ) return;
		
		// 1. ذخیره فرم سناریوها
		if ( isset( $_POST['hub_save_rules'] ) ) {
			check_admin_referer( 'hub_save_nonce', 'hub_nonce' );
			$clean_rules = array();
			if ( isset( $_POST['rules'] ) && is_array( $_POST['rules'] ) ) {
				foreach ( $_POST['rules'] as $rule ) {
					$trigger = sanitize_text_field( $rule['trigger'] );
					$sub = '';
					if ( 'order_status' === $trigger ) $sub = sanitize_text_field( $rule['sub_trigger_order'] );
					elseif ( 'winback_user' === $trigger ) $sub = sanitize_text_field( $rule['sub_trigger_winback'] );

					$clean_conditions = array();
					if ( isset( $rule['conditions'] ) && is_array( $rule['conditions'] ) ) {
						foreach ( $rule['conditions'] as $cond ) {
							$clean_conditions[] = array(
								'field'    => sanitize_text_field( $cond['field'] ),
								'operator' => sanitize_text_field( $cond['operator'] ),
								'value'    => sanitize_text_field( $cond['value'] )
							);
						}
					}

					$clean_actions = array();
					if ( isset( $rule['actions'] ) && is_array( $rule['actions'] ) ) {
						foreach ( $rule['actions'] as $act ) {
							$clean_actions[] = array(
								'id'            => sanitize_text_field( $act['id'] ),
								'type'          => sanitize_text_field( $act['type'] ),
								'connection_id' => isset( $act['connection_id'] ) ? sanitize_text_field( $act['connection_id'] ) : '',
								'target_mode'   => isset( $act['target_mode'] ) ? sanitize_text_field( $act['target_mode'] ) : 'custom',
								'target_value'  => isset( $act['target_value'] ) ? sanitize_text_field( $act['target_value'] ) : '',
								'message'       => wp_kses_post( $act['message'] ),
								'delay'         => array(
									'enabled' => isset( $act['delay']['enabled'] ) ? true : false,
									'value'   => isset( $act['delay']['value'] ) ? intval( $act['delay']['value'] ) : 0,
									'unit'    => isset( $act['delay']['unit'] ) ? sanitize_text_field( $act['delay']['unit'] ) : 'minutes',
								),
								'meta'          => isset( $act['meta'] ) ? array_map( 'sanitize_text_field', $act['meta'] ) : array()
							);
						}
					}

					$clean_rules[] = array(
						'name'            => sanitize_text_field( $rule['name'] ),
						'trigger'         => $trigger,
						'sub_trigger'     => $sub,
						'condition_logic' => sanitize_text_field( $rule['condition_logic'] ),
						'conditions'      => $clean_conditions,
						'actions'         => $clean_actions
					);
				}
			}
			update_option( 'hub_rules', $clean_rules );
			echo '<div class="notice notice-success is-dismissible"><p>✅ سناریوهای اتوماسیون با موفقیت بروزرسانی شدند.</p></div>';
		}

		// 2. ذخیره فرم کانال‌ها
		if ( isset( $_POST['hub_save_webhooks'] ) ) {
			check_admin_referer( 'hub_save_nonce', 'hub_nonce' );
			$clean_webhooks = array();
			if ( isset( $_POST['webhooks'] ) && is_array( $_POST['webhooks'] ) ) {
				foreach ( $_POST['webhooks'] as $wh ) {
					if ( empty( $wh['name'] ) ) continue;
					$key = sanitize_title( $wh['name'] );
					$clean_webhooks[ $key ] = array(
						'name'            => sanitize_text_field( $wh['name'] ),
						'type'            => sanitize_text_field( $wh['type'] ),
						'url'             => !empty($wh['url']) ? esc_url_raw($wh['url']) : '',
						'username'        => !empty($wh['username']) ? sanitize_text_field( $wh['username'] ) : '',
						'password'        => !empty($wh['password']) ? sanitize_text_field( $wh['password'] ) : '',
						'from_number'     => !empty($wh['from_number']) ? sanitize_text_field( $wh['from_number'] ) : '',
						'token'           => !empty($wh['token']) ? sanitize_text_field( $wh['token'] ) : '',
						'chat_id'         => !empty($wh['chat_id']) ? sanitize_text_field( $wh['chat_id'] ) : '',
						'phone_number_id' => !empty($wh['phone_number_id']) ? sanitize_text_field( $wh['phone_number_id'] ) : '',
						'access_token'    => !empty($wh['access_token']) ? sanitize_text_field( $wh['access_token'] ) : '',
						'app_id'          => !empty($wh['app_id']) ? sanitize_text_field( $wh['app_id'] ) : '',
						'rest_api_key'    => !empty($wh['rest_api_key']) ? sanitize_text_field( $wh['rest_api_key'] ) : '',
					);
				}
			}
			update_option( 'hub_webhooks', $clean_webhooks );
			echo '<div class="notice notice-success is-dismissible"><p>✅ تنظیمات کانال‌های ارتباطی با موفقیت ذخیره شد.</p></div>';
		}
	}
}