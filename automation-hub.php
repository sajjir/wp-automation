<?php
/**
 * Plugin Name: Automation Hub (n8n Bridge)
 * Description: پل ارتباطی هوشمند و امن بین ووکامرس و n8n با سیستم صف و لاگ اختصاصی.
 * Version: 1.0.0
 * Author: sajj.ir | هوش مرکزی
 * Text Domain: automation-hub
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// تعریف ثابت‌های مسیر و نسخه
define( 'HUB_VERSION', '1.0.0' );
define( 'HUB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'HUB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'HUB_DB_VERSION', '1.0' );

/**
 * 1. فعال‌سازی و غیرفعال‌سازی
 */
function hub_activate_plugin() {
    require_once HUB_PLUGIN_DIR . 'core/class-hub-activator.php';
    Hub_Activator::activate();
}
register_activation_hook( __FILE__, 'hub_activate_plugin' );

function hub_deactivate_plugin() {
    if ( function_exists( 'as_unschedule_action' ) ) {
        as_unschedule_action( 'hub_process_queue_event' );
    }
}
register_deactivation_hook( __FILE__, 'hub_deactivate_plugin' );

/**
 * 2. بارگذاری کلاس‌ها (فقط اینکلود کردن، بدون اجرا)
 */
function hub_load_classes() {
    // هسته (Core)
    require_once HUB_PLUGIN_DIR . 'modules/logger/class-hub-logger.php';
    require_once HUB_PLUGIN_DIR . 'core/class-hub-security.php';
    require_once HUB_PLUGIN_DIR . 'core/class-hub-queue.php';
    require_once HUB_PLUGIN_DIR . 'core/class-hub-bridge.php';
    require_once HUB_PLUGIN_DIR . 'core/class-hub-auth.php'; // کلاس احراز هویت
    require_once HUB_PLUGIN_DIR . 'core/class-hub-sender.php';
    
    // رابط کاربری و ویجت‌ها (اصلاح شد: با شرط بررسی وجود فایل جهت جلوگیری از خطای بحرانی)
    if ( file_exists( HUB_PLUGIN_DIR . 'integrations/class-persian-wc.php' ) ) {
        require_once HUB_PLUGIN_DIR . 'integrations/class-persian-wc.php';
    }
    
    require_once HUB_PLUGIN_DIR . 'admin/class-hub-admin.php';
    
    if ( file_exists( HUB_PLUGIN_DIR . 'modules/dashboard-widget/class-hub-widget.php' ) ) {
        require_once HUB_PLUGIN_DIR . 'modules/dashboard-widget/class-hub-widget.php';
    }
}
add_action( 'plugins_loaded', 'hub_load_classes' );

/**
 * 3. راه‌اندازی منطق (روی هوک init برای اطمینان از لود شدن وردپرس)
 */
function hub_init_plugin() {
    // الف) لود کردن زبان (رفع خطای _load_textdomain_just_in_time)
    load_plugin_textdomain( 'automation-hub', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

    // ب) شروع به کار ماژول‌ها
    if ( class_exists( 'Hub_Bridge' ) ) Hub_Bridge::init(); // گوش دادن به رویدادها
    if ( class_exists( 'Hub_Admin' ) ) Hub_Admin::init();   // ساخت منوی ادمین
    if ( class_exists( 'Hub_Auth' ) ) Hub_Auth::init();     // سیستم لاگین (حیاتی برای شورت‌کد)
    
    // ج) ویجت داشبورد
    if ( class_exists( 'Hub_Widget' ) ) {
        new Hub_Widget();
    }

    // د) راه‌اندازی صف (با اولویت پایین‌تر برای اطمینان از اکشن اسکجولر)
    if ( class_exists( 'Hub_Sender' ) && class_exists( 'ActionScheduler' ) ) {
        Hub_Sender::init();
    }
}
add_action( 'init', 'hub_init_plugin', 20 ); // اولویت ۲۰ حیاتی است