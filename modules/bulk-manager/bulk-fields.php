<?php
/**
 * Bulk Manager Fields Module
 * Handles bulk editing for Voxel Fields.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class VBM_Bulk_Manager_Fields extends VBM_Bulk_Manager_Core {

    public function __construct() {
        parent::__construct();
        $this->setup_fields_table_config();
    }
    
    /**
     * Sets up the table configuration for the Fields tab.
     */
    private function setup_fields_table_config() {
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
        
        $this->table_configs['fields'] = [
            'id'            => 'vbm-fields-table',
            'title'         => __('Bulk Field Manager', 'voxel-bulk-manager'),
            'copy_btn_id'   => 'copy-json',
            'copy_btn_text' => __('Copy as JSON', 'voxel-bulk-manager'),
            'filters'       => $common_filters,
            'column_groups' => [
                [
                    'title'   => __('All Field Properties', 'voxel-bulk-manager'),
                    'columns' => [
                        ['id' => 0, 'label' => __('Post Type', 'voxel-bulk-manager'), 'checked' => true],
                        ['id' => 1, 'label' => __('Field Key', 'voxel-bulk-manager'), 'checked' => true],
                        ['id' => 2, 'label' => __('Label', 'voxel-bulk-manager'), 'checked' => true],
                        ['id' => 3, 'label' => __('Type', 'voxel-bulk-manager')],
                        ['id' => 4, 'label' => __('Placeholder', 'voxel-bulk-manager')],
                        ['id' => 5, 'label' => __('Description', 'voxel-bulk-manager')],
                        ['id' => 6, 'label' => __('Required', 'voxel-bulk-manager')],
                        ['id' => 7, 'label' => __('Min Length', 'voxel-bulk-manager')],
                        ['id' => 8, 'label' => __('Max Length', 'voxel-bulk-manager')],
                        ['id' => 9, 'label' => __('CSS Class', 'voxel-bulk-manager')],
                        ['id' => 10, 'label' => __('Editor Type', 'voxel-bulk-manager')]
                    ]
                ]
            ],
            'columns' => [
                ['label' => __('Post Type', 'voxel-bulk-manager')],
                ['label' => __('Field Key', 'voxel-bulk-manager')],
                ['label' => __('Label', 'voxel-bulk-manager')],
                ['label' => __('Type', 'voxel-bulk-manager')],
                ['label' => __('Placeholder', 'voxel-bulk-manager')],
                ['label' => __('Description', 'voxel-bulk-manager')],
                ['label' => __('Required', 'voxel-bulk-manager')],
                ['label' => __('Min Length', 'voxel-bulk-manager')],
                ['label' => __('Max Length', 'voxel-bulk-manager')],
                ['label' => __('CSS Class', 'voxel-bulk-manager')],
                ['label' => __('Editor Type', 'voxel-bulk-manager')]
            ],
            'hidden_columns'   => [3,4,5,6,7,8,9,10],
            'orderable_columns'=> [0,1,2,3,4,5,7,8,9,10]
        ];
    }
    
    /**
     * Outputs the Fields tab content.
     */
    public function render_tab_content($post_types) {
        ?>
        <div class="vbm-tab-content active" id="tab-fields">
            <div class="vbm-card">
                <div class="vbm-card-header">
                    <h2><?php echo esc_html($this->table_configs['fields']['title']); ?></h2>
                </div>
                <?php 
                // Pass $post_types into render_filters() for dynamic options.
                $this->render_filters($this->table_configs['fields']['filters'], $post_types);
                ?>
                <div class="vbm-table-top-actions">
                    <div class="vbm-action-bar-right">
                        <button id="<?php echo esc_attr($this->table_configs['fields']['copy_btn_id']); ?>" class="button">
                            <?php echo esc_html($this->table_configs['fields']['copy_btn_text']); ?>
                        </button>
                        <?php $this->render_column_selector('fields', $this->table_configs['fields']['column_groups']); ?>
                    </div>
                </div>
                <?php 
                // Pass column_groups to render multi-level headers
                $this->render_table_start(
                    $this->table_configs['fields']['id'], 
                    $this->table_configs['fields']['columns'], 
                    $this->table_configs['fields']['hidden_columns'],
                    $this->table_configs['fields']['column_groups']
                );
                $this->render_fields_rows($post_types);
                $this->render_table_end();
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Renders the table rows for fields.
     */
    protected function render_fields_rows($post_types) {
        $hidden_columns = $this->table_configs['fields']['hidden_columns'];
        
        foreach ($post_types as $post_type => $post_type_data):
            if (empty($post_type_data['fields'])) continue;
            foreach ($post_type_data['fields'] as $field):
                if (in_array($field['type'], ['ui-step', 'ui-html'])) continue;
                ?>
                <tr data-post-type="<?php echo esc_attr($post_type); ?>" data-field-key="<?php echo esc_attr($field['key']); ?>">
                    <!-- Render all columns but apply CSS to hide those in hidden_columns -->
                    <td><?php echo esc_html($post_type_data['settings']['singular'] ?? $post_type); ?></td>
                    <td><?php echo esc_html($field['key']); ?></td>
                    <td class="editable" data-field-property="label"><?php echo esc_html($field['label'] ?? ''); ?></td>
                    <td <?php echo in_array(3, $hidden_columns) ? 'style="display:none;"' : ''; ?>><?php echo esc_html($field['type']); ?></td>
                    <td class="editable" <?php echo in_array(4, $hidden_columns) ? 'style="display:none;"' : ''; ?> data-field-property="placeholder"><?php echo esc_html($field['placeholder'] ?? ''); ?></td>
                    <td class="editable" <?php echo in_array(5, $hidden_columns) ? 'style="display:none;"' : ''; ?> data-field-property="description"><?php echo esc_html($field['description'] ?? ''); ?></td>
                    <td <?php echo in_array(6, $hidden_columns) ? 'style="display:none;"' : ''; ?>>
                        <label class="switch">
                            <input type="checkbox" class="required-toggle" data-post-type="<?php echo esc_attr($post_type); ?>" data-field-key="<?php echo esc_attr($field['key']); ?>" <?php checked(!empty($field['required'])); ?>>
                            <span class="slider"></span>
                        </label>
                    </td>
                    <td class="editable" <?php echo in_array(7, $hidden_columns) ? 'style="display:none;"' : ''; ?> data-field-property="minlength"><?php echo esc_html($field['minlength'] ?? ''); ?></td>
                    <td class="editable" <?php echo in_array(8, $hidden_columns) ? 'style="display:none;"' : ''; ?> data-field-property="maxlength"><?php echo esc_html($field['maxlength'] ?? ''); ?></td>
                    <td class="editable" <?php echo in_array(9, $hidden_columns) ? 'style="display:none;"' : ''; ?> data-field-property="css_class"><?php echo esc_html($field['css_class'] ?? ''); ?></td>
                    <td <?php echo in_array(10, $hidden_columns) ? 'style="display:none;"' : ''; ?>><?php echo esc_html($field['editor-type'] ?? ''); ?></td>
                </tr>
                <?php 
            endforeach;
        endforeach;
    }
}