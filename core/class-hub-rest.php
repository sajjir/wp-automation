<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registers REST API endpoints for incoming n8n commands and others.
 */
class Hub_REST {
    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        register_rest_route( 'automation-hub/v1', '/event', array(
            'methods'  => 'POST',
            'callback' => array( $this, 'handle_event' ),
            'permission_callback' => '__return_true', // Add proper permission checks!
        ) );
    }

    public function handle_event( $request ) {
        $payload = $request->get_json_params();
        // Push to queue or process immediately
        $queue = new Hub_Queue();
        $queue->push( $payload );

        return rest_ensure_response( array( 'status' => 'queued' ) );
    }
}
