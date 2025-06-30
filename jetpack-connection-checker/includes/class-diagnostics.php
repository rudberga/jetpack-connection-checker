<?php
/**
 * Diagnostics functionality for Jetpack Connection Checker
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class JCC_Diagnostics {
    
    /**
     * Run all diagnostics and return formatted results with status summary
     */
    public function run_all_diagnostics() {
        // Get Jetpack status for summary
        $jetpack_status = $this->get_jetpack_status_summary();
        
        // Generate detailed report
        $detailed_report = $this->generate_detailed_report();
        
        // Generate structured data for JSON export
        $structured_data = $this->generate_structured_data();
        
        return array(
            'status' => $jetpack_status,
            'detailed_report' => $detailed_report,
            'structured_data' => $structured_data
        );
    }
    
    /**
     * Get status summary using three-tier severity classification
     */
    private function get_jetpack_status_summary() {
        $diagnostics = $this->get_categorized_diagnostics();
        $failures = $diagnostics['failures'];
        $issues = $diagnostics['issues'];
        $notices = $diagnostics['notices'];
        
        // 🔴 FAILURES: Critical connection issues (Not Working Properly)
        if ( ! empty( $failures ) ) {
            $primary_failure = $failures[0];
            $additional_count = count( $failures ) - 1;
            
            $description = $primary_failure['message'];
            if ( $additional_count > 0 ) {
                $description .= ' ' . sprintf( 
                    _n( 
                        '(%d additional critical issue)', 
                        '(%d additional critical issues)', 
                        $additional_count, 
                        'jetpack-connection-checker' 
                    ), 
                    $additional_count 
                );
            }
            
            // Determine if it's actually not connected or just broken
            $connection_status = $this->get_jetpack_connection_status();
            $message = $connection_status['connected'] 
                ? __( 'Jetpack connection has critical issues', 'jetpack-connection-checker' )
                : __( 'Jetpack is not connected', 'jetpack-connection-checker' );
            
            return array(
                'level' => 'failure',
                'message' => $message,
                'description' => $description,
                'failures' => $failures,
                'issues' => $issues,
                'notices' => $notices
            );
        }
        
        // 🟡 ISSUES: Degraded but connected (Partially Connected)
        if ( ! empty( $issues ) ) {
            $primary_issue = $issues[0];
            $additional_count = count( $issues ) - 1;
            
            $description = $primary_issue['message'];
            if ( $additional_count > 0 ) {
                $description .= ' ' . sprintf( 
                    _n( 
                        '(%d additional issue)', 
                        '(%d additional issues)', 
                        $additional_count, 
                        'jetpack-connection-checker' 
                    ), 
                    $additional_count 
                );
            }
            
            return array(
                'level' => 'issue',
                'message' => __( 'Jetpack is active, but not fully connected', 'jetpack-connection-checker' ),
                'description' => $description,
                'failures' => $failures,
                'issues' => $issues,
                'notices' => $notices
            );
        }
        
        // 🟢 SUCCESS: Connected (with optional notices)
        $notices_count = count( $notices );
        if ( $notices_count > 0 ) {
            $notices_text = sprintf( 
                _n( 
                    '(%d thing to note)', 
                    '(%d things to note)', 
                    $notices_count, 
                    'jetpack-connection-checker' 
                ), 
                $notices_count 
            );
            
            return array(
                'level' => 'success',
                'message' => sprintf( __( 'Jetpack is connected! %s', 'jetpack-connection-checker' ), $notices_text ),
                'description' => __( 'Your site is successfully connected to WordPress.com and working properly.', 'jetpack-connection-checker' ),
                'failures' => $failures,
                'issues' => $issues,
                'notices' => $notices
            );
        } else {
            return array(
                'level' => 'success',
                'message' => __( 'Jetpack is connected!', 'jetpack-connection-checker' ),
                'description' => __( 'Your site is successfully connected to WordPress.com and all systems are working properly.', 'jetpack-connection-checker' ),
                'failures' => $failures,
                'issues' => $issues,
                'notices' => $notices
            );
        }
    }
    
    /**
     * Generate the detailed diagnostic report
     */
    private function generate_detailed_report() {
        $results = array();
        
        // Add timestamp
        $results[] = '=== JETPACK CONNECTION CHECKER DIAGNOSTIC REPORT ===';
        $results[] = 'Generated: ' . current_time( 'Y-m-d H:i:s T' );
        $results[] = 'Site URL: ' . home_url();
        $results[] = '';
        
        // Run Jetpack diagnostics
        $results[] = '=== JETPACK CONNECTION STATUS ===';
        $jetpack_results = $this->check_jetpack_status();
        $results = array_merge( $results, $jetpack_results );
        
        // Run API diagnostics
        $results[] = '=== API AVAILABILITY TESTS ===';
        $api_results = $this->check_api_availability();
        $results = array_merge( $results, $api_results );
        
        // Run environment diagnostics
        $results[] = '=== ENVIRONMENT INFO ===';
        $env_results = $this->collect_environment_info();
        $results = array_merge( $results, $env_results );
        
        // Return formatted results
        return implode( "\n", $results );
    }
    
    /**
     * Check if there are any connection issues that might affect functionality
     */
    private function has_connection_issues() {
        $diagnostics = $this->get_categorized_diagnostics();
        return ! empty( $diagnostics['failures'] ) || ! empty( $diagnostics['issues'] );
    }
    
    /**
     * Get categorized diagnostic results using three-tier severity system
     */
    private function get_categorized_diagnostics() {
        $failures = array();
        $issues = array();
        $notices = array();
        
        // Check if Jetpack is installed and active
        if ( ! $this->is_jetpack_installed() ) {
            $failures[] = array(
                'type' => 'not_installed',
                'message' => __( 'Jetpack plugin is not installed', 'jetpack-connection-checker' )
            );
            return array( 'failures' => $failures, 'issues' => $issues, 'notices' => $notices );
        }
        
        if ( ! $this->is_jetpack_active() ) {
            $failures[] = array(
                'type' => 'not_active',
                'message' => __( 'Jetpack plugin is not activated', 'jetpack-connection-checker' )
            );
            return array( 'failures' => $failures, 'issues' => $issues, 'notices' => $notices );
        }
        
        // Check connection status
        $connection_status = $this->get_jetpack_connection_status();
        if ( ! $connection_status['connected'] ) {
            $failures[] = array(
                'type' => 'not_connected',
                'message' => __( 'Site is not connected to WordPress.com', 'jetpack-connection-checker' )
            );
            return array( 'failures' => $failures, 'issues' => $issues, 'notices' => $notices );
        }
        
        // Site is connected - check for degraded functionality (Issues)
        
        // Check tokens (Critical for core functionality)
        $token_status = $this->check_jetpack_tokens();
        if ( ! $token_status['blog_token'] ) {
            $failures[] = array(
                'type' => 'blog_token',
                'message' => __( 'Blog token is missing – site authentication failed', 'jetpack-connection-checker' )
            );
        }
        if ( ! $token_status['user_token'] ) {
            $failures[] = array(
                'type' => 'user_token', 
                'message' => __( 'User token is missing – user authentication failed', 'jetpack-connection-checker' )
            );
        }
        
        // Check API availability (Critical for communication)
        $rest_results = $this->test_rest_api();
        if ( $rest_results['status'] === 'Failed' ) {
            $failures[] = array(
                'type' => 'rest_api',
                'message' => __( 'REST API is completely unavailable', 'jetpack-connection-checker' )
            );
        } elseif ( $rest_results['status'] === 'Partial Success' ) {
            $issues[] = array(
                'type' => 'rest_api_partial',
                'message' => __( 'REST API responding but returning invalid data', 'jetpack-connection-checker' )
            );
        }
        
        $xmlrpc_results = $this->test_xmlrpc();
        if ( in_array( $xmlrpc_results['status'], array( 'Failed', 'Blocked', 'Disabled' ) ) ) {
            $failures[] = array(
                'type' => 'xmlrpc',
                'message' => __( 'XML-RPC is disabled or blocked – many Jetpack features will not work', 'jetpack-connection-checker' )
            );
        } elseif ( $xmlrpc_results['status'] === 'Available but Error' ) {
            $issues[] = array(
                'type' => 'xmlrpc_error',
                'message' => __( 'XML-RPC responding but with errors', 'jetpack-connection-checker' )
            );
        }
        
        // Check connection details (Issues if missing)
        $connection_details = $this->get_jetpack_connection_details();
        if ( empty( $connection_details['blog_id'] ) ) {
            $issues[] = array(
                'type' => 'missing_blog_id',
                'message' => __( 'Blog ID is missing from connection data', 'jetpack-connection-checker' )
            );
        }
        if ( empty( $connection_details['master_user'] ) ) {
            $issues[] = array(
                'type' => 'missing_master_user',
                'message' => __( 'Master user is not detected', 'jetpack-connection-checker' )
            );
        }
        
        // Check connection health (Issues if failing)
        $health_checks = $this->check_jetpack_connection_health();
        foreach ( $health_checks as $health_check ) {
            if ( strpos( $health_check, 'Failed' ) !== false ) {
                $issues[] = array(
                    'type' => 'connection_test',
                    'message' => __( 'Connection health test failed', 'jetpack-connection-checker' )
                );
                break;
            }
        }
        
        // Environment warnings (Notices)
        $environment_issues = $this->check_connection_issues();
        foreach ( $environment_issues as $env_issue ) {
            if ( strpos( $env_issue, 'Warning:' ) === 0 ) {
                $notices[] = array(
                    'type' => 'environment',
                    'message' => str_replace( 'Warning: ', '', $env_issue )
                );
            } elseif ( strpos( $env_issue, 'Notice:' ) === 0 ) {
                $notices[] = array(
                    'type' => 'notice',
                    'message' => str_replace( 'Notice: ', '', $env_issue )
                );
            }
        }
        
        // Check for staging/development mode (Notices)
        if ( method_exists( 'Jetpack', 'is_staging_site' ) && Jetpack::is_staging_site() ) {
            $notices[] = array(
                'type' => 'staging_site',
                'message' => __( 'Site is detected as a staging environment', 'jetpack-connection-checker' )
            );
        }
        
        if ( method_exists( 'Jetpack', 'is_development_mode' ) && Jetpack::is_development_mode() ) {
            $notices[] = array(
                'type' => 'development_mode',
                'message' => __( 'Jetpack is running in development mode', 'jetpack-connection-checker' )
            );
        }
        
        // Additional common notice scenarios
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            $notices[] = array(
                'type' => 'debug_mode',
                'message' => __( 'WordPress debug mode is enabled', 'jetpack-connection-checker' )
            );
        }
        
        // Check Jetpack version for outdated versions (example)
        $jetpack_version = $this->get_jetpack_version();
        if ( $jetpack_version && version_compare( $jetpack_version, '11.0', '<' ) ) {
            $notices[] = array(
                'type' => 'old_version',
                'message' => sprintf( __( 'Jetpack version %s may be outdated - consider updating', 'jetpack-connection-checker' ), $jetpack_version )
            );
        }
        
        // Check for conflicting plugins
        $plugin_conflicts = $this->check_plugin_conflicts();
        foreach ( $plugin_conflicts as $conflict ) {
            if ( $conflict['severity'] === 'critical' ) {
                $failures[] = array(
                    'type' => 'plugin_conflict',
                    'message' => $conflict['message']
                );
            } elseif ( $conflict['severity'] === 'warning' ) {
                $issues[] = array(
                    'type' => 'plugin_conflict',
                    'message' => $conflict['message']
                );
            } else {
                $notices[] = array(
                    'type' => 'plugin_conflict',
                    'message' => $conflict['message']
                );
            }
        }
        
        return array( 
            'failures' => $failures, 
            'issues' => $issues, 
            'notices' => $notices 
        );
    }
    
    /**
     * Check Jetpack installation and connection status
     */
    private function check_jetpack_status() {
        $results = array();
        
        // Check if Jetpack is installed
        $jetpack_installed = $this->is_jetpack_installed();
        $results[] = 'Jetpack Installed: ' . ( $jetpack_installed ? 'Yes' : 'No' );
        
        if ( ! $jetpack_installed ) {
            $results[] = 'Status: Jetpack plugin not found';
            $results[] = '';
            return $results;
        }
        
        // Check if Jetpack is active
        $jetpack_active = $this->is_jetpack_active();
        $results[] = 'Jetpack Active: ' . ( $jetpack_active ? 'Yes' : 'No' );
        
        if ( ! $jetpack_active ) {
            $results[] = 'Status: Jetpack plugin is installed but not activated';
            $results[] = '';
            return $results;
        }
        
        // Get Jetpack version
        $jetpack_version = $this->get_jetpack_version();
        if ( $jetpack_version ) {
            $results[] = 'Jetpack Version: ' . $jetpack_version;
        }
        
        // Check connection status
        $connection_status = $this->get_jetpack_connection_status();
        $results[] = 'Connection Status: ' . $connection_status['status'];
        
        if ( $connection_status['connected'] ) {
            // Site is connected - get detailed info
            $connection_details = $this->get_jetpack_connection_details();
            
            if ( ! empty( $connection_details['blog_id'] ) ) {
                $results[] = 'Blog ID: ' . $connection_details['blog_id'];
            }
            
            if ( ! empty( $connection_details['master_user'] ) ) {
                $results[] = 'Master User ID: ' . $connection_details['master_user'];
            }
            
            // Check tokens
            $token_status = $this->check_jetpack_tokens();
            $results[] = 'Blog Token: ' . ( $token_status['blog_token'] ? 'Present' : 'Missing' );
            $results[] = 'User Token: ' . ( $token_status['user_token'] ? 'Present' : 'Missing' );
            
            // Additional connection health checks
            $health_checks = $this->check_jetpack_connection_health();
            if ( ! empty( $health_checks ) ) {
                $results[] = '';
                $results[] = '--- Connection Health ---';
                $results = array_merge( $results, $health_checks );
            }
            
        } else {
            // Site is not connected
            $results[] = 'Blog Token: N/A (not connected)';
            $results[] = 'User Token: N/A (not connected)';
            
            // Check for common connection issues
            $connection_issues = $this->check_connection_issues();
            if ( ! empty( $connection_issues ) ) {
                $results[] = '';
                $results[] = '--- Potential Connection Issues ---';
                $results = array_merge( $results, $connection_issues );
            }
        }
        
        $results[] = '';
        return $results;
    }
    
    /**
     * Check if Jetpack plugin is installed
     */
    private function is_jetpack_installed() {
        return file_exists( WP_PLUGIN_DIR . '/jetpack/jetpack.php' );
    }
    
    /**
     * Check if Jetpack plugin is active
     */
    private function is_jetpack_active() {
        return is_plugin_active( 'jetpack/jetpack.php' );
    }
    
    /**
     * Get Jetpack version
     */
    private function get_jetpack_version() {
        if ( ! function_exists( 'get_plugin_data' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        $plugin_file = WP_PLUGIN_DIR . '/jetpack/jetpack.php';
        if ( file_exists( $plugin_file ) ) {
            $plugin_data = get_plugin_data( $plugin_file );
            return $plugin_data['Version'];
        }
        
        return false;
    }
    
    /**
     * Get Jetpack connection status
     */
    private function get_jetpack_connection_status() {
        if ( ! class_exists( 'Jetpack' ) ) {
            return array(
                'connected' => false,
                'status' => 'Jetpack class not available'
            );
        }
        
        // Check if Jetpack is connected using the proper method
        if ( method_exists( 'Jetpack', 'is_connection_ready' ) ) {
            $connected = Jetpack::is_connection_ready();
            $status = $connected ? 'Connected' : 'Not Connected';
        } elseif ( method_exists( 'Jetpack', 'is_active' ) ) {
            // Fallback for older Jetpack versions
            $connected = Jetpack::is_active();
            $status = $connected ? 'Connected (Legacy Check)' : 'Not Connected';
        } else {
            $connected = false;
            $status = 'Unable to determine connection status';
        }
        
        return array(
            'connected' => $connected,
            'status' => $status
        );
    }
    
    /**
     * Get detailed Jetpack connection information
     */
    private function get_jetpack_connection_details() {
        $details = array();
        
        if ( ! class_exists( 'Jetpack' ) ) {
            return $details;
        }
        
        // Get blog ID
        if ( method_exists( 'Jetpack_Options', 'get_option' ) ) {
            $blog_id = Jetpack_Options::get_option( 'id' );
            if ( $blog_id ) {
                $details['blog_id'] = $blog_id;
            }
        }
        
        // Get master user
        if ( method_exists( 'Jetpack_Options', 'get_option' ) ) {
            $master_user = Jetpack_Options::get_option( 'master_user' );
            if ( $master_user ) {
                $details['master_user'] = $master_user;
            }
        }
        
        return $details;
    }
    
    /**
     * Check if Jetpack tokens are present
     */
    private function check_jetpack_tokens() {
        $token_status = array(
            'blog_token' => false,
            'user_token' => false
        );
        
        if ( ! class_exists( 'Jetpack_Options' ) ) {
            return $token_status;
        }
        
        // Check blog token
        $blog_token = Jetpack_Options::get_option( 'blog_token' );
        $token_status['blog_token'] = ! empty( $blog_token );
        
        // Check user token
        $user_token = Jetpack_Options::get_option( 'user_tokens' );
        $token_status['user_token'] = ! empty( $user_token ) && is_array( $user_token ) && count( $user_token ) > 0;
        
        return $token_status;
    }
    
    /**
     * Check Jetpack connection health
     */
    private function check_jetpack_connection_health() {
        $health_checks = array();
        
        if ( ! class_exists( 'Jetpack' ) ) {
            return $health_checks;
        }
        
        // Check if we can reach WordPress.com
        if ( method_exists( 'Jetpack', 'test_connection' ) ) {
            $connection_test = Jetpack::test_connection();
            if ( is_wp_error( $connection_test ) ) {
                $health_checks[] = 'Connection Test: Failed - ' . $connection_test->get_error_message();
            } else {
                $health_checks[] = 'Connection Test: Passed';
            }
        }
        
        // Check for staging mode
        if ( method_exists( 'Jetpack', 'is_staging_site' ) ) {
            $is_staging = Jetpack::is_staging_site();
            $health_checks[] = 'Staging Site: ' . ( $is_staging ? 'Yes' : 'No' );
        }
        
        // Check for development mode
        if ( method_exists( 'Jetpack', 'is_development_mode' ) ) {
            $is_dev = Jetpack::is_development_mode();
            $health_checks[] = 'Development Mode: ' . ( $is_dev ? 'Yes' : 'No' );
        }
        
        return $health_checks;
    }
    
    /**
     * Check for common connection issues
     */
    private function check_connection_issues() {
        $issues = array();
        
        // Check if site is accessible from external
        $site_url = home_url();
        if ( strpos( $site_url, 'localhost' ) !== false || strpos( $site_url, '127.0.0.1' ) !== false ) {
            $issues[] = 'Warning: Site appears to be on localhost - this will prevent Jetpack connection';
        }
        
        // Check if site is using HTTPS
        if ( ! is_ssl() ) {
            $issues[] = 'Notice: Site is not using HTTPS - this may cause connection issues';
        }
        
        // Check for common staging/dev indicators
        $staging_indicators = array( 'staging', 'dev', 'test', 'demo' );
        foreach ( $staging_indicators as $indicator ) {
            if ( strpos( $site_url, $indicator ) !== false ) {
                $issues[] = 'Warning: Site URL contains "' . $indicator . '" - this may indicate a staging site';
                break;
            }
        }
        
        return $issues;
    }
    
    /**
     * Check API availability (REST API and XML-RPC)
     */
    private function check_api_availability() {
        $results = array();
        
        // Test REST API
        $rest_results = $this->test_rest_api();
        $results[] = 'REST API Test: ' . $rest_results['status'];
        if ( ! empty( $rest_results['details'] ) ) {
            $results[] = 'REST API Details: ' . $rest_results['details'];
        }
        
        // Test XML-RPC
        $xmlrpc_results = $this->test_xmlrpc();
        $results[] = 'XML-RPC Test: ' . $xmlrpc_results['status'];
        if ( ! empty( $xmlrpc_results['details'] ) ) {
            $results[] = 'XML-RPC Details: ' . $xmlrpc_results['details'];
        }
        
        $results[] = '';
        return $results;
    }
    
    /**
     * Test REST API availability
     */
    private function test_rest_api() {
        $rest_url = home_url( '/wp-json/wp/v2/types' );
        
        $response = wp_remote_get( $rest_url, array(
            'timeout' => 15,
            'sslverify' => false, // Allow self-signed certificates for testing
            'headers' => array(
                'Accept' => 'application/json',
            ),
        ) );
        
        if ( is_wp_error( $response ) ) {
            return array(
                'status' => 'Failed',
                'details' => 'Error: ' . $response->get_error_message()
            );
        }
        
        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        
        if ( $response_code === 200 ) {
            // Try to decode JSON to verify it's valid
            $decoded = json_decode( $response_body, true );
            if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
                return array(
                    'status' => 'Success',
                    'details' => 'HTTP 200, valid JSON response with ' . count( $decoded ) . ' post types'
                );
            } else {
                return array(
                    'status' => 'Partial Success',
                    'details' => 'HTTP 200 but invalid JSON response'
                );
            }
        } else {
            return array(
                'status' => 'Failed',
                'details' => 'HTTP ' . $response_code . ' - ' . wp_remote_retrieve_response_message( $response )
            );
        }
    }
    
    /**
     * Test XML-RPC availability
     */
    private function test_xmlrpc() {
        $xmlrpc_url = home_url( '/xmlrpc.php' );
        
        // Check if XML-RPC is disabled via filter or option
        if ( ! $this->is_xmlrpc_enabled() ) {
            return array(
                'status' => 'Disabled',
                'details' => 'XML-RPC is disabled via WordPress settings or filter'
            );
        }
        
        // Prepare XML-RPC request for demo.sayHello
        $xml_request = '<?xml version="1.0"?>
<methodCall>
<methodName>demo.sayHello</methodName>
<params>
</params>
</methodCall>';
        
        $response = wp_remote_post( $xmlrpc_url, array(
            'timeout' => 15,
            'sslverify' => false,
            'headers' => array(
                'Content-Type' => 'text/xml',
                'User-Agent' => 'Jetpack Support Companion XML-RPC Test',
            ),
            'body' => $xml_request,
        ) );
        
        if ( is_wp_error( $response ) ) {
            return array(
                'status' => 'Failed',
                'details' => 'Error: ' . $response->get_error_message()
            );
        }
        
        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        
        // Check for successful XML-RPC response
        if ( $response_code === 200 && strpos( $response_body, 'Hello!' ) !== false ) {
            return array(
                'status' => 'Success',
                'details' => 'HTTP 200, demo.sayHello returned "Hello!"'
            );
        } elseif ( $response_code === 200 && strpos( $response_body, 'methodResponse' ) !== false ) {
            // It's a valid XML-RPC response but might be an error
            if ( strpos( $response_body, 'faultCode' ) !== false ) {
                return array(
                    'status' => 'Available but Error',
                    'details' => 'HTTP 200, XML-RPC endpoint responding but demo.sayHello may not be available'
                );
            } else {
                return array(
                    'status' => 'Success',
                    'details' => 'HTTP 200, valid XML-RPC response received'
                );
            }
        } elseif ( $response_code === 405 ) {
            return array(
                'status' => 'Blocked',
                'details' => 'HTTP 405 Method Not Allowed - XML-RPC may be disabled by server'
            );
        } elseif ( strpos( $response_body, 'XML-RPC server accepts POST requests only' ) !== false ) {
            return array(
                'status' => 'Available',
                'details' => 'XML-RPC endpoint is responding (POST-only message received)'
            );
        } else {
            return array(
                'status' => 'Failed',
                'details' => 'HTTP ' . $response_code . ' - ' . wp_remote_retrieve_response_message( $response )
            );
        }
    }
    
    /**
     * Check if XML-RPC is enabled
     */
    private function is_xmlrpc_enabled() {
        // Check if XML-RPC is disabled via the use_xmlrpc filter
        $xmlrpc_enabled = apply_filters( 'xmlrpc_enabled', true );
        
        if ( ! $xmlrpc_enabled ) {
            return false;
        }
        
        // Check if xmlrpc.php file exists
        if ( ! file_exists( ABSPATH . 'xmlrpc.php' ) ) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Collect comprehensive environment information
     */
    private function collect_environment_info() {
        $results = array();
        
        // WordPress environment
        global $wp_version;
        $results[] = 'WordPress Version: ' . $wp_version;
        $results[] = 'WordPress Language: ' . get_locale();
        $results[] = 'WordPress Timezone: ' . wp_timezone_string();
        $results[] = 'WordPress Debug Mode: ' . ( defined( 'WP_DEBUG' ) && WP_DEBUG ? 'Enabled' : 'Disabled' );
        $results[] = 'WordPress Memory Limit: ' . WP_MEMORY_LIMIT;
        
        // PHP environment
        $results[] = 'PHP Version: ' . phpversion();
        $results[] = 'PHP Memory Limit: ' . ini_get( 'memory_limit' );
        $results[] = 'PHP Max Execution Time: ' . ini_get( 'max_execution_time' ) . ' seconds';
        $results[] = 'PHP Max Input Variables: ' . ini_get( 'max_input_vars' );
        $results[] = 'PHP Post Max Size: ' . ini_get( 'post_max_size' );
        $results[] = 'PHP Upload Max Filesize: ' . ini_get( 'upload_max_filesize' );
        
        // Database info
        $db_info = $this->get_database_info();
        if ( ! empty( $db_info['version'] ) ) {
            $results[] = 'MySQL Version: ' . $db_info['version'];
        }
        if ( ! empty( $db_info['server_info'] ) ) {
            $results[] = 'Database Server: ' . $db_info['server_info'];
        }
        
        // Server environment
        $server_info = $this->get_server_info();
        $results[] = 'Server Software: ' . $server_info['software'];
        $results[] = 'Server OS: ' . $server_info['os'];
        
        // SSL info
        $results[] = 'Site Uses HTTPS: ' . ( is_ssl() ? 'Yes' : 'No' );
        
        // Multisite info
        if ( is_multisite() ) {
            $results[] = 'WordPress Multisite: Yes';
            $results[] = 'Multisite Type: ' . ( is_subdomain_install() ? 'Subdomain' : 'Subdirectory' );
        } else {
            $results[] = 'WordPress Multisite: No';
        }
        
        $results[] = '';
        
        // Active theme info
        $results[] = '--- Active Theme ---';
        $theme_info = $this->get_theme_info();
        $results[] = 'Theme Name: ' . $theme_info['name'];
        $results[] = 'Theme Version: ' . $theme_info['version'];
        if ( ! empty( $theme_info['author'] ) ) {
            $results[] = 'Theme Author: ' . $theme_info['author'];
        }
        if ( $theme_info['is_child'] ) {
            $results[] = 'Child Theme: Yes';
            $results[] = 'Parent Theme: ' . $theme_info['parent_name'] . ' (' . $theme_info['parent_version'] . ')';
        } else {
            $results[] = 'Child Theme: No';
        }
        
        $results[] = '';
        
        // Active plugins
        $results[] = '--- Active Plugins ---';
        $plugin_info = $this->get_active_plugins_info();
        $results[] = 'Total Active Plugins: ' . count( $plugin_info );
        
        if ( ! empty( $plugin_info ) ) {
            foreach ( $plugin_info as $plugin ) {
                $results[] = '• ' . $plugin['name'] . ' (v' . $plugin['version'] . ')' . 
                           ( ! empty( $plugin['author'] ) ? ' by ' . strip_tags( $plugin['author'] ) : '' );
            }
        } else {
            $results[] = 'No active plugins found.';
        }
        
        $results[] = '';
        return $results;
    }
    
    /**
     * Get database information
     */
    private function get_database_info() {
        global $wpdb;
        
        $info = array();
        
        try {
            // Get MySQL version
            $version = $wpdb->get_var( "SELECT VERSION()" );
            if ( $version ) {
                $info['version'] = $version;
            }
            
            // Get server info
            $server_info = $wpdb->get_var( "SELECT @@version_comment" );
            if ( $server_info ) {
                $info['server_info'] = $server_info;
            }
        } catch ( Exception $e ) {
            // Database query failed, but don't break the diagnostic
            $info['error'] = 'Unable to retrieve database info';
        }
        
        return $info;
    }
    
    /**
     * Get server information
     */
    private function get_server_info() {
        $info = array(
            'software' => 'Unknown',
            'os' => 'Unknown'
        );
        
        // Get server software
        if ( isset( $_SERVER['SERVER_SOFTWARE'] ) ) {
            $info['software'] = sanitize_text_field( $_SERVER['SERVER_SOFTWARE'] );
        } elseif ( function_exists( 'apache_get_version' ) ) {
            $info['software'] = apache_get_version();
        }
        
        // Get operating system
        if ( function_exists( 'php_uname' ) ) {
            $info['os'] = php_uname( 's' ) . ' ' . php_uname( 'r' );
        } elseif ( defined( 'PHP_OS' ) ) {
            $info['os'] = PHP_OS;
        }
        
        return $info;
    }
    
    /**
     * Get active theme information
     */
    private function get_theme_info() {
        $theme = wp_get_theme();
        
        $info = array(
            'name' => $theme->get( 'Name' ),
            'version' => $theme->get( 'Version' ),
            'author' => $theme->get( 'Author' ),
            'is_child' => $theme->parent() !== false,
            'parent_name' => '',
            'parent_version' => ''
        );
        
        // Get parent theme info if this is a child theme
        if ( $info['is_child'] ) {
            $parent_theme = $theme->parent();
            $info['parent_name'] = $parent_theme->get( 'Name' );
            $info['parent_version'] = $parent_theme->get( 'Version' );
        }
        
        return $info;
    }
    
    /**
     * Get active plugins information
     */
    private function get_active_plugins_info() {
        if ( ! function_exists( 'get_plugin_data' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        $active_plugins = get_option( 'active_plugins', array() );
        $plugin_info = array();
        
        foreach ( $active_plugins as $plugin_file ) {
            $plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;
            
            if ( file_exists( $plugin_path ) ) {
                $plugin_data = get_plugin_data( $plugin_path );
                
                $plugin_info[] = array(
                    'name' => $plugin_data['Name'],
                    'version' => $plugin_data['Version'],
                    'author' => $plugin_data['Author'],
                    'file' => $plugin_file
                );
            }
        }
        
        // Sort plugins alphabetically by name
        usort( $plugin_info, function( $a, $b ) {
            return strcasecmp( $a['name'], $b['name'] );
        } );
        
        return $plugin_info;
    }
    
    /**
     * Generate structured data for JSON export
     */
    private function generate_structured_data() {
        global $wp_version;
        
        $data = array(
            'report_meta' => array(
                'generated' => current_time( 'Y-m-d H:i:s T' ),
                'site_url' => home_url(),
                'plugin_version' => JCC_VERSION,
                'report_format' => 'json'
            ),
            'jetpack' => array(
                'installed' => $this->is_jetpack_installed(),
                'active' => $this->is_jetpack_active(),
                'version' => $this->get_jetpack_version(),
                'connection' => $this->get_jetpack_connection_status(),
                'connection_details' => $this->get_jetpack_connection_details(),
                'tokens' => $this->check_jetpack_tokens(),
                'health_issues' => $this->has_connection_issues()
            ),
            'api_tests' => array(
                'rest_api' => $this->test_rest_api(),
                'xmlrpc' => $this->test_xmlrpc()
            ),
            'environment' => array(
                'wordpress' => array(
                    'version' => $wp_version,
                    'language' => get_locale(),
                    'timezone' => wp_timezone_string(),
                    'debug_mode' => defined( 'WP_DEBUG' ) && WP_DEBUG,
                    'memory_limit' => WP_MEMORY_LIMIT,
                    'multisite' => is_multisite(),
                    'multisite_type' => is_multisite() ? ( is_subdomain_install() ? 'subdomain' : 'subdirectory' ) : null,
                    'https' => is_ssl()
                ),
                'php' => array(
                    'version' => phpversion(),
                    'memory_limit' => ini_get( 'memory_limit' ),
                    'max_execution_time' => ini_get( 'max_execution_time' ),
                    'max_input_vars' => ini_get( 'max_input_vars' ),
                    'post_max_size' => ini_get( 'post_max_size' ),
                    'upload_max_filesize' => ini_get( 'upload_max_filesize' )
                ),
                'database' => $this->get_database_info(),
                'server' => $this->get_server_info(),
                'theme' => $this->get_theme_info(),
                'plugins' => $this->get_active_plugins_info()
            ),
            'status_summary' => $this->get_jetpack_status_summary()
        );
        
        return $data;
    }
    
    /**
     * Check for plugins that may conflict with Jetpack
     */
    private function check_plugin_conflicts() {
        $conflicts = array();
        
        // Get active plugins
        $active_plugins = get_option( 'active_plugins', array() );
        $plugin_names = array();
        
        // Build list of active plugin names and slugs for checking
        foreach ( $active_plugins as $plugin_file ) {
            $plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;
            if ( file_exists( $plugin_path ) ) {
                if ( ! function_exists( 'get_plugin_data' ) ) {
                    require_once ABSPATH . 'wp-admin/includes/plugin.php';
                }
                $plugin_data = get_plugin_data( $plugin_path );
                $plugin_names[] = array(
                    'name' => strtolower( $plugin_data['Name'] ),
                    'file' => $plugin_file,
                    'display_name' => $plugin_data['Name']
                );
            }
        }
        
        // Define known conflicting plugins
        $known_conflicts = array(
            // XML-RPC Blockers (Critical - but use cautious language)
            array(
                'patterns' => array( 'disable xml-rpc', 'disable xmlrpc', 'xmlrpc-api', 'simple disable xml-rpc' ),
                'severity' => 'critical',
                'category' => 'XML-RPC',
                'message_template' => __( 'Plugin "%s" could be blocking XML-RPC which Jetpack requires. Try disabling this plugin temporarily and test the connection again.', 'jetpack-connection-checker' )
            ),
            
            // Security Plugins (Warning - may block)
            array(
                'patterns' => array( 'ithemes security', 'ithemes-security', 'better-wp-security' ),
                'severity' => 'warning',
                'category' => 'Security',
                'message_template' => __( 'Security plugin "%s" could be blocking XML-RPC. Check its XML-RPC settings and whitelist Jetpack connections.', 'jetpack-connection-checker' )
            ),
            array(
                'patterns' => array( 'all in one wp security', 'all-in-one-wp-security', 'aiowpsecurity' ),
                'severity' => 'warning',
                'category' => 'Security',
                'message_template' => __( 'Security plugin "%s" could be blocking Jetpack. Check the Firewall and Brute Force settings.', 'jetpack-connection-checker' )
            ),
            array(
                'patterns' => array( 'wordfence', 'wordfence security' ),
                'severity' => 'warning',
                'category' => 'Security',
                'message_template' => __( 'Wordfence could be blocking or throttling Jetpack. Check Firewall settings and whitelist WordPress.com IPs.', 'jetpack-connection-checker' )
            ),
            
            // Cache Plugins (Issues)
            array(
                'patterns' => array( 'autoptimize' ),
                'severity' => 'notice',
                'category' => 'Optimization',
                'message_template' => __( 'Autoptimize may affect Jetpack functionality. If issues persist, try excluding Jetpack scripts from optimization.', 'jetpack-connection-checker' )
            ),
            array(
                'patterns' => array( 'w3 total cache', 'w3-total-cache' ),
                'severity' => 'notice',
                'category' => 'Caching',
                'message_template' => __( 'W3 Total Cache may affect Jetpack. Consider excluding Jetpack pages from caching if issues occur.', 'jetpack-connection-checker' )
            ),
            array(
                'patterns' => array( 'litespeed cache', 'litespeed-cache' ),
                'severity' => 'warning',
                'category' => 'Caching',
                'message_template' => __( 'LiteSpeed Cache may conflict with Jetpack. Try disabling "Localize Resources" in Cache settings.', 'jetpack-connection-checker' )
            ),
            array(
                'patterns' => array( 'fast velocity minify', 'fast-velocity-minify' ),
                'severity' => 'notice',
                'category' => 'Optimization',
                'message_template' => __( 'Fast Velocity Minify may affect Jetpack scripts. Consider excluding Jetpack from minification.', 'jetpack-connection-checker' )
            ),
            
            // Coming Soon / Maintenance Mode (Critical)
            array(
                'patterns' => array( 'seedprod', 'maintenance mode', 'coming soon' ),
                'severity' => 'critical',
                'category' => 'Maintenance',
                'message_template' => __( 'Maintenance/Coming Soon plugin "%s" could be blocking Jetpack\'s connection checks. Try disabling temporarily to test.', 'jetpack-connection-checker' )
            ),
            array(
                'patterns' => array( 'wp maintenance mode', 'cmp coming soon', 'cmp – coming soon' ),
                'severity' => 'critical',
                'category' => 'Maintenance',
                'message_template' => __( 'Coming Soon plugin "%s" could be blocking Jetpack. Try disabling or whitelist WordPress.com access.', 'jetpack-connection-checker' )
            ),
            
            // Ads.txt Plugins (Warning)
            array(
                'patterns' => array( 'ad inserter', 'advanced ads', 'ads.txt' ),
                'severity' => 'notice',
                'category' => 'Advertising',
                'message_template' => __( 'Ads plugin "%s" may conflict with Jetpack WordAds. Ensure ads.txt management doesn\'t interfere.', 'jetpack-connection-checker' )
            ),
            
            // Cookie/GDPR (Notice)
            array(
                'patterns' => array( 'complianz', 'gdpr cookie consent' ),
                'severity' => 'notice',
                'category' => 'Privacy',
                'message_template' => __( 'GDPR plugin "%s" may break Jetpack Stats. Check if Jetpack integration needs to be disabled in privacy settings.', 'jetpack-connection-checker' )
            )
        );
        
        // Check each active plugin against known conflicts
        foreach ( $plugin_names as $plugin ) {
            foreach ( $known_conflicts as $conflict ) {
                foreach ( $conflict['patterns'] as $pattern ) {
                    if ( strpos( $plugin['name'], $pattern ) !== false ) {
                        $conflicts[] = array(
                            'severity' => $conflict['severity'],
                            'category' => $conflict['category'],
                            'plugin_name' => $plugin['display_name'],
                            'message' => sprintf( $conflict['message_template'], $plugin['display_name'] )
                        );
                        break 2; // Break out of both loops for this plugin
                    }
                }
            }
        }
        
        return $conflicts;
    }
}