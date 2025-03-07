<?php
/**
 * API Module
 * 
 * Exposes Voxel post types and custom fields to the WordPress REST API
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class VBM_API_Module extends VBM_Module {
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(
            'api',
            'API Access',
            'Exposes Voxel post types and custom fields to the WordPress REST API',
            '1.0.0'
        );
    }
    
    /**
     * Register hooks
     */
    protected function register_hooks() {
        add_action('init', [$this, 'expose_voxel_cpts_to_rest_api'], 99);
        add_action('rest_api_init', [$this, 'register_custom_fields_in_rest_api'], 20);
        add_action('before_delete_post', [$this, 'cleanup_voxel_relations']);
        
        // Handle repeater fields in REST API
        add_filter('rest_prepare_post', [$this, 'prepare_repeater_fields'], 10, 3);
        add_filter('rest_request_after_callbacks', [$this, 'save_repeater_fields'], 10, 3);
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        ?>
        <div class="wrap vbm-module-page">
            <div class="vbm-card">
                <h2>API Endpoints</h2>
                <p>Below are the available endpoints for your Voxel post types:</p>
                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Post Type</th>
                            <th>API Endpoint</th>
                            <th>Available Methods</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $post_types = json_decode(get_option('voxel:post_types'), true) ?: [];
                        foreach ($post_types as $post_type => $post_type_data): 
                            if (!in_array($post_type, ['post', 'page'])):
                        ?>
                        <tr>
                            <td><?php echo esc_html($post_type_data['settings']['singular'] ?? $post_type); ?></td>
                            <td>
                                <code><?php echo esc_html(rest_url('wp/v2/' . $post_type)); ?></code>
                                <a href="<?php echo esc_url(rest_url('wp/v2/' . $post_type)); ?>" target="_blank">
                                    <span class="dashicons dashicons-external"></span>
                                </a>
                            </td>
                            <td>GET, POST, PUT, DELETE</td>
                        </tr>
                        <?php 
                            endif;
                        endforeach; 
                        
                        if (empty($post_types)): ?>
                        <tr>
                            <td colspan="3">No Voxel post types found.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
    
    /**
     * Expose Voxel CPTs to REST API
     */
    public function expose_voxel_cpts_to_rest_api() {
        $voxel_post_types = json_decode(get_option('voxel:post_types'), true) ?: [];
        foreach ($voxel_post_types as $post_type => $post_type_data) {
            if (!in_array($post_type, ['post', 'page'])) {
                $obj = get_post_type_object($post_type);
                if ($obj) {
                    // Add REST API support
                    $obj->show_in_rest = true;
                    $obj->rest_base = $post_type;
                    $obj->rest_controller_class = 'WP_REST_Posts_Controller';
                    
                    // Add create/delete capabilities
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
                        return $this->add_relation_collection_params($params, $post_type_data);
                    });
                    
                    add_filter("rest_{$post_type}_permissions_check", function($permission, $request) {
                        return $this->check_rest_permission($permission, $request);
                    }, 10, 2);
                    
                    add_filter("rest_{$post_type}_schema", function($schema) use ($post_type_data) {
                        return $this->add_relation_schema($schema, $post_type_data);
                    }, 20);
                }
            }
        }
    }
    
    /**
     * Check REST permission
     */
    public function check_rest_permission($permission, $request) {
        $method = $request->get_method();
        
        // Allow GET requests
        if ($method === 'GET') {
            return true;
        }
        
        // Check if user can create/edit/delete posts
        switch ($method) {
            case 'POST':
                return current_user_can('publish_posts');
            case 'PUT':
            case 'PATCH':
                $post_id = $request->get_param('id');
                return current_user_can('edit_post', $post_id);
            case 'DELETE':
                $post_id = $request->get_param('id');
                return current_user_can('delete_post', $post_id);
            default:
                return false;
        }
    }
    
    /**
     * Register custom fields in REST API
     */
    public function register_custom_fields_in_rest_api() {
        $voxel_post_types = json_decode(get_option('voxel:post_types'), true) ?: [];
        
        foreach ($voxel_post_types as $post_type => $post_type_data) {
            if (isset($post_type_data['fields'])) {
                foreach ($post_type_data['fields'] as $field) {
                    if (in_array($field['type'], ['ui-step', 'title'])) continue;
                    
                    if ($field['type'] === 'post-relation') {
                        $relation_key = !empty($field['custom_key']) ? $field['custom_key'] : $field['key'];
                        
                        register_rest_field($post_type, $field['key'], [
                            'get_callback' => function($post_arr) use ($relation_key, $field) {
                                // Return array of IDs directly
                                return $this->get_post_relation_value($post_arr['id'], $relation_key, $field['relation_type']);
                            },
                            'update_callback' => function($value, $post, $field_name) use ($field) {
                                return $this->update_custom_field_value($post->ID, $field, $value);
                            },
                            'schema' => [
                                'type' => 'string',
                                'context' => ['view', 'edit'],
                                'description' => $field['label'] ?? ''
                            ]
                        ]);
                    } else {
                        register_rest_field($post_type, $field['key'], [
                            'get_callback' => function($post_arr) use ($field) {
                                return $this->get_custom_field_value($post_arr['id'], $field);
                            },
                            'schema' => array_merge(
                                ['description' => $field['label'] ?? ''],
                                $this->get_schema_type_for_field($field['type'])
                            ),
                        ]);
                    }
                }
                
                // Add single filter for the post type
                add_filter("rest_prepare_{$post_type}", function($response, $post, $request) use ($post_type_data) {
                    return $this->handle_rest_response($response, $post, $request, $post_type_data['fields']);
                }, 10, 3);
            }
        }
    }
    
    /**
     * Get schema type for field
     */
    private function get_schema_type_for_field($field_type) {
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
     * Get custom field value
     */
    private function get_custom_field_value($post_id, $field) {
        $value = get_post_meta($post_id, $field['key'], true);

        switch ($field['type']) {
            case 'post-relation':
                $related_posts = [];
                $related_ids = $this->get_post_relation_value($post_id, $field['key'], $field['relation_type']);
                foreach ($related_ids as $related_id) {
                    $post = get_post($related_id);
                    if ($post) {
                        $related_posts[] = $this->format_related_post($post);
                    }
                }
                return $related_posts;
            case 'switcher':
                return (bool) $value;
            case 'repeater':
                // If value is a string, try to decode it first
                if (is_string($value)) {
                    // Remove any surrounding quotes if they exist
                    $value = trim($value, '"\'');
                    
                    // Try to decode the JSON string
                    $decoded = json_decode($value, true);
                    
                    // If successfully decoded, re-encode with clean slashes
                    if (is_array($decoded)) {
                        return wp_json_encode($decoded, JSON_UNESCAPED_SLASHES);
                    }
                    
                    // If it's serialized, unserialize it
                    $unserialized = maybe_unserialize($value);
                    if (is_array($unserialized)) {
                        return wp_json_encode($unserialized, JSON_UNESCAPED_SLASHES);
                    }
                }
                
                // If value is already an array
                if (is_array($value)) {
                    return wp_json_encode($value, JSON_UNESCAPED_SLASHES);
                }
                
                // Default empty array
                return '[]';
            case 'image':
            case 'file':
                return (int) $value ?: null;
            case 'location':
                $location = maybe_unserialize($value);
                return is_array($location) ? $location : null;
            case 'taxonomy':
                $terms = wp_get_object_terms($post_id, $field['taxonomy'], array('fields' => 'slugs'));
                if (!is_wp_error($terms)) {
                    return implode(',', $terms);
                }
                return '';
            case 'number':
                return $value !== '' ? (float) $value : null;
            case 'date':
                return $value ? date('c', strtotime($value)) : null;
            case 'work-hours':
                return maybe_unserialize($value) ?: null;
            default:
                return $value !== '' ? (string) $value : null;
        }
    }
    
    /**
     * Update custom field value
     */
    private function update_custom_field_value($post_id, $field, $value) {
        switch ($field['type']) {
            case 'switcher':
                update_post_meta($post_id, $field['key'], (bool) $value);
                break;
            case 'repeater':
                // If the value is already a JSON string, return it
                if (is_string($value) && is_array(json_decode($value, true))) {
                    update_post_meta($post_id, $field['key'], $value);
                    return true;
                }
                
                // If it's a serialized array, unserialize and encode as JSON
                if (is_string($value)) {
                    $unserialized = maybe_unserialize($value);
                    if (is_array($unserialized)) {
                        update_post_meta($post_id, $field['key'], wp_json_encode($unserialized));
                        return true;
                    }
                }
                
                // If it's an array, encode it to JSON
                if (is_array($value)) {
                    update_post_meta($post_id, $field['key'], wp_json_encode($value));
                    return true;
                }
                
                update_post_meta($post_id, $field['key'], '[]');
                break;
            case 'post-relation':
                if (is_string($value)) {
                    $new_relations = array_map('intval', explode(',', $value));
                } else {
                    $new_relations = is_array($value) ? array_map('intval', $value) : [(int) $value];
                }
                
                // Get current relations
                $relation_key = !empty($field['custom_key']) ? $field['custom_key'] : $field['key'];
                $current_relations = $this->get_post_relation_value($post_id, $relation_key, $field['relation_type']);
                
                // Determine relations to add and remove
                $relations_to_add = array_diff($new_relations, $current_relations);
                $relations_to_remove = array_diff($current_relations, $new_relations);
                
                // Add new relations
                foreach ($relations_to_add as $related_id) {
                    if (!$related_id) continue;
                    $this->add_post_relation($post_id, $related_id, $relation_key);
                }
                
                // Remove old relations
                foreach ($relations_to_remove as $related_id) {
                    if (!$related_id) continue;
                    $this->delete_post_relations($post_id, $relation_key, $related_id);
                }
                break;
            case 'location':
            case 'work-hours':
                if (is_array($value)) {
                    $value = wp_json_encode($value);
                }
                update_post_meta($post_id, $field['key'], $value);
                break;
            case 'number':
                update_post_meta($post_id, $field['key'], (float) $value);
                break;
            case 'date':
                update_post_meta($post_id, $field['key'], $value ? date('Y-m-d H:i:s', strtotime($value)) : '');
                break;
            case 'taxonomy':
                $term_ids = $this->get_term_ids_from_slugs($field['taxonomy'], $value);
                update_post_meta($post_id, $field['key'], $term_ids);
                // Also update WordPress taxonomy relationships
                wp_set_object_terms($post_id, $term_ids, $field['taxonomy']);
                break;
            default:
                update_post_meta($post_id, $field['key'], $value);
        }
        return true;
    }
    
    /**
     * Get post relation value
     */
    private function get_post_relation_value($post_id, $relation_key, $relation_type) {
        global $wpdb;
        
        $relations = $wpdb->get_results($wpdb->prepare(
            "SELECT parent_id, child_id 
            FROM {$wpdb->prefix}voxel_relations 
            WHERE (parent_id = %d OR child_id = %d) 
            AND relation_key = %s 
            ORDER BY `order` ASC",
            $post_id,
            $post_id,
            $relation_key
        ));

        $related_ids = [];
        foreach ($relations as $relation) {
            if ($relation_type === 'belongs_to_one' || $relation_type === 'belongs_to_many') {
                if ($relation->child_id == $post_id) {
                    $related_ids[] = (int) $relation->parent_id;
                }
            } else {
                if ($relation->parent_id == $post_id) {
                    $related_ids[] = (int) $relation->child_id;
                }
            }
        }

        return array_values(array_unique($related_ids));
    }
    
    /**
     * Format related post
     */
    private function format_related_post($post) {
        return [
            'id' => $post->ID,
            'type' => $post->post_type,
            'slug' => $post->post_name,
            'link' => get_permalink($post->ID),
            'title' => [
                'rendered' => get_the_title($post->ID)
            ],
        ];
    }
    
    /**
     * Handle REST response
     */
    private function handle_rest_response($response, $post, $request, $fields) {
        foreach ($fields as $field) {
            if ($field['type'] === 'post-relation') {
                $relation_key = !empty($field['custom_key']) ? $field['custom_key'] : $field['key'];
                $related_ids = $this->get_post_relation_value($post->ID, $relation_key, $field['relation_type']);
                
                if (!empty($related_ids)) {
                    $links = [];
                    foreach ($related_ids as $related_id) {
                        foreach ($field['post_types'] as $post_type) {
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
     * Add relation collection parameters
     */
    private function add_relation_collection_params($params, $post_type_data) {
        if (isset($post_type_data['fields'])) {
            foreach ($post_type_data['fields'] as $field) {
                if ($field['type'] === 'post-relation') {
                    $params[$field['key']] = [
                        'description' => $field['label'] ?? '',
                        'type' => 'array',
                        'items' => ['type' => 'integer'],
                        'default' => [],
                    ];
                }
            }
        }
        return $params;
    }
    
    /**
     * Add relation schema
     */
    private function add_relation_schema($schema, $post_type_data) {
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
                    'readonly' => true,
                    '$ref' => '#/definitions/' . $field['post_types'][0]
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
            }
        }

        return $schema;
    }
    
    /**
     * Get term IDs from slugs
     */
    private function get_term_ids_from_slugs($taxonomy, $slugs) {
        if (empty($slugs)) return [];
        
        // Convert string of comma-separated slugs to array
        if (is_string($slugs)) {
            $slugs = array_map('trim', explode(',', $slugs));
        }
        
        $term_ids = [];
        foreach ($slugs as $slug) {
            $term = get_term_by('slug', $slug, $taxonomy);
            if ($term && !is_wp_error($term)) {
                $term_ids[] = $term->term_id;
            }
        }
        return $term_ids;
    }
    
    /**
     * Delete post relations
     */
    private function delete_post_relations($post_id, $relation_key, $related_id = null) {
        global $wpdb;
        $conditions = ['relation_key' => $relation_key, 'parent_id' => $post_id];
        if ($related_id) {
            $conditions['child_id'] = $related_id;
        }
        return $wpdb->delete(
            $wpdb->prefix . 'voxel_relations', 
            $conditions, 
            array_fill(0, count($conditions), '%d')
        );
    }
    
    /**
     * Add post relation
     */
    private function add_post_relation($parent_id, $child_id, $relation_key, $order = 0) {
        global $wpdb;
        return $wpdb->insert(
            $wpdb->prefix . 'voxel_relations',
            [
                'parent_id' => $parent_id,
                'child_id' => $child_id,
                'relation_key' => $relation_key,
                'order' => $order
            ],
            ['%d', '%d', '%s', '%d']
        );
    }
    
    /**
     * Clean up relations when a post is deleted
     */
    public function cleanup_voxel_relations($post_id) {
        global $wpdb;
        
        // Delete relations where this post is either parent or child
        $wpdb->delete(
            $wpdb->prefix . 'voxel_relations',
            ['parent_id' => $post_id],
            ['%d']
        );
        
        $wpdb->delete(
            $wpdb->prefix . 'voxel_relations',
            ['child_id' => $post_id],
            ['%d']
        );
    }
    
    /**
     * Prepare repeater fields in REST API
     */
    public function prepare_repeater_fields($response, $post, $request) {
        $voxel_post_types = json_decode(get_option('voxel:post_types'), true) ?: [];
        $post_type = $post->post_type;
    
        if (isset($voxel_post_types[$post_type]['fields'])) {
            foreach ($voxel_post_types[$post_type]['fields'] as $field) {
                if ($field['type'] === 'repeater') {
                    $field_value = $this->get_custom_field_value($post->ID, $field);
                    if (is_array($field_value)) {
                        $response->data[$field['key']] = wp_json_encode($field_value);
                    }
                }
            }
        }
    
        return $response;
    }
    
    /**
     * Save repeater fields from REST API
     */
    public function save_repeater_fields($response, $handler, $request) {
        if ($request->get_method() === 'POST' || $request->get_method() === 'PUT') {
            $post_id = $response->data['id'];
            $voxel_post_types = json_decode(get_option('voxel:post_types'), true) ?: [];
            $post_type = get_post_type($post_id);
    
            if (isset($voxel_post_types[$post_type]['fields'])) {
                foreach ($voxel_post_types[$post_type]['fields'] as $field) {
                    if ($field['type'] === 'repeater' && isset($request[$field['key']])) {
                        $repeater_value = json_decode($request[$field['key']], true);
                        update_post_meta($post_id, $field['key'], $repeater_value);
                    }
                }
            }
        }
    
        return $response;
    }
}