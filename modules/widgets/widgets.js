/**
 * Voxel Manager - Additional Widgets Module
 * Common JavaScript functionality for widgets
 */
(function($) {
    'use strict';
    
    // Initialize widgets namespace
    window.VoxelWidgets = window.VoxelWidgets || {};
    
    // Common widget utilities
    window.VoxelWidgets.utils = {
        /**
         * Format a date string
         * @param {string} dateString - ISO date string
         * @param {string} format - Format (short, medium, long)
         * @return {string} Formatted date
         */
        formatDate: function(dateString, format) {
            const date = new Date(dateString);
            
            if (isNaN(date.getTime())) {
                return dateString; // Return original if invalid
            }
            
            switch(format) {
                case 'short':
                    return date.toLocaleDateString();
                case 'medium':
                    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                case 'long':
                    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
                default:
                    return date.toLocaleDateString();
            }
        },
        
        /**
         * Apply click handlers to elements
         * @param {string} selector - Element selector
         * @param {Function} callback - Click handler
         */
        applyClickHandlers: function(selector, callback) {
            $(document).on('click', selector, callback);
        }
    };
    
    // Admin-specific functionality
    $(document).ready(function() {
        // Toggle widget documentation sections
        VoxelWidgets.utils.applyClickHandlers('.widget-doc-toggle', function(e) {
            e.preventDefault();
            const widgetId = $(this).data('widget');
            $('.widget-docs').hide();
            $('#widget-docs-' + widgetId).show();
        });
        
        // Close documentation
        VoxelWidgets.utils.applyClickHandlers('.close-widget-docs', function(e) {
            e.preventDefault();
            $(this).closest('.widget-docs').hide();
        });
    });
    
})(jQuery);
