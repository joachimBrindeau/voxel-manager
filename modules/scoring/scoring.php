<?php
/**
 * Scoring Module
 * Description: Calculates and updates post completeness scores based on field values.
 */

if ( ! defined('ABSPATH') ) exit;

class VBM_Scoring_Module extends VBM_Module {

    public function __construct() {
        parent::__construct(
            'scoring',
            'Post Scoring',
            'Calculates and updates post completeness scores based on field values',
            '1.0.0'
        );
    }
    
    protected function register_hooks() {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('save_post', [$this, 'update_post_completeness_score'], 10, 3);
        add_action('wp_ajax_vbm_recalculate_scores', [$this, 'ajax_recalculate_scores']);
        add_action('wp_ajax_vbm_update_all_scores', [$this, 'ajax_update_all_scores']);
        add_action('wp_ajax_vbm_update_field_score', [$this, 'ajax_update_field_score']);
        add_action('wp_ajax_vbm_update_target_score', [$this, 'ajax_update_target_score']);
    }
    
    public function enqueue_admin_assets($hook) {
        // Only load on our admin page
        if (strpos($hook, 'voxel-scoring') === false) {
            return;
        }

        wp_enqueue_style('datatables', 'https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css');
        wp_enqueue_script('datatables', 'https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js', ['jquery'], null, true);
        wp_enqueue_script('vbm-scoring-js', VBM_PLUGIN_URL . 'modules/scoring/scoring.js', ['jquery', 'datatables'], VBM_PLUGIN_VERSION, true);
        wp_localize_script('vbm-scoring-js', 'vbm_scoring', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('vbm_scoring_nonce'),
            'strings'  => [
                'calculating' => __('Calculating...', 'voxel-bulk-manager'),
                'completed'   => __('Calculation completed', 'voxel-bulk-manager'),
                'saving'      => __('Saving...', 'voxel-bulk-manager'),
                'saved'       => __('Saved', 'voxel-bulk-manager'),
                'error'       => __('Error', 'voxel-bulk-manager'),
            ],
            'target_scores' => get_option('vbm_target_scores', [])
        ]);
    }
    
    public function update_post_completeness_score($post_id, $post, $update) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id)) return;
        
        $raw_settings = get_option('voxel:post_types');
        if (!$raw_settings) return;
        $voxel_settings = json_decode($raw_settings, true);
        if (!is_array($voxel_settings)) return;
        
        $post_type = $post->post_type;
        if (!isset($voxel_settings[$post_type])) return;
        
        $fields = isset($voxel_settings[$post_type]['fields']) ? $voxel_settings[$post_type]['fields'] : array();
        $has_score_field = false;
        
        foreach ($fields as $field) {
            if (isset($field['key']) && $field['key'] === 'score') {
                $has_score_field = true;
                break;
            }
        }
        if (!$has_score_field) return;

        // This logic sums up the score values of completed fields
        $total_score = 0;
        foreach ($fields as $field) {
            if (empty($field['css_class'])) continue;
            if (preg_match('/score-(\d+)/', $field['css_class'], $matches)) {
                $score_value = intval($matches[1]);
                $field_key = $field['key'];
                
                $value = null;
                switch ($field_key) {
                    case 'title':
                        $value = $post->post_title;
                        break;
                    case 'content':
                        $value = $post->post_content;
                        break;
                    case 'excerpt':
                        $value = $post->post_excerpt;
                        break;
                    case '_thumbnail_id':
                        $value = has_post_thumbnail($post_id) ? get_post_thumbnail_id($post_id) : '';
                        break;
                    default:
                        $value = get_post_meta($post_id, $field_key, true);
                }
                // Only adds the score if the field has a value
                if (!empty($value)) {
                    $total_score += $score_value;
                }
            }
        }
        
        // Calculate score against target score if set
        $target_scores = get_option('vbm_target_scores', []);
        $target_score = isset($target_scores[$post_type]) ? intval($target_scores[$post_type]) : 0;
        
        if ($target_score > 0) {
            // Calculate percentage and round to nearest integer
            $relative_score = round(($total_score / $target_score) * 100);
            // Cap at 100
            $total_score = min(100, $relative_score);
        }
        
        update_post_meta($post_id, 'score', $total_score);
    }
    
    public function update_all_post_scores() {
        $raw_settings = get_option('voxel:post_types');
        if (!$raw_settings) return;
        $voxel_settings = json_decode($raw_settings, true);
        if (!is_array($voxel_settings)) return;
        
        $updated_count = 0;
        
        foreach ($voxel_settings as $post_type => $config) {
            $fields = isset($config['fields']) ? $config['fields'] : array();
            $has_score_field = false;
            foreach ($fields as $field) {
                if (isset($field['key']) && $field['key'] === 'score') {
                    $has_score_field = true;
                    break;
                }
            }
            if (!$has_score_field) continue;
            
            $posts = get_posts([
                'post_type' => $post_type,
                'posts_per_page' => -1,
                'post_status' => 'any'
            ]);
            
            if (empty($posts)) continue;
            
            foreach ($posts as $post) {
                $this->update_post_completeness_score($post->ID, $post, false);
                $updated_count++;
            }
        }
        
        return $updated_count;
    }
    
    public function ajax_recalculate_scores() {
        check_ajax_referer('vbm_scoring_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized access', 'voxel-bulk-manager')]);
        }
        
        $count = $this->update_all_post_scores();
        
        if ($count !== false) {
            wp_send_json_success([
                'message' => sprintf(__('Recalculated scores for %d posts', 'voxel-bulk-manager'), $count),
                'count' => $count
            ]);
        } else {
            wp_send_json_error(['message' => __('Failed to recalculate scores', 'voxel-bulk-manager')]);
        }
    }
    
    public function ajax_update_all_scores() {
        check_ajax_referer('vbm_scoring_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized access', 'voxel-bulk-manager')]);
        }
        
        $count = $this->update_all_post_scores();
        
        if ($count !== false) {
            wp_send_json_success([
                'message' => sprintf(__('Updated scores for %d posts', 'voxel-bulk-manager'), $count),
                'count' => $count
            ]);
        } else {
            wp_send_json_error(['message' => __('Failed to update scores', 'voxel-bulk-manager')]);
        }
    }
    
    public function ajax_update_field_score() {
        check_ajax_referer('vbm_scoring_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized access', 'voxel-bulk-manager')]);
        }
        
        $post_type = sanitize_text_field($_POST['post_type'] ?? '');
        $field_key = sanitize_text_field($_POST['field_key'] ?? '');
        $score = intval($_POST['score'] ?? 0);
        
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
                
                // Remove any existing score class
                $css_classes = !empty($field['css_class']) ? explode(' ', $field['css_class']) : [];
                $css_classes = array_filter($css_classes, function($class) {
                    return !preg_match('/^score-\d+$/', $class);
                });
                
                // Add new score class if score > 0
                if ($score > 0) {
                    $css_classes[] = "score-{$score}";
                }
                
                $field['css_class'] = implode(' ', $css_classes);
                break;
            }
        }
        
        if (!$field_found) {
            wp_send_json_error(['message' => __('Field not found', 'voxel-bulk-manager')]);
        }
        
        if (update_option('voxel:post_types', wp_json_encode($post_types))) {
            wp_send_json_success([
                'message' => __('Field score updated', 'voxel-bulk-manager'),
                'css_class' => implode(' ', $css_classes)
            ]);
        }
        
        wp_send_json_error(['message' => __('Update failed', 'voxel-bulk-manager')]);
    }
    
    public function ajax_update_target_score() {
        check_ajax_referer('vbm_scoring_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized access', 'voxel-bulk-manager')]);
        }
        
        $post_type = sanitize_text_field($_POST['post_type'] ?? '');
        $score = intval($_POST['score'] ?? 0);
        
        if (empty($post_type)) {
            wp_send_json_error(['message' => __('Missing post type parameter', 'voxel-bulk-manager')]);
            return;
        }
        
        $target_scores = get_option('vbm_target_scores', []);
        $target_scores[$post_type] = $score;
        
        if (update_option('vbm_target_scores', $target_scores)) {
            wp_send_json_success([
                'message' => sprintf(__('Target score for %s updated to %d', 'voxel-bulk-manager'), 
                    $post_type, $score)
            ]);
        } else {
            wp_send_json_error(['message' => __('Failed to update target score', 'voxel-bulk-manager')]);
        }
    }
    
    // Helper function to extract score from CSS classes
    private function extract_score_from_css_class($css_class) {
        if (empty($css_class)) return 0;
        
        if (preg_match('/score-(\d+)/', $css_class, $matches)) {
            return intval($matches[1]);
        }
        
        return 0;
    }
    
    // Helper function to calculate potential max score for a post type
    private function calculate_potential_max_score($post_type) {
        $post_types = json_decode(get_option('voxel:post_types'), true);
        if (!isset($post_types[$post_type]['fields'])) {
            return 0;
        }
        
        $total = 0;
        foreach ($post_types[$post_type]['fields'] as $field) {
            $score = $this->extract_score_from_css_class($field['css_class'] ?? '');
            $total += $score;
        }
        
        return $total;
    }
    
    public function render_admin_page() {
        $post_types_data = get_option('voxel:post_types');
        $post_types = json_decode($post_types_data, true) ?: [];
        
        // Check which post types have score field
        $post_types_with_score = [];
        foreach ($post_types as $post_type => $data) {
            if (!empty($data['fields'])) {
                foreach ($data['fields'] as $field) {
                    if (isset($field['key']) && $field['key'] === 'score') {
                        $post_types_with_score[$post_type] = $data['settings']['singular'] ?? $post_type;
                        break;
                    }
                }
            }
        }
        
        // Get post types with scoring fields
        $post_types_with_scoring = [];
        foreach ($post_types as $post_type => $data) {
            $has_scoring_fields = false;
            if (!empty($data['fields'])) {
                foreach ($data['fields'] as $field) {
                    if (!empty($field['css_class']) && preg_match('/score-\d+/', $field['css_class'])) {
                        $has_scoring_fields = true;
                        break;
                    }
                }
            }
            if ($has_scoring_fields) {
                $post_types_with_scoring[$post_type] = $data['settings']['singular'] ?? $post_type;
            }
        }
        
        // Get target scores
        $target_scores = get_option('vbm_target_scores', []);
        
        // Calculate potential max scores
        $potential_max_scores = [];
        foreach ($post_types as $post_type => $data) {
            $potential_max_scores[$post_type] = $this->calculate_potential_max_score($post_type);
        }
        ?>
        <!-- Tab Navigation -->
        <nav class="nav-tab-wrapper">
            <a href="#cpt-settings" class="nav-tab nav-tab-active"><?php _e('CPT Settings', 'voxel-bulk-manager'); ?></a>
            <a href="#field-settings" class="nav-tab"><?php _e('Field Settings', 'voxel-bulk-manager'); ?></a>
        </nav>
        
        <!-- Tab Content: CPT Settings -->
        <div id="cpt-settings" class="tab-content active">
            <h3><?php _e('Actions', 'voxel-bulk-manager'); ?></h3>
            <button id="recalculate-scores" class="button button-primary">
                <?php _e('Recalculate All Scores', 'voxel-bulk-manager'); ?>
            </button>
            <div id="vbm-scoring-status-message"></div>
            
            <h3><?php _e('Target Score Manager', 'voxel-bulk-manager'); ?></h3>
            <p><?php _e('Set a target score for each post type. When calculating post scores, the system will use this target as 100%.', 'voxel-bulk-manager'); ?></p>
            
            <table id="vbm-target-scores-table" class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Post Type', 'voxel-bulk-manager'); ?></th>
                        <th><?php _e('Has Score Field', 'voxel-bulk-manager'); ?></th>
                        <th><?php _e('Potential Maximum', 'voxel-bulk-manager'); ?></th>
                        <th><?php _e('Target Score', 'voxel-bulk-manager'); ?></th>
                        <th><?php _e('Actions', 'voxel-bulk-manager'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($post_types as $post_type => $post_type_data): ?>
                        <?php 
                        $has_score = isset($post_types_with_score[$post_type]);
                        $potential_max = $potential_max_scores[$post_type];
                        $target = isset($target_scores[$post_type]) ? $target_scores[$post_type] : 0;
                        ?>
                        <tr data-post-type="<?php echo esc_attr($post_type); ?>">
                            <td><?php echo esc_html($post_type_data['settings']['singular'] ?? $post_type); ?></td>
                            <td>
                                <?php if ($has_score): ?>
                                    <span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span>
                                <?php else: ?>
                                    <span class="dashicons dashicons-no-alt" style="color: #d63638;"></span> 
                                    <span class="description"><?php _e('Add a "score" field to enable scoring', 'voxel-bulk-manager'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($potential_max); ?></td>
                            <td>
                                <?php if ($has_score): ?>
                                    <input type="number" class="target-score-input" value="<?php echo esc_attr($target); ?>" min="1" step="1">
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($has_score): ?>
                                    <button class="button button-secondary update-target-score"><?php _e('Update', 'voxel-bulk-manager'); ?></button>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div id="vbm-target-score-status-message"></div>
        </div>
        
        <!-- Tab Content: Field Settings -->
        <div id="field-settings" class="tab-content" style="display: none;">
            <h3><?php _e('Field Scoring Manager', 'voxel-bulk-manager'); ?></h3>
            <p><?php _e('Assign score values to fields. These values will be added to the post\'s score when the field is filled.', 'voxel-bulk-manager'); ?></p>
            
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
            </div>
            
            <table id="vbm-scoring-table" class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Post Type', 'voxel-bulk-manager'); ?></th>
                        <th><?php _e('Field Key', 'voxel-bulk-manager'); ?></th>
                        <th><?php _e('Label', 'voxel-bulk-manager'); ?></th>
                        <th><?php _e('Score', 'voxel-bulk-manager'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    foreach ($post_types as $post_type => $post_type_data):
                        if (empty($post_type_data['fields'])) continue;
                        
                        foreach ($post_type_data['fields'] as $field):
                            // Skip UI step, HTML fields, and score field
                            if (in_array($field['type'], ['ui-step', 'ui-html']) || $field['key'] === 'score') continue;
                            
                            // Extract score from CSS class
                            $score = $this->extract_score_from_css_class($field['css_class'] ?? '');
                    ?>
                    <tr data-post-type="<?php echo esc_attr($post_type); ?>" data-field-key="<?php echo esc_attr($field['key']); ?>">
                        <td><?php echo esc_html($post_type_data['settings']['singular'] ?? $post_type); ?></td>
                        <td><?php echo esc_html($field['key']); ?></td>
                        <td><?php echo esc_html($field['label'] ?? ''); ?></td>
                        <td class="editable" data-field-property="score"><?php echo esc_html($score); ?></td>
                    </tr>
                    <?php 
                        endforeach;
                    endforeach; 
                    ?>
                </tbody>
            </table>
            
            <div id="vbm-field-score-status-message"></div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Add strings for the update button to the localized script object
            if (typeof vbm_scoring !== 'undefined') {
                vbm_scoring.strings.update = '<?php _e('Update', 'voxel-bulk-manager'); ?>';
            }
        });
        </script>
        <?php
    }
}

new VBM_Scoring_Module();
