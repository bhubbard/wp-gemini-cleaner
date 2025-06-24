// admin-script.js
jQuery(document).ready(function($) {
    var ajax_url = wpgc_ajax_object.ajax_url;
    var scan_options_nonce = wpgc_ajax_object.scan_options_nonce;
    var scan_postmeta_nonce = wpgc_ajax_object.scan_postmeta_nonce;
    var delete_option_nonce = wpgc_ajax_object.delete_option_nonce;
    var delete_postmeta_nonce = wpgc_ajax_object.delete_postmeta_nonce;
    var confirm_delete_message = wpgc_ajax_object.confirm_delete;
    var api_key_missing_message = wpgc_ajax_object.api_key_missing;

    var $scanOptionsButton = $('#wpgc-scan-options');
    var $scanPostmetaButton = $('#wpgc-scan-postmeta');
    var $scanResultsSection = $('#wpgc-scan-results');
    var $loadingSpinner = $('#wpgc-loading-spinner');
    var $resultsContent = $('#wpgc-results-content');

    /**
     * Handles the click event for scanning WP Options.
     */
    $scanOptionsButton.on('click', function() {
        if ($('#wpgc_gemini_api_key').val() === '') {
            displayMessage(api_key_missing_message, 'error');
            return;
        }
        performScan('options');
    });

    /**
     * Handles the click event for scanning Post Meta.
     */
    $scanPostmetaButton.on('click', function() {
        if ($('#wpgc_gemini_api_key').val() === '') {
            displayMessage(api_key_missing_message, 'error');
            return;
        }
        performScan('postmeta');
    });

    /**
     * Performs the AJAX scan operation.
     * @param {string} type 'options' or 'postmeta'.
     */
    function performScan(type) {
        $scanResultsSection.show();
        $loadingSpinner.show();
        $resultsContent.empty(); // Clear previous results
        displayMessage('Starting scan, please wait...', 'info');

        var action = (type === 'options') ? 'wpgc_scan_options' : 'wpgc_scan_postmeta';
        var nonce = (type === 'options') ? scan_options_nonce : scan_postmeta_nonce;

        $.ajax({
            url: ajax_url,
            type: 'POST',
            data: {
                action: action,
                nonce: nonce,
            },
            success: function(response) {
                $loadingSpinner.hide();
                if (response.success) {
                    displayMessage(response.data.message, 'success');
                    renderScanResults(response.data.results, response.data.type);
                } else {
                    displayMessage(response.data.message, 'error');
                }
            },
            error: function(xhr, status, error) {
                $loadingSpinner.hide();
                displayMessage('AJAX Error: ' + error + ' - ' + xhr.responseText, 'error');
            }
        });
    }

    /**
     * Renders the scan results into the results content area.
     * @param {Array} results An array of suggested items (option names or meta keys).
     * @param {string} type 'options' or 'postmeta'.
     */
    function renderScanResults(results, type) {
        $resultsContent.empty();

        if (results.length === 0) {
            $resultsContent.append('<p class="wpgc-no-results">No potentially irrelevant ' + type + ' found by Gemini API.</p>');
            return;
        }

        var $ul = $('<ul class="wpgc-result-list">');
        $.each(results, function(index, item) {
            var $li = $('<li>')
                .append('<strong>' + item + '</strong>')
                .append('<button class="button wpgc-delete-button" data-item="' + item + '" data-type="' + type + '">Delete</button>');
            $ul.append($li);
        });
        $resultsContent.append($ul);

        // Attach click handler to newly created delete buttons
        $resultsContent.find('.wpgc-delete-button').on('click', function() {
            var itemToDelete = $(this).data('item');
            var itemType = $(this).data('type');
            if (confirm(confirm_delete_message)) {
                deleteItem(itemToDelete, itemType, $(this));
            }
        });
    }

    /**
     * Handles the deletion of an option or post meta.
     * @param {string} itemToDelete The name of the option or meta key to delete.
     * @param {string} itemType 'options' or 'postmeta'.
     * @param {jQuery} $button The button element that was clicked.
     */
    function deleteItem(itemToDelete, itemType, $button) {
        $button.prop('disabled', true).text('Deleting...');

        var action = (itemType === 'options') ? 'wpgc_delete_option' : 'wpgc_delete_postmeta';
        var nonce = (itemType === 'options') ? delete_option_nonce : delete_postmeta_nonce;
        var data = {
            action: action,
            nonce: nonce
        };

        if (itemType === 'options') {
            data.option_name = itemToDelete;
        } else { // postmeta
            data.meta_key = itemToDelete;
        }

        $.ajax({
            url: ajax_url,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    displayMessage(response.data.message, 'success');
                    $button.closest('li').fadeOut(300, function() {
                        $(this).remove();
                        // Check if list is empty after removal
                        if ($resultsContent.find('li').length === 0) {
                            $resultsContent.append('<p class="wpgc-no-results">All suggested ' + itemType + ' have been removed.</p>');
                        }
                    });
                } else {
                    displayMessage(response.data.message, 'error');
                    $button.prop('disabled', false).text('Delete'); // Re-enable button on failure
                }
            },
            error: function(xhr, status, error) {
                displayMessage('AJAX Error: ' + error + ' - ' + xhr.responseText, 'error');
                $button.prop('disabled', false).text('Delete'); // Re-enable button on failure
            }
        });
    }

    /**
     * Displays a temporary message to the user.
     * @param {string} message The message to display.
     * @param {string} type 'success', 'error', or 'info'.
     */
    function displayMessage(message, type) {
        $('.wpgc-admin-wrap .notice, .wpgc-admin-wrap .error, .wpgc-admin-wrap .updated').remove(); // Remove existing messages
        var $msgDiv = $('<div class="notice is-dismissible ' + (type === 'error' ? 'notice-error' : (type === 'success' ? 'notice-success' : 'notice-info')) + '"><p>' + message + '</p></div>');
        $msgDiv.insertBefore($('.wpgc-admin-wrap h1'));
        // Make it dismissible
        $msgDiv.on('click', '.notice-dismiss', function() {
            $(this).closest('.notice').remove();
        });
        // Auto-dismiss after 5 seconds for success/info messages
        if (type !== 'error') {
            setTimeout(function() {
                $msgDiv.fadeOut(500, function() {
                    $(this).remove();
                });
            }, 5000);
        }
    }
});
