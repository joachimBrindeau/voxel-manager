<?php
/**
 * Post Action Widget
 * Displays a dropdown of posts meeting specific conditions with an action button
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class VBM_Post_Action_Widget {
    /**
     * Initialize the widget
     */
    public function initialize() {
        add_action('init', [$this, 'register_shortcodes']);
        add_filter('widget_text', 'do_shortcode');
        
        // Register assets
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        
        // Register AJAX handler
        add_action('wp_ajax_vbm_post_action', [$this, 'handle_post_action']);
        add_action('wp_ajax_nopriv_vbm_post_action', [$this, 'handle_post_action']);
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        wp_enqueue_style(
            'vbm-post-action',
            VBM_PLUGIN_URL . 'modules/widgets/post-action/post-action.css',
            [],
            VBM_PLUGIN_VERSION
        );
        
        wp_enqueue_script(
            'vbm-post-action',
            VBM_PLUGIN_URL . 'modules/widgets/post-action/post-action.js',
            ['jquery', 'vbm-widgets'],
            VBM_PLUGIN_VERSION,
            true
        );
        
        wp_localize_script('vbm-post-action', 'vbm_post_action', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vbm_post_action_nonce'),
        ]);
    }
    
    /**
     * Register shortcodes
     */
    public function register_shortcodes() {
        add_shortcode('post_action', [$this, 'render_post_action_shortcode']);
    }
    
    /**
     * Render shortcode
     * 
     * @param array $atts Shortcode attributes
     * @return string Rendered HTML
     */
    public function render_post_action_shortcode($atts) {
        // Extract shortcode attributes with defaults
        $atts = shortcode_atts([
            'post' => '', // Target post ID
            'field' => '', // Field key to modify
            'cpt' => '', // Post type for dropdown items
            'condition-field' => '', // Field to check in condition
            'condition-operator' => 'equals', // Operator for condition
            'condition-value' => '', // Value for condition
            'action-type' => 'replace', // Type of action (replace, append, remove)
            'action-text' => 'Apply', // Button text
            'dropdown-placeholder' => 'Select an item', // Dropdown placeholder
        ], $atts);
        
        // Validate required attributes
        if (empty($atts['post']) || empty($atts['field']) || empty($atts['cpt'])) {
            return '<div class="post-action-error">Required parameters missing: post, field, and cpt are required.</div>';
        }
        
        // Generate unique widget ID
        $widget_id = 'vbm-post-action-' . uniqid();
        
        // Query posts based on conditions
        $posts = $this->get_filtered_posts($atts);
        
        if (empty($posts)) {
            return '<div class="post-action-error">No items found matching the specified conditions.</div>';
        }
        
        // Build the widget HTML
        ob_start();
        ?>
        <div id="<?php echo esc_attr($widget_id); ?>" class="vbm-post-action-widget">
            <div class="vbm-post-action-container">
                <select class="vbm-post-dropdown">
                    <option value=""><?php echo esc_html($atts['dropdown-placeholder']); ?></option>
                    <?php foreach ($posts as $post): ?>
                        <option value="<?php echo esc_attr($post->ID); ?>">
                            <?php echo esc_html($post->post_title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <button class="vbm-post-action-button" 
                    data-post="<?php echo esc_attr($atts['post']); ?>" 
                    data-field="<?php echo esc_attr($atts['field']); ?>" 
                    data-action="<?php echo esc_attr($atts['action-type']); ?>">
                    <?php echo esc_html($atts['action-text']); ?>
                </button>
            </div>
            
            <div class="vbm-post-action-messages"></div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get filtered posts based on conditions
     * 
     * @param array $atts Shortcode attributes
     * @return array Array of posts
     */
    private function get_filtered_posts($atts) {
        $args = [
            'post_type' => $atts['cpt'],
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ];
        
        // Apply meta query if condition is provided
        if (!empty($atts['condition-field']) && isset($atts['condition-value'])) {
            $meta_query = [];
            
            switch ($atts['condition-operator']) {
                case 'equals':
                    $meta_query = [
                        'key' => $atts['condition-field'],
                        'value' => $atts['condition-value'],
                        'compare' => '='
                    ];
                    break;
                    
                case 'not_equals':
                    $meta_query = [
                        'key' => $atts['condition-field'],
                        'value' => $atts['condition-value'],
                        'compare' => '!='
                    ];
                    break;
                    
                case 'contains':
                    $meta_query = [
                        'key' => $atts['condition-field'],
                        'value' => $atts['condition-value'],
                        'compare' => 'LIKE'
                    ];
                    break;
                    
                case 'not_contains':
                    $meta_query = [
                        'key' => $atts['condition-field'],
                        'value' => $atts['condition-value'],
                        'compare' => 'NOT LIKE'
                    ];
                    break;
                    
                case 'is_empty':
                    $meta_query = [
                        'key' => $atts['condition-field'],
                        'value' => '',
                        'compare' => '='
                    ];
                    break;
                    
                case 'is_not_empty':
                    $meta_query = [
                        'key' => $atts['condition-field'],
                        'value' => '',
                        'compare' => '!='
                    ];
                    break;
            }
            
            if (!empty($meta_query)) {
                $args['meta_query'] = [$meta_query];
            }
        }
        
        // For Voxel fields, we need special handling with their JSON structure
        // This is a simplified approach - complex conditions may need more work
        if (strpos($atts['condition-field'], 'voxel:') === 0) {
            // Extract the actual field key
            $field_key = str_replace('voxel:', '', $atts['condition-field']);
            
            // Use a custom filter to handle Voxel field filtering
            add_filter('posts_where', function($where) use ($field_key, $atts) {
                global $wpdb;
                
                // Add custom SQL to check inside serialized/JSON field data
                // This is a simplified version and might need refinement
                $where .= $wpdb->prepare(
                    " AND ID IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'voxel_field_data' AND meta_value LIKE %s)",
                    '%"' . $field_key . '"%' . $atts['condition-value'] . '%'
                );
                
                return $where;
            });
        }
        
        $query = new WP_Query($args);
        
        return $query->posts;
    }
    
    /**
     * Handle AJAX action on post field
     */
    public function handle_post_action() {
        // Verify nonce
        if (!check_ajax_referer('vbm_post_action_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Security check failed']);
            exit;
        }
        
        // Get parameters
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $field_key = isset($_POST['field_key']) ? sanitize_text_field($_POST['field_key']) : '';
        $selected_id = isset($_POST['selected_id']) ? intval($_POST['selected_id']) : 0;
        $action_type = isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : 'replace';
        
        // Validate parameters
        if (!$post_id || !$field_key || !$selected_id) {
            wp_send_json_error(['message' => 'Missing required parameters']);
            exit;
        }
        
        // Check if the field exists and is a Voxel field
        $field_data = $this->get_voxel_field_data($post_id, $field_key);
        
        // If not a Voxel field, try regular post meta
        if ($field_data === null) {
            $result = $this->update_post_meta_field($post_id, $field_key, $selected_id, $action_type);
        } else {
            $result = $this->update_voxel_field($post_id, $field_key, $field_data, $selected_id, $action_type);
        }
        
        // Return result
        if ($result) {
            wp_send_json_success(['message' => 'Field updated successfully']);
        } else {
            wp_send_json_error(['message' => 'Failed to update field']);
        }
        
        exit;
    }
    
    /**
     * Get Voxel field data
     * 
     * @param int $post_id Post ID
     * @param string $field_key Field key
     * @return mixed|null Field data or null if not found
     */
    private function get_voxel_field_data($post_id, $field_key) {
        $voxel_data = get_post_meta($post_id, 'voxel_field_data', true);
        
        if (!$voxel_data || !is_array($voxel_data)) {
            return null;
        }
        
        return isset($voxel_data[$field_key]) ? $voxel_data[$field_key] : null;
    }
    
    /**
     * Update a regular post meta field
     * 
     * @param int $post_id Post ID
     * @param string $field_key Field key
     * @param int $selected_id Selected post ID
     * @param string $action_type Action type
     * @return bool Success status
     */
    private function update_post_meta_field($post_id, $field_key, $selected_id, $action_type) {
        $current_value = get_post_meta($post_id, $field_key, true);
        $new_value = '';
        
        switch ($action_type) {
            case 'replace':
                $new_value = $selected_id;
                break;
                
            case 'append':
                if (is_array($current_value)) {
                    if (!in_array($selected_id, $current_value)) {
                        $current_value[] = $selected_id;
                    }
                    $new_value = $current_value;
                } else {
                    $new_value = empty($current_value) ? $selected_id : $current_value . ',' . $selected_id;
                }
                break;
                
            case 'remove':
                if (is_array($current_value)) {
                    $new_value = array_diff($current_value, [$selected_id]);
                } else {
                    $values = explode(',', $current_value);
                    $values = array_filter($values, function($value) use ($selected_id) {
                        return $value != $selected_id;
                    });
                    $new_value = implode(',', $values);
                }
                break;
        }
        
        return update_post_meta($post_id, $field_key, $new_value);
    }
    
    /**
     * Update a Voxel field
     * 
     * @param int $post_id Post ID
     * @param string $field_key Field key
     * @param mixed $field_data Current field data
     * @param int $selected_id Selected post ID
     * @param string $action_type Action type
     * @return bool Success status
     */
    private function update_voxel_field($post_id, $field_key, $field_data, $selected_id, $action_type) {
        $voxel_data = get_post_meta($post_id, 'voxel_field_data', true) ?: [];
        
        // Handle different field types appropriately
        switch ($action_type) {
            case 'replace':
                $voxel_data[$field_key] = $selected_id;
                break;
                
            case 'append':
                if (is_array($field_data)) {
                    if (!in_array($selected_id, $field_data)) {
                        $field_data[] = $selected_id;
                    }
                    $voxel_data[$field_key] = $field_data;
                } else {
                    $voxel_data[$field_key] = $selected_id;
                }
                break;
                
            case 'remove':
                if (is_array($field_data)) {
                    $voxel_data[$field_key] = array_diff($field_data, [$selected_id]);
                } else {
                    // If the only value matches, remove it completely
                    if ($field_data == $selected_id) {
                        $voxel_data[$field_key] = '';
                    }
                }
                break;
        }
        
        return update_post_meta($post_id, 'voxel_field_data', $voxel_data);
    }
    
    /**
     * Render widget documentation
     */
    public function render_documentation() {
        ?>
        <div class="widget-documentation">
            <p>The Post Action widget displays a dropdown of posts meeting specific conditions and a button to perform actions on fields of another post.</p>
            
            <h4>Usage</h4>
            <p>Use the shortcode <code>[post_action]</code> with the following attributes:</p>
            
            <h5>Required Parameters:</h5>
            <ul>
                <li><code>post</code>: ID of the post to modify</li>
                <li><code>field</code>: The Voxel field key to modify</li>
                <li><code>cpt</code>: The post type to query for the dropdown</li>
            </ul>
            
            <h5>Optional Parameters:</h5>
            <ul>
                <li><code>condition-field</code>: Field to check in the condition</li>
                <li><code>condition-operator</code>: Comparison operator (equals, not_equals, contains, not_contains, is_empty, is_not_empty)</li>
                <li><code>condition-value</code>: Value to compare against</li>
                <li><code>action-type</code>: Type of action - replace (default), append, or remove</li>
                <li><code>action-text</code>: Text for the action button</li>
                <li><code>dropdown-placeholder</code>: Placeholder text for the dropdown</li>
            </ul>
            
            <h4>Examples</h4>
            
            <h5>Basic Example (Replace Action):</h5>
            <pre>[post_action post="123" field="related_items" cpt="product" action-type="replace" action-text="Set Item"]</pre>
            
            <h5>Append Action Example:</h5>
            <pre>[post_action post="123" field="related_items" cpt="product" action-type="append" action-text="Add Item"]</pre>
            
            <h5>Remove Action Example:</h5>
            <pre>[post_action post="123" field="related_items" cpt="product" action-type="remove" action-text="Remove Item"]</pre>
            
            <h5>With Filter Conditions:</h5>
            <pre>[post_action post="123" field="related_items" cpt="product" condition-field="category" condition-operator="equals" condition-value="clothing" action-type="append" action-text="Add Item"]</pre>
            
            <h5>Using Voxel Fields:</h5>
            <pre>[post_action post="123" field="voxel:related_posts" cpt="product" action-type="append" action-text="Add to Related"]</pre>
            
            <p>This widget supports both regular WordPress post meta and Voxel fields.</p>
        </div>
        <?php
    }
}
