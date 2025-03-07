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
    update_option('vbm_active_modules', ['bulk_manager', 'exclude_styles']);
    
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
require_once VBM_PLUGIN_DIR . "modules/api/api-module.php";
require_once VBM_PLUGIN_DIR . "modules/bulk-manager/bulk-manager.php";
require_once VBM_PLUGIN_DIR . "modules/exclude-styles/exclude-styles.php";
require_once VBM_PLUGIN_DIR . "modules/scoring/scoring.php";

/**
 * Initialize plugin
 */
function vbm_init_plugin() {
    // Register modules
    $loader = VBM_Module_Loader::instance();
    $loader->register_module(new VBM_API_Module())
           ->register_module(new VBM_Bulk_Manager_Module())
           ->register_module(new VBM_Exclude_Styles_Module())
           ->register_module(new VBM_Scoring_Module());
    
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
    wp_enqueue_style('vbm-admin-style');
    
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
 * Register common assets
 */
function vbm_register_admin_assets() {
    wp_register_style(
        'vbm-style',
        VBM_PLUGIN_URL . 'style.css',
        [],
        VBM_PLUGIN_VERSION
    );
}
add_action('admin_enqueue_scripts', 'vbm_register_admin_assets');

/**
 * Enqueue frontend assets
 */
function vbm_enqueue_frontend_assets() {
    if (vbm_is_module_active('bulk_manager') || vbm_is_module_active('exclude_styles')) {
        wp_enqueue_style('vbm-style');
    }
}
add_action('wp_enqueue_scripts', 'vbm_enqueue_frontend_assets');
