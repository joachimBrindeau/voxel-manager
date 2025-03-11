<?php
// Exit if accessed directly
if (!defined('ABSPATH')) exit;

/**
 * Register custom fields in REST API
 */
function register_custom_fields_in_rest_api() {
    $voxel_post_types = json_decode(get_option('voxel:post_types'), true) ?: [];
    
    foreach ($voxel_post_types as $post_type => $post_type_data) {
        if (!isset($post_type_data['fields']) || !is_array($post_type_data['fields'])) {
            continue;
        }
        
        foreach ($post_type_data['fields'] as $field) {
            // Skip UI fields and fields without required properties
            if (!isset($field['key']) || !isset($field['type']) || 
                strpos($field['type'], 'ui-') === 0 || $field['type'] === 'title') {
                continue;
            }
            
            // Get schema from field registry if available
            $schema = ['description' => $field['label'] ?? ''];
            
            if (function_exists('vbm_field_registry') && method_exists(vbm_field_registry(), 'get_schema_for_field')) {
                $schema = vbm_field_registry()->get_schema_for_field($field);
            } else {
                $schema = array_merge($schema, get_schema_type_for_field($field['type']));
            }
            
            // Register the field
            register_rest_field($post_type, $field['key'], [
                'get_callback' => function($post_arr) use ($field) {
                    return get_custom_field_value($post_arr['id'], $field);
                },
                'update_callback' => function($value, $post, $field_name) use ($field) {
                    return update_custom_field_value($post->ID, $field, $value);
                },
                'schema' => $schema,
            ]);
        }

        // Add single filter for the post type
        add_filter("rest_prepare_{$post_type}", function($response, $post, $request) use ($post_type_data) {
            return handle_rest_response($response, $post, $request, $post_type_data['fields'] ?? []);
        }, 10, 3);
    }
    
    // Register special endpoints for field options
    // Use static flag to prevent duplicate registration
    static $field_options_registered = false;
    if (!$field_options_registered) {
        register_rest_route('voxel/v1', '/field-options/(?P<post_type>[a-zA-Z0-9_-]+)/(?P<field_key>[a-zA-Z0-9_-]+)', [
            'methods' => 'GET',
            'callback' => 'get_field_options',
            'permission_callback' => '__return_true',
        ]);
        $field_options_registered = true;
    }
}

/**
 * Handle REST API response
 */
function handle_rest_response($response, $post, $request, $fields) {
    foreach ($fields as $field) {
        if ($field['type'] === 'post-relation') {
            $relation_key = !empty($field['custom_key']) ? $field['custom_key'] : $field['key'];
            
            // Fix: Add default 'has_many' if relation_type is not set
            $relation_type = vbm_get_field_property($field, 'relation_type', 'has_many');
            $related_ids = get_post_relation_value($post->ID, $relation_key, $relation_type);
            
            if (!empty($related_ids)) {
                $links = [];
                foreach ($related_ids as $related_id) {
                    // Fix: Check if post_types is set
                    $post_types = vbm_get_field_property($field, 'post_types', []);
                    foreach ($post_types as $post_type) {
                        $related_post = get_post($related_id);
                        if ($related_post && $related_post->post_type === $post_type) {
                            $links[] = [
                                'embeddable' => true,
                                'type' => $post_type,
                                'targetHints' => [
                                    'allow' => ['GET']
                                ],
                                'href' => rest_url(sprintf('wp/v2/%s/%d', $post_type, $related_id))
                            ];
                            
                            // Handle embedding
                            if (isset($request['_embed'])) {
                                $controller = new WP_REST_Posts_Controller($post_type);
                                $prepared_post = $controller->prepare_item_for_response($related_post, $request);
                                if (!is_wp_error($prepared_post)) {
                                    $response->add_embedded_item($prepared_post->data, 'wp:' . $field['key']);
                                }
                            }
                        }
                    }
                }
                
                if (!empty($links)) {
                    $response->add_links([
                        'wp:' . $field['key'] => $links
                    ]);
                }
            }
        }
    }
    
    return $response;
}

/**
 * Get schema type for field
 */
function get_schema_type_for_field($field_type) {
    switch ($field_type) {
        case 'post-relation':
            return [
                'type' => ['array', 'integer', 'null'],
                'items' => [
                    'type' => 'integer'
                ]
            ];
        case 'taxonomy':
            return [
                'type' => 'string',
                'description' => 'Comma-separated list of term slugs'
            ];
        case 'number':
            return ['type' => 'number', 'format' => 'float'];
        case 'switcher':
            return ['type' => 'boolean'];
        case 'image':
        case 'file':
            return ['type' => 'integer', 'format' => 'media_id'];
        case 'repeater':
            return [
                'type' => 'string',
                'description' => 'JSON encoded array of repeater items'
            ];
        case 'select':
            return ['type' => 'string'];
        case 'location':
        case 'work-hours':
            return [
                'type' => 'string',
                'description' => 'JSON string'
            ];
        case 'phone':
            return ['type' => 'string', 'format' => 'phone'];
        case 'email':
            return ['type' => 'string', 'format' => 'email'];
        case 'url':
            return ['type' => 'string', 'format' => 'uri'];
        case 'date':
            return ['type' => 'string', 'format' => 'date-time'];
        default:
            return ['type' => 'string'];
    }
}

/**
 * Get field options for select and similar fields
 */
function get_field_options($request) {
    $post_type = $request->get_param('post_type');
    $field_key = $request->get_param('field_key');
    
    $voxel_post_types = json_decode(get_option('voxel:post_types'), true) ?: [];
    
    if (!isset($voxel_post_types[$post_type])) {
        return new WP_Error('invalid_post_type', 'Invalid post type', ['status' => 404]);
    }
    
    $field = null;
    foreach ($voxel_post_types[$post_type]['fields'] as $f) {
        if ($f['key'] === $field_key) {
            $field = $f;
            break;
        }
    }
    
    if (!$field) {
        return new WP_Error('invalid_field', 'Invalid field', ['status' => 404]);
    }
    
    $options = [];
    
    if ($field['type'] === 'select') {
        if (isset($field['choices'])) {
            foreach ($field['choices'] as $key => $choice) {
                $options[] = [
                    'value' => $key,
                    'label' => $choice['label'] ?? $key
                ];
            }
        }
    } elseif ($field['type'] === 'taxonomy') {
        $terms = get_terms([
            'taxonomy' => $field['taxonomy'],
            'hide_empty' => false
        ]);
        
        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                $options[] = [
                    'value' => $term->slug,
                    'label' => $term->name,
                    'id' => $term->term_id
                ];
            }
        }
    } elseif ($field['type'] === 'post-relation') {
        // Get first 100 posts of each related post type
        foreach ($field['post_types'] as $related_post_type) {
            $posts = get_posts([
                'post_type' => $related_post_type,
                'posts_per_page' => 100,
                'post_status' => 'publish'
            ]);
            
            foreach ($posts as $post) {
                $options[] = [
                    'value' => $post->ID,
                    'label' => $post->post_title,
                    'type' => $post->post_type
                ];
            }
        }
    }
    
    return [
        'type' => $field['type'],
        'key' => $field['key'],
        'label' => $field['label'] ?? '',
        'options' => $options
    ];
}
