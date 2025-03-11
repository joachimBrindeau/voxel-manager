<?php
if (!defined('ABSPATH')) exit;

class VBM_FirstCollection_Enhancement {
    public function __construct() {
        add_action('user_register', [$this, 'create_first_collection_for_user']);
    }

    public function create_first_collection_for_user($user_id) {
        $title = get_option('vbm_first_collection_title', 'My First Collection');
        $desc  = get_option('vbm_first_collection_description', 'Welcome to my collection.');

        wp_insert_post([
            'post_type'   => 'collection',
            'post_title'  => $title,
            'post_content'=> $desc,
            'post_status' => 'publish',
            'post_author' => $user_id,
        ]);
    }
}
