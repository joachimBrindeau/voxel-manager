/**
 * Voxel Manager - Main JavaScript
 */
(function($) {
    'use strict';
    
    // Initialize on document ready
    $(document).ready(function() {
        initRelationKeys();
        initConfirmationDialogs();
        initDataTables();
    });
    
    /**
     * Initialize relation key selector
     */
    function initRelationKeys() {
        const postTypeSelect = $('#post-type-new');
        
        function updateRelationKeys() {
            const selectedType = $(this).val();
            let html = '<option value="">Select Relation</option>';
            
            if (selectedType && window.relationKeys && window.relationKeys[selectedType]) {
                Object.entries(window.relationKeys[selectedType]).forEach(([key, label]) => {
                    html += `<option value="${key}">${label}</option>`;
                });
            }
            
            $('#relation-key-new').html(html);
        }
        
        postTypeSelect.on('change', updateRelationKeys);
    }
    
    /**
     * Initialize confirmation dialog functionality
     */
    function initConfirmationDialogs() {
        window.confirmRuleDeletion = function(element, message) {
            $.confirm({
                title: 'Confirm',
                content: message,
                buttons: {
                    confirm: function() {
                        window.location.href = $(element).data('href');
                    },
                    cancel: function() {}
                }
            });
        };
        
        $('#new-rule').on('click', function() {
            $('.ipc-modal').addClass('visible');
        });
    }
    
    /**
     * Initialize data tables and filtering
     */
    function initDataTables() {
        const table = $("#fields-table").DataTable({
            paging: false,
            searching: true,
            dom: "t"
        });
        
        // Add custom search filtering
        $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
            const selectedPostType = $("#post-type-filter").val();
            const selectedCondition = $("#condition-filter").val();
            const selectedAction = $("#action-filter").val();
            
            const rowPostType = data[0];
            const rowCondition = data[1];
            const rowAction = data[2];
            
            // If no filters selected, show all rows
            if (!selectedPostType && !selectedCondition && !selectedAction) {
                return true;
            }
            
            const postTypeMatch = !selectedPostType || rowPostType === selectedPostType;
            const conditionMatch = !selectedCondition || rowCondition === selectedCondition;
            const actionMatch = !selectedAction || rowAction === selectedAction;
            
            return postTypeMatch && conditionMatch && actionMatch;
        });
        
        // Update filters when selected
        $("#post-type-filter, #condition-filter, #action-filter").on("change", function() {
            table.draw();
        });
        
        // Copy JSON functionality
        $("#copy-json").on("click", function() {
            // Gather visible (filtered) data
            const jsonData = [];
            table.rows({search: "applied"}).every(function() {
                const data = this.data();
                jsonData.push({
                    post_type: data[0],
                    condition: data[1],
                    action: data[2],
                    relation: data[3]
                });
            });
            
            copyToClipboard(JSON.stringify(jsonData, null, 2), this);
        });
        
        // Enable inline editing
        $("#fields-table tbody").on("dblclick", "td.editable", function() {
            const cell = $(this);
            const original = cell.text();
            const row = cell.closest("tr");
            const index = row.data('index');
            const action = row.find("td:eq(2)").data('value');
            const condition = row.find("td:eq(1)").data('value');
            const postType = row.find("td:first").data('value');
            const column = table.cell(this).index().column;
            const fieldType = ["post_type", "condition", "action", "relation-key"][column];
            
            cell.empty();
            
            // Create the correct input based on field type
            switch (fieldType) {
                case "post_type":
                    $('#post-type-edit').clone().val(postType).appendTo(cell);
                    break;
                case "condition":
                    $('#condition-edit').clone().val(condition).appendTo(cell);
                    break;
                case "action":
                    $('#action-edit').clone().val(action).appendTo(cell);
                    break;
            }
            
            // Handle input events
            const input = cell.find("select");
            input.focus();
            
            input.on("blur keyup", function(e) {
                if (e.keyCode === 13 || e.type === "blur") {
                    const newValue = input.val();
                    const displayValue = input.find('option:checked').first().text();
                    
                    if (newValue !== original) {
                        updateRelation({
                            postType: fieldType === 'post-type' ? newValue : postType,
                            condition: fieldType === 'condition' ? newValue : condition,
                            action: fieldType === 'action' ? newValue : action,
                            index: index,
                            cell: cell,
                            displayValue: displayValue,
                            original: original
                        });
                    } else {
                        cell.text(original);
                    }
                } else if (e.keyCode === 27) {
                    cell.text(original);
                }
            });
        });
    }
    
    /**
     * Update relation via AJAX
     * 
     * @param {Object} params Parameters for update
     */
    function updateRelation(params) {
        $.ajax({
            url: window.ajaxurl,
            type: "POST",
            data: {
                action: "ipc_update_relation",
                post_type: params.postType,
                condition: params.condition,
                relation: params.action,  // renamed from "action2"
                index: params.index
            },
            success: function(response) {
                $.alert(response);
                params.cell.text(params.displayValue);
            },
            error: function() {
                params.cell.text(params.original);
                $.alert("Update failed");
            }
        });
    }
    
    /**
     * Copy text to clipboard
     * 
     * @param {string} text Text to copy
     * @param {HTMLElement} button Button element for feedback
     */
    function copyToClipboard(text, button) {
        const textarea = document.createElement("textarea");
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        
        try {
            document.execCommand("copy");
            $(button).text("Copied!");
            setTimeout(() => {
                $(button).text("Copy as JSON");
            }, 2000);
        } catch (err) {
            console.error("Failed to copy:", err);
            alert("Failed to copy to clipboard");
        }
        
        document.body.removeChild(textarea);
    }
    
    // Removed duplicate registration of VBM_Automation_Module
})(jQuery);