<?php
/**
 * Plugin Name: Jetpack Connection Checker
 * Plugin URI: https://github.com/fujifika/jetpack-connection-checker
 * Description: A simple plugin that helps you verify if Jetpack is correctly connected to WordPress.com and identify common connection issues.
 * Version: 1.0.6
 * Author: fujifika
 * Author URI: https://fujifika.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: jetpack-connection-checker
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'JCC_VERSION', '1.0.6' );
define( 'JCC_PLUGIN_FILE', __FILE__ );
define( 'JCC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'JCC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main plugin class
 */
class Jetpack_Connection_Checker {
    
    /**
     * Plugin instance
     */
    private static $instance = null;
    
    /**
     * Get plugin instance
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
        add_action( 'plugins_loaded', array( $this, 'init' ) );
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        error_log( 'JCC: Plugin initializing' );
        
        // Load text domain
        load_plugin_textdomain( 'jetpack-connection-checker', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
        
        // Initialize admin interface
        if ( is_admin() ) {
            error_log( 'JCC: Loading admin interface' );
            $this->load_admin();
        }
    }
    
    /**
     * Load admin functionality
     */
    private function load_admin() {
        error_log( 'JCC: Loading admin class from: ' . JCC_PLUGIN_DIR . 'includes/class-admin.php' );
        require_once JCC_PLUGIN_DIR . 'includes/class-admin.php';
        error_log( 'JCC: Creating admin instance' );
        JCC_Admin::get_instance();
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Set default options if needed
        add_option( 'jcc_version', JCC_VERSION );
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clean up temporary data
        delete_transient( 'jcc_diagnostics_cache' );
    }
}

// Initialize plugin
Jetpack_Connection_Checker::get_instance();