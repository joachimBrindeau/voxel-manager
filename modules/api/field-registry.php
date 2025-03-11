<?php
/**
 * Field Registry for Voxel API
 * 
 * Handles registration and management of custom field types.
 *
 * @package VoxelManager
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

/**
 * Field Registry class
 * 
 * Manages custom field types and their handlers for the REST API
 */
class VBM_Field_Registry {
    /**
     * Registered field types with handlers
     */
    private $field_types = [];
    
    /**
     * Instance of the class
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->register_default_field_types();
    }
    
    /**
     * Register default field types
     */
    private function register_default_field_types() {
        $default_types = [
            'text' => [
                'schema' => ['type' => 'string'],
                'sanitize' => 'sanitize_text_field',
            ],
            'textarea' => [
                'schema' => ['type' => 'string'],
                'sanitize' => 'sanitize_textarea_field',
            ],
            'number' => [
                'schema' => ['type' => 'number'],
                'sanitize' => function($value) { return is_numeric($value) ? floatval($value) : null; },
            ],
            'date' => [
                'schema' => ['type' => 'string', 'format' => 'date-time'],
                'sanitize' => function($value) { return sanitize_text_field($value); },
                'transform' => function($value) { return $value ? date('c', strtotime($value)) : null; },
            ],
            'email' => [
                'schema' => ['type' => 'string', 'format' => 'email'],
                'sanitize' => 'sanitize_email',
            ],
            'url' => [
                'schema' => ['type' => 'string', 'format' => 'uri'],
                'sanitize' => 'esc_url_raw',
            ],
            'image' => [
                'schema' => ['type' => 'integer'],
                'sanitize' => 'absint',
            ],
            'file' => [
                'schema' => ['type' => 'integer'],
                'sanitize' => 'absint',
            ],
            'select' => [
                'schema' => ['type' => 'string'],
                'sanitize' => 'sanitize_text_field',
            ],
            'switcher' => [
                'schema' => ['type' => 'boolean'],
                'sanitize' => function($value) { return (bool) $value; },
            ],
            'taxonomy' => [
                'schema' => ['type' => 'string'],
                'sanitize' => 'sanitize_text_field',
            ],
            'post-relation' => [
                'schema' => [
                    'type' => ['array', 'integer', 'null'],
                    'items' => ['type' => 'integer']
                ],
                'sanitize' => function($value) {
                    if (is_array($value)) {
                        return array_map('absint', $value);
                    } elseif (is_string($value) && strpos($value, ',') !== false) {
                        return array_map('absint', explode(',', $value));
                    } else {
                        return absint($value);
                    }
                },
            ],
            'repeater' => [
                'schema' => ['type' => 'string'],
                'sanitize' => function($value) {
                    if (is_string($value) && is_array(json_decode($value, true))) {
                        return $value;
                    }
                    return '[]';
                },
            ],
            'location' => [
                'schema' => ['type' => 'string'],
                'sanitize' => function($value) {
                    if (is_array($value)) {
                        return wp_json_encode($value);
                    }
                    return $value;
                },
            ],
            'work-hours' => [
                'schema' => ['type' => 'string'],
                'sanitize' => function($value) {
                    if (is_array($value)) {
                        return wp_json_encode($value);
                    }
                    return $value;
                },
            ],
        ];
        
        foreach ($default_types as $type => $config) {
            $this->register_field_type($type, $config);
        }
    }
    
    /**
     * Register a field type
     */
    public function register_field_type($type, $config) {
        $this->field_types[$type] = $config;
    }
    
    /**
     * Get field type config
     */
    public function get_field_type($type) {
        return isset($this->field_types[$type]) ? $this->field_types[$type] : null;
    }
    
    /**
     * Get schema for field type
     */
    public function get_schema_for_field($field) {
        $type = $field['type'];
        $config = $this->get_field_type($type);
        
        if (!$config || !isset($config['schema'])) {
            return ['type' => 'string'];
        }
        
        return array_merge(
            ['description' => $field['label'] ?? ''],
            $config['schema']
        );
    }
    
    /**
     * Sanitize value for field type
     */
    public function sanitize_value($value, $field) {
        $type = $field['type'];
        $config = $this->get_field_type($type);
        
        if (!$config || !isset($config['sanitize'])) {
            return sanitize_text_field($value);
        }
        
        $sanitize = $config['sanitize'];
        return is_callable($sanitize) ? $sanitize($value) : $value;
    }
    
    /**
     * Transform value for output
     */
    public function transform_value($value, $field) {
        $type = $field['type'];
        $config = $this->get_field_type($type);
        
        if (!$config || !isset($config['transform'])) {
            return $value;
        }
        
        $transform = $config['transform'];
        return is_callable($transform) ? $transform($value) : $value;
    }
}

/**
 * Get field registry instance
 */
function vbm_field_registry() {
    return VBM_Field_Registry::instance();
}

// Initialize the field registry
vbm_field_registry();
