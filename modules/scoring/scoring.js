/**
 * Voxel Manager - Scoring Module JavaScript
 */
(function($) {
    'use strict';
    
    // Initialize on document ready
    $(document).ready(function() {
        initScoringModule();
        initTabNavigation();
        initDataTables();
        initFieldScoreEditing();
        initTargetScoreEditing();
        initTargetScoreUpdateButtons(); // Add this new function call
    });
    
    /**
     * Initialize the Scoring Module
     */
    function initScoringModule() {
        // Recalculate scores button
        $('#recalculate-scores').on('click', function() {
            const button = $(this);
            const originalText = button.text();
            const statusMessage = $('#vbm-scoring-status-message');
            
            // Prevent double clicks
            if (button.prop('disabled')) {
                return;
            }
            
            // Disable button and show processing state
            button.prop('disabled', true).text(vbm_scoring.strings.calculating);
            statusMessage.removeClass('notice-success notice-error').addClass('notice').text(vbm_scoring.strings.calculating).show();
            
            // Make the AJAX call to recalculate scores
            $.ajax({
                url: vbm_scoring.ajax_url,
                type: 'POST',
                data: {
                    action: 'vbm_recalculate_scores',
                    nonce: vbm_scoring.nonce
                },
                success: function(response) {
                    if (response.success) {
                        statusMessage.removeClass('notice-error').addClass('notice-success')
                            .html('<p>' + response.data.message + '</p>');
                    } else {
                        statusMessage.removeClass('notice-success').addClass('notice-error')
                            .html('<p>' + response.data.message + '</p>');
                    }
                },
                error: function() {
                    statusMessage.removeClass('notice-success').addClass('notice-error')
                        .html('<p>' + vbm_scoring.strings.error + '</p>');
                },
                complete: function() {
                    button.prop('disabled', false).text(originalText);
                }
            });
        });
    }
    
    /**
     * Initialize Tab Navigation
     */
    function initTabNavigation() {
        $('.nav-tab-wrapper a').on('click', function(e) {
            e.preventDefault();
            
            // Update active tab
            $('.nav-tab-wrapper a').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');
            
            // Show corresponding tab content
            const target = $(this).attr('href');
            $('.tab-content').hide();
            $(target).show();
        });
    }
    
    /**
     * Initialize DataTables
     */
    function initDataTables() {
        if ($('#vbm-scoring-table').length > 0) {
            const table = $('#vbm-scoring-table').DataTable({
                paging: false,
                ordering: true,
                info: false,
                searching: true,
                autoWidth: false,
                dom: 'frt' // Only show filter, processing, table without page controls
            });
            
            // Filter by Post Type
            $('#post-type-filter').on('change', function() {
                const val = $(this).val();
                table.column(0).search(val ? '^' + val + '$' : '', true, false).draw();
            });
        }
    }
    
    /**
     * Initialize Field Score Editing
     */
    function initFieldScoreEditing() {
        $('#vbm-scoring-table').on('click', 'td.editable', function() {
            const cell = $(this);
            const original = cell.text();
            cell.data('original-value', original);
    
            const input = $('<input type="number" class="inline-edit" min="0" max="100">')
                .val(original);
            
            cell.empty().append(input);
            input.focus().on('blur keyup', function(e) {
                if (e.type === 'blur' || e.which === 13 || e.which === 27) {
                    const newValue = parseInt(input.val()) || 0;
                    if (e.which === 27) {
                        cell.text(original);
                    } else if (newValue !== parseInt(original)) {
                        updateFieldScore(cell.closest('tr'), newValue, cell);
                    } else {
                        cell.text(original);
                    }
                }
            });
        });
    }
    
    /**
     * Initialize Target Score Editing
     */
    function initTargetScoreEditing() {
        $('#vbm-target-score').on('click', function() {
            const cell = $(this);
            const original = cell.text();
            cell.data('original-value', original);
    
            const input = $('<input type="number" class="inline-edit" min="0" max="100">')
                .val(original);
            
            cell.empty().append(input);
            input.focus().on('blur keyup', function(e) {
                if (e.type === 'blur' || e.which === 13 || e.which === 27) {
                    const newValue = parseInt(input.val()) || 0;
                    if (e.which === 27) {
                        cell.text(original);
                    } else if (newValue !== parseInt(original)) {
                        updateTargetScore(newValue, cell);
                    } else {
                        cell.text(original);
                    }
                }
            });
        });
    }
    
    /**
     * Initialize Target Score Update Buttons
     */
    function initTargetScoreUpdateButtons() {
        $('.update-target-score').on('click', function() {
            const button = $(this);
            const row = button.closest('tr');
            const postType = row.data('post-type');
            const scoreInput = row.find('.target-score-input');
            const score = parseInt(scoreInput.val()) || 0;
            const statusMessage = $('#vbm-target-score-status-message');
            
            // Prevent double clicks
            if (button.prop('disabled')) {
                return;
            }
            
            // Disable button and show processing state
            button.prop('disabled', true).text(vbm_scoring.strings.saving);
            statusMessage.removeClass('notice-success notice-error')
                .addClass('notice')
                .text(vbm_scoring.strings.saving)
                .show();
            
            // Make the AJAX call to update target score
            $.ajax({
                url: vbm_scoring.ajax_url,
                type: 'POST',
                data: {
                    action: 'vbm_update_target_score',
                    nonce: vbm_scoring.nonce,
                    post_type: postType,
                    score: score
                },
                success: function(response) {
                    if (response.success) {
                        statusMessage.removeClass('notice-error')
                            .addClass('notice-success')
                            .html('<p>' + response.data.message + '</p>');
                    } else {
                        statusMessage.removeClass('notice-success')
                            .addClass('notice-error')
                            .html('<p>' + response.data.message + '</p>');
                    }
                },
                error: function() {
                    statusMessage.removeClass('notice-success')
                        .addClass('notice-error')
                        .html('<p>' + vbm_scoring.strings.error + '</p>');
                },
                complete: function() {
                    button.prop('disabled', false).text(vbm_scoring.strings.update || 'Update');
                }
            });
        });
    }
    
    // AJAX call to update field score (via CSS class)
    function updateFieldScore(row, score, cell) {
        const postType = row.data('post-type'),
              fieldKey = row.data('field-key');
        
        showStatusMessage('saving');
    
        $.ajax({
            url: vbm_scoring.ajax_url,
            type: 'POST',
            data: {
                action: 'vbm_update_field_score',
                nonce: vbm_scoring.nonce,
                post_type: postType,
                field_key: fieldKey,
                score: score
            },
            success: function(response) {
                if (response.success) {
                    cell.text(score);
                    showStatusMessage('saved');
                } else {
                    cell.text(cell.data('original-value'));
                    showStatusMessage('error', response.data.message);
                }
            },
            error: function() {
                cell.text(cell.data('original-value'));
                showStatusMessage('error');
            }
        });
    }
    
    // AJAX call to update target score
    function updateTargetScore(score, cell) {
        showStatusMessage('saving');
    
        $.ajax({
            url: vbm_scoring.ajax_url,
            type: 'POST',
            data: {
                action: 'vbm_update_target_score',
                nonce: vbm_scoring.nonce,
                score: score
            },
            success: function(response) {
                if (response.success) {
                    cell.text(score);
                    showStatusMessage('saved');
                } else {
                    cell.text(cell.data('original-value'));
                    showStatusMessage('error', response.data.message);
                }
            },
            error: function() {
                cell.text(cell.data('original-value'));
                showStatusMessage('error');
            }
        });
    }
    
    // Function to display status messages
    function showStatusMessage(type, message) {
        const statusEl = $('#vbm-scoring-status-message');
        
        if (type === 'saving') {
            statusEl.html('<span class="spinner is-active"></span> ' + vbm_scoring.strings.saving)
                    .removeClass('notice-success notice-error')
                    .addClass('notice-info')
                    .show();
        } else if (type === 'saved') {
            statusEl.text(vbm_scoring.strings.saved)
                    .removeClass('notice-info notice-error')
                    .addClass('notice-success')
                    .show()
                    .delay(2000)
                    .fadeOut();
        } else if (type === 'error') {
            statusEl.text(message || vbm_scoring.strings.error)
                    .removeClass('notice-info notice-success')
                    .addClass('notice-error')
                    .show();
        }
    }
})(jQuery);
