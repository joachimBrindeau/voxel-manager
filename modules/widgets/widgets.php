<?php
/**
 * Widgets Module
 * Manages additional widgets for Voxel
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

// Include version management file
require_once VBM_PLUGIN_DIR . 'modules/widgets/widgets-versions.php';

class VBM_Widgets_Module extends VBM_Module {
    /**
     * @var array Available widgets
     */
    private $widgets = [];
    
    /**
     * @var array Active widgets
     */
    private $active_widgets = [];
    
    public function __construct() {
        parent::__construct(
            'widgets',
            'Additional Widgets',
            'Adds additional widgets and shortcodes to Voxel',
            '1.0.0'
        );
        
        // Register available widgets
        $this->register_available_widgets();
        
        // Load active widgets
        $this->load_active_widgets();
    }
    
    /**
     * Register available widgets
     */
    private function register_available_widgets() {
        // Register Claims List widget
        $this->widgets['claims_list'] = [
            'name' => 'Claims List',
            'description' => 'Displays claim requests through shortcodes',
            'file' => VBM_PLUGIN_DIR . 'modules/widgets/claims-list/claims-list.php',
            'class' => 'VBM_Claims_List_Widget'
        ];
        
        // Register Post Action widget
        $this->widgets['post_action'] = [
            'name' => 'Post Action',
            'description' => 'Dropdown with action button to modify post fields',
            'file' => VBM_PLUGIN_DIR . 'modules/widgets/post-action/post-action.php',
            'class' => 'VBM_Post_Action_Widget'
        ];
        
        // Additional widgets can be registered here in the future
    }
    
    /**
     * Load active widgets
     */
    private function load_active_widgets() {
        $this->active_widgets = get_option('vbm_active_widgets', []);
        
        // If option doesn't exist yet, activate all widgets by default
        if (empty($this->active_widgets)) {
            $this->active_widgets = array_keys($this->widgets);
            update_option('vbm_active_widgets', $this->active_widgets);
        }
    }
    
    /**
     * Register module hooks
     */
    protected function register_hooks() {
        // Load active widgets
        add_action('init', [$this, 'load_widget_files'], 5);
        
        // Register assets
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }
    
    /**
     * Load widget files
     */
    public function load_widget_files() {
        foreach ($this->active_widgets as $widget_id) {
            if (isset($this->widgets[$widget_id]) && file_exists($this->widgets[$widget_id]['file'])) {
                require_once $this->widgets[$widget_id]['file'];
                
                // Initialize widget if class exists
                $class_name = $this->widgets[$widget_id]['class'];
                if (class_exists($class_name)) {
                    $widget = new $class_name();
                    if (method_exists($widget, 'initialize')) {
                        $widget->initialize();
                    }
                }
            }
        }
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        // Common widgets JavaScript
        wp_enqueue_script(
            'vbm-widgets',
            VBM_PLUGIN_URL . 'modules/widgets/widgets.js',
            ['jquery'],
            VBM_PLUGIN_VERSION,
            true
        );
        
        // Individual widget assets are loaded by their respective classes
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'voxel-widgets') !== false) {
            wp_enqueue_script(
                'vbm-widgets-admin',
                VBM_PLUGIN_URL . 'modules/widgets/widgets.js',
                ['jquery'],
                VBM_PLUGIN_VERSION,
                true
            );
        }
    }
    
    /**
     * Activate a widget
     * 
     * @param string $widget_id Widget ID
     * @return bool Success
     */
    public function activate_widget($widget_id) {
        if (!isset($this->widgets[$widget_id])) {
            return false;
        }
        
        if (!in_array($widget_id, $this->active_widgets)) {
            $this->active_widgets[] = $widget_id;
            update_option('vbm_active_widgets', $this->active_widgets);
            return true;
        }
        
        return false;
    }
    
    /**
     * Deactivate a widget
     * 
     * @param string $widget_id Widget ID
     * @return bool Success
     */
    public function deactivate_widget($widget_id) {
        if (!isset($this->widgets[$widget_id])) {
            return false;
        }
        
        if (in_array($widget_id, $this->active_widgets)) {
            $this->active_widgets = array_diff($this->active_widgets, [$widget_id]);
            update_option('vbm_active_widgets', $this->active_widgets);
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if a widget is active
     * 
     * @param string $widget_id Widget ID
     * @return bool Whether widget is active
     */
    public function is_widget_active($widget_id) {
        return in_array($widget_id, $this->active_widgets);
    }
    
    /**
     * Render module admin page
     */
    public function render_admin_page() {
        // Handle form submission
        if (isset($_POST['submit']) && check_admin_referer('vbm_update_widgets')) {
            $active_widgets = isset($_POST['widgets']) ? (array)$_POST['widgets'] : [];
            $active_widgets = array_intersect($active_widgets, array_keys($this->widgets));
            
            update_option('vbm_active_widgets', $active_widgets);
            $this->active_widgets = $active_widgets;
            
            echo '<div class="notice notice-success"><p>Widget settings updated successfully!</p></div>';
        }
        
        ?>
        <p>Enable or disable additional widgets for your site.</p>
        
        <form method="post" action="">
            <?php wp_nonce_field('vbm_update_widgets'); ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Widget</th>
                        <th>Description</th>
                        <th>Version</th>
                        <th>Status</th>
                        <th>Instructions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($this->widgets as $widget_id => $widget): 
                        $version_status = vbm_get_widget_version($widget_id);
                        $version_class = strtolower(str_replace(' ', '-', $version_status));
                    ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($widget['name']); ?></strong>
                            </td>
                            <td><?php echo esc_html($widget['description']); ?></td>
                            <td>
                                <span class="version-status <?php echo esc_attr($version_class); ?>">
                                    <?php echo esc_html($version_status); ?>
                                </span>
                            </td>
                            <td>
                                <label class="switch">
                                    <input type="checkbox" name="widgets[]" value="<?php echo esc_attr($widget_id); ?>" <?php checked(in_array($widget_id, $this->active_widgets)); ?>>
                                    <span class="slider"></span>
                                </label>
                            </td>
                            <td>
                                <button type="button" class="button button-secondary widget-doc-toggle" data-widget="<?php echo esc_attr($widget_id); ?>" onclick="showWidgetDocs('<?php echo esc_attr($widget_id); ?>')">
                                    See Instructions
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p class="submit">
                <input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes">
            </p>
        </form>
        
        <?php
        // Show documentation for each widget (hidden by default)
        foreach ($this->widgets as $widget_id => $widget):
            $class_name = $widget['class'];
            if (in_array($widget_id, $this->active_widgets) && class_exists($class_name)):
                $widget_instance = new $class_name();
            ?>
                <div id="widget-docs-<?php echo esc_attr($widget_id); ?>" class="widget-docs" style="display: none;">
                    <div class="widget-docs-header">
                        <h3><?php echo esc_html($widget['name']); ?> Instructions</h3>
                        <button type="button" class="button button-secondary close-widget-docs" onclick="hideWidgetDocs('<?php echo esc_attr($widget_id); ?>')">
                            Close Instructions
                        </button>
                    </div>
                    <?php 
                    if (method_exists($widget_instance, 'render_documentation')) {
                        $widget_instance->render_documentation();
                    } else {
                        echo '<p>No documentation available for this widget.</p>';
                    }
                    ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
        
        <style>
            .widget-docs-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 15px;
                padding-bottom: 10px;
                border-bottom: 1px solid #eee;
            }
            .widget-docs-header h3 {
                margin: 0;
            }
            /* Ensure documentation card is visible when shown */
            .widget-docs {
                position: relative;
                z-index: 100;
                background: #fff;
                border: 1px solid #ccd0d4;
                padding: 20px;
                margin-top: 20px;
                box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            }
        </style>
        
        <script>
            // Direct JavaScript functions that don't rely on jQuery ready
            function showWidgetDocs(widgetId) {
                console.log('Showing docs for widget:', widgetId);
                // Hide all docs first
                var allDocs = document.querySelectorAll('.widget-docs');
                for (var i = 0; i < allDocs.length; i++) {
                    allDocs[i].style.display = 'none';
                }
                
                // Show the selected widget docs
                var docElement = document.getElementById('widget-docs-' + widgetId);
                if (docElement) {
                    docElement.style.display = 'block';
                    // Scroll to the docs
                    docElement.scrollIntoView({behavior: 'smooth'});
                } else {
                    console.error('Documentation element not found:', 'widget-docs-' + widgetId);
                }
            }
            
            function hideWidgetDocs(widgetId) {
                console.log('Hiding docs for widget:', widgetId);
                var docElement = document.getElementById('widget-docs-' + widgetId);
                if (docElement) {
                    docElement.style.display = 'none';
                }
            }
            
            // For jQuery-based functionality, we'll still keep this as a backup
            jQuery(document).ready(function($) {
                console.log('Widget manager ready');
                
                // Log available widgets for debugging
                $('.widget-doc-toggle').each(function() {
                    console.log('Found widget toggle button:', $(this).data('widget'));
                });
                
                // We're using onclick attributes now, but keeping this as backup
                $('.widget-doc-toggle').on('click', function(e) {
                    var widgetId = $(this).data('widget');
                    console.log('jQuery click handler - Showing docs for:', widgetId);
                    // Functionality now handled by onclick
                });
                
                $('.close-widget-docs').on('click', function(e) {
                    console.log('jQuery click handler - Hiding docs');
                    // Functionality now handled by onclick
                });
            });
        </script>
        <?php
    }
}
