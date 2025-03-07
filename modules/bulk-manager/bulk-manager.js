(function($) {
    'use strict';
  
    $(document).ready(function() {
        // Initialize DataTable for the fields table
        const table = $('#vbm-fields-table').DataTable({
            paging: false,
            ordering: true,
            info: false,
            searching: true,
            autoWidth: false,
            columnDefs: [{ orderable: false, targets: [6] }],
            dom: 'frt' // Only show filter, processing, table without page controls
        });
        
        // Filter by Post Type
        $('#post-type-filter').on('change', function() {
            const val = $(this).val();
            table.column(0).search(val ? '^' + val + '$' : '', true, false).draw();
        });
        
        // Filter by Field Type
        $('#field-type-filter').on('change', function() {
            const val = $(this).val();
            table.column(3).search(val ? '^' + val + '$' : '', true, false).draw();
        });
        
        // Global search
        $('#field-search').on('keyup', function() {
            table.search($(this).val()).draw();
        });
        
        // Copy as JSON functionality
        $('#copy-json').on('click', function() {
            const jsonData = [];
            table.rows({search: 'applied'}).every(function() {
                const row = $(this.node());
                jsonData.push({
                    post_type: row.data('post-type'),
                    field_key: row.data('field-key'),
                    label: row.find('td[data-field-property="label"]').text(),
                    type: row.find('td:eq(3)').text(),
                    placeholder: row.find('td[data-field-property="placeholder"]').text(),
                    description: row.find('td[data-field-property="description"]').text(),
                    required: row.find('.required-toggle').prop('checked')
                });
            });
    
            const textarea = document.createElement("textarea");
            textarea.value = JSON.stringify(jsonData, null, 2);
            document.body.appendChild(textarea);
            textarea.select();
            try {
                document.execCommand("copy");
                $(this).text("Copied!");
                setTimeout(() => {
                    $(this).text("Copy as JSON");
                }, 2000);
            } catch (err) {
                console.error("Failed to copy JSON:", err);
                alert("Failed to copy JSON to clipboard");
            }
            document.body.removeChild(textarea);
        });
        
        // Inline editing for cells marked as editable
        $('#vbm-fields-table').on('click', 'td.editable', function() {
            const cell = $(this);
            // Don't reinitialize if already editing
            if (cell.find('input.inline-edit').length > 0) {
                return;
            }
            
            const original = cell.text().trim();
            const fieldProp = cell.data('field-property');
            cell.data('original-value', original);
    
            const input = $('<input type="text" class="inline-edit">').val(original);
            cell.html(input);
            input.focus().select();
            
            input.on('blur keyup', function(e) {
                if (e.type === 'blur' || e.which === 13 || e.which === 27) {
                    const newValue = input.val();
                    if (e.which === 27 || newValue === original) {
                        cell.text(original);
                    } else if (e.type === 'blur' || e.which === 13) {
                        updateFieldValue(cell.closest('tr'), fieldProp, newValue, cell);
                    }
                }
            });
        });
        
        // Toggle required status
        $('#vbm-fields-table').on('change', '.required-toggle', function() {
            const checkbox = $(this),
                  postType = checkbox.data('post-type'),
                  fieldKey = checkbox.data('field-key'),
                  isRequired = checkbox.prop('checked');
            updateRequiredStatus(postType, fieldKey, isRequired, checkbox);
        });
    });
    
    // Ajax call to update inline editable field value
    function updateFieldValue(row, fieldProp, newValue, cell) {
        const postType = row.data('post-type'),
              fieldKey = row.data('field-key');
        showStatusMessage('saving');
    
        $.ajax({
            url: vbm_bulk_manager.ajax_url,
            type: 'POST',
            data: {
                action: 'vbm_update_field_value',
                nonce: vbm_bulk_manager.nonce,
                post_type: postType,
                field_key: fieldKey,
                field_property: fieldProp,
                value: newValue
            },
            success: function(response) {
                if (response.success) {
                    cell.text(newValue);
                    showStatusMessage('saved');
                } else {
                    cell.text(cell.data('original-value'));
                    showStatusMessage('error', response.data.message);
                }
            },
            error: function() {
                cell.text(cell.data('original-value'));
                showStatusMessage('error', 'Network error');
            }
        });
    }
    
    // Ajax call to update the required status
    function updateRequiredStatus(postType, fieldKey, isRequired, checkbox) {
        showStatusMessage('saving');
    
        $.ajax({
            url: vbm_bulk_manager.ajax_url,
            type: 'POST',
            data: {
                action: 'vbm_update_field_required',
                nonce: vbm_bulk_manager.nonce,
                post_type: postType,
                field_key: fieldKey,
                value: isRequired
            },
            success: function(response) {
                if (response.success) {
                    showStatusMessage('saved');
                } else {
                    checkbox.prop('checked', !isRequired);
                    showStatusMessage('error', response.data.message);
                }
            },
            error: function() {
                checkbox.prop('checked', !isRequired);
                showStatusMessage('error', 'Network error');
            }
        });
    }
    
    // Function to display status messages
    function showStatusMessage(type, message) {
        const statusEl = $('#vbm-status-message');
        if (type === 'saving') {
            statusEl.html('<span class="spinner is-active"></span> ' + vbm_bulk_manager.strings.saving)
                    .removeClass('notice-success notice-error')
                    .addClass('notice-info')
                    .show();
        } else if (type === 'saved') {
            statusEl.text(vbm_bulk_manager.strings.saved)
                    .removeClass('notice-info notice-error')
                    .addClass('notice-success')
                    .show()
                    .delay(2000)
                    .fadeOut();
        } else if (type === 'error') {
            statusEl.text(message || vbm_bulk_manager.strings.error)
                    .removeClass('notice-info notice-success')
                    .addClass('notice-error')
                    .show();
        }
    }
})(jQuery);