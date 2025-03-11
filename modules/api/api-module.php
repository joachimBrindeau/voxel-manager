<?php
/**
 * Voxel API Module
 * 
 * Exposes Voxel custom post types to the REST API
 * 
 * @package VoxelManager
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

class VBM_API_Module extends VBM_Module {
    /**
     * Relations manager instance
     */
    private $relations_manager;
    
    /**
     * Logger instance
     */
    private $logger;
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(
            'api', 
            'API Access', 
            'Exposes Voxel custom post types and fields to the REST API',
            '1.0.0'
        );
        
        // Define constants for this module
        if (!defined('VOXEL_API_PATH')) {
            define('VOXEL_API_PATH', plugin_dir_path(__FILE__));
            define('VOXEL_API_URL', plugin_dir_url(__FILE__));
        }
    }

    /**
     * Initialize the module
     */
    public function initialize() {
        // Required files array with error handling
        $required_files = [
            'utilities.php',
            'post-types.php',
            'rest-fields.php',
            'fields-handlers.php',
            'relation-handlers.php'
        ];
        
        $load_failed = false;
        foreach ($required_files as $file) {
            if (!file_exists(VOXEL_API_PATH . $file)) {
                error_log("Voxel API Module: Required file missing: {$file}");
                $load_failed = true;
            } else {
                require_once VOXEL_API_PATH . $file;
            }
        }
        
        // Optional files with graceful handling
        $optional_files = [
            'relations-manager.php',
            'field-registry.php',
            'api-tester/api-tester.php'
        ];
        
        foreach ($optional_files as $file) {
            if (file_exists(VOXEL_API_PATH . $file)) {
                require_once VOXEL_API_PATH . $file;
            }
        }
        
        if ($load_failed) {
            error_log("Voxel API Module: Failed to initialize due to missing files");
            return;
        }
        
        // Initialize module components
        $this->relations_manager = class_exists('VBM_Relations_Manager') ? new VBM_Relations_Manager() : null;
        $this->logger = new VBM_Logger();
        
        // Call parent to register hooks
        parent::initialize();
        
        // Make API module globally accessible
        global $vbm_api_module;
        $vbm_api_module = $this;
        
        // Initialize the REST API only once
        add_action('rest_api_init', [$this, 'init_rest_api'], 5);
    }

    /**
     * Initialize REST API support
     */
    public function init_rest_api() {
        try {
            // Expose Voxel CPTs to REST API
            if (function_exists('expose_voxel_cpts_to_rest_api')) {
                expose_voxel_cpts_to_rest_api();
            }
            
            // Register custom fields
            if (function_exists('register_custom_fields_in_rest_api')) {
                register_custom_fields_in_rest_api();
            }
            
            $this->logger->log('REST API initialized');
        } catch (Exception $e) {
            $this->logger->log('REST API initialization error: ' . $e->getMessage(), 'error');
        }
    }

    /**
     * Register module hooks
     */
    protected function register_hooks() {
        // Don't re-register endpoints, now handled in init_rest_api
        // Clean up relations when posts are deleted
        add_action('before_delete_post', 'cleanup_voxel_relations');
        
        // Add custom headers for CORS support
        add_action('rest_api_init', [$this, 'add_cors_support']);
        
        // Add filters for repeater fields
        add_filter('rest_prepare_post', [$this, 'prepare_repeater_fields'], 10, 3);
        add_filter('rest_request_after_callbacks', [$this, 'handle_repeater_updates'], 10, 3);
        
        // Register admin assets
        add_action('admin_enqueue_scripts', [$this, 'register_admin_assets']);
        
        // Add support for POST, PUT, DELETE operations
        add_filter('rest_pre_serve_request', [$this, 'handle_preflight_request']);
        
        // Add debugging filters for REST API requests
        if (WP_DEBUG) {
            add_filter('rest_request_before_callbacks', [$this, 'debug_rest_request'], 10, 3);
            add_filter('rest_request_after_callbacks', [$this, 'debug_rest_response'], 999, 3);
        }
    }
    
    /**
     * Add CORS support
     */
    public function add_cors_support() {
        remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
        add_filter('rest_pre_serve_request', [$this, 'send_cors_headers']);
    }
    
    /**
     * Send CORS headers
     */
    public function send_cors_headers($served) {
        $origin = get_http_origin();
        
        if ($origin) {
            // Get allowed origins from options or use a default
            $allowed_origins = get_option('vbm_allowed_origins', ['*']);
            
            if (in_array('*', $allowed_origins) || in_array($origin, $allowed_origins)) {
                header('Access-Control-Allow-Origin: ' . $origin);
                header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
                header('Access-Control-Allow-Credentials: true');
                header('Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce');
                header('Access-Control-Expose-Headers: X-WP-Total, X-WP-TotalPages');
            }
        }
        
        return $served;
    }
    
    /**
     * Handle preflight request
     */
    public function handle_preflight_request($served) {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            status_header(200);
            return true;
        }
        
        return $served;
    }
    
    /**
     * Debug REST request
     */
    public function debug_rest_request($response, $handler, $request) {
        $this->logger->log('REST Request: ' . $request->get_method() . ' ' . $request->get_route());
        $this->logger->log('Request Params: ' . json_encode($request->get_params()));
        return $response;
    }
    
    /**
     * Debug REST response
     */
    public function debug_rest_response($response, $handler, $request) {
        if (is_wp_error($response)) {
            $this->logger->log('REST Response Error: ' . $response->get_error_message());
        }
        return $response;
    }
    
    /**
     * Prepare repeater fields for REST API
     */
    public function prepare_repeater_fields($response, $post, $request) {
        $voxel_post_types = json_decode(get_option('voxel:post_types'), true) ?: [];
        $post_type = $post->post_type;

        if (isset($voxel_post_types[$post_type]['fields'])) {
            foreach ($voxel_post_types[$post_type]['fields'] as $field) {
                if ($field['type'] === 'repeater') {
                    $field_value = get_custom_field_value($post->ID, $field);
                    if (is_array($field_value)) {
                        $response->data[$field['key']] = wp_json_encode($field_value);
                    }
                }
            }
        }

        return $response;
    }
    
    /**
     * Handle repeater field updates
     */
    public function handle_repeater_updates($response, $handler, $request) {
        if ($request->get_method() === 'POST' || $request->get_method() === 'PUT') {
            $post_id = $response->data['id'];
            $voxel_post_types = json_decode(get_option('voxel:post_types'), true) ?: [];
            $post_type = get_post_type($post_id);

            if (isset($voxel_post_types[$post_type]['fields'])) {
                foreach ($voxel_post_types[$post_type]['fields'] as $field) {
                    if ($field['type'] === 'repeater' && isset($request[$field['key']])) {
                        $repeater_value = json_decode($request[$field['key']], true);
                        update_post_meta($post_id, $field['key'], $repeater_value);
                    }
                }
            }
        }

        return $response;
    }

    /**
     * Get relations manager instance
     */
    public function get_relations_manager() {
        return $this->relations_manager;
    }
    
    /**
     * Get logger instance
     */
    public function get_logger() {
        return $this->logger;
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        ?>
        <div class="vbm-api-page">
            <h2>Voxel API Access</h2>
            <p>This module exposes your Voxel custom post types and fields to the WordPress REST API.</p>
            
            <div class="vbm-card">
                <h3>Documentation</h3>
                <p>Your API endpoints are available at the following URLs:</p>
                
                <ul>
                    <?php
                    $voxel_post_types = json_decode(get_option('voxel:post_types'), true) ?: [];
                    foreach ($voxel_post_types as $post_type => $data) {
                        $label = $data['settings']['singular'] ?? $post_type;
                        echo '<li><strong>' . esc_html($label) . ':</strong> <code>' . 
                             esc_url(rest_url('wp/v2/' . $post_type)) . '</code></li>';
                    }
                    ?>
                </ul>
            </div>
            
            <?php 
            // Render API tester UI if function exists
            if (function_exists('render_api_tester_ui')) {
                render_api_tester_ui();
            }
            ?>
        </div>
        <?php
    }
    
    /**
     * Register admin assets
     */
    public function register_admin_assets($hook) {
        // Only load on our plugin page
        if (strpos($hook, 'voxel-api') === false) {
            return;
        }

        // Register API tester assets
        wp_enqueue_style('vbm-api-tester-style', VOXEL_API_URL . 'api-tester/api-tester.css', [], VBM_PLUGIN_VERSION);
        wp_enqueue_script('vbm-api-tester', VOXEL_API_URL . 'api-tester/api-tester.js', ['jquery'], VBM_PLUGIN_VERSION, true);
        
        wp_localize_script('vbm-api-tester', 'vbmApiTester', [
            'ajax_nonce' => wp_create_nonce('vbm_api_tester')
        ]);
    }
}

/**
 * Logger class
 */
class VBM_Logger {
    public function log($message, $type = 'info') {
        if (WP_DEBUG) {
            error_log("VBM API [$type]: $message");
        }
    }
}
