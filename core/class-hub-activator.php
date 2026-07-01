<?php

/**
 * Fired during plugin activation.
 * This class defines all code necessary to run during the plugin's activation.
 */
class Hub_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		self::create_tables();
		
		// ذخیره نسخه دیتابیس برای مدیریت آپدیت‌های آینده
		add_option( 'hub_db_version', HUB_DB_VERSION );
	}

	/**
	 * Create database tables for Queue and Logs
	 */
	private static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// 1. جدول صف (Queue)
		// اینجا رویدادها ذخیره می‌شوند تا یکی‌یکی ارسال شوند.
		$table_queue = $wpdb->prefix . 'hub_queue';
		
		$sql_queue = "CREATE TABLE $table_queue (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			event_type varchar(100) NOT NULL,    -- نوع رویداد: order.created, user.register
			payload longtext NOT NULL,           -- دیتای جیسون کامل
			status varchar(20) DEFAULT 'pending', -- وضعیت: pending, processing, completed, failed
			attempts int(3) DEFAULT 0,           -- تعداد تلاش برای ارسال
			priority int(3) DEFAULT 10,          -- اولویت (برای آینده)
			created_at datetime DEFAULT '0000-00-00 00:00:00',
			updated_at datetime DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY status (status)                  -- ایندکس برای سرعت بالای جستجو
		) $charset_collate;";

		// 2. جدول لاگ‌ها (Logs)
		// برای اینکه wp_options سنگین نشود، لاگ‌ها اینجا می‌آیند.
		$table_logs = $wpdb->prefix . 'hub_logs';

		$sql_logs = "CREATE TABLE $table_logs (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			log_type varchar(20) DEFAULT 'info', -- info, error, warning, success
			source varchar(50) NOT NULL,         -- منبع: n8n_bridge, queue_worker, security
			message text NOT NULL,               -- پیام کوتاه
			context longtext NULL,               -- دیتای فنی و جیسون مربوطه
			created_at datetime DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY log_type (log_type)
		) $charset_collate;";

		// استفاده از dbDelta برای ساخت استاندارد جداول
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql_queue );
		dbDelta( $sql_logs );
	}
}