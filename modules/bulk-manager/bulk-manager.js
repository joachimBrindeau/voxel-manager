/**
 * Voxel Bulk Manager
 * JavaScript functionality for bulk management of Voxel fields and post types
 */

// TableToJSON library - minified version for table to JSON conversion
!function(a){"use strict";var b=function(b,c,d){this.$element=a(b),this.index=c,this.cachedRowSpan=null,this.options=a.extend({},a.fn.tableToJSONCell.defaults,d),this.init()};b.prototype={constructor:b,value:function(b){var c=a.extend({},this.options,b),d=a.trim(this.$element.text()),e=this.$element.attr(this.options.textDataOverride);if(e)d=e;else{if(c.extractor||c.textExtractor)return this.extractedValue();c.allowHTML&&(d=a.trim(this.$element.html()))}return this.withColSpan(d)},extractedValue:function(){var b=this.options.extractor||this.options.textExtractor,c=null;return a.isFunction(b)?c=b(this.index,this.$element):"object"==typeof b&&a.isFunction(b[this.index])&&(c=b[this.index](this.index,this.$element)),"string"==typeof c?a.trim(c):c},colSpan:function(){var a=1;return this.$element.attr("colSpan")&&(a=parseInt(this.$element.attr("colSpan"),10)),a},rowSpan:function(a){return 1===arguments.length?this.cachedRowSpan=a:this.cachedRowSpan||(this.cachedRowSpan=1,this.$element.attr("rowSpan")&&(this.cachedRowSpan=parseInt(this.$element.attr("rowSpan"),10))),this.cachedRowSpan},withColSpan:function(a){var b=a;if(this.$element.attr("colSpan")){var c=this.colSpan();if(c>1){b=[];for(var d=0;d<c;d++)b.push(a)}}return b},init:function(){a.proxy(function(){this.$element.triggerHandler("init",this)},this)}},a.fn.tableToJSONCell=function(a,c){return new b(this,a,c)},a.fn.tableToJSONCell.defaults={allowHTML:!1,textDataOverride:"data-override",extractor:null,textExtractor:null}}(jQuery),function(a){"use strict";var b=function(b,c){this.$element=a(b),this.cells=[],this.options=a.extend({},a.fn.tableToJSONRow.defaults,c),this.init()};b.prototype={constructor:b,id:function(){return this.$element.attr("id")?this.$element.attr("id"):null},valuesWithHeadings:function(a){for(var b={},c=this.values(),d=0;d<c.length;d++)b[a[d]]=c[d];return b},isEmpty:function(){for(var a=!0,b=this.values(),c=0;a&&c<b.length;c++)""!==b[c]&&(a=!1);return a},cell:function(a){return a<this.cells.length?this.cells[a]:null},insert:function(a,b){this.cells.splice(a,0,b)},getRowSpans:function(a){for(var b,c,d=[],e=0;e<this.cells.length;e++){if(d=[],c=this.cells[e]){for(b=c.rowSpan();b>1;)d.push(c),b--;c.rowSpan(1)}d.length>0&&(a[e]=d)}return a},insertRowSpans:function(a){for(var b=0;b<a.length;b++)if(a[b]&&a[b].length>0){var c=a[b].splice(0,1)[0];this.insert(b,c)}return a},rowSpans:function(){for(var a,b,c=[],d=[],e=0;e<this.cells.length;e++){for(d=[],b=this.cells[e],a=b.rowSpan();a>1;)d.push(b),a--;b.rowSpan(1),d.length>0&&(c[e]=d)}return c},values:function(b){for(var c=a.extend({},this.options,b),d=[],e=null,f=0,g=0;g<this.cells.length;g++)if(e=this.cells[g].value(c),1===this.cells[g].colSpan())this.ignoreColumn(f)||(d=d.concat(e)),f++;else for(var h=0;h<e.length;h++)this.ignoreColumn(f)||(d=d.concat(e[h])),f++;return d},ignoreColumn:function(a){return this.options.onlyColumns?this.options.onlyColumns.indexOf(a)<0:this.options.ignoreColumns.indexOf(a)>-1},init:function(){var b=this;this.$element.children(this.options.cellSelector).each(function(c,d){b.cells.push(a(d).tableToJSONCell(c,b.options))}),a.proxy(function(){this.$element.triggerHandler("init",this)},this)}},a.fn.tableToJSONRow=function(a){return new b(this,a)},a.fn.tableToJSONRow.defaults={onlyColumns:null,ignoreColumns:[],cellSelector:"td,th"}}(jQuery),function(a){"use strict";var b=function(b,c){this.$element=a(b),this.rows=[],this.options=a.extend({},a.fn.tableToJSON.defaults,c),this.init()};b.prototype={constructor:b,headings:function(){return this.rows.length>0&&!this.options.headings?this.rows[0].values({extractor:null,textExtractor:null}):this.options.headings?this.options.headings:[]},values:function(){var a=[],b=this.headings(),c=this.options.headings?0:1;for(c;c<this.rows.length;c++)if(!this.ignoreRow(this.rows[c],c))if(this.options.includeRowId){var d="string"==typeof this.options.includeRowId?this.options.includeRowId:"rowId",e=this.rows[c].valuesWithHeadings(b);e[d]=this.rows[c].id(),a.push(e)}else a.push(this.rows[c].valuesWithHeadings(b));return a},ignoreRow:function(a,b){return this.options.ignoreRows&&this.options.ignoreRows.indexOf(b)>-1||a.$element.data("ignore")&&"false"!==a.$element.data("ignore")||this.options.ignoreHiddenRows&&!a.$element.is(":visible")||this.options.ignoreEmptyRows&&a.isEmpty()},addRow:function(a,b){return a.insertRowSpans(b),this.rows.push(a),a.getRowSpans(b)},init:function(){var b=this,c=[],d=null;this.$element.children(this.options.rowParentSelector).children(this.options.rowSelector).each(function(e,f){d=a(f).tableToJSONRow(b.options),c=b.addRow(d,c)}),a.proxy(function(){this.$element.triggerHandler("init",this)},this)}},a.fn.tableToJSON=function(a){return new b(this,a).values()},a.fn.tableToJSON.defaults={ignoreRows:[],ignoreHiddenRows:!0,ignoreEmptyRows:!1,headings:null,includeRowId:!1,rowParentSelector:"tbody,*",rowSelector:"tr"}}(jQuery);

