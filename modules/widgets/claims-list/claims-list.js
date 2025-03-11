/**
 * Voxel Manager - Claims List Widget Scripts
 */
(function($) {
    'use strict';
    
    // Create Claims List namespace
    window.VoxelWidgets = window.VoxelWidgets || {};
    window.VoxelWidgets.ClaimsList = {};
    
    // Initialize Claims List functionality
    window.VoxelWidgets.ClaimsList.init = function() {
        // Future enhancements can be added here
        // Currently, all functionality is handled via CSS and the onclick attribute
        
        // Example of using shared utilities:
        // VoxelWidgets.utils.applyClickHandlers('.claim-request-item', function() {
        //     // Custom click handler logic
        // });
    };
    
    // When document is ready
    $(document).ready(function() {
        // Initialize the widget if it exists on the page
        if ($('.claim-requests-list').length > 0) {
            window.VoxelWidgets.ClaimsList.init();
        }
    });
    
})(jQuery);
