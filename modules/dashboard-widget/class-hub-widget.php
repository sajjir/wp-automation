<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Hub_Widget {
    public function __construct() {
        add_action( 'wp_dashboard_setup', array( $this, 'register_widget' ) );
    }

    public function register_widget() {
        if ( current_user_can( 'manage_options' ) ) {
            wp_add_dashboard_widget( 'hub_summary_widget', '🚀 وضعیت هاب اتوماسیون', array( $this, 'render' ) );
        }
    }

    public function render() {
        global $wpdb;
        $t_queue = $wpdb->prefix . 'hub_queue';
        
        // آمار صف
        $pending = $wpdb->get_var( "SELECT COUNT(*) FROM $t_queue WHERE status = 'pending'" );
        $failed = $wpdb->get_var( "SELECT COUNT(*) FROM $t_queue WHERE status = 'failed'" );
        $completed_today = $wpdb->get_var( "SELECT COUNT(*) FROM $t_queue WHERE status = 'completed' AND created_at >= CURDATE()" );

        ?>
        <div class="hub-widget-content" style="display: flex; justify-content: space-around; text-align: center;">
            <div>
                <span style="font-size: 2em; color: #ffba00; display: block;"><?php echo $pending; ?></span>
                <small>در صف</small>
            </div>
            <div>
                <span style="font-size: 2em; color: #46b450; display: block;"><?php echo $completed_today; ?></span>
                <small>ارسال امروز</small>
            </div>
            <div>
                <span style="font-size: 2em; color: #dc3232; display: block;"><?php echo $failed; ?></span>
                <small>خطا</small>
            </div>
        </div>
        <hr>
        <p style="text-align: left; margin-bottom: 0;">
            <a href="<?php echo admin_url('admin.php?page=automation-hub&tab=logs'); ?>" class="button button-small">مشاهده گزارش کامل</a>
        </p>
        <?php
    }
}