<?php
/**
 * Misc Enhancements Versions
 * Stores version status for each enhancement
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Get versions for all misc enhancements
 * 
 * @return array Enhancement versions (alpha, beta, working)
 */
function vbm_get_misc_enhancement_versions() {
    return [
        'custom_field_requirement' => 'Beta',
        'first_collection' => 'Working',
        // Add more enhancements here as they are developed
    ];
}

/**
 * Get version status for specific enhancement
 * 
 * @param string $enhancement_id Enhancement ID
 * @return string Version status (Alpha, Beta, Working)
 */
function vbm_get_misc_enhancement_version($enhancement_id) {
    $versions = vbm_get_misc_enhancement_versions();
    return isset($versions[$enhancement_id]) ? $versions[$enhancement_id] : 'Alpha';
}
