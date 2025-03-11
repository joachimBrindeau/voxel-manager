<?php
// Exit if accessed directly
if (!defined('ABSPATH')) exit;

/**
 * Expose Voxel custom post types to the REST API
 */
function expose_voxel_cpts_to_rest_api() {
    $voxel_post_types = json_decode(get_option('voxel:post_types'), true) ?: [];
    foreach ($voxel_post_types as $post_type => $post_type_data) {
        if (!in_array($post_type, ['post', 'page'])) {
            $obj = get_post_type_object($post_type);
            if ($obj) {
                // Add REST API support
                $obj->show_in_rest = true;
                $obj->rest_base = $post_type;
                $obj->rest_controller_class = 'WP_REST_Posts_Controller';
                
                // Set capabilities more precisely
                $obj->capability_type = 'post';
                $obj->capabilities = array(
                    'edit_post' => 'edit_post',
                    'read_post' => 'read_post',
                    'delete_post' => 'delete_post',
                    'edit_posts' => 'edit_posts',
                    'edit_others_posts' => 'edit_others_posts',
                    'publish_posts' => 'publish_posts',
                    'read_private_posts' => 'read_private_posts',
                );
                
                // Add filters
                add_filter("rest_{$post_type}_collection_params", function($params) use ($post_type_data) {
                    return add_relation_collection_params($params, $post_type_data);
                });
                
                add_filter("rest_{$post_type}_query", function($args, $request) use ($post_type_data) {
                    return filter_query_by_custom_params($args, $request, $post_type_data);
                }, 10, 2);
                
                add_filter("rest_{$post_type}_permissions_check", function($permission, $request) {
                    return check_rest_permission($permission, $request);
                }, 10, 2);
                
                add_filter("rest_{$post_type}_schema", function($schema) use ($post_type_data) {
                    return add_relation_schema($schema, $post_type_data);
                }, 20);
            }
        }
    }
}

/**
 * Check REST API permissions
 */
function check_rest_permission($permission, $request) {
    $method = $request->get_method();
    
    // Always allow GET requests to published content
    if ($method === 'GET') {
        // For GET requests to specific posts, check if the post is published or user has appropriate permissions
        $post_id = isset($request['id']) ? $request->get_param('id') : null;
        if ($post_id) {
            $post = get_post($post_id);
            if (!$post) {
                return new WP_Error('rest_post_invalid_id', __('Invalid post ID.'), ['status' => 404]);
            }
            
            // If post is published, allow access
            if ($post->post_status === 'publish') {
                return true;
            }
            
            // For non-published posts, check permissions
            if (current_user_can('read_post', $post_id)) {
                return true;
            }
            
            return false;
        }
        
        return true; // Allow GET requests for collections
    }
    
    // For other methods, check if user has the required capabilities
    switch ($method) {
        case 'POST':
            return current_user_can('publish_posts') || current_user_can('edit_posts');
        case 'PUT':
        case 'PATCH':
            $post_id = isset($request['id']) ? $request->get_param('id') : null;
            if ($post_id) {
                $post = get_post($post_id);
                if (!$post) {
                    return new WP_Error('rest_post_invalid_id', __('Invalid post ID.'), ['status' => 404]);
                }
                
                // Allow if user can edit this post
                if (current_user_can('edit_post', $post_id)) {
                    return true;
                }
                
                // Allow post authors to edit their own posts
                $current_user_id = get_current_user_id();
                if ($current_user_id && $post->post_author == $current_user_id) {
                    return true;
                }
            }
            return false;
        case 'DELETE':
            $post_id = isset($request['id']) ? $request->get_param('id') : null;
            return $post_id && (current_user_can('delete_post', $post_id) || 
                               (get_current_user_id() && get_post($post_id)->post_author == get_current_user_id()));
        default:
            return false;
    }
}

/**
 * Add relation schema to post type
 */
