<?php
/**
 * Bulk Manager Core Module
 * Handles common functionality for Bulk Manager.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

require_once __DIR__ . '/bulk-fields.php';
require_once __DIR__ . '/bulk-cpt.php';

class VBM_Bulk_Manager_Core extends VBM_Module {

    // Common table configuration data used by child modules.
    protected $table_configs = [];

    public function __construct() {
        parent::__construct(
            'bulk_manager',
            'Bulk Field Manager',
            'Bulk editing of Voxel fields',
            '1.0.0'
        );
        $this->setup_table_configs();
    }
    
    protected function register_hooks() {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_ajax_vbm_update_field_value', [$this, 'ajax_update_field_value']);
        add_action('wp_ajax_vbm_update_field_required', [$this, 'ajax_update_field_required']);
        add_action('wp_ajax_vbm_update_cpt_value', [$this, 'ajax_update_cpt_value']);
        add_action('wp_ajax_vbm_update_cpt_toggle', [$this, 'ajax_update_cpt_toggle']);
    }
    
    public function enqueue_admin_assets($hook) {
        // Only load on our admin page.
        if (strpos($hook, 'voxel-bulk_manager') === false) {
            return;
        }
        
        // Merge child module config based on current tab
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'fields';
        if ($current_tab === 'fields') {
            $fields_module = new VBM_Bulk_Manager_Fields();
            // Merge config so that 'fields' key is added
            $this->table_configs = array_merge($this->table_configs, $fields_module->table_configs);
        } elseif ($current_tab === 'post_types') {
            $cpt_module = new VBM_Bulk_Manager_CPT();
            $this->table_configs = array_merge($this->table_configs, $cpt_module->table_configs);
        }
        
        // Load DataTables core script but not the default stylesheet - we'll use WP styling
        wp_enqueue_script('datatables', 'https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js', ['jquery'], null, true);
        
        // Add custom inline CSS to handle specific DataTables elements that need styling
        wp_add_inline_style('vbm-style', '
            /* Make editable cells show cursor */
            .vbm-data-table td.editable { 
                cursor: pointer; 
            }
            
            /* Ensure consistency with WP tables when using DataTables */
            .dataTables_wrapper .vbm-data-table {
                width: 100% !important;
                border-spacing: 0;
            }
            
            /* Style the inline edit inputs */
            .inline-edit {
                width: 100%;
                box-sizing: border-box;
                padding: 5px 8px;
            }
            
            /* Fix any DataTables styling that conflicts with WP */
            table.dataTable thead th, table.dataTable tfoot th {
                font-weight: 600;
            }
            
            table.dataTable tbody tr {
                background-color: transparent;
            }
            
            .dataTables_wrapper .dataTables_filter {
                display: none;
            }
        ');
        
        wp_enqueue_script('vbm-bulk-manager-js', VBM_PLUGIN_URL . 'modules/bulk-manager/bulk-manager.js', ['jquery', 'datatables'], VBM_PLUGIN_VERSION, true);
        wp_localize_script('vbm-bulk-manager-js', 'vbm_bulk_manager', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('vbm_bulk_manager_nonce'),
            'strings'  => [
                'saving' => __('Saving...', 'voxel-bulk-manager'),
                'saved'  => __('Saved', 'voxel-bulk-manager'),
                'error'  => __('Error', 'voxel-bulk-manager'),
            ],
            'table_configs' => $this->table_configs,
            'current_tab' => $current_tab
        ]);
    }
    
    private function setup_table_configs() {
        // Setup common filters.
        $common_filters = [
            [
                'id'          => 'common-search',
                'label'       => __('Search:', 'voxel-bulk-manager'),
                'type'        => 'text',
                'placeholder' => __('Search...', 'voxel-bulk-manager')
            ],
            [
                'id'             => 'post-type-filter',
                'label'          => __('Post Type:', 'voxel-bulk-manager'),
                'type'           => 'select',
                'options'        => ['' => __('All Post Types', 'voxel-bulk-manager')],
                'dynamic_options'=> true,
                'data_source'    => 'post_types'
            ]
        ];

        // This core module does not fully define fields or CPT configurations.
        // Child modules (bulk-fields.php and bulk-cpt.php) will set up their specific table configs.
        $this->table_configs = [
            'common_filters' => $common_filters
        ];
    }

    /**
     * Populates dynamic filter options.
     * This common logic can be reused by child modules.
     */
    public function populate_filter_options($post_types) {
        $post_type_options = ['' => __('All Post Types', 'voxel-bulk-manager')];
        foreach ($post_types as $post_type => $data) {
            $post_type_options[$post_type] = isset($data['settings']['singular']) ? 
                $data['settings']['singular'] : $post_type;
        }
        // Child modules can merge these options into their own filter configurations.
    }

    /**
     * Renders the common admin page structure.
     * Child modules will output tab-specific content.
     */
    public function render_admin_page() {
        $post_types_data = get_option('voxel:post_types');
        $post_types = json_decode($post_types_data, true) ?: [];
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'fields';
        
        // Update dynamic filters; child modules may override this.
        $this->populate_filter_options($post_types);
        ?>
        <div class="wrap vbm-module-page">
            <div class="vbm-tab-wrapper">
                <h2 class="nav-tab-wrapper">
                    <a href="?page=voxel-bulk_manager&tab=fields" class="nav-tab <?php echo $current_tab === 'fields' ? 'nav-tab-active' : ''; ?>">
                        <?php _e('Field Management', 'voxel-bulk-manager'); ?>
                    </a>
                    <a href="?page=voxel-bulk_manager&tab=post_types" class="nav-tab <?php echo $current_tab === 'post_types' ? 'nav-tab-active' : ''; ?>">
                        <?php _e('CPT Management', 'voxel-bulk-manager'); ?>
                    </a>
                </h2>
            </div>
            
            <div id="vbm-status-message" class="vbm-status-message"></div>
            
            <?php
            // Delegate to the proper child module
            if ($current_tab === 'fields') { 
                // Instantiate the Fields module and render its tab
                $fields_module = new VBM_Bulk_Manager_Fields();
                $fields_module->render_tab_content($post_types);
            } elseif ($current_tab === 'post_types') {
                // Instantiate the CPT module and render its tab
                $cpt_module = new VBM_Bulk_Manager_CPT();
                $cpt_module->render_tab_content($post_types);
            }
            ?>
        </div>
        <?php
    }

    // The following AJAX handlers use common security checks.
    // Their specific implementations will be provided in the child modules.

    public function ajax_update_field_value() {
        check_ajax_referer('vbm_bulk_manager_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized access', 'voxel-bulk-manager')]);
        }
        
        // Get the post type, field key, and new value from the request
        $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : '';
        $field_key = isset($_POST['field_key']) ? sanitize_text_field($_POST['field_key']) : '';
        $field_property = isset($_POST['field_property']) ? sanitize_text_field($_POST['field_property']) : '';
        $value = isset($_POST['value']) ? sanitize_text_field($_POST['value']) : '';
        
        if (empty($post_type) || empty($field_key) || empty($field_property)) {
            wp_send_json_error(['message' => __('Missing required parameters', 'voxel-bulk-manager')]);
        }
        
        // Get post types data
        $post_types_data = get_option('voxel:post_types');
        $post_types = json_decode($post_types_data, true);
        
        if (!isset($post_types[$post_type]) || !isset($post_types[$post_type]['fields'])) {
            wp_send_json_error(['message' => __('Post type or fields not found', 'voxel-bulk-manager')]);
        }
        
        // Find the field to update
        $field_updated = false;
        foreach ($post_types[$post_type]['fields'] as $index => $field) {
            if ($field['key'] === $field_key) {
                $post_types[$post_type]['fields'][$index][$field_property] = $value;
                $field_updated = true;
                break;
            }
        }
        
        if (!$field_updated) {
            wp_send_json_error(['message' => __('Field not found', 'voxel-bulk-manager')]);
        }
        
        // Save updated post types
        update_option('voxel:post_types', wp_json_encode($post_types));
        
        wp_send_json_success(['message' => __('Field updated successfully', 'voxel-bulk-manager')]);
    }
    
    public function ajax_update_field_required() {
        check_ajax_referer('vbm_bulk_manager_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized access', 'voxel-bulk-manager')]);
        }
        
        // Get the post type, field key, and new value from the request
        $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : '';
        $field_key = isset($_POST['field_key']) ? sanitize_text_field($_POST['field_key']) : '';
        $value = isset($_POST['value']) ? filter_var($_POST['value'], FILTER_VALIDATE_BOOLEAN) : false;
        
        if (empty($post_type) || empty($field_key)) {
            wp_send_json_error(['message' => __('Missing required parameters', 'voxel-bulk-manager')]);
        }
        
        // Get post types data
        $post_types_data = get_option('voxel:post_types');
        $post_types = json_decode($post_types_data, true);
        
        if (!isset($post_types[$post_type]) || !isset($post_types[$post_type]['fields'])) {
            wp_send_json_error(['message' => __('Post type or fields not found', 'voxel-bulk-manager')]);
        }
        
        // Find the field to update
        $field_updated = false;
        foreach ($post_types[$post_type]['fields'] as $index => $field) {
            if ($field['key'] === $field_key) {
                $post_types[$post_type]['fields'][$index]['required'] = $value;
                $field_updated = true;
                break;
            }
        }
        
        if (!$field_updated) {
            wp_send_json_error(['message' => __('Field not found', 'voxel-bulk-manager')]);
        }
        
        // Save updated post types
        update_option('voxel:post_types', wp_json_encode($post_types));
        
        wp_send_json_success(['message' => __('Field updated successfully', 'voxel-bulk-manager')]);
    }
    
    public function ajax_update_cpt_value() {
        check_ajax_referer('vbm_bulk_manager_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized access', 'voxel-bulk-manager')]);
        }
        
        // Get the post type, property, and new value from the request
        $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : '';
        $property = isset($_POST['property']) ? sanitize_text_field($_POST['property']) : '';
        $value = isset($_POST['value']) ? sanitize_text_field($_POST['value']) : '';
        
        if (empty($post_type) || empty($property)) {
            wp_send_json_error(['message' => __('Missing required parameters', 'voxel-bulk-manager')]);
        }
        
        // Get post types data
        $post_types_data = get_option('voxel:post_types');
        $post_types = json_decode($post_types_data, true);
        
        if (!isset($post_types[$post_type])) {
            wp_send_json_error(['message' => __('Post type not found', 'voxel-bulk-manager')]);
        }
        
        // Update the property based on what it is
        switch ($property) {
            case 'singular':
                $post_types[$post_type]['settings']['singular'] = $value;
                break;
                
            case 'plural':
                $post_types[$post_type]['settings']['plural'] = $value;
                break;
                
            case 'slug':
                $post_types[$post_type]['settings']['permalinks']['slug'] = $value;
                break;
                
            case 'submission_status':
                $post_types[$post_type]['settings']['submission']['status'] = $value;
                break;
                
            case 'update_status':
                $post_types[$post_type]['settings']['submission']['update_status'] = $value;
                break;
                
            default:
                wp_send_json_error(['message' => __('Unsupported property', 'voxel-bulk-manager')]);
                break;
        }
        
        // Save updated post types
        update_option('voxel:post_types', wp_json_encode($post_types));
        
        wp_send_json_success(['message' => __('Post type updated successfully', 'voxel-bulk-manager')]);
    }
    
    public function ajax_update_cpt_toggle() {
        check_ajax_referer('vbm_bulk_manager_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized access', 'voxel-bulk-manager')]);
        }
        
        // Get the post type, property, and new value from the request
        $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : '';
        $property = isset($_POST['property']) ? sanitize_text_field($_POST['property']) : '';
        $value = isset($_POST['value']) ? filter_var($_POST['value'], FILTER_VALIDATE_BOOLEAN) : false;
        
        if (empty($post_type) || empty($property)) {
            wp_send_json_error(['message' => __('Missing required parameters', 'voxel-bulk-manager')]);
        }
        
        // Get post types data
        $post_types_data = get_option('voxel:post_types');
        $post_types = json_decode($post_types_data, true);
        
        if (!isset($post_types[$post_type])) {
            wp_send_json_error(['message' => __('Post type not found', 'voxel-bulk-manager')]);
        }
        
        // Update the property based on what it is
        switch ($property) {
            case 'timeline':
                if (!isset($post_types[$post_type]['settings']['timeline'])) {
                    $post_types[$post_type]['settings']['timeline'] = [];
                }
                $post_types[$post_type]['settings']['timeline']['enabled'] = $value;
                break;
                
            case 'messages':
                if (!isset($post_types[$post_type]['settings']['messages'])) {
                    $post_types[$post_type]['settings']['messages'] = [];
                }
                $post_types[$post_type]['settings']['messages']['enabled'] = $value;
                break;
                
            default:
                wp_send_json_error(['message' => __('Unsupported property', 'voxel-bulk-manager')]);
                break;
        }
        
        // Save updated post types
        update_option('voxel:post_types', wp_json_encode($post_types));
        
        wp_send_json_success(['message' => __('Post type updated successfully', 'voxel-bulk-manager')]);
    }

    // Add a helper to return post type options
    protected function get_post_type_options($post_types) {
        $opts = ['' => __('All Post Types', 'voxel-bulk-manager')];
        foreach ($post_types as $post_type => $data) {
            $opts[$post_type] = isset($data['settings']['singular']) ? $data['settings']['singular'] : $post_type;
        }
        return $opts;
    }

    /**
     * Renders a standardized filters UI.
     * Now accepts $post_types to fill dynamic options.
     */
    protected function render_filters($filters, $post_types = []) {
        if (empty($filters)) return;
        ?>
        <div class="vbm-filters-bar">
            <?php foreach ($filters as $filter):
                // If dynamic options requested, update options accordingly.
                if (isset($filter['dynamic_options'], $filter['data_source']) 
                    && $filter['dynamic_options'] && $filter['data_source'] === 'post_types' 
                    && !empty($post_types)) {
                    $filter['options'] = $this->get_post_type_options($post_types);
                }
                ?>
                <div class="vbm-filter-item">
                    <label for="<?php echo esc_attr($filter['id']); ?>"><?php echo $filter['label']; ?></label>
                    <?php if ($filter['type'] === 'select'): ?>
                        <select id="<?php echo esc_attr($filter['id']); ?>" class="vbm-filter-select">
                            <?php foreach ($filter['options'] as $value => $label): ?>
                                <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php elseif ($filter['type'] === 'text'): ?>
                        <input type="text" id="<?php echo esc_attr($filter['id']); ?>" placeholder="<?php echo esc_attr($filter['placeholder'] ?? ''); ?>">
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <div class="vbm-filter-item vbm-filter-actions">
                <label>&nbsp;</label>
                <button id="reset-filters" type="button" class="button button-secondary">
                    <?php _e('Reset Filters', 'voxel-bulk-manager'); ?>
                </button>
            </div>
        </div>
        <?php
    }
    
    /**
     * Renders the column selector UI.
     * Now wraps each checkbox in a switch markup.
     */
    protected function render_column_selector($table_type = null, $column_groups = null) {
        ?>
        <div class="vbm-column-selector">
            <button type="button" class="button button-secondary button-select-columns">
                <?php _e('Select columns', 'voxel-bulk-manager'); ?>
            </button>
            <div class="vbm-column-selector-dropdown">
                <?php if (!empty($column_groups)): ?>
                    <?php foreach ($column_groups as $group): ?>
                        <div class="vbm-column-selector-group">
                            <div class="vbm-column-selector-group-title"><?php echo esc_html($group['title']); ?></div>
                            <?php foreach ($group['columns'] as $index => $column): ?>
                                <div class="vbm-column-option">
                                    <label class="switch">
                                        <?php 
                                          // Use group column index if the configured "id" does not match the actual order.
                                          $col_index = isset($column['id']) ? $column['id'] : $index;
                                        ?>
                                        <input type="checkbox" data-column="<?php echo esc_attr($col_index); ?>" data-table="<?php echo esc_attr($table_type); ?>" 
                                        <?php 
                                            echo (!empty($column['checked']) && !in_array($col_index, $this->table_configs[$table_type]['hidden_columns'])
                                                ? 'checked' 
                                                : '');
                                        ?>>
                                        <span class="slider"></span>
                                    </label>
                                    <span style="margin-left:8px; font-size:13px;"><?php echo esc_html($column['label']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    // Added methods for rendering table markup used by child modules.
    // Modified method to render just the column headers without the group titles
    protected function render_table_start($table_id, $columns, $hidden_columns = [], $column_groups = null) {
        echo "<table id='" . esc_attr($table_id) . "' class='vbm-data-table wp-list-table widefat fixed striped'>";
        echo "<thead><tr>";
        foreach ($columns as $index => $column) {
            $style = in_array($index, $hidden_columns) ? " style='display:none;'" : "";
            echo "<th scope='col' class='manage-column' data-column='{$index}'{$style}>" . esc_html($column['label']) . "</th>";
        }
        echo "</tr></thead>";
        echo "<tbody>";
    }

    protected function render_table_end() {
        echo "</tbody></table>";
    }
}