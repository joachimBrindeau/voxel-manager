/**
 * Voxel Manager - Post Action Widget Scripts
 */
(function($) {
    'use strict';
    
    // Create Post Action namespace
    window.VoxelWidgets = window.VoxelWidgets || {};
    window.VoxelWidgets.PostAction = {};
    
    // Initialize Post Action functionality
    window.VoxelWidgets.PostAction.init = function() {
        // Attach event listener to all action buttons
        $('.vbm-post-action-button').on('click', function(e) {
            e.preventDefault();
            
            const button = $(this);
            const container = button.closest('.vbm-post-action-widget');
            const dropdown = container.find('.vbm-post-dropdown');
            const messagesContainer = container.find('.vbm-post-action-messages');
            
            // Get selected value
            const selectedId = dropdown.val();
            
            // Validate selection
            if (!selectedId) {
                VoxelWidgets.PostAction.showMessage(messagesContainer, 'Please select an item from the dropdown', 'error');
                return;
            }
            
            // Get button data attributes
            const postId = button.data('post');
            const fieldKey = button.data('field');
            const actionType = button.data('action');
            
            // Disable button while processing
            button.prop('disabled', true).addClass('processing');
            
            // Show loading message
            VoxelWidgets.PostAction.showMessage(messagesContainer, 'Processing...', 'info');
            
            // Send AJAX request
            $.ajax({
                url: vbm_post_action.ajax_url,
                type: 'POST',
                data: {
                    action: 'vbm_post_action',
                    nonce: vbm_post_action.nonce,
                    post_id: postId,
                    field_key: fieldKey,
                    selected_id: selectedId,
                    action_type: actionType
                },
                success: function(response) {
                    if (response.success) {
                        VoxelWidgets.PostAction.showMessage(messagesContainer, response.data.message, 'success');
                        
                        // Reset dropdown to default option
                        dropdown.val('');
                    } else {
                        VoxelWidgets.PostAction.showMessage(messagesContainer, response.data.message, 'error');
                    }
                },
                error: function() {
                    VoxelWidgets.PostAction.showMessage(messagesContainer, 'An error occurred while processing your request', 'error');
                },
                complete: function() {
                    // Re-enable button
                    button.prop('disabled', false).removeClass('processing');
                }
            });
        });
    };
    
    // Helper function to show messages
    window.VoxelWidgets.PostAction.showMessage = function(container, message, type) {
        // Clear previous messages
        container.empty();
        
        // Add new message
        const messageClass = type === 'error' ? 'error' : (type === 'success' ? 'success' : 'info');
        container.append(
            $('<div class="vbm-message vbm-message-' + messageClass + '"></div>').text(message)
        );
        
        // Auto-hide success messages after 3 seconds
        if (type === 'success') {
            setTimeout(function() {
                container.find('.vbm-message').fadeOut(300, function() {
                    $(this).remove();
                });
            }, 3000);
        }
    };
    
    // When document is ready
    $(document).ready(function() {
        // Initialize the widget if it exists on the page
        if ($('.vbm-post-action-widget').length > 0) {
            window.VoxelWidgets.PostAction.init();
        }
    });
    
    // Helper function to add WordPress core classes to dynamically created elements
    function addWpCoreClasses() {
        // Add WordPress core button classes to post action buttons
        const actionButtons = document.querySelectorAll('.vbm-post-action-button');
        actionButtons.forEach(button => {
            if (!button.classList.contains('button')) {
                button.classList.add('button', 'button-primary');
            }
        });
        
        // Add WordPress notification classes to message elements
        const successMessages = document.querySelectorAll('.vbm-message-success');
        successMessages.forEach(msg => {
            if (!msg.classList.contains('notice')) {
                msg.classList.add('notice', 'notice-success');
            }
        });
        
        const errorMessages = document.querySelectorAll('.vbm-message-error');
        errorMessages.forEach(msg => {
            if (!msg.classList.contains('notice')) {
                msg.classList.add('notice', 'notice-error');
            }
        });
        
        const infoMessages = document.querySelectorAll('.vbm-message-info');
        infoMessages.forEach(msg => {
            if (!msg.classList.contains('notice')) {
                msg.classList.add('notice', 'notice-info');
            }
        });
    }
    
    // Call when document is ready and when content is updated dynamically
    document.addEventListener('DOMContentLoaded', addWpCoreClasses);
    
    // Use MutationObserver to detect dynamic content changes
    const observer = new MutationObserver(mutations => {
        mutations.forEach(mutation => {
            if (mutation.addedNodes && mutation.addedNodes.length) {
                addWpCoreClasses();
            }
        });
    });
    
    // Start observing the document body for changes
    observer.observe(document.body, { 
        childList: true, 
        subtree: true 
    });
    
})(jQuery);