function add_relation_schema($schema, $post_type_data) {
    if (!isset($post_type_data['fields'])) {
        return $schema;
    }

    foreach ($post_type_data['fields'] as $field) {
        if ($field['type'] === 'post-relation') {
            $relation_key = !empty($field['custom_key']) ? $field['custom_key'] : $field['key'];
            
            // Add relation field to schema properties
            $schema['properties'][$field['key']] = [
                'type' => 'array',
                'items' => [
                    'type' => 'integer'
                ],
                'description' => $field['label'] ?? '',
                'context' => ['view', 'edit'],
            ];

            // Add relation to _links schema
            if (!isset($schema['properties']['_links'])) {
                $schema['properties']['_links'] = [
                    'type' => 'object',
                    'context' => ['embed', 'view', 'edit'],
                    'readonly' => true
                ];
            }

            if (!isset($schema['properties']['_links']['properties'])) {
                $schema['properties']['_links']['properties'] = [];
            }

            $schema['properties']['_links']['properties']['wp:' . $field['key']] = [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'embeddable' => [
                            'type' => 'boolean'
                        ],
                        'href' => [
                            'type' => 'string',
                            'format' => 'uri'
                        ],
                        'type' => [
                            'type' => 'string'
                        ]
                    ]
                ]
            ];
        } else {
            // Add other field types to schema
            $field_schema = get_schema_type_for_field($field['type']);
            if ($field_schema) {
                $schema['properties'][$field['key']] = array_merge(
                    ['description' => $field['label'] ?? ''],
                    $field_schema
                );
            }
        }
    }

    return $schema;
}

/**
 * Add relation collection parameters
 */
function add_relation_collection_params($params, $post_type_data) {
    if (isset($post_type_data['fields'])) {
        foreach ($post_type_data['fields'] as $field) {
            // Add parameters for filtering by fields
            if (in_array($field['type'], ['post-relation', 'taxonomy', 'select', 'number', 'date', 'text', 'email', 'url'])) {
                $params[$field['key']] = [
                    'description' => sprintf('Filter by %s', $field['label'] ?? $field['key']),
                    'type' => in_array($field['type'], ['number']) ? 'number' : 'string',
                ];
                
                // Add range filters for number and date fields
                if (in_array($field['type'], ['number', 'date'])) {
                    $params[$field['key'] . '_min'] = [
                        'description' => sprintf('Filter by minimum %s', $field['label'] ?? $field['key']),
                        'type' => $field['type'] === 'number' ? 'number' : 'string',
                    ];
                    
                    $params[$field['key'] . '_max'] = [
                        'description' => sprintf('Filter by maximum %s', $field['label'] ?? $field['key']),
                        'type' => $field['type'] === 'number' ? 'number' : 'string',
                    ];
                }
            }
        }
    }
    return $params;
}

/**
 * Filter query by custom parameters
 */
