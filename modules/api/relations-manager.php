<?php
// Exit if accessed directly
if (!defined('ABSPATH')) exit;

/**
 * Relations Manager class - handles post relationships
 */
class VBM_Relations_Manager {
    /**
     * Get post relation value
     */
    public function get_relation_value($post_id, $relation_key, $relation_type) {
        return get_post_relation_value($post_id, $relation_key, $relation_type);
    }
    
    /**
     * Add post relation
     */
    public function add_relation($parent_id, $child_id, $relation_key, $order = 0) {
        return add_post_relation($parent_id, $child_id, $relation_key, $order);
    }
    
    /**
     * Delete post relations
     */
    public function delete_relations($post_id, $relation_key, $related_id = null) {
        return delete_post_relations($post_id, $relation_key, $related_id);
    }
    
    /**
     * Clean up relations when a post is deleted
     */
    public function cleanup_relations($post_id) {
        return cleanup_voxel_relations($post_id);
    }
}
