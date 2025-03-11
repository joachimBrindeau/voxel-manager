<?php
/**
 * Widgets Versions
 * Stores version status for each widget
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Get versions for all widgets
 * 
 * @return array Widget versions (Alpha, Beta, Working)
 */
function vbm_get_widget_versions() {
    return [
        'claims_list' => 'Working',
        'post_action' => 'Alpha',
        // Add more widgets here as they are developed
    ];
}

/**
 * Get version status for specific widget
 * 
 * @param string $widget_id Widget ID
 * @return string Version status (Alpha, Beta, Working)
 */
function vbm_get_widget_version($widget_id) {
    $versions = vbm_get_widget_versions();
    return isset($versions[$widget_id]) ? $versions[$widget_id] : 'Alpha';
}
