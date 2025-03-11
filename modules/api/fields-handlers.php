<?php
// Exit if accessed directly
if (!defined('ABSPATH')) exit;

/**
 * Get custom field value
 */
function get_custom_field_value($post_id, $field) {
    $value = get_post_meta($post_id, $field['key'], true);
    
    // Use field registry if available
    if (function_exists('vbm_field_registry') && method_exists(vbm_field_registry(), 'transform_value')) {
        $transformed = vbm_field_registry()->transform_value($value, $field);
        if ($transformed !== $value) {
            return $transformed;
        }
    }
    
    switch ($field['type']) {
        case 'post-relation':
            $related_posts = [];
            // Fix: Add default 'has_many' if relation_type is not set
            $relation_type = vbm_get_field_property($field, 'relation_type', 'has_many');
            $related_ids = get_post_relation_value($post_id, $field['key'], $relation_type);
            foreach ($related_ids as $related_id) {
                $post = get_post($related_id);
                if ($post) {
                    $related_posts[] = format_related_post($post);
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
            // Fix: Check if taxonomy is set before accessing
            $taxonomy = vbm_get_field_property($field, 'taxonomy');
            if (!$taxonomy) {
                return '';
            }
            $terms = wp_get_object_terms($post_id, $taxonomy, array('fields' => 'slugs'));
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
function update_custom_field_value($post_id, $field, $value) {
    // Use field registry for sanitization if available
    if (function_exists('vbm_field_registry') && method_exists(vbm_field_registry(), 'sanitize_value')) {
        $value = vbm_field_registry()->sanitize_value($value, $field);
    }
    
    switch ($field['type']) {
        case 'switcher':
            update_post_meta($post_id, $field['key'], (bool) $value);
            break;
        case 'repeater':
            // If the value is already a JSON string, return it
            if (is_string($value) && is_array(json_decode($value, true))) {
                return $value;
            }
            
            // If it's a serialized array, unserialize and encode as JSON
            if (is_string($value)) {
                $unserialized = maybe_unserialize($value);
                if (is_array($unserialized)) {
                    return wp_json_encode($unserialized);
                }
            }
            
            // If it's an array, encode it to JSON
            if (is_array($value)) {
                return wp_json_encode($value);
            }
            
            return '[]'; 
        case 'post-relation':
            if (is_string($value)) {
                $new_relations = array_map('intval', explode(',', $value));
            } else {
                $new_relations = is_array($value) ? array_map('intval', $value) : [(int) $value];
            }
            
            // Fix: Add default 'has_many' if relation_type is not set
            $relation_type = vbm_get_field_property($field, 'relation_type', 'has_many');
            
            // Get current relations
            $current_relations = get_post_relation_value($post_id, $field['key'], $relation_type);
            
            // Determine relations to add and remove
            $relations_to_add = array_diff($new_relations, $current_relations);
            $relations_to_remove = array_diff($current_relations, $new_relations);
            
            // Add new relations
            foreach ($relations_to_add as $related_id) {
                if ($related_id > 0) { // Ensure valid post ID
                    if ($relation_type === 'has_one' || $relation_type === 'has_many') {
                        add_post_relation($post_id, $related_id, $field['key']);
                    } else {
                        add_post_relation($related_id, $post_id, $field['key']);
                    }
                }
            }
            
            // Remove old relations
            foreach ($relations_to_remove as $related_id) {
                if ($related_id > 0) { // Ensure valid post ID
                    if ($relation_type === 'has_one' || $relation_type === 'has_many') {
                        delete_post_relations($post_id, $field['key'], $related_id);
                    } else {
                        delete_post_relations($related_id, $field['key'], $post_id);
                    }
                }
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
            // Fix: Check if taxonomy is set before accessing
            $taxonomy = vbm_get_field_property($field, 'taxonomy');
            if (!$taxonomy) {
                break;
            }
            $term_ids = get_term_ids_from_slugs($taxonomy, $value);
            update_post_meta($post_id, $field['key'], $term_ids);
            // Also update WordPress taxonomy relationships
            wp_set_object_terms($post_id, $term_ids, $taxonomy);
            break;
        default:
            update_post_meta($post_id, $field['key'], $value);
    }
    return true;
}

/**
 * Format related post
 */
function format_related_post($post) {
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
