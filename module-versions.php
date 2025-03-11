<?php
/**
 * Module Versions
 * Stores version status for each module
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Get versions for all modules
 * 
 * @return array Module versions (Alpha, Beta, Working)
 */
function vbm_get_module_versions() {
    return [
        'api' => 'Alpha',
        'bulk_manager' => 'Working',
        'exclude_styles' => 'Working',
        'scoring' => 'Beta',
        'widgets' => 'Not applicable',
        'misc-enhancements' => 'Not applicable',
        // Add more modules here as they are developed
    ];
}

/**
 * Get version status for specific module
 * 
 * @param string $module_id Module ID
 * @return string Version status (Alpha, Beta, Working)
 */
function vbm_get_module_version($module_id) {
    $versions = vbm_get_module_versions();
    return isset($versions[$module_id]) ? $versions[$module_id] : 'Alpha';
}
