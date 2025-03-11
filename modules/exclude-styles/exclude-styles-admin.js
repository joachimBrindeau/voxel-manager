/**
 * Voxel Exclude Styles - Admin Interface
 * Handles the field exclusions UI in the admin area
 */

(function($) {
    'use strict';
    
    // Main controller object
    const VBMExcludeStylesAdmin = {
        init: function() {
            this.initSelectors();
            this.setupEventListeners();
            this.setupDebugTools();
        },
        
        initSelectors: function() {
            // Initialize Select2 dropdowns
            $('.vbm-exclusion-selector').select2({
                placeholder: 'Select styles to exclude',
                allowClear: true,
                width: '100%'
            });
        },
        
        setupEventListeners: function() {
            // Handle changes to exclusion selectors
            $('.vbm-exclusion-selector').on('change', function() {
                const $row = $(this).closest('tr');
                const postType = $row.data('post-type');
                const fieldKey = $row.data('field-key');
                const selectedExclusions = $(this).val() || [];
                
                VBMExcludeStylesAdmin.updateFieldExclusions(postType, fieldKey, selectedExclusions);
            });
        },
        
        // Setup debugging tools
        setupDebugTools: function() {
            // Only run in debug mode
            if (!vbmExcludeStylesAdmin || !vbmExcludeStylesAdmin.debug) {
                return;
            }
            
            // Add debug button
            const $debugBtn = $('<button>', {
                type: 'button',
                class: 'button',
                id: 'vbm-debug-field-types',
                text: 'Analyze Field Types',
                css: {
                    'margin-top': '10px'
                }
            });
            
            // Add debug container
            const $debugContainer = $('<div>', {
                id: 'vbm-debug-output',
                class: 'notice notice-info',
                css: {
                    'padding': '10px',
                    'margin-top': '15px',
                    'display': 'none'
                }
            }).html('<h3>Field Type Analysis</h3><div id="vbm-debug-content"></div>');
            
            // Append to the page
            $('.vbm-table-note').last().after($debugBtn).after($debugContainer);
            
            // Setup click handler
            $debugBtn.on('click', function() {
                VBMExcludeStylesAdmin.analyzeFieldTypes();
            });
        },
        
        // Analyze field types for debugging
        analyzeFieldTypes: function() {
            $.ajax({
                url: vbmExcludeStylesAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vbm_analyze_field_types',
                    nonce: vbmExcludeStylesAdmin.nonce
                },
                beforeSend: function() {
                    $('#vbm-debug-content').html('<p>Analyzing field types...</p>');
                    $('#vbm-debug-output').show();
                },
                success: function(response) {
                    if (response.success) {
                        let html = '<p><strong>Field types found:</strong></p><ul>';
                        
                        for (const [type, count] of Object.entries(response.data.types)) {
                            html += `<li><code>${type}</code>: ${count} field(s)</li>`;
                        }
                        
                        html += '</ul>';
                        
                        if (response.data.suggestions.length > 0) {
                            html += '<p><strong>Suggestions:</strong></p><ul>';
                            response.data.suggestions.forEach(suggestion => {
                                html += `<li>${suggestion}</li>`;
                            });
                            html += '</ul>';
                        }
                        
                        $('#vbm-debug-content').html(html);
                    } else {
                        $('#vbm-debug-content').html('<p class="notice-error">Error analyzing field types</p>');
                    }
                },
                error: function() {
                    $('#vbm-debug-content').html('<p class="notice-error">Network error occurred</p>');
                }
            });
        },
        
        // Show status message
        showStatus: function(message, type = 'success') {
            const $status = $('#vbm-status-message');
            $status.removeClass('notice-success notice-error')
                   .addClass(type === 'success' ? 'notice-success' : 'notice-error')
                   .html(`<p>${message}</p>`)
                   .show();
            
            setTimeout(function() {
                $status.fadeOut();
            }, 3000);
        },
        
        // Update field exclusions via AJAX
        updateFieldExclusions: function(postType, fieldKey, exclusions) {
            $.ajax({
                url: vbmExcludeStylesAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vbm_update_field_exclusions',
                    nonce: vbmExcludeStylesAdmin.nonce,
                    post_type: postType,
                    field_key: fieldKey,
                    exclusions: exclusions
                },
                beforeSend: function() {
                    // Could add loading indicator here
                },
                success: function(response) {
                    if (response.success) {
                        VBMExcludeStylesAdmin.showStatus('Field exclusions updated successfully');
                    } else {
                        VBMExcludeStylesAdmin.showStatus(response.data.message || 'Error updating field exclusions', 'error');
                    }
                },
                error: function() {
                    VBMExcludeStylesAdmin.showStatus('Network error occurred', 'error');
                }
            });
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        VBMExcludeStylesAdmin.init();
    });
    
})(jQuery);
