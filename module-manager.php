<?php
declare(strict_types=1);

/**
 * Voxel Manager Module System
 * 
 * Combined file containing module management functionality:
 * - Base Module class
 * - Module Loader
 * - Module management functions
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

// Include version management file
require_once VBM_PLUGIN_DIR . 'module-versions.php';

/**
 * Abstract base class for all modules
 */
abstract class VBM_Module {
    /**
     * @var string Module ID
     */
    protected $id;

    /**
     * @var string Module name for display
     */
    protected $name;

    /**
     * @var string Module description
     */
    protected $description;

    /**
     * @var string Module version
     */
    protected $version = '1.0.0';
    
    /**
     * Constructor
     * 
     * @param string $id Module ID
     * @param string $name Module name
     * @param string $description Module description
     * @param string $version Module version
     */
    public function __construct($id, $name, $description = '', $version = '1.0.0') {
        $this->id = sanitize_key($id);
        $this->name = sanitize_text_field($name);
        $this->description = sanitize_text_field($description);
        $this->version = sanitize_text_field($version);
    }

    /**
     * Get module ID
     * 
     * @return string
     */
    public function get_id() {
        return $this->id;
    }

    /**
     * Get module name
     * 
     * @return string
     */
    public function get_name() {
        return $this->name;
    }

    /**
     * Get module description
     * 
     * @return string
     */
    public function get_description() {
        return $this->description;
    }

    /**
     * Get module version
     * 
     * @return string
     */
    public function get_version() {
        return $this->version;
    }
    
    /**
     * Check if module is active
     * 
     * @return bool
     */
    public function is_active() {
        return vbm_is_module_active($this->id);
    }

    /**
     * Initialize the module
     * This is called only when the module is active
     */
    public function initialize() {
        $this->register_hooks();
    }

    /**
     * Register module hooks
     * Override in child classes
     */
    abstract protected function register_hooks();
    
    /**
     * Render module admin page
     * Override in child classes
     */
    abstract public function render_admin_page();
    
    /**
     * Get module details for menu registration
     * 
     * @return array Module details
     */
    public function get_menu_details() {
        return [
            'title' => $this->name,
            'callback' => [$this, 'render_admin_page'],
            'requires_feature' => true
        ];
    }
    
    /**
     * Enqueue admin assets
     * Override in child classes if needed
     * 
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_assets($hook) {
        // Default implementation - override as needed
    }
}

/**
 * Module loader class - manages all module instances
 */
class VBM_Module_Loader {
    /**
     * @var VBM_Module[] Registered modules
     */
    private $modules = [];
    
    /**
     * @var VBM_Module_Loader Instance
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
     * 
     * @return VBM_Module_Loader
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor to enforce singleton
     */
    private function __construct() {}
    
    /**
     * Register a module
     * 
     * @param VBM_Module $module Module instance
     * @return VBM_Module_Loader Self for chaining
     */
    public function register_module(VBM_Module $module) {
        $this->modules[$module->get_id()] = $module;
        return $this;
    }
    
    /**
     * Initialize all active modules
     */
    public function initialize() {
        foreach ($this->modules as $id => $module) {
            if ($module->is_active()) {
                $module->initialize();
            }
        }
        
        // Register admin hooks
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }
    
    /**
     * Get all registered modules
     * 
     * @return VBM_Module[] Array of module instances
     */
    public function get_modules() {
        return $this->modules;
    }
    
    /**
     * Get module by ID
     * 
     * @param string $id Module ID
     * @return VBM_Module|null Module instance or null if not found
     */
    public function get_module($id) {
        return isset($this->modules[$id]) ? $this->modules[$id] : null;
    }
    
    /**
     * Get menu details for all modules
     * 
     * @return array Module menu details
     */
    public function get_module_menu_details() {
        $details = [];
        foreach ($this->modules as $id => $module) {
            $details[$id] = $module->get_menu_details();
        }
        return $details;
    }
    
