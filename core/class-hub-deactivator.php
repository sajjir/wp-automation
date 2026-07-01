<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles plugin deactivation cleanup.
 */
class Hub_Deactivator {
    public static function deactivate() {
        // For safety, we don't drop tables automatically.
        // You can optionally clean up transient data here.
    }
}
