<?php
/**
 * Admin functionality for Jetpack Connection Checker
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class JCC_Admin {
    
    /**
     * Class instance
     */
    private static $instance = null;
    
    /**
     * Get class instance
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        error_log( 'JCC: Admin class constructor called' );
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        add_action( 'wp_ajax_jcc_run_diagnostics', array( $this, 'ajax_run_diagnostics' ) );
        error_log( 'JCC: Admin hooks registered' );
    }
    
    /**
     * Add admin menu page
     */
    public function add_admin_menu() {
        $hook = add_management_page(
            __( 'Jetpack Connection Checker', 'jetpack-connection-checker' ),
            __( 'Jetpack Connection', 'jetpack-connection-checker' ),
            'manage_options',
            'jetpack-connection-checker',
            array( $this, 'render_admin_page' )
        );
        
        error_log( 'JCC: Admin menu hook registered as: ' . $hook );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts( $hook ) {
        // Debug: Log the current page hook
        error_log( 'JCC: Current admin page hook: ' . $hook );
        
        // Only load on our admin page
        if ( 'tools_page_jetpack-connection-checker' !== $hook ) {
            error_log( 'JCC: Script not loaded - hook mismatch. Expected: tools_page_jetpack-connection-checker, Got: ' . $hook );
            // Temporarily disable this check to debug
            // return;
        }
        
        error_log( 'JCC: Enqueuing admin scripts' );
        
        // Enqueue clipboard.js for copy functionality
        wp_enqueue_script(
            'jcc-clipboard',
            'https://cdn.jsdelivr.net/npm/clipboard@2/dist/clipboard.min.js',
            array(),
            '2.0.11',
            true
        );
        
        // Enqueue our admin script
        $script_url = JCC_PLUGIN_URL . 'assets/js/admin.js';
        error_log( 'JCC: Script URL: ' . $script_url );
        
        wp_enqueue_script(
            'jcc-admin',
            $script_url,
            array( 'jquery' ),
            JCC_VERSION,
            true
        );
        
        error_log( 'JCC: Admin script enqueued' );
        
        // Localize script for AJAX
        $ajax_data = array(
            'url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'jcc_diagnostics_nonce' ),
            'strings' => array(
                'running' => __( 'Checking connection...', 'jetpack-connection-checker' ),
                'copied' => __( 'Copied to clipboard!', 'jetpack-connection-checker' ),
                'copy_failed' => __( 'Failed to copy. Please select and copy manually.', 'jetpack-connection-checker' ),
                'error' => __( 'Error checking connection. Please try again.', 'jetpack-connection-checker' ),
                'show_details' => __( 'Show Advanced Details', 'jetpack-connection-checker' ),
                'hide_details' => __( 'Hide Advanced Details', 'jetpack-connection-checker' ),
            )
        );
        
        error_log( 'JCC: Localizing script with data: ' . json_encode( $ajax_data ) );
        
        wp_localize_script( 'jcc-admin', 'jcc_ajax', $ajax_data );
        
        // Enqueue admin styles
        wp_enqueue_style(
            'jcc-admin',
            JCC_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            JCC_VERSION
        );
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        // Check user permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'jetpack-connection-checker' ) );
        }
        
        ?>
        <div class="wrap">
            <h1><?php _e( 'Check Your Jetpack Connection', 'jetpack-connection-checker' ); ?></h1>
            
            <div class="jcc-intro">
                <p><?php _e( 'This tool helps you verify if Jetpack is correctly connected to WordPress.com and identify common connection issues.', 'jetpack-connection-checker' ); ?></p>
            </div>
            
            <div class="jcc-actions">
                <button type="button" id="jcc-run-diagnostics" class="button button-primary button-large">
                    <?php _e( 'Run Connection Check', 'jetpack-connection-checker' ); ?>
                </button>
            </div>
            
            <div id="jcc-loading" class="jcc-loading" style="display: none;">
                <div class="jcc-loading-spinner"></div>
                <p><?php _e( 'Checking connection...', 'jetpack-connection-checker' ); ?></p>
            </div>
            
            <!-- Status Summary -->
            <div id="jcc-status-summary" class="jcc-status-summary" style="display: none;">
                <div class="jcc-status-header">
                    <h2 id="jcc-status-message" class="jcc-status-message"></h2>
                    <p id="jcc-status-description" class="jcc-status-description"></p>
                    
                    <!-- Notices Accordion (inside status) -->
                    <div id="jcc-notices-accordion" class="jcc-notices-accordion" style="display: none;">
                        <button type="button" id="jcc-toggle-notices" class="jcc-toggle-notices">
                            <span class="dashicons dashicons-arrow-down-alt2"></span>
                            <span id="jcc-notices-count"><?php _e( 'Show details', 'jetpack-connection-checker' ); ?></span>
                        </button>
                        <div id="jcc-notices-content" class="jcc-notices-content" style="display: none;">
                            <p id="jcc-notices-intro" class="jcc-notices-intro"></p>
                            <div id="jcc-notices-list"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Details Section (Issues & Failures) -->
            <div id="jcc-details" class="jcc-details" style="display: none;">
                <div class="jcc-details-header">
                    <h3><?php _e( 'Details', 'jetpack-connection-checker' ); ?></h3>
                </div>
                <div id="jcc-details-content" class="jcc-details-content"></div>
            </div>
            
            <!-- Action Buttons -->
            <div id="jcc-action-buttons" class="jcc-action-buttons" style="display: none;">
                <button type="button" id="jcc-copy-results" class="button button-secondary">
                    <span class="dashicons dashicons-clipboard"></span>
                    <?php _e( 'Copy Report', 'jetpack-connection-checker' ); ?>
                </button>
                <button type="button" id="jcc-download-txt" class="button button-secondary">
                    <span class="dashicons dashicons-download"></span>
                    <?php _e( 'Download Report (.txt)', 'jetpack-connection-checker' ); ?>
                </button>
                <button type="button" id="jcc-download-json" class="button button-secondary">
                    <span class="dashicons dashicons-media-code"></span>
                    <?php _e( 'Save as JSON', 'jetpack-connection-checker' ); ?>
                </button>
            </div>
            
            <!-- User Guidance -->
            <div id="jcc-guidance" class="jcc-guidance" style="display: none;">
                <p><?php _e( 'You can copy or download this report and share it with Jetpack support if needed.', 'jetpack-connection-checker' ); ?></p>
            </div>
            
            <!-- Advanced Details Toggle -->
            <div id="jcc-advanced-toggle" class="jcc-advanced-toggle" style="display: none;">
                <div class="jcc-toggle-actions">
                    <button type="button" id="jcc-toggle-advanced" class="button">
                        <span class="dashicons dashicons-arrow-down-alt2"></span>
                        <?php _e( 'Advanced Details (for support or developers)', 'jetpack-connection-checker' ); ?>
                    </button>
                </div>
            </div>
            
            <!-- Advanced Details -->
            <div id="jcc-advanced-details" class="jcc-advanced-details" style="display: none;">
                <div class="jcc-advanced-header">
                    <h3><?php _e( 'Technical Information', 'jetpack-connection-checker' ); ?></h3>
                    <p><?php _e( 'This section contains detailed technical information about your Jetpack connection, API tests, and server environment.', 'jetpack-connection-checker' ); ?></p>
                </div>
                <div id="jcc-advanced-content" class="jcc-advanced-content"></div>
            </div>
            
            <!-- Version Footer -->
            <div class="jcc-version-footer">
                <p><?php printf( __( 'Jetpack Connection Checker v%s – Created by fujifika', 'jetpack-connection-checker' ), JCC_VERSION ); ?></p>
            </div>
        </div>
        <?php
    }
    
    /**
     * AJAX handler for running diagnostics
     */
    public function ajax_run_diagnostics() {
        error_log('JCC: AJAX handler called');
        
        // Check if nonce exists
        if ( ! isset( $_POST['nonce'] ) ) {
            error_log('JCC: No nonce provided');
            wp_send_json_error( 'No nonce provided' );
            return;
        }
        
        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['nonce'], 'jcc_diagnostics_nonce' ) ) {
            error_log('JCC: Nonce verification failed');
            wp_send_json_error( 'Security check failed' );
            return;
        }
        
        // Check permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            error_log('JCC: Insufficient permissions');
            wp_send_json_error( 'Insufficient permissions' );
            return;
        }
        
        error_log('JCC: Loading diagnostics class');
        
        // Load diagnostics class
        require_once JCC_PLUGIN_DIR . 'includes/class-diagnostics.php';
        $diagnostics = new JCC_Diagnostics();
        
        // Run diagnostics
        error_log('JCC: Running diagnostics');
        $results = $diagnostics->run_all_diagnostics();
        
        error_log('JCC: Diagnostics completed, sending response');
        
        // Return results
        wp_send_json_success( $results );
    }
}