    /**
     * Enqueue admin assets for active modules
     * 
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_assets($hook) {
        // Enqueue common stylesheet
        wp_enqueue_style('vbm-style');
        
        // Let active modules enqueue their assets
        foreach ($this->modules as $id => $module) {
            if ($module->is_active()) {
                $module->enqueue_admin_assets($hook);
            }
        }
    }
}

/**
 * Module Manager functionality
 */

/**
 * Get available modules
 * 
 * @return array Modules list with ID => Name
 */
function vbm_get_available_modules() {
    return [
        'api' => 'API Access',
        'bulk_manager' => 'Bulk Field Manager',
        'exclude_styles' => 'Style Manager',
        'scoring' => 'Post Scoring',
        'widgets' => 'Additional Widgets',
        'misc-enhancements' => 'Misc Enhancements'
    ];
}

/**
 * Check if a module is active
 * 
 * @param string $module_id Module ID
 * @return bool Whether module is active
 */
function vbm_is_module_active($module_id) {
    static $active_modules = null;
    
    if ($active_modules === null) {
        $active_modules = get_option('vbm_active_modules', []);
        
        // Backward compatibility with old option name
        if (empty($active_modules)) {
            $active_modules = get_option('vbm_active_scripts', []);
            if (!empty($active_modules)) {
                update_option('vbm_active_modules', $active_modules);
                delete_option('vbm_active_scripts'); // Clean up old option
            }
        }
        
        // Ensure it's always an array
        if (!is_array($active_modules)) {
            $active_modules = [];
            update_option('vbm_active_modules', $active_modules);
        }
    }
    
    return in_array($module_id, $active_modules);
}

/**
 * Activate a module
 * 
 * @param string $module_id Module ID
 * @return bool Success
 */
function vbm_activate_module($module_id) {
    $dependencies = vbm_get_module_dependencies();
    
    // Check if module exists
    if (!isset($dependencies[$module_id])) {
        return false;
    }
    
    // Check dependencies
    foreach ($dependencies[$module_id] as $dependency) {
        if (!vbm_is_module_active($dependency)) {
            add_settings_error(
                'vbm_modules',
                'dependency_error',
                sprintf('Cannot activate %s: Requires %s to be active first.', $module_id, $dependency),
                'error'
            );
            return false;
        }
    }
    
    $active_modules = get_option('vbm_active_modules', []);
    
    if (!in_array($module_id, $active_modules)) {
        $active_modules[] = $module_id;
        update_option('vbm_active_modules', $active_modules);
        return true;
    }
    
    return false;
}

/**
 * Deactivate a module
 * 
 * @param string $module_id Module ID
 * @return bool Success
 */
function vbm_deactivate_module($module_id) {
    $active_modules = get_option('vbm_active_modules', []);
    
    if (in_array($module_id, $active_modules)) {
        $active_modules = array_diff($active_modules, [$module_id]);
        update_option('vbm_active_modules', $active_modules);
        return true;
    }
    
    return false;
}

/**
 * Get module dependencies
 * 
 * @return array Module dependencies
 */
function vbm_get_module_dependencies() {
    return [
        'api' => [],
        'bulk_manager' => [],
        'exclude_styles' => [],
        'scoring' => [],
        'widgets' => [],
        'misc-enhancements' => []
    ];
}

/**
 * Render the main module management page
 */
function vbm_render_module_manager_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $available_modules = vbm_get_available_modules();
    $active_modules = get_option('vbm_active_modules', []);
    $updated = false;

    if (isset($_POST['submit']) && check_admin_referer('vbm_update_features')) {
        $new_features = isset($_POST['features']) ? (array)$_POST['features'] : [];
        $new_features = array_intersect(array_keys($available_modules), $new_features);
        update_option('vbm_active_modules', $new_features);
        $active_modules = $new_features;
        $updated = true;
    }

    ?>
    <div class="wrap vbm-module-page">
        <h1>Voxel Manager Features</h1>
        
        <?php if ($updated): ?>
        <div class="notice notice-success"><p>Features updated successfully!</p></div>
        <?php endif; ?>
        
        <p>Enable or disable plugin features as needed.</p>
        
        <form method="post" action="">
            <?php wp_nonce_field('vbm_update_features'); ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Feature</th>
                        <th>Description</th>
                        <th>Version</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $modules_details = [];
                    $loader = VBM_Module_Loader::instance();
                    
                    foreach ($available_modules as $id => $name) {
                        $module = $loader->get_module($id);
                        if ($module) {
                            $is_active = in_array($id, $active_modules);
                            $version_status = vbm_get_module_version($id);
                            $version_class = strtolower(str_replace(' ', '-', $version_status));
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($name); ?></strong>
                                </td>
                                <td><?php echo esc_html($module->get_description()); ?></td>
                                <td>
                                    <span class="version-status <?php echo esc_attr($version_class); ?>">
                                        <?php echo esc_html($version_status); ?>
                                    </span>
                                </td>
                                <td>
                                    <label class="switch">
                                        <input type="checkbox" name="features[]" value="<?php echo esc_attr($id); ?>" <?php checked($is_active); ?>>
                                        <span class="slider"></span>
                                    </label>
                                </td>
                            </tr>
                            <?php
                        }
                    }
                    ?>
                </tbody>
            </table>
            <p class="submit">
                <input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes">
            </p>
        </form>
    </div>
    <?php
}