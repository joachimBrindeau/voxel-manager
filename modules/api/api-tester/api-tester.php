<?php
/**
 * API Tester for Voxel Manager
 * 
 * @package VoxelManager
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

/**
 * Register API tester AJAX handlers
 */
add_action('wp_ajax_vbm_get_post_fields', 'vbm_ajax_get_post_fields');
add_action('wp_ajax_vbm_get_post_list', 'vbm_ajax_get_post_list');
add_action('wp_ajax_vbm_test_api_update', 'vbm_ajax_test_api_update');

/**
 * AJAX handler to get fields for a post type
 */
function vbm_ajax_get_post_fields() {
    check_ajax_referer('vbm_api_tester', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : '';
    
    if (!$post_type) {
        wp_send_json_error('Invalid post type');
    }
    
    // Get Voxel post types
    $voxel_post_types = json_decode(get_option('voxel:post_types'), true) ?: [];
    
    if (!isset($voxel_post_types[$post_type])) {
        wp_send_json_error('Post type not found in Voxel configuration');
    }
    
    $fields = [];
    
    foreach ($voxel_post_types[$post_type]['fields'] as $field) {
        // Skip UI fields
        if (!isset($field['key']) || !isset($field['type']) || 
            strpos($field['type'], 'ui-') === 0 || $field['type'] === 'title') {
            continue;
        }
        
        $fields[] = [
            'key' => $field['key'],
            'label' => $field['label'] ?? $field['key'],
            'type' => $field['type']
        ];
    }
    
    wp_send_json_success($fields);
}

/**
 * AJAX handler to get posts of a specific post type
 */
function vbm_ajax_get_post_list() {
    check_ajax_referer('vbm_api_tester', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : '';
    
    if (!$post_type) {
        wp_send_json_error('Invalid post type');
    }
    
    $posts = get_posts([
        'post_type' => $post_type,
        'posts_per_page' => 50,
        'post_status' => 'publish'
    ]);
    
    $post_list = [];
    foreach ($posts as $post) {
        $post_list[] = [
            'id' => $post->ID,
            'title' => $post->post_title
        ];
    }
    
    wp_send_json_success($post_list);
}

/**
 * AJAX handler to test API update
 */
function vbm_ajax_test_api_update() {
    check_ajax_referer('vbm_api_tester', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    $field_key = isset($_POST['field_key']) ? sanitize_text_field($_POST['field_key']) : '';
    $field_type = isset($_POST['field_type']) ? sanitize_text_field($_POST['field_type']) : '';
    $field_value = isset($_POST['field_value']) ? $_POST['field_value'] : '';
    $http_method = isset($_POST['http_method']) ? sanitize_text_field($_POST['http_method']) : 'POST';
    
    if (!$post_id) {
        wp_send_json_error('Missing required parameters');
    }
    
    $post = get_post($post_id);
    if (!$post) {
        wp_send_json_error('Post not found');
    }
    
    // Generate the REST API URL
    $api_url = rest_url('wp/v2/' . $post->post_type . '/' . $post_id);
    
    // Prepare the request body and method
    $request_body = [];
    
    if ($http_method === 'POST' || $http_method === 'PUT') {
        // Format the value based on field type
        $formatted_value = $field_value ? format_field_value($field_value, $field_type) : '';
        
        // Check if this is a WordPress core post field
        $wp_posts_field = map_field_to_wp_posts_column($field_key);
        
        if ($wp_posts_field) {
            // This is a wp_posts table field, format accordingly
            $request_body[$wp_posts_field] = $formatted_value;
        } else if ($field_key) {
            // Regular custom field
            $request_body[$field_key] = $formatted_value;
        }
    }
    
    // Prepare the request
    $args = [
        'method' => $http_method,
        'headers' => [
            'Content-Type' => 'application/json',
            'X-WP-Nonce' => wp_create_nonce('wp_rest')
        ],
        'cookies' => $_COOKIE // Pass the current cookies to authenticate the request
    ];
    
    // Add body for POST or PUT requests
    if ($http_method === 'POST' || $http_method === 'PUT') {
        $args['body'] = json_encode($request_body);
    }
    
    // Make the request
    $response = wp_remote_request($api_url, $args);
    
    // Check for errors
    if (is_wp_error($response)) {
        wp_send_json_error([
            'message' => $response->get_error_message(),
            'code' => $response->get_error_code()
        ]);
    }
    
    $status_code = wp_remote_retrieve_response_code($response);
    $response_body = json_decode(wp_remote_retrieve_body($response), true);
    
    if ($status_code >= 200 && $status_code < 300) {
        // Success
        wp_send_json_success([
            'status_code' => $status_code,
            'response' => $response_body,
            'request' => [
                'method' => $http_method,
                'url' => $api_url,
                'body' => $request_body
            ]
        ]);
    } else {
        // Error
        wp_send_json_error([
            'status_code' => $status_code,
            'response' => $response_body,
            'request' => [
                'method' => $http_method,
                'url' => $api_url,
                'body' => $request_body
            ]
        ]);
    }
}

/**
 * Format field value based on field type
 */
function format_field_value($value, $field_type) {
    switch ($field_type) {
        case 'number':
            return floatval($value);
            
        case 'switcher':
            return $value === 'true' || $value === '1' || $value === 'yes';
            
        case 'post-relation':
            // Convert comma-separated string to array of integers
            if (strpos($value, ',') !== false) {
                return array_map('intval', explode(',', $value));
            }
            return intval($value);
            
        case 'repeater':
            // Try to decode as JSON, fallback to empty array
            $decoded = json_decode($value, true);
            return $decoded ?: [];
            
        default:
            return $value;
    }
}

/**
 * Map a field key to its wp_posts table column name
 * 
 * @param string $field_key Field key to check
 * @return string|false WordPress post field name or false if not a core field
 */
function map_field_to_wp_posts_column($field_key) {
    $wp_posts_fields = [
        'title' => 'title',
        'post_title' => 'title',
        'content' => 'content', 
        'post_content' => 'content',
        'excerpt' => 'excerpt',
        'post_excerpt' => 'excerpt',
        'slug' => 'slug',
        'post_name' => 'slug',
        'status' => 'status',
        'post_status' => 'status',
        'date' => 'date',
        'post_date' => 'date',
        'modified' => 'modified',
        'post_modified' => 'modified',
        'author' => 'author',
        'post_author' => 'author',
        'featured_media' => 'featured_media', // Featured image ID
        'comment_status' => 'comment_status',
        'ping_status' => 'ping_status',
        'menu_order' => 'menu_order',
        'post_parent' => 'parent',
        'parent' => 'parent',
        'password' => 'password',
        'format' => 'format',
        'post_format' => 'format'
    ];
    
    return isset($wp_posts_fields[$field_key]) ? $wp_posts_fields[$field_key] : false;
}

/**
 * Render API tester UI
 */
function render_api_tester_ui() {
    // Get all Voxel post types
    $voxel_post_types = json_decode(get_option('voxel:post_types'), true) ?: [];
    
    // Sort post types alphabetically by label
    $sorted_post_types = [];
    foreach ($voxel_post_types as $post_type => $data) {
        $label = $data['settings']['singular'] ?? $post_type;
        $sorted_post_types[$post_type] = $label;
    }
    asort($sorted_post_types); // Sort by label alphabetically
    ?>
    <div class="vbm-api-tester">
        <h3>API Tester</h3>
        <p>Use this tool to test the API functionality with your existing content.</p>
        
        <div class="vbm-api-tester-form">
            <!-- Step 1: Select Post Type -->
            <div class="vbm-form-row">
                <label for="vbm-test-post-type"><strong>Step 1:</strong> Select Post Type:</label>
                <select id="vbm-test-post-type" class="vbm-select">
                    <option value="">-- Select Post Type --</option>
                    <?php foreach ($sorted_post_types as $post_type => $label): ?>
                        <option value="<?php echo esc_attr($post_type); ?>">
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Step 2: Select Post -->
            <div class="vbm-form-row" id="post-selector-container" style="display:none;">
                <label for="vbm-test-post"><strong>Step 2:</strong> Select Post:</label>
                <select id="vbm-test-post" class="vbm-select">
                    <option value="">-- Select Post --</option>
                </select>
            </div>
            
            <!-- Step 3: Select Action -->
            <div class="vbm-form-row" id="action-selector-container" style="display:none;">
                <label for="vbm-test-method"><strong>Step 3:</strong> Select Action:</label>
                <select id="vbm-test-method" class="vbm-select">
                    <option value="GET">GET (Retrieve post data)</option>
                    <option value="POST" selected>POST (Update post data)</option>
                    <option value="DELETE">DELETE (Move to trash)</option>
                </select>
                <p class="description">Choose the HTTP method for the API request</p>
            </div>
            
            <!-- Step 4: Select Field (only for POST) -->
            <div class="vbm-form-row" id="field-selector-container" style="display:none;">
                <label for="vbm-test-field"><strong>Step 4:</strong> Select Field:</label>
                <select id="vbm-test-field" class="vbm-select">
                    <option value="">-- Select Field --</option>
                </select>
                <p class="description">Includes both custom fields and WordPress core fields</p>
            </div>
            
            <!-- Step 5: Enter Value (only for POST) -->
            <div class="vbm-form-row" id="value-input-container" style="display:none;">
                <label for="vbm-test-value"><strong>Step 5:</strong> Enter Value:</label>
                <textarea id="vbm-test-value" class="vbm-textarea" rows="3"></textarea>
                <p class="description">For multiple values (like post relations), use comma-separated IDs.</p>
                <p class="description">
                    <strong>Note:</strong> WordPress core fields (title, content, excerpt, etc.) 
                    are stored in the WordPress posts table, not the postmeta table.
                </p>
            </div>
            
            <!-- Step 6: Run Test -->
            <div class="vbm-form-row" id="api-test-controls" style="display:none;">
                <button id="vbm-run-api-test" class="button button-primary">
                    Test API Request
                </button>
                <span id="vbm-api-test-status" class="api-status"></span>
                <div id="vbm-api-test-result" class="api-result"></div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Enqueue admin assets
 */
function vbm_api_tester_enqueue_admin_assets($hook) {
    // Check if this is our admin page
    if (strpos($hook, 'voxel-api_tester') === false) {
        return;
    }
    
    // No need to enqueue module-specific CSS as it's now part of the main stylesheet
    
    // Only enqueue the JavaScript file
    wp_enqueue_script(
        'vbm-api-tester-js', 
        VBM_PLUGIN_URL . 'modules/api/api-tester/api-tester.js',
        ['jquery'],
        VBM_PLUGIN_VERSION,
        true
    );
    
    wp_localize_script('vbm-api-tester-js', 'vbm_api_tester', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('vbm_api_tester')
    ]);
}
add_action('admin_enqueue_scripts', 'vbm_api_tester_enqueue_admin_assets');