(function($) {
    'use strict';

    // Main controller object
    const VBMController = {
        // Store DataTables instances
        tables: {},
        
        // Store the current active tab
        activeTab: '',
        
        // Status message handling
        statusMessage: {
            show: function(message, type = 'success') {
                const $status = $('#vbm-status-message');
                $status.removeClass('success error').addClass(type).text(message).fadeIn();
                setTimeout(() => {
                    $status.fadeOut();
                }, 3000);
            },
            hide: function() {
                $('#vbm-status-message').fadeOut();
            }
        },
        
        // Initialize everything
        init: function() {
            this.activeTab = this.getActiveTab();
            this.setupTables();
            this.setupEventHandlers();
            this.setupColumnSelectors();
            this.setupFilters();
            this.setupCopyButtons();
        },
        
        // Get the currently active tab from URL or default to 'fields'
        getActiveTab: function() {
            const urlParams = new URLSearchParams(window.location.search);
            return urlParams.get('tab') || 'fields';
        },
        
        // Initialize DataTables for the active tab
        setupTables: function() {
            this.initializeDataTable(this.activeTab);
        },
        
        // Set up general event handlers
        setupEventHandlers: function() {
            this.setupTabSwitching();
            this.setupEditableFields();
            this.setupToggleSwitches();
        },
        
        // Handle tab switching
        setupTabSwitching: function() {
            $('.nav-tab').on('click', () => {
                setTimeout(() => {
                    this.activeTab = this.getActiveTab();
                    this.initializeDataTable(this.activeTab);
                }, 100);
            });
        },
        
        // Initialize a specific DataTable
        initializeDataTable: function(tableType) {
            const config = vbm_bulk_manager.table_configs[tableType];
            if (!config) return;
            
            const tableId = '#' + config.id;
            if (!$(tableId).length) return;
            
            // Check if table is already initialized - destroy it first
            if ($.fn.DataTable.isDataTable(tableId)) {
                $(tableId).DataTable().destroy();
                this.tables[tableType] = null;
            }
            
            const tableOptions = {
                paging: false,
                ordering: true,
                info: false,
                searching: true,
                autoWidth: false,
                dom: 'rt', // Removed filter from DOM to use our custom filters
                language: {
                    search: '',
                    searchPlaceholder: "Search..."
                },
                columnDefs: [],
                classes: {
                    sTable: 'wp-list-table widefat fixed striped',
                },
                stripeClasses: [],
                initComplete: function() {
                    // Ensure WordPress styling remains intact after DataTables initialization
                    $(tableId).addClass('wp-list-table widefat fixed striped');
                    $(tableId + ' thead th').addClass('manage-column');
                    $(tableId + ' tbody tr').addClass('alternate');
                }
            };
            
            // Add non-sortable columns
            const nonSortableTargets = tableType === 'fields' ? [6] : [5, 6];
            tableOptions.columnDefs.push({ orderable: false, targets: nonSortableTargets });
            
            // Add hidden columns definition
            if (config.hidden_columns && config.hidden_columns.length) {
                tableOptions.columnDefs.push({
                    visible: false,
                    targets: config.hidden_columns
                });
            }
            
            // Initialize the DataTable
            this.tables[tableType] = $(tableId).DataTable(tableOptions);
            
            // Apply any saved column visibility settings (but don't override default hidden columns)
            // Don't try to load saved settings on first run
            if (!localStorage.getItem('vbm_first_run')) {
                localStorage.setItem('vbm_first_run', 'true');
            } else {
                this.applyColumnVisibility(tableType);
            }
            
            // Force a redraw after initialization to ensure columns are displayed correctly
            setTimeout(() => {
                this.tables[tableType].columns.adjust().draw(false);
                
                // Ensure selector checkboxes match current column state
                this.updateColumnSelectorState(tableType);
            }, 100);
        },
        
        // Set up editable fields functionality 
        setupEditableFields: function() {
            // Fields tab: handle inline editing
            $(document).on('click', '.editable', function() {
                if ($(this).find('input').length) return;
                
                const currentValue = $(this).text().trim();
                const $input = $('<input type="text" class="inline-edit" />').val(currentValue);
                
                // Store original value for canceling
                $input.data('original-value', currentValue);
                
                // Replace cell content with input
                $(this).html($input);
                $input.focus();
            });
            
            // Save changes when leaving the field
            $(document).on('blur', '.inline-edit', function() {
                const $this = $(this);
                const $cell = $this.closest('.editable');
                const newValue = $this.val();
                const originalValue = $this.data('original-value');
                
                // Skip AJAX if value hasn't changed
                if (newValue === originalValue) {
                    $cell.text(newValue);
                    return;
                }
                
                // Show saving indicator
                VBMController.statusMessage.show(vbm_bulk_manager.strings.saving, 'info');
                
                // Determine if this is a field or post type property
                if ($cell.data('field-property') !== undefined) {
                    // Field property update
                    const $row = $cell.closest('tr');
                    const fieldProperty = $cell.data('field-property');
                    
                    $.ajax({
                        url: vbm_bulk_manager.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'vbm_update_field_value',
                            nonce: vbm_bulk_manager.nonce,
                            post_type: $row.data('post-type'),
                            field_key: $row.data('field-key'),
                            field_property: fieldProperty,
                            value: newValue
                        },
                        success: function(response) {
                            if (response.success) {
                                $cell.text(newValue);
                                VBMController.statusMessage.show(vbm_bulk_manager.strings.saved);
                            } else {
                                $cell.text(originalValue);
                                VBMController.statusMessage.show(response.data.message || vbm_bulk_manager.strings.error, 'error');
                            }
                        },
                        error: function() {
                            $cell.text(originalValue);
                            VBMController.statusMessage.show(vbm_bulk_manager.strings.error, 'error');
                        }
                    });
                } else if ($cell.data('cpt-property') !== undefined) {
                    // Post type property update
                    const $row = $cell.closest('tr');
                    const cptProperty = $cell.data('cpt-property');
                    
                    $.ajax({
                        url: vbm_bulk_manager.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'vbm_update_cpt_value',
                            nonce: vbm_bulk_manager.nonce,
                            post_type: $row.data('post-type'),
                            property: cptProperty,
                            value: newValue
                        },
                        success: function(response) {
                            if (response.success) {
                                $cell.text(newValue);
                                VBMController.statusMessage.show(vbm_bulk_manager.strings.saved);
                            } else {
                                $cell.text(originalValue);
                                VBMController.statusMessage.show(response.data.message || vbm_bulk_manager.strings.error, 'error');
                            }
                        },
                        error: function() {
                            $cell.text(originalValue);
                            VBMController.statusMessage.show(vbm_bulk_manager.strings.error, 'error');
                        }
                    });
                }
            });
            
            // Handle Enter key press to save
            $(document).on('keypress', '.inline-edit', function(e) {
                if (e.which === 13) { // Enter key
                    $(this).blur();
                }
            });
            
            // Handle Escape key press to cancel
            $(document).on('keydown', '.inline-edit', function(e) {
                if (e.which === 27) { // Escape key
                    const originalValue = $(this).data('original-value');
                    $(this).closest('.editable').text(originalValue);
                }
            });
        },
        
        // Set up toggle switches functionality
        setupToggleSwitches: function() {
            // Field required toggle
            $(document).on('change', '.required-toggle', function() {
                const $this = $(this);
                const postType = $this.data('post-type');
                const fieldKey = $this.data('field-key');
                const isChecked = $this.prop('checked');
                
                VBMController.statusMessage.show(vbm_bulk_manager.strings.saving, 'info');
                
                $.ajax({
                    url: vbm_bulk_manager.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'vbm_update_field_required',
                        nonce: vbm_bulk_manager.nonce,
                        post_type: postType,
                        field_key: fieldKey,
                        value: isChecked
                    },
                    success: function(response) {
                        if (response.success) {
                            VBMController.statusMessage.show(vbm_bulk_manager.strings.saved);
                        } else {
                            $this.prop('checked', !isChecked); // Revert the change
                            VBMController.statusMessage.show(response.data.message || vbm_bulk_manager.strings.error, 'error');
                        }
                    },
                    error: function() {
                        $this.prop('checked', !isChecked); // Revert the change
                        VBMController.statusMessage.show(vbm_bulk_manager.strings.error, 'error');
                    }
                });
            });
            
            // CPT toggles (timeline, messages)
            $(document).on('change', '.timeline-toggle, .messages-toggle', function() {
                const $this = $(this);
                const postType = $this.data('post-type');
                const isChecked = $this.prop('checked');
                const property = $this.hasClass('timeline-toggle') ? 'timeline' : 'messages';
                
                VBMController.statusMessage.show(vbm_bulk_manager.strings.saving, 'info');
                
                $.ajax({
                    url: vbm_bulk_manager.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'vbm_update_cpt_toggle',
                        nonce: vbm_bulk_manager.nonce,
                        post_type: postType,
                        property: property,
                        value: isChecked
                    },
                    success: function(response) {
                        if (response.success) {
                            VBMController.statusMessage.show(vbm_bulk_manager.strings.saved);
                        } else {
                            $this.prop('checked', !isChecked); // Revert the change
                            VBMController.statusMessage.show(response.data.message || vbm_bulk_manager.strings.error, 'error');
                        }
                    },
                    error: function() {
                        $this.prop('checked', !isChecked); // Revert the change
                        VBMController.statusMessage.show(vbm_bulk_manager.strings.error, 'error');
                    }
                });
            });
        },
        
        // Set up column visibility selectors
        setupColumnSelectors: function() {
            // Column selector dropdown toggle
            $(document).on('click', '.button-select-columns', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $('.vbm-column-selector-dropdown').not($(this).siblings('.vbm-column-selector-dropdown')).removeClass('visible');
                $(this).siblings('.vbm-column-selector-dropdown').toggleClass('visible');
            });
            
            // Close dropdown when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.vbm-column-selector').length) {
                    $('.vbm-column-selector-dropdown').removeClass('visible');
                }
            });
            
            // Handle column visibility toggles using data() instead of attr()
            $(document).on('change', '.vbm-column-option input[type="checkbox"]', function() {
                const columnIndex = parseInt($(this).data('column'), 10);
                const tableType = $(this).data('table');
                const isVisible = $(this).is(':checked');
                
                if (VBMController.tables[tableType]) {
                    // Update DataTables column visibility
                    const column = VBMController.tables[tableType].column(columnIndex);
                    
                    // Make sure we're actually changing the visibility
                    if (column.visible() !== isVisible) {
                        column.visible(isVisible);
                        
                        // Ensure DataTables has updated the DOM
                        setTimeout(function() {
                            // Force redraw to ensure proper rendering
                            VBMController.tables[tableType].columns.adjust().draw(false);
                            
                            // Double-check and fix any inconsistencies
                            if (column.visible() !== isVisible) {
                                column.visible(isVisible, false);
                                VBMController.tables[tableType].columns.adjust().draw(false);
                            }
                            
                            // Also force CSS visibility for this column for browsers that might not respect DataTables
                            const actualColumnIndex = VBMController.getActualColumnIndex(tableType, columnIndex);
                            const displayValue = isVisible ? 'table-cell' : 'none';
                            $(`#${VBMController.tables[tableType].table().node().id} tr > *:nth-child(${actualColumnIndex})`)
                                .css('display', displayValue);
                            
                            // Save to localStorage after all DOM updates are complete
                            VBMController.saveColumnVisibility(tableType);
                            
                            // Show status message for user feedback
                            VBMController.statusMessage.show(
                                isVisible ? 'Column shown' : 'Column hidden', 
                                'success'
                            );
                            
                            // Refresh selector state to capture any DataTables adjustments
                            VBMController.updateColumnSelectorState(tableType);
                        }, 50);
                    }
                }
            });
        },
        
        // Save column visibility settings to localStorage
        saveColumnVisibility: function(tableType) {
            if (!VBMController.tables[tableType]) return;
            
            const visibilitySettings = {};
            VBMController.tables[tableType].columns().every(function(index) {
                visibilitySettings[index] = this.visible();
            });
            
            localStorage.setItem(`vbm_columns_${tableType}`, JSON.stringify(visibilitySettings));
        },
        
        // Apply saved column visibility settings
        applyColumnVisibility: function(tableType) {
            if (!VBMController.tables[tableType]) return;
            
            const savedSettings = localStorage.getItem(`vbm_columns_${tableType}`);
            if (!savedSettings) return;
            
            try {
                const settings = JSON.parse(savedSettings);
                const config = vbm_bulk_manager.table_configs[tableType];
                
                // Apply saved settings to the table - but prioritize showing columns that
                // should be visible when a user clicks them
                Object.keys(settings).forEach(columnIndex => {
                    const index = parseInt(columnIndex, 10);
                    let visible = settings[columnIndex];
                    
                    // If user explicitly unchecked a column that should be visible, respect that
                    VBMController.tables[tableType].column(index).visible(visible);
                    
                    // Update checkbox state
                    $(`.vbm-column-option[data-table="${tableType}"] input[data-column="${index}"]`)
                        .prop('checked', visible);
                });
            } catch (e) {
                console.error('Error applying saved column settings:', e);
            }
        },
        
        // Set up filter functionality for both tables
        setupFilters: function() {
            // Handle the common search filter
            $('#common-search').on('keyup', function() {
                const tableType = VBMController.activeTab;
                const table = VBMController.tables[tableType];
                if (table) {
                    table.search($(this).val()).draw();
                }
            });
            
            // Handle post type filter for both tabs
            $('#post-type-filter').on('change', function() {
                const tableType = VBMController.activeTab;
                const table = VBMController.tables[tableType];
                if (table) {
                    const value = $(this).val();
                    if (value === '') {
                        table.column(0).search('').draw();
                    } else {
                        // Use the selected option's text instead of its value
                        const text = $(this).find('option:selected').text();
                        table.column(0).search(text).draw();
                    }
                }
            });
            
            // Reset filters button
            $('#reset-filters').on('click', function() {
                $('#common-search').val('');
                $('#post-type-filter').val('');
                const tableType = VBMController.activeTab;
                const table = VBMController.tables[tableType];
                if (table) {
                    table.search('').columns().search('').draw();
                }
            });
        },
        
        // Set up copy to clipboard functionality
        setupCopyButtons: function() {
            // Update selector to find the proper buttons by ID
            $('#copy-json, #copy-cpt-json').on('click', function() {
                const tableType = VBMController.activeTab;
                VBMController.copyTableAsJSON(tableType);
            });
        },
        
        // Copy table data as JSON - Using TableToJSON library to ensure only visible content is copied
        copyTableAsJSON: function(tableType) {
            if (!VBMController.tables[tableType]) return;
            
            const tableId = '#' + vbm_bulk_manager.table_configs[tableType].id;
            const $table = $(tableId);
            
            if (!$table.length) return;
            
            try {
                // Define standard property names based on table type
                const isFieldsTable = tableType === 'fields';
                const standardPropertyNames = isFieldsTable ? 
                    ['post_type', 'key', 'label', 'type', 'placeholder', 'description', 'required', 'min_length', 'max_length', 'css_class', 'editor_type'] :
                    ['key', 'singular', 'plural', 'slug', 'icon', 'timeline', 'messages', 'submissions_enabled', 'submission_status', 'update_status', 'deletable'];
                
                // Get visible column indices
                const visibleColumns = [];
                $table.find('th').each(function(index) {
                    if ($(this).is(':visible')) {
                        visibleColumns.push(index);
                    }
                });
                
                // Prepare rows data
                let jsonData = [];
                
                // Process each visible row
                $table.find('tbody tr:visible').each(function() {
                    const $row = $(this);
                    const rowData = {};
                    
                    // Add post_type data attribute for fields table if needed
                    if (isFieldsTable && $row.data('post-type')) {
                        rowData.post_type = $row.data('post-type');
                    }
                    
                    // Process visible cells
                    visibleColumns.forEach((colIndex, i) => {
                        const $cell = $row.find('td').eq(colIndex);
                        let value;
                        
                        // Extract proper value based on cell content
                        if ($cell.find('.switch input[type="checkbox"]').length) {
                            // Handle toggle switches
                            value = $cell.find('input[type="checkbox"]').is(':checked');
                        } else {
                            // Regular text cells
                            value = $.trim($cell.text());
                        }
                        
                        // Get appropriate property name for this column
                        const propName = standardPropertyNames[colIndex] || 'column_' + colIndex;
                        
                        // For CPT table, if column 0 (key) is already set as post_type, don't duplicate
                        if (!isFieldsTable && colIndex === 0 && rowData.post_type === value) {
                            // Skip duplicate
                        } else {
                            rowData[propName] = value;
                        }
                    });
                    
                    jsonData.push(rowData);
                });
                
                // Copy to clipboard
                const jsonString = JSON.stringify(jsonData, null, 2);
                this.copyToClipboard(jsonString);
                VBMController.statusMessage.show('JSON copied to clipboard');
                
            } catch (error) {
                console.error('Error generating JSON:', error);
                VBMController.statusMessage.show('Error generating JSON', 'error');
            }
        },
        
        // Copy text to clipboard
        copyToClipboard: function(text) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
        },

        // Refreshes the column selector checkboxes based on current DataTable column visibility
        updateColumnSelectorState: function(tableType) {
            if (!this.tables[tableType]) return;
            this.tables[tableType].columns().every(function(index) {
                $(`.vbm-column-option[data-table="${tableType}"] input[data-column="${index}"]`)
                    .prop('checked', this.visible());
            });
        },

        // Helper to get the actual column position in the DOM (accounting for hidden columns)
        getActualColumnIndex: function(tableType, dataIndex) {
            let actualIndex = dataIndex + 1; // 1-based for CSS selectors
            
            // Count how many columns before this one are hidden
            for (let i = 0; i < dataIndex; i++) {
                if (this.tables[tableType] && !this.tables[tableType].column(i).visible()) {
                    actualIndex--;
                }
            }
            
            return actualIndex;
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        VBMController.init();
    });

})(jQuery);