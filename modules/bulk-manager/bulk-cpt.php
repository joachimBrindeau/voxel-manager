<?php
/**
 * Bulk Manager CPT Module
 * Handles bulk editing for Voxel Custom Post Types.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class VBM_Bulk_Manager_CPT extends VBM_Bulk_Manager_Core {

    public function __construct() {
        parent::__construct();
        $this->setup_cpt_table_config();
    }
    
    /**
     * Sets up the table configuration for the CPT tab.
     */
    private function setup_cpt_table_config() {
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
        
        $this->table_configs['post_types'] = [
            'id'            => 'vbm-cpt-table',
            'title'         => __('Post Type Manager', 'voxel-bulk-manager'),
            'copy_btn_id'   => 'copy-cpt-json',
            'copy_btn_text' => __('Copy as JSON', 'voxel-bulk-manager'),
            'filters'       => $common_filters,
            'column_groups' => [
                [
                    'title'   => __('All CPT Properties', 'voxel-bulk-manager'),
                    'columns' => [
                        ['id' => 0, 'label' => __('Key', 'voxel-bulk-manager'), 'checked' => true],
                        ['id' => 1, 'label' => __('Singular', 'voxel-bulk-manager'), 'checked' => true],
                        ['id' => 2, 'label' => __('Plural', 'voxel-bulk-manager'), 'checked' => true],
                        ['id' => 3, 'label' => __('Slug', 'voxel-bulk-manager')],
                        ['id' => 4, 'label' => __('Icon', 'voxel-bulk-manager')],
                        ['id' => 5, 'label' => __('Timeline', 'voxel-bulk-manager')],
                        ['id' => 6, 'label' => __('Messages', 'voxel-bulk-manager')],
                        ['id' => 7, 'label' => __('Submissions Enabled', 'voxel-bulk-manager')],
                        ['id' => 8, 'label' => __('Submission Status', 'voxel-bulk-manager')],
                        ['id' => 9, 'label' => __('Update Status', 'voxel-bulk-manager')],
                        ['id' => 10, 'label' => __('Deletable', 'voxel-bulk-manager')]
                    ]
                ]
            ],
            'columns' => [
                ['label' => __('Key', 'voxel-bulk-manager')],
                ['label' => __('Singular', 'voxel-bulk-manager')],
                ['label' => __('Plural', 'voxel-bulk-manager')],
                ['label' => __('Slug', 'voxel-bulk-manager')],
                ['label' => __('Icon', 'voxel-bulk-manager')],
                ['label' => __('Timeline', 'voxel-bulk-manager')],
                ['label' => __('Messages', 'voxel-bulk-manager')],
                ['label' => __('Submissions Enabled', 'voxel-bulk-manager')],
                ['label' => __('Submission Status', 'voxel-bulk-manager')],
                ['label' => __('Update Status', 'voxel-bulk-manager')],
                ['label' => __('Deletable', 'voxel-bulk-manager')]
            ],
            'hidden_columns'   => [3,4,5,6,7,8,9,10],
            'orderable_columns'=> [0,1,2,3,4,7,8,9,10]
        ];
    }
    
    /**
     * Outputs the CPT tab content.
     */
    public function render_tab_content($post_types) {
        ?>
        <div class="vbm-tab-content <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'post_types') ? 'active' : ''; ?>" id="tab-post_types">
            <div class="vbm-card">
                <div class="vbm-card-header">
                    <h2><?php echo esc_html($this->table_configs['post_types']['title']); ?></h2>
                </div>
                <?php 
                // Pass $post_types for dynamic select options.
                $this->render_filters($this->table_configs['post_types']['filters'], $post_types);
                ?>
                <div class="vbm-table-top-actions">
                    <div class="vbm-action-bar-right">
                        <button id="<?php echo esc_attr($this->table_configs['post_types']['copy_btn_id']); ?>" class="button">
                            <?php echo esc_html($this->table_configs['post_types']['copy_btn_text']); ?>
                        </button>
                        <?php $this->render_column_selector('post_types', $this->table_configs['post_types']['column_groups']); ?>
                    </div>
                </div>
                <?php 
                // Pass column_groups so advanced columns are grouped and hidden by default
                $this->render_table_start(
                    $this->table_configs['post_types']['id'],
                    $this->table_configs['post_types']['columns'],
                    $this->table_configs['post_types']['hidden_columns'],
                    $this->table_configs['post_types']['column_groups']
                );
                $this->render_post_types_rows($post_types);
                $this->render_table_end();
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Renders the CPT table rows.
     */
    private function render_post_types_rows($post_types) {
        $hidden_columns = $this->table_configs['post_types']['hidden_columns'];
        
        foreach ($post_types as $post_type => $post_type_data):
            ?>
            <tr data-post-type="<?php echo esc_attr($post_type); ?>">
                <td><?php echo esc_html($post_type); ?></td>
                <td class="editable" data-cpt-property="singular"><?php echo esc_html($post_type_data['settings']['singular'] ?? ''); ?></td>
                <td class="editable" data-cpt-property="plural"><?php echo esc_html($post_type_data['settings']['plural'] ?? ''); ?></td>
                <td class="editable" <?php echo in_array(3, $hidden_columns) ? 'style="display:none;"' : ''; ?> data-cpt-property="slug"><?php echo esc_html($post_type_data['settings']['permalinks']['slug'] ?? ''); ?></td>
                <td <?php echo in_array(4, $hidden_columns) ? 'style="display:none;"' : ''; ?>><?php echo esc_html($post_type_data['settings']['icon'] ?? ''); ?></td>
                <td <?php echo in_array(5, $hidden_columns) ? 'style="display:none;"' : ''; ?>>
                    <label class="switch">
                        <input type="checkbox" class="timeline-toggle" data-post-type="<?php echo esc_attr($post_type); ?>" <?php checked(!empty($post_type_data['settings']['timeline']['enabled'])); ?>>
                        <span class="slider"></span>
                    </label>
                </td>
                <td <?php echo in_array(6, $hidden_columns) ? 'style="display:none;"' : ''; ?>>
                    <label class="switch">
                        <input type="checkbox" class="messages-toggle" data-post-type="<?php echo esc_attr($post_type); ?>" <?php checked(!empty($post_type_data['settings']['messages']['enabled'])); ?>>
                        <span class="slider"></span>
                    </label>
                </td>
                <td <?php echo in_array(7, $hidden_columns) ? 'style="display:none;"' : ''; ?>><?php echo esc_html(!empty($post_type_data['settings']['submission']['enabled']) ? 'Yes' : 'No'); ?></td>
                <td class="editable" <?php echo in_array(8, $hidden_columns) ? 'style="display:none;"' : ''; ?> data-cpt-property="submission_status"><?php echo esc_html($post_type_data['settings']['submission']['status'] ?? ''); ?></td>
                <td class="editable" <?php echo in_array(9, $hidden_columns) ? 'style="display:none;"' : ''; ?> data-cpt-property="update_status"><?php echo esc_html($post_type_data['settings']['submission']['update_status'] ?? ''); ?></td>
                <td <?php echo in_array(10, $hidden_columns) ? 'style="display:none;"' : ''; ?>>
                    <?php 
                        $deletable = $post_type_data['settings']['submission']['deletable'] ?? false;
                        echo esc_html(!empty($deletable) ? 'Yes' : 'No'); 
                    ?>
                </td>
            </tr>
            <?php 
        endforeach;
    }
}