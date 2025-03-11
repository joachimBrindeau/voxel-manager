<?php
if (!defined('ABSPATH')) exit;

// Include version management file
require_once VBM_PLUGIN_DIR . 'modules/misc-enhancements/misc-enhancements-versions.php';

class VBM_Misc_Enhancements_Module extends VBM_Module {
    private $enhancements = [];
    private $active_enhancements = [];

    public function __construct() {
        parent::__construct(
            'misc-enhancements',
            'Misc Enhancements',
            'Provides miscellaneous enhancements to Voxel',
            '1.0.0'
        );
        $this->register_available_enhancements();
        $this->load_active_enhancements();
        $this->include_active_enhancements();
        add_action('admin_menu', [$this, 'register_misc_menu']);

        add_filter('vbm_registered_modules', function($modules){
            $modules[$this->slug] = $this;
            return $modules;
        });
    }

    private function register_available_enhancements() {
        $this->enhancements['custom_field_requirement'] = [
            'name' => 'Custom Field Requirement',
            'description' => 'Adds clearer requirement styles for fields',
            'file' => VBM_PLUGIN_DIR . 'modules/misc-enhancements/custom-field-requirement/custom-field-requirement.php',
            'class' => 'VBM_CustomFieldRequirement_Enhancement',
        ];
        $this->enhancements['first_collection'] = [
            'name' => 'First Collection',
            'description' => 'Creates a First Collection post for every new user automatically',
            'file' => VBM_PLUGIN_DIR . 'modules/misc-enhancements/first-collection/first-collection.php',
            'class' => 'VBM_FirstCollection_Enhancement',
        ];
    }

    private function load_active_enhancements() {
        $saved = get_option('vbm_active_misc_enhancements', []);
        $this->active_enhancements = array_intersect(array_keys($this->enhancements), $saved);
    }

    private function include_active_enhancements() {
        foreach ($this->active_enhancements as $enhancement_id) {
            $enhancement = $this->enhancements[$enhancement_id];
            if (file_exists($enhancement['file'])) {
                include_once $enhancement['file'];
                if (class_exists($enhancement['class'])) {
                    new $enhancement['class']();
                }
            }
        }
    }

    public function register_misc_menu() {
        add_submenu_page(
            'options-general.php',
            'Misc Enhancements',
            'Misc Enhancements',
            'manage_options',
            'misc-enhancements',
            [$this, 'render_settings_page']
        );

        add_submenu_page(
            'misc-enhancements',
            'First Collection',
            'First Collection',
            'manage_options',
            'misc-first-collection',
            [$this, 'render_first_collection_page']
        );
    }

    // Settings page output
    public function render_settings_page() {
        if (!current_user_can('manage_options')) return;

        $updated = false;
        if (isset($_POST['vbm_misc_submit']) && check_admin_referer('vbm_misc_update')) {
            $active = isset($_POST['enhancements']) ? (array)$_POST['enhancements'] : [];
            update_option('vbm_misc_enhancements', $active);

            echo '<div class="notice notice-success"><p>Miscellaneous enhancements updated.</p></div>';
            $updated = true;
        }

        $active = get_option('vbm_misc_enhancements', []);
        ?>
        <?php if ($updated): ?>
        <div class="notice notice-success"><p>Enhancements updated successfully!</p></div>
        <?php endif; ?>
        
        <div class="notice notice-info">
            <p>Enable or disable enhancements as needed.</p>
        </div>
        
        <form method="post" action="">
            <?php wp_nonce_field('vbm_misc_update'); ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Enhancement</th>
                        <th>Description</th>
                        <th>Version</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($this->enhancements as $id => $enhancement): 
                        $version_status = vbm_get_misc_enhancement_version($id);
                        $version_class = strtolower(str_replace(' ', '-', $version_status));
                    ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($enhancement['name']); ?></strong>
                            </td>
                            <td><?php echo esc_html($enhancement['description']); ?></td>
                            <td>
                                <span class="version-status <?php echo esc_attr($version_class); ?>">
                                    <?php echo esc_html($version_status); ?>
                                </span>
                            </td>
                            <td>
                                <label class="switch">
                                    <input type="checkbox" name="enhancements[]" value="<?php echo esc_attr($id); ?>" <?php checked(in_array($id, $active, true)); ?>>
                                    <span class="slider"></span>
                                </label>
                            </td>
                            <td>
                                <?php if ($id === 'first_collection'): ?>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=misc-first-collection')); ?>" class="button">
                                    Settings
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p class="submit">
                <input type="submit" name="vbm_misc_submit" id="submit" class="button button-primary" value="Save Changes">
            </p>
        </form>
        <?php
    }

    public function render_first_collection_page() {
        if (!current_user_can('manage_options')) return;

        $active = get_option('vbm_misc_enhancements', []);
        $is_active = in_array('first_collection', $active, true);
        $title = get_option('vbm_first_collection_title', '');
        $desc = get_option('vbm_first_collection_description', '');
        
        // Process form submission only if feature is active
        if ($is_active && isset($_POST['vbm_misc_submit']) && check_admin_referer('vbm_misc_update')) {
            update_option('vbm_first_collection_title', sanitize_text_field($_POST['vbm_first_collection_title']));
            update_option('vbm_first_collection_description', sanitize_textarea_field($_POST['vbm_first_collection_description']));
            echo '<div class="notice notice-success"><p>First Collection settings updated.</p></div>';
        }
        ?>
        <?php if (!$is_active): ?>
        <div class="notice notice-warning">
            <p>This feature is currently inactive. Please <a href="<?php echo admin_url('admin.php?page=misc-enhancements'); ?>">enable it from the Misc Enhancements page</a> first.</p>
        </div>
        <?php endif; ?>
        
        <form method="post">
            <?php wp_nonce_field('vbm_misc_update'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">Collection Title</th>
                    <td>
                        <input type="text" name="vbm_first_collection_title" value="<?php echo esc_attr($title); ?>" <?php disabled(!$is_active); ?> />
                    </td>
                </tr>
                <tr>
                    <th scope="row">Collection Description</th>
                    <td>
                        <textarea name="vbm_first_collection_description" <?php disabled(!$is_active); ?>><?php echo esc_textarea($desc); ?></textarea>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="vbm_misc_submit" class="button button-primary" value="Save Settings" <?php disabled(!$is_active); ?> />
            </p>
        </form>
        <?php
    }

    protected function register_hooks() {
        // No hooks for now
    }

    public function render_admin_page() {
        $this->render_settings_page();
    }
}
