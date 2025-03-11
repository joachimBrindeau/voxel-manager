<?php
/**
 * Claims List Widget
 * Displays claim requests through shortcodes
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class VBM_Claims_List_Widget {
    public function initialize() {
        add_action('init', [$this, 'register_shortcodes']);
        add_filter('widget_text', 'do_shortcode');
        
        // Register assets
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
    }
    
    public function enqueue_frontend_assets() {
        wp_enqueue_style(
            'vbm-claims-list',
            VBM_PLUGIN_URL . 'modules/widgets/claims-list/claims-list.css',
            [],
            VBM_PLUGIN_VERSION
        );
        
        // Enqueue JS only if needed
        wp_enqueue_script(
            'vbm-claims-list',
            VBM_PLUGIN_URL . 'modules/widgets/claims-list/claims-list.js',
            ['jquery', 'vbm-widgets'],
            VBM_PLUGIN_VERSION,
            true
        );
    }
    
    public function register_shortcodes() {
        add_shortcode('claims', [$this, 'display_user_claims_shortcode']);
    }
    
    public function display_user_claims_shortcode($atts) {
        // Extract shortcode attributes
        $atts = shortcode_atts([
            'user' => '', // User ID parameter - optional
            'type' => '', // Post type parameter
        ], $atts);
        
        $user_id = !empty($atts['user']) ? intval($atts['user']) : get_current_user_id();
        $post_type = sanitize_text_field($atts['type']);
        
        // Handle case where user ID is provided but invalid
        if (!empty($atts['user']) && $user_id <= 0) {
            return '<div class="claim-error-container">
                        <p class="error-message">Invalid user ID specified.</p>
                    </div>';
        }
        
        // If no user is logged in and no valid user is specified, show login message
        if ($user_id <= 0) {
            return '<div class="claim-error-container">
                        <p class="error-message">Please log in to view claim requests.</p>
                    </div>';
        }
        
        global $wpdb;
        
        // Base SQL query to get claim requests
        $sql_base = "
            SELECT 
                o.id as order_id,
                o.created_at as date,
                o.details as order_details,
                oi.details as item_details,
                p.post_type as post_type
            FROM 
                {$wpdb->prefix}vx_orders o
            JOIN 
                {$wpdb->prefix}vx_order_items oi ON o.id = oi.order_id
            LEFT JOIN 
                {$wpdb->posts} p ON (
                    JSON_EXTRACT(o.details, '$.cart.items[*].product.post_id') LIKE CONCAT('%', p.ID, '%')
                )
            WHERE 
                o.customer_id = %d
                AND o.details LIKE %s
        ";
        
        // Add post type filter if specified
        $sql_params = array($user_id, '%voxel:claim%');
        
        if (!empty($post_type)) {
            $sql_base .= " AND p.post_type = %s";
            $sql_params[] = $post_type;
        }
        
        // Add order by clause
        $sql_base .= " ORDER BY o.created_at DESC";
        
        // Prepare the final SQL query
        $prepared_sql = $wpdb->prepare($sql_base, $sql_params);
        
        // Get all claim requests for the specified user
        $results = $wpdb->get_results($prepared_sql);
        
        if (empty($results)) {
            // Create message based on post type
            $message = 'You have not yet claimed any ';
            if (!empty($post_type)) {
                $post_type_obj = get_post_type_object($post_type);
                if ($post_type_obj) {
                    $message .= strtolower($post_type_obj->labels->name);
                } else {
                    $message .= $post_type;
                }
            } else {
                $message .= 'organization';
            }
            $message .= '. If your organization is in the directory, click the claim button on its profile to gain control of that listing.';
            
            return '<div class="no-claims-container">
                        <p class="no-claims-message">' . esc_html($message) . '</p>
                    </div>';
        }
        
        // Build output
        $output = '<div class="claim-requests-list">';
        $output .= '<table class="claim-requests-table">
                    <thead>
                        <tr>
                            <th class="date-column">Date</th>
                            <th class="post-column">Post</th>
                            <th class="status-column">Status</th>
                            <th class="id-column">Request ID</th>
                        </tr>
                    </thead>
                    <tbody>';
        
        foreach ($results as $row) {
            $order_details = json_decode($row->order_details, true);
            $item_details = json_decode($row->item_details, true);
            
            // Skip if JSON decode fails
            if (json_last_error() !== JSON_ERROR_NONE) {
                continue;
            }
            
            // Extract post ID from order details
            $post_id = null;
            if (isset($order_details['cart']['items'])) {
                foreach ($order_details['cart']['items'] as $item_key => $item) {
                    if (isset($item['product']['post_id']) && 
                        isset($item['product']['field_key']) && 
                        $item['product']['field_key'] === 'voxel:claim') {
                        $post_id = $item['product']['post_id'];
                        break;
                    }
                }
            }
            
            // Get post title
            $post_title = 'Unknown Post';
            if ($post_id) {
                $post = get_post($post_id);
                if ($post) {
                    $post_title = $post->post_title;
                    
                    // Check if post type matches the requested type (additional filter)
                    if (!empty($post_type) && $post->post_type !== $post_type) {
                        continue; // Skip this item if post type doesn't match
                    }
                }
            } elseif (isset($item_details['product']['label'])) {
                $post_title = $item_details['product']['label'];
            }
            
            // Determine claim status
            $status = 'Pending';
            $status_class = 'pending';
            if (isset($item_details['claim']['approved'])) {
                if ($item_details['claim']['approved'] === true) {
                    $status = 'Approved';
                    $status_class = 'approved';
                } else {
                    $status = 'Rejected';
                    $status_class = 'rejected';
                }
            }
            
            // Format date
            $date = date('Y-m-d', strtotime($row->date));
            
            // Create order URL
            $order_url = 'https://intellectual-property.org/account/manage/orders/?order_id=' . esc_attr($row->order_id);
            
            $output .= '<tr class="claim-request-item" data-order-id="' . esc_attr($row->order_id) . '" onclick="window.location.href=\'' . esc_js($order_url) . '\'">';
            $output .= '<td class="date-column">' . esc_html($date) . '</td>';
            $output .= '<td class="post-column">' . esc_html($post_title) . '</td>';
            $output .= '<td class="status-column"><div class="status-tag status-' . $status_class . '">' . esc_html($status) . '</div></td>';
            $output .= '<td class="id-column"><small>#' . esc_html($row->order_id) . '</small></td>';
            $output .= '</tr>';
        }
        
        $output .= '</tbody></table></div>';
        
        return $output;
    }
    
    public function render_documentation() {
        ?>
        <div class="widget-documentation">
            <p>Use the shortcode <code>[claims type=org]</code> to display claim requests.</p>
            
            <h4>Usage</h4>
            <p>The shortcode accepts the following attributes:</p>
            <ul>
                <li><strong>user</strong>: User ID (optional - defaults to current logged-in user)</li>
                <li><strong>type</strong>: Post type filter (optional)</li>
            </ul>
            
            <h4>Examples</h4>
            <pre>[claims]</pre>
            <p>This will display all claim requests for the current logged-in user.</p>
            
            <pre>[claims user=123 type=org]</pre>
            <p>This will display all claim requests for user with ID 123, filtered to only show claims for posts of type "org".</p>
        </div>
        <?php
    }
}
