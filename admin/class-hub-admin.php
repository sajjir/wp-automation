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

        // AJAX Hooks for Instant Testing
        add_action( 'wp_ajax_hub_search_orders', array( $this, 'ajax_search_orders' ) );
        add_action( 'wp_ajax_hub_test_action', array( $this, 'ajax_test_action' ) );
	}

	public function enqueue_admin_assets() {
        // راه حل قطعی برای لود شدن استایل‌ها (رفع باگ سفیدی صفحه)
		if ( ! isset($_GET['page']) || $_GET['page'] !== 'automation-hub' ) {
			return;
		}
		
		wp_enqueue_style( 'hub-admin-css', HUB_PLUGIN_URL . 'admin/js/css/hub-admin.css', array(), HUB_VERSION );
		wp_enqueue_script( 'hub-admin-js', HUB_PLUGIN_URL . 'admin/js/hub-admin.js', array( 'jquery' ), HUB_VERSION, true );

        wp_localize_script( 'hub-admin-js', 'hubAdmin', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'hub_admin_ajax' )
        ));
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
				<div class="hub-header-content">
					<h1><span class="dashicons dashicons-networking"></span> اتوماسیون هاب <span class="hub-badge">پرو ۲.۰</span></h1>
					<p class="hub-subtitle">سیستم هوشمند اتوماسیون و اطلاع‌رسانی چندکاناله فروشگاه شما</p>
				</div>
			</div>

			<h2 class="nav-tab-wrapper hub-nav-tabs">
				<a href="?page=automation-hub&tab=campaigns" class="nav-tab <?php echo $active_tab == 'campaigns' ? 'nav-tab-active' : ''; ?>">📋 مدیریت سناریوها</a>
				<a href="?page=automation-hub&tab=connections" class="nav-tab <?php echo $active_tab == 'connections' ? 'nav-tab-active' : ''; ?>">🔌 مدیریت کانال‌ها</a>
				<a href="?page=automation-hub&tab=otp_settings" class="nav-tab <?php echo $active_tab == 'otp_settings' ? 'nav-tab-active' : ''; ?>">📱 ورود و OTP پیامکی</a>
			</h2>

			<form method="POST" action="" class="hub-form-container">
				<?php
				wp_nonce_field( 'hub_save_nonce', 'hub_nonce' );
				if ( 'campaigns' === $active_tab ) {
					$this->render_campaigns_tab();
				} elseif ( 'connections' === $active_tab ) {
					$this->render_connections_tab();
				} else {
					$this->render_otp_settings_tab();
				}
				?>
			</form>
		</div>

        <!-- پاپ‌آپ تست اقدام آنی (Modal) - فقط زمانی که CSS لود شود مخفی است -->
        <div id="hub-test-modal" class="hub-modal-overlay">
            <div class="hub-modal-box">
                <div class="hub-modal-header">
                    <h3>⚡ انتخاب سفارش برای اقدام آنی</h3>
                    <button type="button" class="hub-modal-close"><span class="dashicons dashicons-no-alt"></span></button>
                </div>
                <div class="hub-modal-body">
                    <input type="text" id="hub-order-search" class="hub-search-box" placeholder="جستجوی شماره سفارش یا موبایل مشتری..." />
                    <div id="hub-order-results"></div>
                </div>
            </div>
        </div>

        <!-- مخزن تمیز برای آپشن‌های کانال جهت استفاده JS -->
        <div id="hub-connection-options-template" style="display:none;">
            <option value="">-- انتخاب کانال --</option>
            <?php 
            $webhooks = get_option( 'hub_webhooks', array() );
            if(is_array($webhooks)) {
                foreach($webhooks as $w_key => $w_val): ?>
                    <option value="<?php echo esc_attr($w_key); ?>" data-provider="<?php echo esc_attr($w_val['type']); ?>"><?php echo esc_html($w_val['name']); ?></option>
                <?php endforeach; 
            } ?>
        </div>
		<?php
	}

	private function render_campaigns_tab() {
		$rules = get_option( 'hub_rules', array() );
		$webhooks = get_option( 'hub_webhooks', array() );
		?>
		<div class="hub-tab-content">
			<div class="hub-actions-top">
				<div class="hub-top-text">سناریوهای خود را بسازید تا فرآیندهای سایت به صورت خودکار اجرا شوند.</div>
				<div class="hub-top-buttons">
					<button type="button" id="btn-add-new-rule" class="hub-btn hub-btn-primary"><span class="dashicons dashicons-plus-alt2"></span> افزودن سناریو</button>
				</div>
			</div>

			<div id="rules-repeater-container">
				<?php 
				if ( ! empty( $rules ) && is_array( $rules ) ) {
					foreach ( $rules as $index => $rule ) {
						$this->render_rule_row( $rule, $index, $webhooks );
					}
				} else {
					echo '<div class="hub-empty-state"><span class="dashicons dashicons-media-document"></span><h3>هیچ سناریویی یافت نشد</h3><p>برای شروع، روی دکمه "افزودن سناریو" کلیک کنید.</p></div>';
				}
				?>
			</div>

            <!-- دکمه ذخیره ثابت و استاندارد در پایین فرم -->
            <div class="hub-bottom-save-bar">
                <span>تغییرات را اعمال کنید:</span>
                <button type="submit" name="hub_save_rules" class="hub-btn hub-btn-success hub-btn-lg"><span class="dashicons dashicons-saved"></span> ذخیره تمامی سناریوها</button>
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
					<span class="dashicons dashicons-controls-repeat hub-icon-muted"></span>
					<input type="text" name="<?php echo $input_prefix; ?>[name]" value="<?php echo esc_attr($name); ?>" class="hub-input-title txt-rule-name-input" placeholder="عنوان سناریو (مثلا: پیگیری سبد خرید)" required />
				</div>
				<button type="button" class="hub-btn-icon-danger remove-row" title="حذف سناریو"><span class="dashicons dashicons-trash"></span></button>
			</div>
			
			<div class="hub-card-body">
				<div class="hub-section">
					<div class="hub-section-header">
						<span class="hub-step-number">۱</span>
						<h4 class="hub-section-title">رویداد شروع (Trigger)</h4>
					</div>
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
								<div class="hub-input-addon">
									<span class="addon-text">پس از</span>
									<input type="number" name="<?php echo $input_prefix; ?>[sub_trigger_winback]" value="<?php echo esc_attr($sub_trigger); ?>" class="hub-input" style="width:80px; text-align:center;" />
									<span class="addon-text">روز عدم ورود</span>
								</div>
							</div>
						</div>
					</div>
				</div>

				<div class="hub-section hub-bg-light">
					<div class="hub-section-header">
						<span class="hub-step-number">۲</span>
						<h4 class="hub-section-title">شروط هوشمند (Conditions)</h4>
					</div>
					<div class="hub-form-group">
						<label class="hub-inline-label">
							اجرای اقدامات در صورتی که: 
							<select name="<?php echo $input_prefix; ?>[condition_logic]" class="hub-select" style="width: auto; display: inline-block;">
								<option value="AND" <?php selected($condition_logic, 'AND'); ?>>همه شروط زیر برقرار باشند (AND)</option>
								<option value="OR" <?php selected($condition_logic, 'OR'); ?>>حداقل یکی از شروط زیر برقرار باشد (OR)</option>
							</select>
						</label>
					</div>
					
					<div class="conditions-container-rows">
						<?php 
						if(is_array($conditions) && !empty($conditions)){
							foreach($conditions as $c_idx => $cond){
								$c_field    = isset($cond['field']) ? $cond['field'] : '';
								$c_operator = isset($cond['operator']) ? $cond['operator'] : '';
								$c_val      = isset($cond['value']) ? $cond['value'] : '';
								?>
								<div class="hub-condition-row">
									<select name="<?php echo $input_prefix; ?>[conditions][<?php echo $c_idx; ?>][field]" class="hub-select">
										<option value="order_total" <?php selected($c_field, 'order_total'); ?>>جمع کل سفارش</option>
										<option value="billing_city" <?php selected($c_field, 'billing_city'); ?>>شهر صورتحساب</option>
										<option value="user_role" <?php selected($c_field, 'user_role'); ?>>نقش کاربری</option>
									</select>
									<select name="<?php echo $input_prefix; ?>[conditions][<?php echo $c_idx; ?>][operator]" class="hub-select">
										<option value="equals" <?php selected($c_operator, 'equals'); ?>>برابر با</option>
										<option value="not_equals" <?php selected($c_operator, 'not_equals'); ?>>مخالف با</option>
										<option value="greater_than" <?php selected($c_operator, 'greater_than'); ?>>بزرگتر از</option>
										<option value="contains" <?php selected($c_operator, 'contains'); ?>>شامل عبارت</option>
									</select>
									<input type="text" name="<?php echo $input_prefix; ?>[conditions][<?php echo $c_idx; ?>][value]" value="<?php echo esc_attr($c_val); ?>" class="hub-input" placeholder="مقدار ارزیابی..." />
									<button type="button" class="hub-btn-icon-danger btn-remove-condition"><span class="dashicons dashicons-no"></span></button>
								</div>
								<?php
							}
						}
						?>
					</div>
					<button type="button" class="hub-btn hub-btn-outline btn-add-condition" style="margin-top: 15px;"><span class="dashicons dashicons-plus"></span> افزودن شرط</button>
				</div>

				<div class="hub-section">
					<div class="hub-section-header">
						<span class="hub-step-number">۳</span>
						<h4 class="hub-section-title">اقدامات اجرایی (Actions)</h4>
					</div>
					<div class="actions-holder-rows">
						<?php 
						if(is_array($actions) && !empty($actions)){
							foreach($actions as $a_idx => $act){
								$act_id      = isset($act['id']) ? $act['id'] : uniqid();
								$act_prefix  = $input_prefix . "[actions][$a_idx]";
                                $act_name    = isset($act['name']) && !empty($act['name']) ? $act['name'] : 'اقدام خروجی';
								$act_type    = isset($act['type']) ? $act['type'] : 'sms';
								$conn_id_val = isset($act['connection_id']) ? $act['connection_id'] : '';
                                $t_mode      = isset($act['target_mode']) ? $act['target_mode'] : 'customer';
                                $t_value     = isset($act['target_value']) ? $act['target_value'] : '';
								$act_msg     = isset($act['message']) ? $act['message'] : '';
								$d_enabled   = !empty($act['delay']['enabled']);
								$d_val       = isset($act['delay']['value']) ? $act['delay']['value'] : 0;
								$d_unit      = isset($act['delay']['unit']) ? $act['delay']['unit'] : 'minutes';
								?>
								<div class="hub-action-card">
									<div class="hub-action-header">
										<div class="hub-action-title">
                                            <span class="dashicons dashicons-megaphone"></span> 
                                            <input type="text" name="<?php echo $act_prefix; ?>[name]" value="<?php echo esc_attr($act_name); ?>" class="hub-action-title-input" placeholder="نام اقدام (مثلا: ارسال به مدیر)" />
                                        </div>
                                        <div class="hub-action-controls">
                                            <button type="button" class="hub-btn hub-btn-warning btn-test-action">⚡ اقدام آنی</button>
										    <button type="button" class="hub-btn-icon-danger btn-delete-action-node" title="حذف اقدام"><span class="dashicons dashicons-trash"></span></button>
                                        </div>
									</div>
									<input type="hidden" name="<?php echo $act_prefix; ?>[id]" value="<?php echo esc_attr($act_id); ?>" />
									
									<div class="hub-grid-2">
										<div class="hub-form-group">
											<label>نوع اقدام</label>
											<select name="<?php echo $act_prefix; ?>[type]" class="hub-select action-type-selector">
												<option value="sms" <?php selected($act_type, 'sms'); ?>>ارسال پیامک (SMS)</option>
												<option value="telegram" <?php selected($act_type, 'telegram'); ?>>ارسال به تلگرام</option>
												<option value="n8n" <?php selected($act_type, 'n8n'); ?>>ارسال به n8n (وب‌هوک)</option>
												<option value="email" <?php selected($act_type, 'email'); ?>>ارسال ایمیل (SMTP)</option>
												<option value="order_note" <?php selected($act_type, 'order_note'); ?>>داخلی - ثبت یادداشت سفارش</option>
												<option value="order_status" <?php selected($act_type, 'order_status'); ?>>داخلی - تغییر وضعیت سفارش</option>
											</select>
										</div>
										<div class="hub-form-group connection-group">
											<label>انتخاب کانال ارتباطی</label>
											<select name="<?php echo $act_prefix; ?>[connection_id]" class="hub-select connection-selector">
												<option value="">-- انتخاب کانال --</option>
												<?php 
                                                if(is_array($webhooks)) {
                                                    foreach($webhooks as $w_key => $w_val): ?>
													<option value="<?php echo esc_attr($w_key); ?>" data-provider="<?php echo esc_attr($w_val['type']); ?>" <?php selected($conn_id_val, $w_key); ?>><?php echo esc_html($w_val['name']); ?></option>
												    <?php endforeach; 
                                                } ?>
											</select>
										</div>
									</div>

                                    <div class="hub-grid-2 target-group" style="<?php echo in_array($act_type, ['n8n', 'order_note', 'order_status']) ? 'display:none;' : ''; ?>">
                                        <div class="hub-form-group">
                                            <label>گیرنده پیام (Target)</label>
                                            <select name="<?php echo $act_prefix; ?>[target_mode]" class="hub-select target-mode-selector">
                                                <option value="customer" <?php selected($t_mode, 'customer'); ?>>مشتری (صاحب سفارش)</option>
                                                <option value="admin" <?php selected($t_mode, 'admin'); ?>>مدیر سایت</option>
                                                <option value="custom" <?php selected($t_mode, 'custom'); ?>>شماره / ایمیل دلخواه</option>
                                            </select>
                                        </div>
                                        <div class="hub-form-group target-custom-box" style="<?php echo $t_mode === 'custom' ? '' : 'display:none;'; ?>">
                                            <label>مقدار گیرنده</label>
                                            <input type="text" name="<?php echo $act_prefix; ?>[target_value]" value="<?php echo esc_attr($t_value); ?>" class="hub-input ltr-input" placeholder="0912..." />
                                        </div>
                                    </div>
									
									<div class="hub-form-group">
										<label>محتوای پیام</label>
										<textarea name="<?php echo $act_prefix; ?>[message]" class="hub-textarea" rows="3"><?php echo esc_textarea($act_msg); ?></textarea>
										<div class="hub-tags-help">متغیرهای مجاز: <code>{order_id}</code> <code>{total}</code> <code>{full_name}</code> <code>{phone}</code></div>
									</div>

									<div class="hub-delay-box">
										<label class="hub-checkbox-label">
											<input type="checkbox" name="<?php echo $act_prefix; ?>[delay][enabled]" value="1" <?php checked($d_enabled, true); ?> class="chk-delay-toggle" />
											اجرای با تاخیر (زمان‌بندی شده)
										</label>
										<div class="delay-values-wrapper <?php echo $d_enabled ? '' : 'hidden-box'; ?>" style="margin-top:10px;">
											<input type="number" name="<?php echo $act_prefix; ?>[delay][value]" value="<?php echo esc_attr($d_val); ?>" class="hub-input-small" style="width:80px;" />
											<select name="<?php echo $act_prefix; ?>[delay][unit]" class="hub-select-small" style="width:120px;">
												<option value="minutes" <?php selected($d_unit, 'minutes'); ?>>دقیقه</option>
												<option value="hours" <?php selected($d_unit, 'hours'); ?>>ساعت</option>
												<option value="days" <?php selected($d_unit, 'days'); ?>>روز</option>
											</select>
										</div>
									</div>
								</div>
								<?php
							}
						}
						?>
					</div>
					<button type="button" class="hub-btn hub-btn-outline btn-add-action-node" style="margin-top:15px;"><span class="dashicons dashicons-plus"></span> افزودن اقدام جدید</button>
				</div>
			</div>
		</div>
		<?php
	}

	private function render_connections_tab() {
		$webhooks = get_option( 'hub_webhooks', array() );
		?>
		<div class="hub-tab-content">
			<div class="hub-actions-top">
				<div class="hub-top-text">پروایدرها و درگاه‌های ارتباطی خود (مثل پیامک، تلگرام، n8n) را در اینجا تنظیم کنید.</div>
				<div class="hub-top-buttons">
					<button type="button" id="btn-add-new-webhook" class="hub-btn hub-btn-primary"><span class="dashicons dashicons-plus-alt2"></span> افزودن کانال</button>
				</div>
			</div>

			<div id="webhooks-repeater-container">
				<?php 
				if ( ! empty( $webhooks ) && is_array( $webhooks ) ) {
					foreach ( $webhooks as $key => $webhook ) {
						$this->render_webhook_row( $webhook, $key );
					}
				} else {
					echo '<div class="hub-empty-state"><span class="dashicons dashicons-admin-network"></span><h3>هیچ کانالی یافت نشد</h3><p>هم‌اکنون اولین ارائه‌دهنده (Provider) خود را تعریف کنید.</p></div>';
				}
				?>
			</div>

            <div class="hub-bottom-save-bar">
                <span>فراموش نکنید کانال‌ها را ذخیره کنید:</span>
                <button type="submit" name="hub_save_webhooks" class="hub-btn hub-btn-success hub-btn-lg"><span class="dashicons dashicons-saved"></span> ذخیره تمامی کانال‌ها</button>
            </div>
		</div>

		<script type="text/template" id="webhook-template">
			<?php $this->render_webhook_row( array(), '{{WH_INDEX}}', true ); ?>
		</script>
		<?php
	}

	private function render_webhook_row( $webhook = array(), $index = '', $is_template = false ) {
		$name            = isset( $webhook['name'] ) ? $webhook['name'] : '';
		$type            = isset( $webhook['type'] ) ? $webhook['type'] : 'melipayamak';
		$url             = isset( $webhook['url'] ) ? $webhook['url'] : '';
		$username        = isset( $webhook['username'] ) ? $webhook['username'] : '';
		$password        = isset( $webhook['password'] ) ? $webhook['password'] : '';
		$from_number     = isset( $webhook['from_number'] ) ? $webhook['from_number'] : '';
		$token           = isset( $webhook['token'] ) ? $webhook['token'] : '';
		$chat_id         = isset( $webhook['chat_id'] ) ? $webhook['chat_id'] : '';

		$row_class = $is_template ? 'hub-card webhook-row template-hidden' : 'hub-card webhook-row';
		$attr_index = $is_template ? 'data-index="{{WH_INDEX}}"' : 'data-index="' . esc_attr($index) . '"';
		$input_prefix = "webhooks[$index]";
		?>
		<div class="<?php echo $row_class; ?>" <?php echo $attr_index; ?>>
			<div class="hub-card-header">
				<div class="hub-card-title">
					<span class="dashicons dashicons-admin-links hub-icon-muted"></span>
					<strong>تنظیمات اتصال ارائه‌دهنده</strong>
				</div>
				<button type="button" class="hub-btn-icon-danger remove-webhook-row" title="حذف کانال"><span class="dashicons dashicons-trash"></span></button>
			</div>
			
			<div class="hub-card-body">
				<div class="hub-grid-2">
					<div class="hub-form-group">
						<label>نام کانال (شناسه داخلی)</label>
						<input type="text" name="<?php echo $input_prefix; ?>[name]" value="<?php echo esc_attr($name); ?>" class="hub-input" placeholder="مثلا: پنل پیامک ۱" required />
					</div>
					<div class="hub-form-group">
						<label>پلتفرم ارائه‌دهنده (Provider)</label>
						<select name="<?php echo $input_prefix; ?>[type]" class="hub-select webhook-type-selector">
							<option value="melipayamak" <?php selected($type, 'melipayamak'); ?>>ملی‌پیامک (SMS)</option>
							<option value="telegram" <?php selected($type, 'telegram'); ?>>تلگرام (Telegram Bot)</option>
							<option value="n8n" <?php selected($type, 'n8n'); ?>>n8n (Webhook)</option>
							<option value="email" <?php selected($type, 'email'); ?>>ایمیل (SMTP سایت)</option>
						</select>
					</div>
				</div>

				<div class="hub-dynamic-fields">
					<div class="wh-field wh-melipayamak" style="display:none;">
						<div class="hub-grid-3">
							<div class="hub-form-group">
								<label>نام کاربری پنل</label>
								<input type="text" name="<?php echo $input_prefix; ?>[username]" value="<?php echo esc_attr($username); ?>" class="hub-input ltr-input" />
							</div>
							<div class="hub-form-group">
								<label>رمز عبور پنل</label>
								<input type="password" name="<?php echo $input_prefix; ?>[password]" value="<?php echo esc_attr($password); ?>" class="hub-input ltr-input" />
							</div>
							<div class="hub-form-group">
								<label>شماره خط فرستنده</label>
								<input type="text" name="<?php echo $input_prefix; ?>[from_number]" value="<?php echo esc_attr($from_number); ?>" class="hub-input ltr-input" placeholder="مثلا: 1000xxxx" />
							</div>
						</div>
					</div>

					<div class="wh-field wh-telegram" style="display:none;">
						<div class="hub-grid-2">
							<div class="hub-form-group">
								<label>توکن ربات (Bot Token)</label>
								<input type="text" name="<?php echo $input_prefix; ?>[token]" value="<?php echo esc_attr($token); ?>" class="hub-input ltr-input" />
							</div>
							<div class="hub-form-group">
								<label>شناسه چت پیشفرض (Chat ID)</label>
								<input type="text" name="<?php echo $input_prefix; ?>[chat_id]" value="<?php echo esc_attr($chat_id); ?>" class="hub-input ltr-input" />
							</div>
						</div>
					</div>

					<div class="wh-field wh-webhook-url" style="display:none;">
						<div class="hub-form-group">
							<label>آدرس وب‌هوک (n8n URL)</label>
							<input type="url" name="<?php echo $input_prefix; ?>[url]" value="<?php echo esc_attr($url); ?>" class="hub-input ltr-input" placeholder="https://..." />
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

    private function render_otp_settings_tab() {
        $settings = get_option( 'hub_auth_settings', array() );
        $active = isset($settings['active']) ? $settings['active'] : false;
        $redirect = isset($settings['redirect_url']) ? $settings['redirect_url'] : '';
        $unified = isset($settings['unified_login']) ? $settings['unified_login'] : false;
        $conn_id = isset($settings['connection_id']) ? $settings['connection_id'] : '';
        $webhooks = get_option( 'hub_webhooks', array() );
        ?>
        <div class="hub-tab-content">
			<div class="hub-actions-top">
				<div class="hub-top-text">تنظیمات صفحه ورود پیامکی و احراز هویت پیامکی (OTP).</div>
			</div>
            <div class="hub-card">
                <div class="hub-card-body">
                    <div class="hub-form-group">
                        <label class="hub-checkbox-label">
                            <input type="checkbox" name="auth_settings[active]" value="1" <?php checked($active, true); ?> />
                            فعال‌سازی سیستم ورود با موبایل (OTP)
                        </label>
                    </div>
                    <div class="hub-grid-2">
                        <div class="hub-form-group">
                            <label>آدرس انتقال پس از ورود (Redirect URL)</label>
                            <input type="url" name="auth_settings[redirect_url]" class="hub-input ltr-input" value="<?php echo esc_attr($redirect); ?>" placeholder="<?php echo home_url('/my-account'); ?>" />
                        </div>
                        <div class="hub-form-group">
                            <label>انتخاب پنل پیامک برای ارسال کد تایید</label>
                            <select name="auth_settings[connection_id]" class="hub-select">
                                <option value="">-- انتخاب پنل ملی‌پیامک --</option>
                                <?php 
                                if(is_array($webhooks)){
                                    foreach($webhooks as $w_key => $w_val): 
                                        if(isset($w_val['type']) && $w_val['type'] === 'melipayamak') {
                                            echo '<option value="'.esc_attr($w_key).'" '.selected($conn_id, $w_key, false).'>'.esc_html($w_val['name']).'</option>';
                                        }
                                    endforeach; 
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="hub-form-group">
                        <label class="hub-checkbox-label">
                            <input type="checkbox" name="auth_settings[unified_login]" value="1" <?php checked($unified, true); ?> />
                            جایگزینی فرم ورود ووکامرس (صفحه My Account) با فرم OTP
                        </label>
                    </div>
                    <p class="hub-help-text" style="margin-top:15px;">برای استفاده در صفحات دیگر، از شورت‌کد <code>[hub_login_form]</code> استفاده کنید.</p>
                </div>
            </div>
            <div class="hub-bottom-save-bar">
                <span>ذخیره تغییرات بخش OTP:</span>
                <button type="submit" name="hub_save_otp" class="hub-btn hub-btn-success hub-btn-lg"><span class="dashicons dashicons-saved"></span> ذخیره تنظیمات ورود</button>
            </div>
        </div>
        <?php
    }

	public function save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) return;
		if ( ! isset( $_POST['hub_nonce'] ) || ! wp_verify_nonce( $_POST['hub_nonce'], 'hub_save_nonce' ) ) return;

		if ( isset( $_POST['hub_save_rules'] ) ) {
			$clean_rules = array();
			if ( ! empty( $_POST['rules'] ) && is_array( $_POST['rules'] ) ) {
				foreach ( $_POST['rules'] as $rule ) {
					$clean_actions = array();
					if ( isset( $rule['actions'] ) && is_array( $rule['actions'] ) ) {
						foreach ( $rule['actions'] as $act ) {
							$clean_actions[] = array(
								'id'            => sanitize_text_field( $act['id'] ),
                                'name'          => isset($act['name']) ? sanitize_text_field( $act['name'] ) : 'اقدام',
								'type'          => isset($act['type']) ? sanitize_text_field( $act['type'] ) : 'sms',
								'connection_id' => isset($act['connection_id']) ? sanitize_text_field( $act['connection_id'] ) : '',
								'target_mode'   => isset($act['target_mode']) ? sanitize_text_field( $act['target_mode'] ) : 'customer',
								'target_value'  => isset($act['target_value']) ? sanitize_text_field( $act['target_value'] ) : '',
								'message'       => isset($act['message']) ? wp_kses_post( $act['message'] ) : '',
                                'delay'         => array(
									'enabled' => !empty($act['delay']['enabled']),
									'value'   => isset($act['delay']['value']) ? intval( $act['delay']['value'] ) : 0,
									'unit'    => isset($act['delay']['unit']) ? sanitize_text_field( $act['delay']['unit'] ) : 'minutes',
								),
							);
						}
					}
					$clean_rules[] = array(
						'name'            => isset($rule['name']) ? sanitize_text_field( $rule['name'] ) : 'بدون نام',
						'trigger'         => sanitize_text_field( $rule['trigger'] ),
						'sub_trigger'     => isset($rule['sub_trigger_order']) ? sanitize_text_field( $rule['sub_trigger_order'] ) : '',
						'condition_logic' => isset($rule['condition_logic']) ? sanitize_text_field( $rule['condition_logic'] ) : 'AND',
						'conditions'      => isset($rule['conditions']) ? $rule['conditions'] : array(),
						'actions'         => $clean_actions
					);
				}
			}
			update_option( 'hub_rules', $clean_rules );
			echo '<div class="notice notice-success is-dismissible"><p>✅ سناریوهای اتوماسیون ذخیره شدند.</p></div>';
		}

		if ( isset( $_POST['hub_save_webhooks'] ) ) {
			$clean_webhooks = array();
			if ( ! empty( $_POST['webhooks'] ) && is_array( $_POST['webhooks'] ) ) {
				foreach ( $_POST['webhooks'] as $wh_key => $wh ) {
					if ( empty( $wh['name'] ) ) continue;
                    $key = (strpos($wh_key, 'wh_') === 0) ? sanitize_text_field($wh_key) : uniqid('wh_');
					$clean_webhooks[ $key ] = array(
						'name'            => sanitize_text_field( $wh['name'] ),
						'type'            => sanitize_text_field( $wh['type'] ),
						'url'             => esc_url_raw($wh['url']),
						'username'        => sanitize_text_field( $wh['username'] ),
						'password'        => sanitize_text_field( $wh['password'] ),
						'from_number'     => sanitize_text_field( $wh['from_number'] ),
						'token'           => sanitize_text_field( $wh['token'] ),
						'chat_id'         => sanitize_text_field( $wh['chat_id'] ),
					);
				}
			}
			update_option( 'hub_webhooks', $clean_webhooks );
			echo '<div class="notice notice-success is-dismissible"><p>✅ کانال‌ها با موفقیت ذخیره شدند.</p></div>';
		}

        if ( isset( $_POST['hub_save_otp'] ) && isset($_POST['auth_settings']) ) {
            $settings = array(
                'active'        => isset($_POST['auth_settings']['active']),
                'unified_login' => isset($_POST['auth_settings']['unified_login']),
                'redirect_url'  => esc_url_raw($_POST['auth_settings']['redirect_url']),
                'connection_id' => sanitize_text_field($_POST['auth_settings']['connection_id'])
            );
            update_option( 'hub_auth_settings', $settings );
            echo '<div class="notice notice-success is-dismissible"><p>✅ تنظیمات OTP ذخیره شد.</p></div>';
        }
	}

    public function ajax_search_orders() {
        check_ajax_referer( 'hub_admin_ajax', 'nonce' );
        if ( ! function_exists('wc_get_orders') ) wp_send_json_error();

        $orders = wc_get_orders( array('limit' => 10, 'orderby' => 'date', 'order' => 'DESC') );
        $html = '';
        if ( empty($orders) ) {
            $html = '<p style="text-align:center;color:#64748b;margin:20px 0;">هیچ سفارشی یافت نشد.</p>';
        } else {
            $html .= '<ul class="hub-order-list">';
            foreach ( $orders as $order ) {
                $html .= '<li class="hub-order-item"><div>';
                $html .= '<strong>سفارش #' . $order->get_id() . '</strong><span>' . wc_get_order_status_name($order->get_status()) . '</span>';
                $html .= '</div><button class="hub-btn hub-btn-outline btn-execute-test" data-order-id="' . $order->get_id() . '">ارسال</button></li>';
            }
            $html .= '</ul>';
        }
        wp_send_json_success( $html );
    }

    public function ajax_test_action() {
        check_ajax_referer( 'hub_admin_ajax', 'nonce' );
        $order_id = intval($_POST['order_id']);
        $action_data = $_POST['action_data'];
        $order = wc_get_order( $order_id );

        $bridge = new Hub_Bridge();
        $message = $bridge->parse_shortcodes( stripslashes($action_data['message']), $order, 'order' );

        $dispatch_args = array(
            'connection_id' => sanitize_text_field($action_data['connection_id']),
            'target_mode'   => sanitize_text_field($action_data['target_mode']),
            'target_value'  => sanitize_text_field($action_data['target_value']),
            'message'       => $message,
            'entity'        => $order,
            'entity_type'   => 'order'
        );

        $result = Hub_Sender::dispatch( sanitize_text_field($action_data['type']), $dispatch_args );
        if ( is_array($result) && !$result['success'] ) wp_send_json_error( $result['msg'] );
        wp_send_json_success( 'ارسال با موفقیت شبیه‌سازی شد.' );
    }
}