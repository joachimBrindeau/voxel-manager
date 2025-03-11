<?php
/**
 * Exclude Styles Module
 * 
 * Allows removing specific TinyMCE editor styles and controls based on CSS classes
 * added to Voxel field containers.
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class VBM_Exclude_Styles_Module extends VBM_Module {
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(
            'exclude_styles',
            'Style Manager',
            'Selectively remove formatting options from the TinyMCE editor in Voxel fields by adding special CSS classes.',
            '1.0.0'
        );
    }
    
    /**
     * Register hooks
     */
    protected function register_hooks() {
        // Enqueue scripts on admin and frontend pages
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        
        // Add debug information for admins
        if (current_user_can('manage_options') && is_admin()) {
            add_action('admin_notices', [$this, 'admin_notice']);
        }
        
        // Handle AJAX request to update field exclusions
        add_action('wp_ajax_vbm_update_field_exclusions', [$this, 'ajax_update_field_exclusions']);
    }
    
    /**
     * Enqueue admin assets
     * 
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_assets($hook) {
        $page = $_GET['page'] ?? '';
        
        if ($page === 'voxel-exclude_styles') {
            // Main stylesheet is already enqueued globally in main.php
            // Just enqueue other specific dependencies
            wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
            wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], null, true);
            
            // Add admin JS for handling the field exclusions UI
            wp_enqueue_script('vbm-exclude-styles-admin', VBM_PLUGIN_URL . 'modules/exclude-styles/exclude-styles-admin.js', ['jquery', 'select2'], VBM_PLUGIN_VERSION, true);
            
            // Localize script with available exclusion options and nonce
            wp_localize_script('vbm-exclude-styles-admin', 'vbmExcludeStylesAdmin', [
                'nonce' => wp_create_nonce('vbm_exclude_styles_nonce'),
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'exclusionOptions' => $this->get_exclusion_options()
            ]);
        }
    }
    
    /**
     * Show debug notice to admin users
     */
    public function admin_notice() {
        // Only show on TinyMCE-related screens
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->base, ['post', 'page', 'voxel_post_type'])) {
            return;
        }
        
        echo '<div class="notice notice-info is-dismissible">';
        echo '<p><strong>Exclude Styles Module:</strong> Active and monitoring for class-based exclusions. Add <code>exclude-{element}</code> classes to your fields to hide TinyMCE elements.</p>';
        echo '</div>';
    }
    
    /**
     * Enqueue scripts on both admin and frontend
     */
    public function enqueue_scripts() {
        // Only load on pages that might have TinyMCE editor
        $is_admin = is_admin();
        $screen = $is_admin ? get_current_screen() : null;
        
        // Load on standard admin edit screens
        $is_editor_screen = $screen && (
            method_exists($screen, 'is_block_editor') && $screen->is_block_editor() || 
            in_array($screen->base, ['post', 'page', 'voxel_post_type'])
        );
        
        // Also load on frontend when Voxel forms might be present
        $is_voxel_frontend = !$is_admin && (
            is_singular() || 
            (function_exists('vx_is_template') && vx_is_template('create')) ||
            isset($_GET['action']) && $_GET['action'] === 'edit'
        );
        
        if (!$is_editor_screen && !$is_voxel_frontend) {
            return;
        }
        
        // Enqueue script only - main styles already loaded
        wp_enqueue_script(
            'voxel-exclude-styles', 
            VBM_PLUGIN_URL . 'modules/exclude-styles/exclude-styles.js',
            ['jquery'],
            VBM_PLUGIN_VERSION,
            true
        );
        
        // Pass data to our script for better debugging
        wp_localize_script('voxel-exclude-styles', 'vbmExcludeStyles', [
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
            'isAdmin' => $is_admin,
            'version' => VBM_PLUGIN_VERSION
        ]);
    }
    
    /**
     * Get all available exclusion options
     * 
     * @return array List of exclusion options with name and class
     */
    private function get_exclusion_options() {
        return [
            ['name' => 'Heading 1', 'class' => 'exclude-h1'],
            ['name' => 'Heading 2', 'class' => 'exclude-h2'],
            ['name' => 'Heading 3', 'class' => 'exclude-h3'],
            ['name' => 'Heading 4', 'class' => 'exclude-h4'],
            ['name' => 'Heading 5', 'class' => 'exclude-h5'],
            ['name' => 'Heading 6', 'class' => 'exclude-h6'],
            ['name' => 'Paragraph', 'class' => 'exclude-p'],
            ['name' => 'Bold', 'class' => 'exclude-bold'],
            ['name' => 'Italic', 'class' => 'exclude-italic'],
            ['name' => 'Bulleted list', 'class' => 'exclude-bullist'],
            ['name' => 'Numbered list', 'class' => 'exclude-numlist'],
            ['name' => 'Insert/edit link', 'class' => 'exclude-link'],
            ['name' => 'Remove link', 'class' => 'exclude-unlink'],
            ['name' => 'Strikethrough', 'class' => 'exclude-strikethrough'],
            ['name' => 'Horizontal line', 'class' => 'exclude-hr'],
            ['name' => 'Blockquote', 'class' => 'exclude-blockquote'],
            ['name' => 'Preformatted', 'class' => 'exclude-pre'],
            ['name' => 'Format', 'class' => 'exclude-formatselect'],
            ['name' => 'Styles', 'class' => 'exclude-styleselect'],
            ['name' => 'Font Family', 'class' => 'exclude-fontselect'],
            ['name' => 'Font Sizes', 'class' => 'exclude-fontsizeselect'],
            ['name' => 'Align left', 'class' => 'exclude-alignleft'],
            ['name' => 'Align center', 'class' => 'exclude-aligncenter'],
            ['name' => 'Align right', 'class' => 'exclude-alignright'],
            ['name' => 'Text color', 'class' => 'exclude-forecolor'],
            ['name' => 'Background color', 'class' => 'exclude-backcolor']
        ];
    }
    
    /**
     * Extract exclusion classes from a field's CSS class string
     * 
     * @param string $css_class Field CSS class string
     * @return array List of exclusion classes
     */
    private function extract_exclusion_classes($css_class) {
        if (!$css_class) return [];
        
        $classes = explode(' ', $css_class);
        return array_filter($classes, function($class) {
            return strpos($class, 'exclude-') === 0;
        });
    }
    
    /**
     * Handle AJAX request to update field exclusions
     */
    public function ajax_update_field_exclusions() {
        check_ajax_referer('vbm_exclude_styles_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized access']);
        }
        
        $post_type = sanitize_text_field($_POST['post_type'] ?? '');
        $field_key = sanitize_text_field($_POST['field_key'] ?? '');
        $exclusions = isset($_POST['exclusions']) ? array_map('sanitize_text_field', $_POST['exclusions']) : [];
        
        if (empty($post_type) || empty($field_key)) {
            wp_send_json_error(['message' => 'Missing required parameters']);
        }
        
        // Get existing post types data
        $post_types_data = get_option('voxel:post_types');
        $post_types = json_decode($post_types_data, true);
        
        if (!isset($post_types[$post_type]['fields'])) {
            wp_send_json_error(['message' => 'Post type or field not found']);
        }
        
        // Find the field and update its CSS class
        $field_updated = false;
        foreach ($post_types[$post_type]['fields'] as &$field) {
            if ($field['key'] === $field_key) {
                // Get existing CSS classes (non-exclude ones)
                $existing_classes = [];
                if (!empty($field['css_class'])) {
                    $classes = explode(' ', $field['css_class']);
                    $existing_classes = array_filter($classes, function($class) {
                        return strpos($class, 'exclude-') !== 0;
                    });
                }
                
                // Combine existing non-exclude classes with new exclusions
                $new_class_str = implode(' ', array_merge($existing_classes, $exclusions));
                $field['css_class'] = $new_class_str;
                $field_updated = true;
                break;
            }
        }
        
        if ($field_updated) {
            update_option('voxel:post_types', json_encode($post_types));
            wp_send_json_success(['message' => 'Field exclusions updated']);
        } else {
            wp_send_json_error(['message' => 'Field not found']);
        }
    }
    
    /**
     * Render admin page with field exclusions table
     */
    public function render_admin_page() {
        // Get Voxel post types data
        $post_types_data = get_option('voxel:post_types');
        $post_types = json_decode($post_types_data, true);
        
        // Get exclusion options for select dropdowns
        $exclusion_options = $this->get_exclusion_options();
        
        // Debug information
        $field_count = 0;
        $text_editor_fields = 0;
        $field_types_found = [];
        
        // Debug mode for showing field types
        $show_field_types = defined('WP_DEBUG') && WP_DEBUG;
        ?>
        <p><?php _e('Configure which TinyMCE formatting options to exclude for each field.', 'voxel-bulk-manager'); ?></p>
        
        <div class="vbm-status-message" id="vbm-status-message" style="display:none;"></div>
        
        <?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
        <div class="notice notice-info">
            <p>Debug info: Found <?php echo is_array($post_types) ? count($post_types) : 0; ?> post types in Voxel configuration.</p>
            
            <!-- Add toggle button for field type debugging -->
            <p><button type="button" class="button" id="vbm-toggle-field-types">Show/Hide Field Types</button></p>
            
            <!-- This div will be populated with field types -->
            <div id="vbm-field-types-debug" style="display:none;">
                <p><strong>Loading field types...</strong></p>
            </div>
        </div>
        <?php endif; ?>
        
        <table class="wp-list-table widefat fixed striped" id="vbm-exclude-styles-table">
            <thead>
                <tr>
                    <th style="width: 20%;"><?php _e('CPT', 'voxel-bulk-manager'); ?></th>
                    <th style="width: 20%;"><?php _e('Field Name', 'voxel-bulk-manager'); ?></th>
                    <th style="width: 15%;"><?php _e('Field Key', 'voxel-bulk-manager'); ?></th>
                    <?php if ($show_field_types): ?>
                    <th style="width: 10%;"><?php _e('Field Type', 'voxel-bulk-manager'); ?></th>
                    <?php endif; ?>
                    <th style="width: <?php echo $show_field_types ? '35%' : '45%'; ?>;"><?php _e('Excluded Styles', 'voxel-bulk-manager'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Loop through post types and their fields
                if (is_array($post_types) && !empty($post_types)):
                    foreach ($post_types as $post_type_key => $post_type_data):
                        if (empty($post_type_data['fields'])) continue;
                        
                        $post_type_label = isset($post_type_data['settings']['singular']) ? 
                            $post_type_data['settings']['singular'] : $post_type_key;
                        
                        // Track all potential fields with rich text capabilities
                        $found_editor_fields = false;
                        
                        // Examine all fields from this post type
                        foreach ($post_type_data['fields'] as $field):
                            $field_count++;
                            
                            // Track all field types for debugging
                            $field_type = strtolower($field['type'] ?? '');
                            if (!isset($field_types_found[$field_type])) {
                                $field_types_found[$field_type] = 0;
                            }
                            $field_types_found[$field_type]++;
                            
                            // More flexible check for editor fields
                            // Including common variations of text editor field type names
                            $editor_type_patterns = [
                                'text-editor', 'texteditor', 'text_editor', 
                                'textarea', 'text-area', 'text_area',
                                'wp-editor', 'wpeditor', 'wp_editor', 
                                'description', 'editor', 'rich-text', 'richtext', 'rich_text'
                            ];
                            
                            $is_editor_field = false;
                            foreach ($editor_type_patterns as $pattern) {
                                if ($field_type === $pattern || strpos($field_type, $pattern) !== false) {
                                    $is_editor_field = true;
                                    break;
                                }
                            }
                            
                            // Skip non-editor fields
                            if (!$is_editor_field) continue;
                            
                            $text_editor_fields++;
                            $found_editor_fields = true;
                            
                            // Get any existing exclusion classes
                            $field_css_class = $field['css_class'] ?? '';
                            $current_exclusions = $this->extract_exclusion_classes($field_css_class);
                            ?>
                            <tr data-post-type="<?php echo esc_attr($post_type_key); ?>" data-field-key="<?php echo esc_attr($field['key']); ?>">
                                <td><?php echo esc_html($post_type_label); ?></td>
                                <td><?php echo esc_html($field['label'] ?? ''); ?></td>
                                <td><?php echo esc_html($field['key']); ?></td>
                                <?php if ($show_field_types): ?>
                                <td><code><?php echo esc_html($field['type'] ?? ''); ?></code></td>
                                <?php endif; ?>
                                <td>
                                    <select class="vbm-exclusion-selector" multiple="multiple" style="width: 100%;">
                                        <?php foreach ($exclusion_options as $option): ?>
                                            <option value="<?php echo esc_attr($option['class']); ?>"
                                                <?php selected(in_array($option['class'], $current_exclusions)); ?>>
                                                <?php echo esc_html($option['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <?php
                        endforeach;
                    endforeach;
                endif;
                
                // If no fields were found
                if ($text_editor_fields === 0):
                    ?>
                    <tr>
                        <td colspan="<?php echo $show_field_types ? '5' : '4'; ?>">
                            <div class="notice notice-warning inline">
                                <p><?php 
                                if ($field_count > 0) {
                                    _e('No text editor fields found. This module only works with text editor, textarea, wp-editor, or description field types.', 'voxel-bulk-manager');
                                    
                                    if ($show_field_types && !empty($field_types_found)) {
                                        echo '<br><strong>Field types found:</strong> ';
                                        $type_strings = [];
                                        foreach ($field_types_found as $type => $count) {
                                            $type_strings[] = "<code>$type</code> ($count)";
                                        }
                                        echo implode(', ', $type_strings);
                                    }
                                    
                                } else if (!is_array($post_types) || empty($post_types)) {
                                    _e('No Voxel post types found. Please make sure Voxel is properly configured.', 'voxel-bulk-manager');
                                } else {
                                    _e('No fields found in any post type.', 'voxel-bulk-manager');
                                }
                                ?></p>
                            </div>
                        </td>
                    </tr>
                    <?php
                endif;
                ?>
            </tbody>
        </table>
        
        <?php if ($text_editor_fields > 0): ?>
        <div class="vbm-table-note" style="margin-top: 15px;">
            <p><strong><?php _e('Note:', 'voxel-bulk-manager'); ?></strong> 
            <?php _e('Changes are saved automatically when modifying the excluded styles for a field.', 'voxel-bulk-manager'); ?></p>
        </div>
        <?php else: ?>
        <div class="vbm-table-note" style="margin-top: 15px;">
            <p><strong><?php _e('No Editor Fields Found:', 'voxel-bulk-manager'); ?></strong> 
            <?php _e('This module only applies to text editor fields in Voxel post types.', 'voxel-bulk-manager'); ?></p>
            <p><?php _e('To create a text editor field, go to your Voxel post type editor and add a "Text editor" field.', 'voxel-bulk-manager'); ?></p>
            
            <?php if ($show_field_types && !empty($field_types_found)): ?>
            <p><strong><?php _e('Field types in your Voxel configuration:', 'voxel-bulk-manager'); ?></strong></p>
            <ul>
                <?php foreach ($field_types_found as $type => $count): ?>
                <li><code><?php echo esc_html($type); ?></code>: <?php echo $count; ?> field(s)</li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
        <script>
            jQuery(document).ready(function($) {
                // Toggle field types debug information
                $('#vbm-toggle-field-types').on('click', function() {
                    $('#vbm-field-types-debug').toggle();
                    
                    if ($('#vbm-field-types-debug').is(':visible')) {
                        const fieldTypes = <?php echo json_encode($field_types_found); ?>;
                        let html = '<h4>Field Types Found:</h4><ul>';
                        
                        for (const type in fieldTypes) {
                            html += `<li><code>${type}</code>: ${fieldTypes[type]} field(s)</li>`;
                        }
                        
                        html += '</ul>';
                        html += '<p>If your text editor fields have a different type name than expected, ' +
                                'please check the code to include that type.</p>';
                                
                        $('#vbm-field-types-debug').html(html);
                    }
                });
            });
        </script>
        <?php endif; ?>
        <?php
    }
}