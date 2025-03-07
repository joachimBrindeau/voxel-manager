<?php
/**
 * Bulk Manager Module
 * Description: Bulk editing of Voxel fields.
 */

if ( ! defined('ABSPATH') ) exit;

class VBM_Bulk_Manager_Module extends VBM_Module {

    public function __construct() {
        parent::__construct(
            'bulk_manager',
            'Bulk Field Manager',
            'Bulk editing of Voxel fields',
            '1.0.0'
        );
    }
    
    protected function register_hooks() {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_ajax_vbm_update_field_value', [$this, 'ajax_update_field_value']);
        add_action('wp_ajax_vbm_update_field_required', [$this, 'ajax_update_field_required']);
    }
    
    public function enqueue_admin_assets($hook) {
        // Only load on our admin page
        if (strpos($hook, 'voxel-bulk_manager') === false) {
            return;
        }

        wp_enqueue_style('datatables', 'https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css');
        wp_enqueue_script('datatables', 'https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js', ['jquery'], null, true);
        wp_enqueue_script('vbm-bulk-manager-js', VBM_PLUGIN_URL . 'modules/bulk-manager/bulk-manager.js', ['jquery','datatables'], VBM_PLUGIN_VERSION, true);
        wp_localize_script('vbm-bulk-manager-js', 'vbm_bulk_manager', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('vbm_bulk_manager_nonce'),
            'strings'  => [
                'saving' => __('Saving...', 'voxel-bulk-manager'),
                'saved'  => __('Saved', 'voxel-bulk-manager'),
                'error'  => __('Error', 'voxel-bulk-manager'),
            ],
        ]);
    }
    
    public function render_admin_page() {
        $post_types_data = get_option('voxel:post_types');
        $post_types = json_decode($post_types_data, true) ?: [];
        $field_types = $this->get_all_field_types($post_types);
        ?>
        <div class="wrap vbm-module-page">
            <div class="vbm-card">
                <h2><?php _e('Bulk Field Manager', 'voxel-bulk-manager'); ?></h2>
                <div class="vbm-filters">
                    <label for="post-type-filter"><?php _e('Post Type:', 'voxel-bulk-manager'); ?></label>
                    <select id="post-type-filter">
                        <option value=""><?php _e('All Post Types', 'voxel-bulk-manager'); ?></option>
                        <?php foreach ($post_types as $post_type => $data): ?>
                            <option value="<?php echo esc_attr($post_type); ?>">
                                <?php echo esc_html($data['settings']['singular'] ?? $post_type); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label for="field-type-filter"><?php _e('Field Type:', 'voxel-bulk-manager'); ?></label>
                    <select id="field-type-filter">
                        <option value=""><?php _e('All Field Types', 'voxel-bulk-manager'); ?></option>
                        <?php foreach ($field_types as $field_type): ?>
                            <option value="<?php echo esc_attr($field_type); ?>">
                                <?php echo esc_html(ucfirst(str_replace('-', ' ', $field_type))); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label for="field-search"><?php _e('Search:', 'voxel-bulk-manager'); ?></label>
                    <input type="text" id="field-search" placeholder="<?php _e('Search fields...', 'voxel-bulk-manager'); ?>">
                </div>
                <button id="copy-json"><?php _e('Copy as JSON', 'voxel-bulk-manager'); ?></button>
                <div class="vbm-table-container">
                    <table id="vbm-fields-table" class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Post Type', 'voxel-bulk-manager'); ?></th>
                                <th><?php _e('Field Key', 'voxel-bulk-manager'); ?></th>
                                <th><?php _e('Label', 'voxel-bulk-manager'); ?></th>
                                <th><?php _e('Type', 'voxel-bulk-manager'); ?></th>
                                <th><?php _e('Placeholder', 'voxel-bulk-manager'); ?></th>
                                <th><?php _e('Description', 'voxel-bulk-manager'); ?></th>
                                <th><?php _e('Required', 'voxel-bulk-manager'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            foreach ($post_types as $post_type => $post_type_data):
                                if (empty($post_type_data['fields'])) continue;
                                foreach ($post_type_data['fields'] as $field):
                                    if (in_array($field['type'], ['ui-step', 'ui-html'])) continue;
                            ?>
                            <tr data-post-type="<?php echo esc_attr($post_type); ?>" data-field-key="<?php echo esc_attr($field['key']); ?>">
                                <td><?php echo esc_html($post_type_data['settings']['singular'] ?? $post_type); ?></td>
                                <td><?php echo esc_html($field['key']); ?></td>
                                <td class="editable" data-field-property="label"><?php echo esc_html($field['label'] ?? ''); ?></td>
                                <td><?php echo esc_html($field['type']); ?></td>
                                <td class="editable" data-field-property="placeholder"><?php echo esc_html($field['placeholder'] ?? ''); ?></td>
                                <td class="editable" data-field-property="description"><?php echo esc_html($field['description'] ?? ''); ?></td>
                                <td>
                                    <?php if ( ! in_array($field['type'], ['ui-step', 'ui-html', 'title'])): ?>
                                        <label class="switch">
                                            <input type="checkbox" class="required-toggle" 
                                               data-post-type="<?php echo esc_attr($post_type); ?>" 
                                               data-field-key="<?php echo esc_attr($field['key']); ?>" 
                                               <?php checked(!empty($field['required'])); ?>>
                                            <span class="slider"></span>
                                        </label>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php 
                                endforeach;
                            endforeach; 
                            ?>
                        </tbody>
                    </table>
                </div>
                <div id="vbm-status-message"></div>
            </div>
        </div>
        <?php
    }
    
    public function ajax_update_field_value() {
        check_ajax_referer('vbm_bulk_manager_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized access', 'voxel-bulk-manager')]);
        }
        $post_type = sanitize_text_field($_POST['post_type'] ?? '');
        $field_key = sanitize_text_field($_POST['field_key'] ?? '');
        $field_property = sanitize_text_field($_POST['field_property'] ?? '');
        $value = sanitize_text_field($_POST['value'] ?? '');
        
        if (!$post_type || !$field_key || !$field_property) {
            wp_send_json_error(['message' => __('Missing parameters', 'voxel-bulk-manager')]);
        }
        
        $post_types = json_decode(get_option('voxel:post_types'), true);
        if (!isset($post_types[$post_type])) {
            wp_send_json_error(['message' => __('Post type not found', 'voxel-bulk-manager')]);
        }
        
        $field_found = false;
        foreach ($post_types[$post_type]['fields'] as &$field) {
            if ($field['key'] === $field_key) {
                $field_found = true;
                $field[$field_property] = $value;
                break;
            }
        }
        
        if (!$field_found) {
            wp_send_json_error(['message' => __('Field not found', 'voxel-bulk-manager')]);
        }
        
        if (update_option('voxel:post_types', wp_json_encode($post_types))) {
            wp_send_json_success(['message' => __('Field updated', 'voxel-bulk-manager')]);
        }
        wp_send_json_error(['message' => __('Update failed', 'voxel-bulk-manager')]);
    }
    
    public function ajax_update_field_required() {
        check_ajax_referer('vbm_bulk_manager_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized access', 'voxel-bulk-manager')]);
        }
        $post_type = sanitize_text_field($_POST['post_type'] ?? '');
        $field_key = sanitize_text_field($_POST['field_key'] ?? '');
        $value = rest_sanitize_boolean($_POST['value'] ?? false);
        
        if (!$post_type || !$field_key) {
            wp_send_json_error(['message' => __('Missing parameters', 'voxel-bulk-manager')]);
        }
        
        $post_types = json_decode(get_option('voxel:post_types'), true);
        if (!isset($post_types[$post_type])) {
            wp_send_json_error(['message' => __('Post type not found', 'voxel-bulk-manager')]);
        }
        
        $field_found = false;
        foreach ($post_types[$post_type]['fields'] as &$field) {
            if ($field['key'] === $field_key) {
                $field_found = true;
                if (in_array($field['type'], ['ui-step', 'ui-html', 'title'])) {
                    wp_send_json_error(['message' => __('Field type does not support required toggle', 'voxel-bulk-manager')]);
                }
                if ($value) {
                    $field['required'] = true;
                } else {
                    unset($field['required']);
                }
                break;
            }
        }
        
        if (!$field_found) {
            wp_send_json_error(['message' => __('Field not found', 'voxel-bulk-manager')]);
        }
        
        if (update_option('voxel:post_types', wp_json_encode($post_types))) {
            wp_send_json_success(['message' => __('Required status updated', 'voxel-bulk-manager')]);
        }
        wp_send_json_error(['message' => __('Update failed', 'voxel-bulk-manager')]);
    }
    
    private function get_all_field_types($post_types) {
        $field_types = [];
        foreach ($post_types as $data) {
            if (!empty($data['fields'])) {
                foreach ($data['fields'] as $field) {
                    if (!empty($field['type'])) {
                        $field_types[$field['type']] = true;
                    }
                }
            }
        }
        return array_keys($field_types);
    }
}

new VBM_Bulk_Manager_Module();