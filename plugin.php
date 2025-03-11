<?php
/**
 * Load API module classes
 */
require_once __DIR__ . '/modules/api/relations-manager.php';
require_once __DIR__ . '/modules/api/field-registry.php';
require_once __DIR__ . '/modules/api/api-module.php';

// Initialize API module
$voxel_api_module = new VBM_API_Module();
$voxel_api_module->initialize();
