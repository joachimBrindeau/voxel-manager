<?php
// Exit if accessed directly
if (!defined('ABSPATH')) exit;

/**
 * Get post relation value
 */
function get_post_relation_value($post_id, $relation_key, $relation_type) {
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
 * Add post relation
 */
function add_post_relation($parent_id, $child_id, $relation_key, $order = 0) {
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
 * Delete post relations
 */
function delete_post_relations($post_id, $relation_key, $related_id = null) {
    global $wpdb;
    $conditions = ['relation_key' => $relation_key, 'parent_id' => $post_id];
    if ($related_id) {
        $conditions['child_id'] = $related_id;
    }
    $wpdb->delete($wpdb->prefix . 'voxel_relations', $conditions, array_fill(0, count($conditions), '%d'));
}

/**
 * Clean up relations when a post is deleted
 */
function cleanup_voxel_relations($post_id) {
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
