<?php
declare(strict_types=1);

/**
 * Plugin Name: Voxel Manager
 * Description: Additional features for Voxel
 * Version: 0.1.0
 * Requires at least: 6.7.1
 * Requires PHP: 7.0
 * Author: Joachim Brindeau
 * Text Domain: voxel-bulk-manager
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

// Define plugin constants
define('VBM_PLUGIN_FILE', __FILE__);
define('VBM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('VBM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('VBM_PLUGIN_VERSION', '0.1.0');

/**
 * Plugin activation handler
 */
function vbm_activate_plugin() {
    // Set default modules using valid IDs (changed "automation" to "exclude_styles")
    update_option('vbm_active_modules', ['bulk_manager', 'exclude_styles', 'api', 'widgets']);
    
    // Migrate existing rules from old option name if present
    $old_rules = get_option('cpt_relation_rules', []);
    if (!empty($old_rules)) {
        // We'll keep using the old option name for backward compatibility
        // but we'll make sure it's properly formatted
        update_option('cpt_relation_rules', $old_rules);
    }
}
register_activation_hook(__FILE__, 'vbm_activate_plugin');

// Load core files with updated paths
require_once VBM_PLUGIN_DIR . "module-manager.php";
// Removed duplicate API module loading
// require_once VBM_PLUGIN_DIR . "modules/api/api-module.php";
// Old load removed:
// require_once VBM_PLUGIN_DIR . "modules/bulk-manager/bulk-manager.php";
// New bulk manager files:
require_once VBM_PLUGIN_DIR . "modules/bulk-manager/bulk-core.php";
require_once VBM_PLUGIN_DIR . "modules/bulk-manager/bulk-fields.php";
require_once VBM_PLUGIN_DIR . "modules/bulk-manager/bulk-cpt.php";
require_once VBM_PLUGIN_DIR . "modules/exclude-styles/exclude-styles.php";
require_once VBM_PLUGIN_DIR . "modules/scoring/scoring.php";
require_once VBM_PLUGIN_DIR . "modules/widgets/widgets.php";
require_once VBM_PLUGIN_DIR . "modules/misc-enhancements/misc-enhancements.php";

/**
 * Initialize plugin
 */
function vbm_init_plugin() {
    // Define API module files
    $api_files = [
        'utilities.php',
        'relations-manager.php',
        'field-registry.php',
        'api-module.php'
    ];
    
    // Load API module files with error checking
    foreach ($api_files as $file) {
        $file_path = VBM_PLUGIN_DIR . 'modules/api/' . $file;
        if (file_exists($file_path)) {
            require_once $file_path;
        } else {
            error_log('Voxel Manager: Missing required file: ' . $file_path);
        }
    }
    
    // Check if API module class exists before registering
    $loader = VBM_Module_Loader::instance();
    
    // Register modules with existence checks
    if (class_exists('VBM_API_Module')) {
        $loader->register_module(new VBM_API_Module());
    }
    
    // Register other modules
    if (class_exists('VBM_Bulk_Manager_Core')) {
        $loader->register_module(new VBM_Bulk_Manager_Core());
    }
    
    if (class_exists('VBM_Exclude_Styles_Module')) {
        $loader->register_module(new VBM_Exclude_Styles_Module());
    }
    
    if (class_exists('VBM_Scoring_Module')) {
        $loader->register_module(new VBM_Scoring_Module());
    }
    
    if (class_exists('VBM_Widgets_Module')) {
        $loader->register_module(new VBM_Widgets_Module());
    }
    
    if (class_exists('VBM_Misc_Enhancements_Module')) {
        $loader->register_module(new VBM_Misc_Enhancements_Module());
    }
    
    // Initialize modules
    $loader->initialize();
    
    // Register admin menu
    add_action('admin_menu', 'vbm_register_admin_menu');
}
add_action('plugins_loaded', 'vbm_init_plugin', 10);

/**
 * Register admin menu
 */
function vbm_register_admin_menu() {
    // Main menu page
    add_menu_page(
        'Voxel Manager',
        'Voxel Manager',
        'manage_options',
        'voxel-manager',
        'vbm_render_module_manager_page',
        'dashicons-networking',
        10
    );
    
    // Register module submenus
    $modules = VBM_Module_Loader::instance()->get_module_menu_details();
    foreach ($modules as $id => $module) {
        add_submenu_page(
            'voxel-manager',
            $module['title'],
            $module['title'],
            'manage_options',
            'voxel-' . $id,
            'vbm_render_module_page_wrapper'
        );
    }
}

/**
 * Common wrapper for all module pages
 */
function vbm_render_module_page_wrapper() {
    $page = $_GET['page'] ?? '';
    $module_id = str_replace('voxel-', '', $page);
    $module = VBM_Module_Loader::instance()->get_module($module_id);
    
    if (!$module) {
        wp_die(__('Invalid module', 'voxel-bulk-manager'));
    }
    
    // Enqueue admin styles
    wp_enqueue_style('vbm-style');
    
    echo '<div class="wrap vbm-module-page">';
    echo '<h1>' . esc_html($module->get_name()) . '</h1>';
    
    if (!$module->is_active()) {
        echo '<div class="notice notice-warning"><p>' . 
            sprintf(
                __('This feature is currently inactive. <a href="%s">Enable it from the Module Manager</a> to use this functionality.', 'voxel-bulk-manager'),
                esc_url(admin_url('admin.php?page=voxel-manager'))
            ) . 
            '</p></div>';
    } else {
        $module->render_admin_page();
    }
    
    echo '</div>';
}

/**
 * Register and enqueue common admin assets
 */
function vbm_register_admin_assets() {
    // Register and immediately enqueue the main stylesheet for all admin pages
    wp_register_style(
        'vbm-style',
        VBM_PLUGIN_URL . 'style.css',
        [],
        VBM_PLUGIN_VERSION
    );
    
    // Always enqueue the main stylesheet on admin pages
    wp_enqueue_style('vbm-style');
}
add_action('admin_enqueue_scripts', 'vbm_register_admin_assets');

/**
 * Enqueue frontend assets
 */
function vbm_enqueue_frontend_assets() {
    // Always enqueue the main stylesheet on frontend
    wp_enqueue_style(
        'vbm-style',
        VBM_PLUGIN_URL . 'style.css',
        [],
        VBM_PLUGIN_VERSION
    );
}
add_action('wp_enqueue_scripts', 'vbm_enqueue_frontend_assets');
