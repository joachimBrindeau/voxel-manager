<?php
// Exit if accessed directly
if (!defined('ABSPATH')) exit;

/**
 * Check if string is valid JSON
 */
function is_json($string) {
    json_decode($string);
    return (json_last_error() === JSON_ERROR_NONE);
}

/**
 * Get term IDs from slugs
 */
function get_term_ids_from_slugs($taxonomy, $slugs) {
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
 * Check if a REST route is registered
 * 
 * This is a simple way to check if a route might be registered,
 * but it's not 100% accurate. We use a static variable to track
 * our own route registrations instead.
 * 
 * @param string $namespace The route namespace
 * @param string $route The route path
 * @return bool Whether route appears to be registered
 */
function vbm_check_rest_route($namespace, $route) {
    // Safe access to REST server
    if (!function_exists('rest_get_server')) {
        return false;
    }
    
    $server = rest_get_server();
    if (!$server) {
        return false;
    }
    
    // Get registered routes
    $routes = $server->get_routes();
    
    // Check if route exists
    $full_route = '/' . trim($namespace, '/') . '/' . trim($route, '/');
    foreach ($routes as $route_pattern => $route_handlers) {
        if ($route_pattern === $full_route || strpos($route_pattern, $full_route) === 0) {
            return true;
        }
    }
    
    return false;
}

/**
 * Safely register a REST route while avoiding duplicates
 * 
 * @param string $namespace Route namespace
 * @param string $route Route path
 * @param array $args Route arguments
 * @return bool Whether registration was attempted
 */
function vbm_register_rest_route($namespace, $route, $args) {
    static $registered_routes = [];
    
    // Create a unique key for this route
    $route_key = $namespace . '/' . $route;
    
    // Only register if not already registered
    if (!isset($registered_routes[$route_key])) {
        register_rest_route($namespace, $route, $args);
        $registered_routes[$route_key] = true;
        return true;
    }
    
    return false;
}

/**
 * Safely get field property with default value
 * 
 * @param array $field Field configuration array
 * @param string $key Property key to access
 * @param mixed $default Default value if key doesn't exist
 * @return mixed Field property value or default
 */
function vbm_get_field_property($field, $key, $default = null) {
    return isset($field[$key]) ? $field[$key] : $default;
}
