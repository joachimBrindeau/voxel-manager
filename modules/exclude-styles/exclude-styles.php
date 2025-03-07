<?php
/**
 * Exclude Styles Module
 * 
 * Allows removing specific TinyMCE editor styles and controls based on CSS classes
 * added to Voxel field containers.
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class VBM_Exclude_Styles_Module extends VBM_Module {
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(
            'exclude_styles',
            'Style Manager',
            'Selectively remove formatting options from the TinyMCE editor in Voxel fields by adding special CSS classes.',
            '1.0.0'
        );
    }
    
    /**
     * Register hooks
     */
    protected function register_hooks() {
        // Enqueue scripts on admin and frontend pages
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        
        // Add debug information for admins
        if (current_user_can('manage_options') && is_admin()) {
            add_action('admin_notices', [$this, 'admin_notice']);
        }
    }
    
    /**
     * Enqueue admin assets
     * 
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_assets($hook) {
        $page = $_GET['page'] ?? '';
        
        if ($page === 'voxel-exclude_styles') {
            wp_enqueue_style('vbm-style', VBM_PLUGIN_URL . 'assets/css/style.css', [], VBM_PLUGIN_VERSION);
        }
    }
    
    /**
     * Show debug notice to admin users
     */
    public function admin_notice() {
        // Only show on TinyMCE-related screens
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->base, ['post', 'page', 'voxel_post_type'])) {
            return;
        }
        
        echo '<div class="notice notice-info is-dismissible">';
        echo '<p><strong>Exclude Styles Module:</strong> Active and monitoring for class-based exclusions. Add <code>exclude-{element}</code> classes to your fields to hide TinyMCE elements.</p>';
        echo '</div>';
    }
    
    /**
     * Enqueue scripts on both admin and frontend
     */
    public function enqueue_scripts() {
        // Only load on pages that might have TinyMCE editor
        $is_admin = is_admin();
        $screen = $is_admin ? get_current_screen() : null;
        
        // Load on standard admin edit screens
        $is_editor_screen = $screen && (
            method_exists($screen, 'is_block_editor') && $screen->is_block_editor() || 
            in_array($screen->base, ['post', 'page', 'voxel_post_type'])
        );
        
        // Also load on frontend when Voxel forms might be present
        $is_voxel_frontend = !$is_admin && (
            is_singular() || 
            (function_exists('vx_is_template') && vx_is_template('create')) ||
            isset($_GET['action']) && $_GET['action'] === 'edit'
        );
        
        if (!$is_editor_screen && !$is_voxel_frontend) {
            return;
        }
        
        // Enqueue with debugging data
        wp_enqueue_script(
            'voxel-exclude-styles', 
            VBM_PLUGIN_URL . 'modules/exclude-styles/exclude-styles.js',
            ['jquery'],
            VBM_PLUGIN_VERSION,
            true
        );
        
        // Pass data to our script for better debugging
        wp_localize_script('voxel-exclude-styles', 'vbmExcludeStyles', [
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
            'isAdmin' => $is_admin,
            'version' => VBM_PLUGIN_VERSION
        ]);
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        ?>
        <div class="wrap">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Style</th>
                        <th>Exclusion Class</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Updated styles data based on the JS mapping
                    $styles = [
                        ['Heading 1', 'exclude-h1'],
                        ['Heading 2', 'exclude-h2'],
                        ['Heading 3', 'exclude-h3'],
                        ['Heading 4', 'exclude-h4'],
                        ['Heading 5', 'exclude-h5'],
                        ['Heading 6', 'exclude-h6'],
                        ['Paragraph', 'exclude-p'],
                        ['Bold', 'exclude-bold'],
                        ['Italic', 'exclude-italic'],
                        ['Bulleted list', 'exclude-bullist'],
                        ['Numbered list', 'exclude-numlist'],
                        ['Insert/edit link', 'exclude-link'],
                        ['Remove link', 'exclude-unlink'],
                        ['Strikethrough', 'exclude-strikethrough'],
                        ['Horizontal line', 'exclude-hr'],
                        ['Blockquote', 'exclude-blockquote'],
                        ['Preformatted', 'exclude-pre'],
                        ['Format', 'exclude-formatselect'],
                        ['Styles', 'exclude-styleselect'],
                        ['Font Family', 'exclude-fontselect'],
                        ['Font Sizes', 'exclude-fontsizeselect'],
                        ['Align left', 'exclude-alignleft'],
                        ['Align center', 'exclude-aligncenter'],
                        ['Align right', 'exclude-alignright'],
                        ['Text color', 'exclude-forecolor'],
                        ['Background color', 'exclude-backcolor']
                    ];
                    foreach ($styles as $style) {
                        echo '<tr>';
                        echo '<td>' . esc_html($style[0]) . '</td>';
                        echo '<td>' . esc_html($style[1]) . '</td>';
                        echo '</tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}