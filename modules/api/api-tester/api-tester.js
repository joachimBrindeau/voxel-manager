/**
 * API Tester JavaScript
 */
(function($) {
    'use strict';
    
    // Store the selected field type
    let currentFieldType = '';
    
    // WordPress core fields
    const wpCoreFields = [
        'title', 'content', 'excerpt', 'slug', 'status', 
        'date', 'modified', 'author', 'featured_media', 
        'comment_status', 'ping_status', 'menu_order', 'parent'
    ];
    
    // Initialize when document is ready
    $(document).ready(function() {
        initPostTypeSelector();
        initPostSelector();
        initMethodSelector();
        initFieldSelector();
        initApiTest();
    });
    
    /**
     * Initialize post type selector
     */
    function initPostTypeSelector() {
        $('#vbm-test-post-type').on('change', function() {
            const postType = $(this).val();
            
            // Reset subsequent selectors
            $('#vbm-test-field').html('<option value="">-- Select Field --</option>');
            $('#vbm-test-post').html('<option value="">-- Select Post --</option>');
            $('#vbm-test-value').val('');
            $('#vbm-api-test-result').html('');
            $('#vbm-api-test-status').removeClass('status-success status-error').empty();
            
            if (!postType) {
                $('#post-selector-container, #action-selector-container, #field-selector-container, #value-input-container, #api-test-controls').hide();
                return;
            }
            
            // Load posts for this post type
            loadPostsForType(postType);
            
            // Reset HTTP method
            $('#vbm-test-method').val('POST');
            
            // Hide other selectors until post is selected
            $('#action-selector-container, #field-selector-container, #value-input-container, #api-test-controls').hide();
        });
    }
    
    /**
     * Initialize HTTP method selector
     */
    function initMethodSelector() {
        $('#vbm-test-method').on('change', function() {
            const method = $(this).val();
            const postSelected = $('#vbm-test-post').val();
            
            // Reset field and value
            $('#vbm-test-field').html('<option value="">-- Select Field --</option>');
            $('#vbm-test-value').val('');
            $('#vbm-api-test-result').html('');
            $('#vbm-api-test-status').removeClass('status-success status-error').empty();
            
            if (postSelected) {
                // If a post is selected, adjust UI based on method
                if (method === 'GET' || method === 'DELETE') {
                    // GET and DELETE don't need field selector or value input
                    $('#field-selector-container, #value-input-container').hide();
                    $('#api-test-controls').show();
                } else {
                    // POST needs field selector
                    $('#field-selector-container').show();
                    $('#value-input-container, #api-test-controls').hide();
                    
                    // Load fields for this post type
                    const postType = $('#vbm-test-post-type').val();
                    loadFieldsForType(postType);
                }
            }
        });
    }
    
    /**
     * Initialize field selector
     */
    function initFieldSelector() {
        $('#vbm-test-field').on('change', function() {
            const fieldKey = $(this).val();
            let fieldType = $(this).find('option:selected').data('type');
            const isCoreField = wpCoreFields.includes(fieldKey);
            
            // Reset value input
            $('#vbm-test-value').val('');
            $('#vbm-api-test-result').html('');
            $('#vbm-api-test-status').removeClass('status-success status-error').empty();
            
            if (!fieldKey) {
                $('#value-input-container, #api-test-controls').hide();
                return;
            }
            
            // Show value input
            $('#value-input-container, #api-test-controls').show();
            
            // Override field type for WordPress core fields if needed
            if (isCoreField) {
                if (fieldKey === 'content' || fieldKey === 'excerpt') {
                    fieldType = 'textarea';
                } else if (fieldKey === 'featured_media' || fieldKey === 'author' || fieldKey === 'parent' || fieldKey === 'menu_order') {
                    fieldType = 'number';
                } else if (fieldKey === 'status' || fieldKey === 'comment_status' || fieldKey === 'ping_status') {
                    fieldType = 'select';
                } else if (!fieldType) {
                    fieldType = 'text';
                }
            }
            
            // Update current field type
            currentFieldType = fieldType;
            
            // Update placeholder and description based on field type and key
            updateValueFieldForType(currentFieldType, fieldKey);
        });
    }
    
    /**
     * Initialize post selector
     */
    function initPostSelector() {
        $('#vbm-test-post').on('change', function() {
            // Reset value input
            $('#vbm-api-test-result').html('');
            $('#vbm-api-test-status').removeClass('status-success status-error').empty();
            
            if (!$(this).val()) {
                $('#action-selector-container, #field-selector-container, #value-input-container, #api-test-controls').hide();
                return;
            }
            
            // Show action selector immediately when a post is selected
            $('#action-selector-container').show();
            
            // Trigger method change to setup UI correctly
            $('#vbm-test-method').trigger('change');
        });
    }
    
    /**
     * Initialize API test button
     */
    function initApiTest() {
        $('#vbm-run-api-test').on('click', function() {
            const postId = $('#vbm-test-post').val();
            const fieldKey = $('#vbm-test-field').val();
            const fieldValue = $('#vbm-test-value').val();
            const httpMethod = $('#vbm-test-method').val();
            
            if (!postId) {
                showError('Please select a post');
                return;
            }
            
            // For POST method, ensure field is selected
            if (httpMethod === 'POST' && !fieldKey) {
                showError('Please select a field to update');
                return;
            }
            
            runApiTest(postId, fieldKey, fieldValue, httpMethod);
        });
    }
    
    /**
     * Load fields for selected post type
     */
    function loadFieldsForType(postType) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'vbm_get_post_fields',
                post_type: postType,
                nonce: vbmApiTester.ajax_nonce
            },
            beforeSend: function() {
                $('#vbm-test-field').html('<option value="">Loading fields...</option>');
            },
            success: function(response) {
                if (response.success) {
                    const fields = response.data;
                    
                    // Check for existing core fields in Voxel data
                    const existingCoreFields = {};
                    fields.forEach(function(field) {
                        if (wpCoreFields.includes(field.key)) {
                            existingCoreFields[field.key] = true;
                            // Mark it as a core field for proper handling
                            field.isCore = true;
                        }
                    });
                    
                    // Add only missing WordPress core fields
                    const coresToAdd = [
                        { key: 'title', label: 'Title (WordPress core)', type: 'text' },
                        { key: 'content', label: 'Content (WordPress core)', type: 'textarea' },
                        { key: 'excerpt', label: 'Excerpt (WordPress core)', type: 'textarea' },
                        { key: 'slug', label: 'URL Slug (WordPress core)', type: 'text' },
                        { key: 'status', label: 'Status (WordPress core)', type: 'select' },
                        { key: 'featured_media', label: 'Featured Image ID (WordPress core)', type: 'number' }
                    ].filter(item => !existingCoreFields[item.key]);
                    
                    // Combine and sort all fields alphabetically
                    const allFields = fields.concat(coresToAdd);
                    allFields.sort(function(a, b) {
                        return a.label.localeCompare(b.label);
                    });
                    
                    let options = '<option value="">-- Select Field --</option>';
                    
                    allFields.forEach(function(field) {
                        // Add a data attribute to identify core fields
                        const isCoreAttr = wpCoreFields.includes(field.key) ? ' data-core="true"' : '';
                        options += `<option value="${field.key}" data-type="${field.type}"${isCoreAttr}>
                            ${field.label}
                        </option>`;
                    });
                    
                    $('#vbm-test-field').html(options);
                } else {
                    $('#vbm-test-field').html('<option value="">Error loading fields</option>');
                    console.error('Error loading fields:', response.data);
                }
            },
            error: function(xhr, status, error) {
                $('#vbm-test-field').html('<option value="">Error loading fields</option>');
                console.error('AJAX Error:', status, error);
            }
        });
    }
    
    /**
     * Load posts for selected post type
     */
    function loadPostsForType(postType) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'vbm_get_post_list',
                post_type: postType,
                nonce: vbmApiTester.ajax_nonce
            },
            beforeSend: function() {
                $('#vbm-test-post').html('<option value="">Loading posts...</option>');
                $('#post-selector-container').show();
            },
            success: function(response) {
                if (response.success) {
                    const posts = response.data;
                    
                    // Sort posts alphabetically by title
                    posts.sort(function(a, b) {
                        return a.title.localeCompare(b.title);
                    });
                    
                    let options = '<option value="">-- Select Post --</option>';
                    
                    posts.forEach(function(post) {
                        options += `<option value="${post.id}">${post.title}</option>`;
                    });
                    
                    $('#vbm-test-post').html(options);
                } else {
                    $('#vbm-test-post').html('<option value="">Error loading posts</option>');
                    console.error('Error loading posts:', response.data);
                }
            },
            error: function(xhr, status, error) {
                $('#vbm-test-post').html('<option value="">Error loading posts</option>');
                console.error('AJAX Error:', status, error);
            }
        });
    }
    
    /**
     * Update value field based on field type and name
     */
    function updateValueFieldForType(fieldType, fieldName) {
        const $valueField = $('#vbm-test-value');
        const $description = $valueField.siblings('.description').first();
        
        // Check if this is a WordPress core field
        const isCoreField = wpCoreFields.includes(fieldName);
        
        switch (fieldType) {
            case 'number':
                $valueField.attr('placeholder', 'Enter a number (e.g. 42.5)');
                $description.text('Enter a numeric value');
                break;
                
            case 'switcher':
                $valueField.attr('placeholder', 'Enter true or false');
                $description.text('Use "true" or "false" (without quotes)');
                break;
                
            case 'post-relation':
                $valueField.attr('placeholder', 'Enter post ID(s)');
                $description.text('For multiple values, enter comma-separated IDs (e.g. 123,456)');
                break;
                
            case 'date':
                $valueField.attr('placeholder', 'YYYY-MM-DD HH:MM:SS');
                $description.text('Enter date in YYYY-MM-DD HH:MM:SS format');
                break;
                
            case 'repeater':
                $valueField.attr('placeholder', '{"key": "value"}');
                $description.text('Enter properly formatted JSON');
                break;
                
            default:
                if (isCoreField) {
                    // Provide specific guidance for core fields
                    switch(fieldName) {
                        case 'title':
                            $valueField.attr('placeholder', 'Enter post title');
                            $description.text('The title of the post');
                            break;
                        case 'content':
                            $valueField.attr('placeholder', 'Enter post content');
                            $description.text('The full content of the post');
                            break;
                        case 'excerpt':
                            $valueField.attr('placeholder', 'Enter post excerpt');
                            $description.text('A short summary of the content');
                            break;
                        case 'slug':
                            $valueField.attr('placeholder', 'enter-post-slug');
                            $description.text('The URL-friendly slug (no spaces, lowercase)');
                            break;
                        case 'status':
                            $valueField.attr('placeholder', 'publish, draft, private, etc.');
                            $description.text('Valid options: publish, draft, pending, private, future');
                            break;
                        case 'featured_media':
                            $valueField.attr('placeholder', 'Enter media ID');
                            $description.text('The ID of the featured image attachment');
                            break;
                        case 'comment_status':
                            $valueField.attr('placeholder', 'open or closed');
                            $description.text('Whether comments are allowed (open) or not (closed)');
                            break;
                        default:
                            $valueField.attr('placeholder', 'Enter field value');
                            $description.text('Enter the value for this WordPress core field');
                    }
                } else {
                    $valueField.attr('placeholder', 'Enter field value');
                    $description.text('Enter the value to update via API');
                }
                break;
        }
    }
    
    /**
     * Run API test
     */
    function runApiTest(postId, fieldKey, fieldValue, httpMethod) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'vbm_test_api_update',
                post_id: postId,
                field_key: fieldKey,
                field_type: currentFieldType,
                field_value: fieldValue,
                http_method: httpMethod,
                nonce: vbmApiTester.ajax_nonce
            },
            beforeSend: function() {
                $('#vbm-api-test-status').removeClass('status-success status-error').html('Testing...');
                $('#vbm-api-test-result').html('');
                $('#vbm-run-api-test').prop('disabled', true);
            },
            success: function(response) {
                $('#vbm-run-api-test').prop('disabled', false);
                
                if (response.success) {
                    // Format the response for display
                    const formattedResponse = JSON.stringify(response.data, null, 2);
                    $('#vbm-api-test-result').html(`<pre>${escapeHtml(formattedResponse)}</pre>`);
                    
                    if (httpMethod === 'POST') {
                        // Verify the submitted value is in the response
                        const isValueInResponse = verifyValueInResponse(
                            fieldKey, 
                            fieldValue, 
                            currentFieldType,
                            response.data.response
                        );
                        
                        if (isValueInResponse) {
                            // API call was successful and value is verified in response
                            $('#vbm-api-test-status').addClass('status-success').text('Success');
                        } else {
                            // API call succeeded but value wasn't found in response
                            $('#vbm-api-test-status').addClass('status-error').text('Error: Value not found in response');
                        }
                    } else {
                        // For GET and DELETE, just check status code
                        $('#vbm-api-test-status').addClass('status-success').text('Success');
                    }
                } else {
                    // API call failed
                    $('#vbm-api-test-status').addClass('status-error').text('Error');
                    
                    // Format the error for display
                    const formattedError = JSON.stringify(response.data, null, 2);
                    $('#vbm-api-test-result').html(`<pre>${escapeHtml(formattedError)}</pre>`);
                }
            },
            error: function(xhr, status, error) {
                $('#vbm-run-api-test').prop('disabled', false);
                $('#vbm-api-test-status').addClass('status-error').text('Error');
                $('#vbm-api-test-result').html(`<p>AJAX Error: ${status} ${error}</p>`);
                console.error('AJAX Error:', status, error);
            }
        });
    }
    
    /**
     * Verify the submitted value is in the API response
     * 
     * @param {string} fieldKey The key of the field
     * @param {*} fieldValue The value that was submitted
     * @param {string} fieldType The type of the field
     * @param {object} responseData The API response data
     * @return {boolean} Whether the value was found in the response
     */
    function verifyValueInResponse(fieldKey, fieldValue, fieldType, responseData) {
        if (!responseData) {
            return false;
        }
        
        // For core WordPress fields, check mapped field names
        const wpField = getWpCoreField(fieldKey);
        const actualFieldKey = wpField || fieldKey;
        
        // If the field doesn't exist in the response, it wasn't updated
        if (typeof responseData[actualFieldKey] === 'undefined') {
            return false;
        }
        
        const responseValue = responseData[actualFieldKey];
        
        // Format the field value according to type for comparison
        switch (fieldType) {
            case 'number':
                // Compare as numbers
                return parseFloat(responseValue) === parseFloat(fieldValue);
                
            case 'switcher':
                // Convert string representations of boolean to actual booleans
                const boolValue = fieldValue === 'true' || fieldValue === '1' || fieldValue === 'yes';
                return responseValue === boolValue;
                
            case 'post-relation':
                // For post relations, we need to handle both arrays and single values
                let submittedIds = [];
                
                if (typeof fieldValue === 'string' && fieldValue.includes(',')) {
                    submittedIds = fieldValue.split(',').map(id => parseInt(id.trim(), 10));
                } else if (typeof fieldValue === 'string') {
                    submittedIds = [parseInt(fieldValue, 10)];
                } else if (Array.isArray(fieldValue)) {
                    submittedIds = fieldValue.map(id => parseInt(id, 10));
                } else {
                    submittedIds = [parseInt(fieldValue, 10)];
                }
                
                // Response could be formatted in different ways
                let responseIds = [];
                
                if (Array.isArray(responseValue)) {
                    responseIds = responseValue.map(item => {
                        return typeof item === 'object' ? item.id : parseInt(item, 10);
                    });
                } else if (typeof responseValue === 'string' && responseValue.includes(',')) {
                    responseIds = responseValue.split(',').map(id => parseInt(id.trim(), 10));
                } else {
                    responseIds = [parseInt(responseValue, 10)];
                }
                
                // Check if all submitted IDs are in the response
                return submittedIds.every(id => responseIds.includes(id));
                
            case 'repeater':
                // For repeaters, we need to compare parsed JSON objects
                try {
                    const fieldObj = typeof fieldValue === 'string' ? JSON.parse(fieldValue) : fieldValue;
                    const responseObj = typeof responseValue === 'string' ? JSON.parse(responseValue) : responseValue;
                    return JSON.stringify(fieldObj) === JSON.stringify(responseObj);
                } catch (e) {
                    return false;
                }
                
            case 'date':
                // Compare normalized date formats
                const fieldDate = new Date(fieldValue).toISOString();
                const responseDate = new Date(responseValue).toISOString();
                return fieldDate === responseDate;
                
            default:
                // For all other fields, do a simple string comparison
                return String(responseValue).includes(String(fieldValue));
        }
    }
    
    /**
     * Get the WordPress core field name from a field key
     */
    function getWpCoreField(fieldKey) {
        const wpFieldsMap = {
            'title': 'title',
            'post_title': 'title',
            'content': 'content',
            'post_content': 'content', 
            'excerpt': 'excerpt',
            'post_excerpt': 'excerpt',
            'slug': 'slug',
            'post_name': 'slug',
            'status': 'status',
            'post_status': 'status',
            'featured_media': 'featured_media'
        };
        
        return wpFieldsMap[fieldKey] || null;
    }
    
    /**
     * Show error message
     */
    function showError(message) {
        $('#vbm-api-test-status').addClass('status-error').text('Error');
        $('#vbm-api-test-result').html(`<p>${message}</p>`);
    }
    
    /**
     * Escape HTML for safe display
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.innerText = text;
        return div.innerHTML;
    }
    
})(jQuery);
