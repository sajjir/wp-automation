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
		// نکته: در ریپازیتوری شما فایل CSS در مسیر admin/js/css/ قرار دارد.
		// بهتر است فایل را به admin/css/ منتقل کنید تا این مسیر استاندارد درست کار کند.
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
		<div class="wrap automation-hub-wrap rtl">
			<h1>⚙️ اتوماسیون هاب <small style="font-size: 12px; color: #777;">نسخه ۲.۰ پرو</small></h1>
			<h2 class="nav-tab-wrapper">
				<a href="?page=automation-hub&tab=campaigns" class="nav-tab <?php echo $active_tab == 'campaigns' ? 'nav-tab-active' : ''; ?>">📋 سناریوهای اتوماسیون</a>
				<a href="?page=automation-hub&tab=connections" class="nav-tab <?php echo $active_tab == 'connections' ? 'nav-tab-active' : ''; ?>">🔌 مدیریت کانال‌ها</a>
			</h2>
			<form method="POST" action="">
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
	 * Renders dynamic campaign items architecture
	 */
	private function render_campaigns_tab() {
		$rules = get_option( 'hub_rules', array() );
		$webhooks = get_option( 'hub_webhooks', array() );
		?>
		<div class="campaigns-manager-holder" style="margin-top: 20px;">
			<div id="rules-repeater-container">
				<?php 
				if ( ! empty( $rules ) && is_array( $rules ) ) {
					foreach ( $rules as $index => $rule ) {
						$this->render_rule_row( $rule, $index, $webhooks );
					}
				}
				?>
			</div>
			
			<p>
				<button type="button" id="btn-add-new-rule" class="button button-primary button-large">➕ افزودن سناریوی جدید</button>
				<input type="submit" name="hub_save_settings" class="button button-success button-large style-save-btn" value="💾 ذخیره‌سازی کل تغییرات" style="background:#46b450;color:#fff;border-color:#349b40;" />
			</p>
		</div>

		<script type="text/template" id="rule-template">
			<?php $this->render_rule_row( array(), '{{RULE_INDEX}}', $webhooks, true ); ?>
		</script>
		<?php
	}

	/**
	 * Renders Connections (Webhooks) Tab
	 */
	private function render_connections_tab() {
		$webhooks = get_option( 'hub_webhooks', array() );
		?>
		<div class="connections-manager-holder" style="margin-top: 20px;">
			<div id="webhooks-repeater-container">
				<?php 
				if ( ! empty( $webhooks ) && is_array( $webhooks ) ) {
					$index = 0;
					foreach ( $webhooks as $key => $webhook ) {
						$this->render_webhook_row( $webhook, $index );
						$index++;
					}
				}
				?>
			</div>
			
			<p>
				<button type="button" id="btn-add-new-webhook" class="button button-primary button-large">➕ افزودن اتصال جدید</button>
				<input type="submit" name="hub_save_settings" class="button button-success button-large style-save-btn" value="💾 ذخیره‌سازی کانال‌ها" style="background:#46b450;color:#fff;border-color:#349b40;" />
			</p>
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

		$row_class = $is_template ? 'webhook-row repeater-row template-hidden' : 'webhook-row repeater-row';
		$attr_index = $is_template ? 'data-index="{{WH_INDEX}}"' : 'data-index="' . $index . '"';
		$input_prefix = "webhooks[$index]";
		?>
		<div class="<?php echo $row_class; ?>" <?php echo $attr_index; ?> style="background: #fff; border: 1px solid #ccc; padding: 15px; margin-bottom: 20px; border-radius: 5px; position: relative;">
			<button type="button" class="remove-webhook-row button-link" style="position: absolute; left: 15px; top: 15px; color: #a00;">❌ حذف کانال</button>
			<h3 style="margin-top: 0;">🔌 تنظیمات اتصال</h3>
			
			<table class="form-table">
				<tr>
					<th>نام کانال</th>
					<td><input type="text" name="<?php echo $input_prefix; ?>[name]" value="<?php echo esc_attr($name); ?>" class="regular-text" placeholder="مثلا: پیامک اصلی سایت" required /></td>
				</tr>
				<tr>
					<th>نوع اتصال (Provider)</th>
					<td>
						<select name="<?php echo $input_prefix; ?>[type]" class="webhook-type-selector regular-text">
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
					</td>
				</tr>
			</table>

			<div style="background:#f9f9f9; padding: 10px; border-radius:3px; margin-top: 10px;">
				<div class="wh-field wh-melipayamak <?php echo $type == 'melipayamak' ? '' : 'hidden-box'; ?>">
					<p>نام کاربری: <input type="text" name="<?php echo $input_prefix; ?>[username]" value="<?php echo esc_attr($username); ?>" /></p>
					<p>رمز عبور: <input type="password" name="<?php echo $input_prefix; ?>[password]" value="<?php echo esc_attr($password); ?>" /></p>
					<p>شماره خط فرستنده: <input type="text" name="<?php echo $input_prefix; ?>[from_number]" value="<?php echo esc_attr($from_number); ?>" dir="ltr" /></p>
				</div>
				<div class="wh-field wh-telegram <?php echo $type == 'telegram' ? '' : 'hidden-box'; ?>">
					<p>توکن ربات (Bot Token): <input type="text" name="<?php echo $input_prefix; ?>[token]" value="<?php echo esc_attr($token); ?>" class="regular-text" dir="ltr" /></p>
					<p>شناسه چت (Chat ID): <input type="text" name="<?php echo $input_prefix; ?>[chat_id]" value="<?php echo esc_attr($chat_id); ?>" dir="ltr" /></p>
				</div>
				<div class="wh-field wh-n8n wh-google_sheet wh-slack wh-discord <?php echo in_array($type, ['n8n', 'google_sheet', 'slack', 'discord']) ? '' : 'hidden-box'; ?>">
					<p>آدرس وب‌هوک (URL): <input type="url" name="<?php echo $input_prefix; ?>[url]" value="<?php echo esc_attr($url); ?>" class="large-text" dir="ltr" /></p>
				</div>
				<div class="wh-field wh-email <?php echo $type == 'email' ? '' : 'hidden-box'; ?>">
					<p class="description">برای ایمیل نیاز به تنظیمات اضافه‌ای نیست و از تابع wp_mail خود سایت استفاده می‌شود.</p>
				</div>
				<div class="wh-field wh-whatsapp <?php echo $type == 'whatsapp' ? '' : 'hidden-box'; ?>">
					<p>شناسه شماره تلفن (Phone Number ID): <input type="text" name="<?php echo $input_prefix; ?>[phone_number_id]" value="<?php echo esc_attr($phone_number_id); ?>" class="regular-text" dir="ltr" /></p>
					<p>توکن دسترسی (Access Token): <input type="text" name="<?php echo $input_prefix; ?>[access_token]" value="<?php echo esc_attr($access_token); ?>" class="large-text" dir="ltr" /></p>
				</div>
				<div class="wh-field wh-onesignal <?php echo $type == 'onesignal' ? '' : 'hidden-box'; ?>">
					<p>شناسه اپلیکیشن (App ID): <input type="text" name="<?php echo $input_prefix; ?>[app_id]" value="<?php echo esc_attr($app_id); ?>" class="regular-text" dir="ltr" /></p>
					<p>کلید API (REST API Key): <input type="text" name="<?php echo $input_prefix; ?>[rest_api_key]" value="<?php echo esc_attr($rest_api_key); ?>" class="regular-text" dir="ltr" /></p>
				</div>
			</div>
		</div>
		<?php
	}

	private function render_rule_row( $rule = array(), $index = 0, $webhooks = array(), $is_template = false ) {
		$name = isset( $rule['name'] ) ? $rule['name'] : '';
		$trigger = isset( $rule['trigger'] ) ? $rule['trigger'] : 'order_status';
		$sub_trigger = isset( $rule['sub_trigger'] ) ? $rule['sub_trigger'] : '';
		$condition_logic = isset( $rule['condition_logic'] ) ? $rule['condition_logic'] : 'AND';
		$conditions = isset( $rule['conditions'] ) ? $rule['conditions'] : array();
		$actions = isset( $rule['actions'] ) ? $rule['actions'] : array();
		
		$row_class = $is_template ? 'rule-row repeater-row template-hidden' : 'rule-row repeater-row';
		$attr_index = $is_template ? 'data-index="{{RULE_INDEX}}"' : 'data-index="' . $index . '"';
		$input_prefix = "rules[$index]";
		?>
		<div class="<?php echo $row_class; ?>" <?php echo $attr_index; ?> style="background: #fff; border: 1px solid #ccc; padding: 15px; margin-bottom: 20px; border-radius: 5px; position: relative;">
			<button type="button" class="remove-row button-link" style="position: absolute; left: 15px; top: 15px; color: #a00;">❌ حذف سناریو</button>
			<h3 class="handle-title" style="margin-top: 0; cursor: pointer;">🔍 سناریو: <span class="lbl-rule-title"><?php echo $name ? esc_html($name) : 'بدون نام'; ?></span></h3>
			
			<table class="form-table">
				<tr>
					<th>نام سناریو</th>
					<td><input type="text" name="<?php echo $input_prefix; ?>[name]" value="<?php echo esc_attr($name); ?>" class="regular-text txt-rule-name-input" placeholder="مثلا: ارسال پیامک تشکر خرید" required /></td>
				</tr>
				<tr>
					<th>رویداد اصلی (Trigger)</th>
					<td>
						<select name="<?php echo $input_prefix; ?>[trigger]" class="trigger-selector regular-text">
							<option value="order_status" <?php selected($trigger, 'order_status'); ?>>WooCommerce - تغییر وضعیت سفارش</option>
							<option value="abandoned_cart" <?php selected($trigger, 'abandoned_cart'); ?>>WooCommerce - سبد خرید رها شده</option>
							<option value="low_stock" <?php selected($trigger, 'low_stock'); ?>>WooCommerce - موجودی انبار رو به اتمام / تمام شده</option>
							<option value="order_refunded" <?php selected($trigger, 'order_refunded'); ?>>WooCommerce - ثبت مرجوعی سفارش</option>
							<option value="product_review" <?php selected($trigger, 'product_review'); ?>>WooCommerce - ثبت دیدگاه/امتیاز محصول</option>
							<option value="winback_user" <?php selected($trigger, 'winback_user'); ?>>Users - کاربر غیرفعال (Win-back)</option>
						</select>
					</td>
				</tr>
				<tr class="sub-trigger-row">
					<th>جزئیات رویداد</th>
					<td>
						<div class="cond-box cond-order_status <?php echo $trigger == 'order_status' ? '' : 'hidden-box'; ?>">
							<select name="<?php echo $input_prefix; ?>[sub_trigger_order]" class="regular-text">
								<option value="pending" <?php selected($sub_trigger, 'pending'); ?>>در انتظار پرداخت</option>
								<option value="processing" <?php selected($sub_trigger, 'processing'); ?>>در حال پردازش</option>
								<option value="completed" <?php selected($sub_trigger, 'completed'); ?>>تکمیل شده</option>
								<option value="cancelled" <?php selected($sub_trigger, 'cancelled'); ?>>لغو شده</option>
								<option value="refunded" <?php selected($sub_trigger, 'refunded'); ?>>مرجوع شده</option>
							</select>
						</div>
						<div class="cond-box cond-winback_user <?php echo $trigger == 'winback_user' ? '' : 'hidden-box'; ?>">
							عدم ورود کاربر به سایت پس از <input type="number" name="<?php echo $input_prefix; ?>[sub_trigger_winback]" value="<?php echo esc_attr($sub_trigger); ?>" style="width:70px;" /> روز
						</div>
					</td>
				</tr>
			</table>

			<hr />
			<h4>🎯 شروط هوشمند پردازش (Conditional Logic)</h4>
			<div class="conditions-grid-wrapper">
				<div class="logic-header" style="margin-bottom:10px;">
					تطابق شروط بر اساس منطق: 
					<select name="<?php echo $input_prefix; ?>[condition_logic]">
						<option value="AND" <?php selected($condition_logic, 'AND'); ?>>همه شروط برقرار باشند (AND)</option>
						<option value="OR" <?php selected($condition_logic, 'OR'); ?>>حداقل یکی از شروط برقرار باشد (OR)</option>
					</select>
				</div>
				<div class="conditions-container-rows" style="background:#f9f9f9; padding: 10px; border-radius:3px;">
					<?php 
					if(is_array($conditions)){
						foreach($conditions as $c_idx => $cond){
							?>
							<div class="condition-item-block" style="margin-bottom:5px;">
								<select name="<?php echo $input_prefix; ?>[conditions][<?php echo $c_idx; ?>][field]">
									<option value="order_total" <?php selected($cond['field'], 'order_total'); ?>>جمع کل سفارش</option>
									<option value="billing_city" <?php selected($cond['field'], 'billing_city'); ?>>شهر صورتحساب</option>
									<option value="user_role" <?php selected($cond['field'], 'user_role'); ?>>نقش کاربری</option>
								</select>
								<select name="<?php echo $input_prefix; ?>[conditions][<?php echo $c_idx; ?>][operator]">
									<option value="equals" <?php selected($cond['operator'], 'equals'); ?>>برابر با</option>
									<option value="greater_than" <?php selected($cond['operator'], 'greater_than'); ?>>بزرگتر از</option>
									<option value="contains" <?php selected($cond['operator'], 'contains'); ?>>شامل عبارت</option>
								</select>
								<input type="text" name="<?php echo $input_prefix; ?>[conditions][<?php echo $c_idx; ?>][value]" value="<?php echo esc_attr($cond['value']); ?>" placeholder="مقدار هدف" />
								<button type="button" class="btn-remove-condition button-link" style="color:#a00;">❌</button>
							</div>
							<?php
						}
					}
					?>
				</div>
				<button type="button" class="btn-add-condition button button-secondary button-small" style="margin-top:5px;">➕ افزودن شرط جدید</button>
			</div>

			<hr />
			<h4>⚡ اقدامات و خروجی‌ها (Actions Grid)</h4>
			<div class="actions-grid-wrapper" style="background:#f4f4f4; padding:15px; border-radius:4px;">
				<div class="actions-holder-rows">
					<?php 
					if(is_array($actions)){
						foreach($actions as $a_idx => $act){
							$act_id = $act['id'];
							$act_prefix = $input_prefix . "[actions][$a_idx]";
							?>
							<div class="action-block-item collapsible-box" style="background:#fff; border:1px solid #ddd; padding:10px; margin-bottom:10px; border-radius:3px;">
								<div class="action-top-header" style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #eee; padding-bottom:5px; margin-bottom:10px;">
									<strong>🎬 اقدام خروجی (#<?php echo esc_html($act_id); ?>)</strong>
									<button type="button" class="btn-delete-action-node button-link" style="color:#a00;">حذف اقدام</button>
								</div>
								<input type="hidden" name="<?php echo $act_prefix; ?>[id]" value="<?php echo esc_attr($act_id); ?>" />
								
								<table class="form-table" style="margin:0;">
									<tr>
										<th style="width:120px;">نوع اتصال/کانال</th>
										<td>
											<select name="<?php echo $act_prefix; ?>[type]" class="action-type-selector">
												<option value="sms" <?php selected($act['type'], 'sms'); ?>>ارسال پیامک بیرونی</option>
												<option value="telegram" <?php selected($act['type'], 'telegram'); ?>>ارسال پیام تلگرام</option>
												<option value="n8n" <?php selected($act['type'], 'n8n'); ?>>اتصال وب هوک به n8n</option>
												<option value="email" <?php selected($act['type'], 'email'); ?>>ارسال ایمیل (HTML)</option>
												<option value="order_note" <?php selected($act['type'], 'order_note'); ?>>داخلی - ثبت یادداشت روی سفارش</option>
												<option value="order_status" <?php selected($act['type'], 'order_status'); ?>>داخلی - تغییر وضعیت سفارش</option>
											</select>
										</td>
									</tr>
									<tr>
										<th>انتخاب اکانت کانال</th>
										<td>
											<select name="<?php echo $act_prefix; ?>[connection_id]">
												<option value="">اتصال پیشفرض سیستم</option>
												<?php foreach($webhooks as $w_key => $w_val): ?>
													<option value="<?php echo esc_attr($w_key); ?>" <?php selected($act['connection_id'], $w_key); ?>><?php echo esc_html($w_val['name']); ?></option>
												<?php endforeach; ?>
											</select>
										</td>
									</tr>
									<tr>
										<th>متن پیام خروجی</th>
										<td>
											<textarea name="<?php echo $act_prefix; ?>[message]" rows="3" style="width:100%;"><?php echo esc_textarea($act['message']); ?></textarea>
											<p class="description">شورت‌کدهای مجاز: <code>{order_id}</code>, <code>{total}</code>, <code>{full_name}</code>, <code>{phone}</code>, <code>{address}</code>, <code>{date}</code>, <code>{items_detailed}</code></p>
										</td>
									</tr>
									<tr>
										<th>⏱ تاخیر در اجرا</th>
										<td>
											<label>
												<input type="checkbox" name="<?php echo $act_prefix; ?>[delay][enabled]" value="1" <?php checked(!empty($act['delay']['enabled']), true); ?> class="chk-delay-toggle" /> فعالسازی مکانیزم تاخیر زمان‌بندی شده
											</label>
											<div class="delay-values-wrapper <?php echo !empty($act['delay']['enabled']) ? '' : 'hidden-box'; ?>" style="margin-top:5px;">
												مقدار تاخیر: <input type="number" name="<?php echo $act_prefix; ?>[delay][value]" value="<?php echo esc_attr(isset($act['delay']['value'])?$act['delay']['value']:0); ?>" style="width:60px;" />
												<select name="<?php echo $act_prefix; ?>[delay][unit]">
													<option value="minutes" <?php selected(isset($act['delay']['unit'])?$act['delay']['unit']:'', 'minutes'); ?>>دقیقه</option>
													<option value="hours" <?php selected(isset($act['delay']['unit'])?$act['delay']['unit']:'', 'hours'); ?>>ساعت</option>
													<option value="days" <?php selected(isset($act['delay']['unit'])?$act['delay']['unit']:'', 'days'); ?>>روز</option>
												</select>
											</div>
										</td>
									</tr>
								</table>
							</div>
							<?php
						}
					}
					?>
				</div>
				<button type="button" class="btn-add-action-node button button-secondary" style="margin-top:5px;">➕ افزودن اقدام (Action) جدید</button>
			</div>
		</div>
		<?php
	}

	/**
	 * Form Save Handlers Processing Sanitization Engine Framework
	 */
	public function save_settings() {
		// الان هر دو فرم Rules و Webhooks با کلیک روی نام hub_save_settings تایید میشن
		if ( ! isset( $_POST['hub_save_settings'] ) ) return;
		if ( ! current_user_can( 'manage_options' ) ) return;
		check_admin_referer( 'hub_save_nonce', 'hub_nonce' );

		// 1. ذخیره Rules
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
							'connection_id' => sanitize_text_field( $act['connection_id'] ),
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

		// 2. ذخیره Webhooks (اتصالات)
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

		echo '<div class="notice notice-success is-dismissible"><p>✅ تغییرات با موفقیت ذخیره شدند.</p></div>';
	}
}