function filter_query_by_custom_params($args, $request, $post_type_data) {
    if (!isset($post_type_data['fields'])) {
        return $args;
    }

    $meta_query = isset($args['meta_query']) ? $args['meta_query'] : [];
    
    foreach ($post_type_data['fields'] as $field) {
        $param_value = $request->get_param($field['key']);
        
        if ($param_value !== null) {
            switch ($field['type']) {
                case 'post-relation':
                    // This requires a custom query, handled outside meta_query
                    global $wpdb;
                    $relation_key = !empty($field['custom_key']) ? $field['custom_key'] : $field['key'];
                    $related_posts = is_array($param_value) ? $param_value : [$param_value];
                    
                    // Get post IDs related to the specified posts
                    $placeholders = implode(',', array_fill(0, count($related_posts), '%d'));
                    
                    // Fix for proper relation query
                    $query_params = array_merge($related_posts, [$relation_key]);
                    $post_ids = $wpdb->get_col($wpdb->prepare(
                        "SELECT DISTINCT parent_id 
                        FROM {$wpdb->prefix}voxel_relations 
                        WHERE child_id IN ($placeholders) 
                        AND relation_key = %s",
                        $query_params
                    ));
                    
                    // Handle the case when we need to find children instead of parents
                    if (isset($field['relation_type']) && ($field['relation_type'] === 'has_one' || $field['relation_type'] === 'has_many')) {
                        $child_post_ids = $wpdb->get_col($wpdb->prepare(
                            "SELECT DISTINCT child_id 
                            FROM {$wpdb->prefix}voxel_relations 
                            WHERE parent_id IN ($placeholders) 
                            AND relation_key = %s",
                            $query_params
                        ));
                        
                        // Merge with parent post IDs
                        $post_ids = array_unique(array_merge($post_ids, $child_post_ids));
                    }
                    
                    if (!empty($post_ids)) {
                        if (empty($args['post__in'])) {
                            $args['post__in'] = $post_ids;
                        } else {
                            $args['post__in'] = array_intersect($args['post__in'], $post_ids);
                            if (empty($args['post__in'])) {
                                $args['post__in'] = [0]; // No results
                            }
                        }
                    } else {
                        $args['post__in'] = [0]; // No results
                    }
                    break;
                    
                case 'number':
                    // Handle range filters
                    $min = $request->get_param($field['key'] . '_min');
                    $max = $request->get_param($field['key'] . '_max');
                    
                    if ($min !== null && $max !== null) {
                        $meta_query[] = [
                            'key' => $field['key'],
                            'value' => [$min, $max],
                            'type' => 'NUMERIC',
                            'compare' => 'BETWEEN',
                        ];
                    } elseif ($min !== null) {
                        $meta_query[] = [
                            'key' => $field['key'],
                            'value' => $min,
                            'type' => 'NUMERIC',
                            'compare' => '>=',
                        ];
                    } elseif ($max !== null) {
                        $meta_query[] = [
                            'key' => $field['key'],
                            'value' => $max,
                            'type' => 'NUMERIC',
                            'compare' => '<=',
                        ];
                    } else {
                        $meta_query[] = [
                            'key' => $field['key'],
                            'value' => $param_value,
                            'type' => 'NUMERIC',
                            'compare' => '=',
                        ];
                    }
                    break;
                    
                case 'taxonomy':
                    // Skip taxonomy filtering here as it's better handled by WP_Query's tax_query
                    if (!empty($param_value)) {
                        $term_ids = get_term_ids_from_slugs($field['taxonomy'], $param_value);
                        if (!empty($term_ids)) {
                            if (empty($args['tax_query'])) {
                                $args['tax_query'] = [];
                            }
                            
                            $args['tax_query'][] = [
                                'taxonomy' => $field['taxonomy'],
                                'field' => 'term_id',
                                'terms' => $term_ids,
                            ];
                        }
                    }
                    break;
                    
                case 'date':
                    // Handle date range filters
                    $min = $request->get_param($field['key'] . '_min');
                    $max = $request->get_param($field['key'] . '_max');
                    
                    if ($min !== null && $max !== null) {
                        $meta_query[] = [
                            'key' => $field['key'],
                            'value' => [date('Y-m-d', strtotime($min)), date('Y-m-d', strtotime($max))],
                            'type' => 'DATE',
                            'compare' => 'BETWEEN',
                        ];
                    } elseif ($min !== null) {
                        $meta_query[] = [
                            'key' => $field['key'],
                            'value' => date('Y-m-d', strtotime($min)),
                            'type' => 'DATE',
                            'compare' => '>=',
                        ];
                    } elseif ($max !== null) {
                        $meta_query[] = [
                            'key' => $field['key'],
                            'value' => date('Y-m-d', strtotime($max)),
                            'type' => 'DATE',
                            'compare' => '<=',
                        ];
                    } else {
                        $meta_query[] = [
                            'key' => $field['key'],
                            'value' => $param_value,
                            'compare' => 'LIKE',
                        ];
                    }
                    break;
                    
                default:
                    // Default behavior for other field types
                    $meta_query[] = [
                        'key' => $field['key'],
                        'value' => $param_value,
                        'compare' => 'LIKE',
                    ];
                    break;
            }
        }
    }
    
    if (!empty($meta_query)) {
        $args['meta_query'] = $meta_query;
    }
    
    return $args;
}
