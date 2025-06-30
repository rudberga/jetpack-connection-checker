jQuery(document).ready(function($) {
    console.log('Jetpack Connection Checker: Script loaded');
    console.log('jcc_ajax object:', typeof jcc_ajax !== 'undefined' ? jcc_ajax : 'undefined');
    
    var clipboard;
    var originalButtonText = $('#jcc-run-diagnostics').text();
    var diagnosticsData = null;
    
    console.log('Button found:', $('#jcc-run-diagnostics').length > 0);
    console.log('Original button text:', originalButtonText);
    
    // Initialize clipboard functionality
    function initClipboard() {
        console.log('ClipboardJS available:', typeof ClipboardJS !== 'undefined');
        if (typeof ClipboardJS !== 'undefined') {
            clipboard = new ClipboardJS('#jcc-copy-results', {
                target: function() {
                    return document.querySelector('#jcc-advanced-content pre');
                }
            });
            
            clipboard.on('success', function(e) {
                showMessage(jcc_ajax.strings.copied, 'success');
                e.clearSelection();
            });
            
            clipboard.on('error', function(e) {
                showMessage(jcc_ajax.strings.copy_failed, 'error');
            });
        } else {
            console.log('ClipboardJS not available, using fallback');
        }
    }
    
    // Download text file
    function downloadTxtFile() {
        if (!diagnosticsData || !diagnosticsData.detailed_report) {
            showMessage('No report data available', 'error');
            return;
        }
        
        var timestamp = new Date().toISOString().slice(0, 19).replace(/:/g, '-');
        var filename = 'jetpack-connection-checker-' + timestamp + '.txt';
        var content = diagnosticsData.detailed_report;
        
        downloadFile(filename, content, 'text/plain');
    }
    
    // Download JSON file
    function downloadJsonFile() {
        if (!diagnosticsData || !diagnosticsData.structured_data) {
            showMessage('No report data available', 'error');
            return;
        }
        
        var timestamp = new Date().toISOString().slice(0, 19).replace(/:/g, '-');
        var filename = 'jetpack-connection-checker-' + timestamp + '.json';
        var content = JSON.stringify(diagnosticsData.structured_data, null, 2);
        
        downloadFile(filename, content, 'application/json');
    }
    
    // Generic file download function
    function downloadFile(filename, content, mimeType) {
        var blob = new Blob([content], { type: mimeType });
        var url = window.URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = filename;
        a.style.display = 'none';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
        
        showMessage('Download started: ' + filename, 'success');
    }
    
    // Show message to user
    function showMessage(message, type) {
        var messageClass = type === 'success' ? 'notice-success' : 'notice-error';
        var messageHtml = '<div class="notice ' + messageClass + ' is-dismissible"><p>' + message + '</p></div>';
        
        $('.wrap h1').after(messageHtml);
        
        // Auto-dismiss after 3 seconds
        setTimeout(function() {
            $('.notice.is-dismissible').fadeOut();
        }, 3000);
    }
    
    // Display status summary with new three-tier system
    function displayStatusSummary(status) {
        var $summary = $('#jcc-status-summary');
        var $message = $('#jcc-status-message');
        var $description = $('#jcc-status-description');
        
        // Simple emoji mapping with fallbacks
        var emojiMap = {
            'failure': '🔴',
            'issue': '🟡', 
            'success': '🟢'
        };
        
        // Get emoji with fallback to text
        var emoji = emojiMap[status.level] || '';
        
        // Set content with emoji
        $message.text(emoji + ' ' + status.message);
        $description.text(status.description);
        
        // Set status level class (failure, issue, success)
        $summary.removeClass('failure issue success').addClass(status.level);
        
        // Show the summary
        $summary.show();
        
        // Handle Details section (failures + issues)
        var hasDetails = (status.failures && status.failures.length > 0) || 
                        (status.issues && status.issues.length > 0);
        
        if (hasDetails) {
            displayDetails(status.failures || [], status.issues || []);
            $('#jcc-details').show();
        } else {
            $('#jcc-details').hide();
        }
        
        // Handle accordion (inside status summary) - show failures, issues, or notices
        var accordionItems = [];
        var accordionTitle = 'Show details';
        
        // Priority: failures first, then issues, then notices
        if (status.failures && status.failures.length > 0) {
            accordionItems = accordionItems.concat(status.failures.map(function(item) {
                return {type: 'failure', message: item.message};
            }));
        }
        
        if (status.issues && status.issues.length > 0) {
            accordionItems = accordionItems.concat(status.issues.map(function(item) {
                return {type: 'issue', message: item.message};
            }));
        }
        
        if (status.notices && status.notices.length > 0) {
            accordionItems = accordionItems.concat(status.notices.map(function(item) {
                return {type: 'notice', message: item.message};
            }));
        }
        
        if (accordionItems.length > 0) {
            // Update accordion title based on content type
            if (status.failures && status.failures.length > 0) {
                accordionTitle = 'Show error details';
            } else if (status.issues && status.issues.length > 0) {
                accordionTitle = 'Show connection issues';
            } else {
                accordionTitle = 'Show notices';
            }
            
            setupStatusAccordion(accordionItems, accordionTitle);
            $('#jcc-notices-accordion').show();
        } else {
            $('#jcc-notices-accordion').hide();
        }
    }
    
    // Display detailed issues and failures
    function displayDetails(failures, issues) {
        var $detailsContent = $('#jcc-details-content');
        $detailsContent.empty();
        
        // Display failures first (more critical)
        failures.forEach(function(failure) {
            var $item = $('<div class="jcc-details-item">');
            $item.append('<div class="jcc-details-item-icon">&#128308;</div>'); // 🔴 as HTML entity
            $item.append('<p class="jcc-details-item-message">' + $('<div>').text(failure.message).html() + '</p>');
            $detailsContent.append($item);
        });
        
        // Then display issues
        issues.forEach(function(issue) {
            var $item = $('<div class="jcc-details-item">');
            $item.append('<div class="jcc-details-item-icon">&#128993;</div>'); // 🟡 as HTML entity
            $item.append('<p class="jcc-details-item-message">' + $('<div>').text(issue.message).html() + '</p>');
            $detailsContent.append($item);
        });
    }
    
    // Setup status accordion (failures, issues, or notices)
    function setupStatusAccordion(items, title) {
        var $noticesList = $('#jcc-notices-list');
        var $intro = $('#jcc-notices-intro');
        $noticesList.empty();
        
        // Set appropriate intro text based on item types
        var hasFailures = items.some(function(item) { return item.type === 'failure'; });
        var hasIssues = items.some(function(item) { return item.type === 'issue'; });
        var hasNotices = items.some(function(item) { return item.type === 'notice'; });
        
        if (hasFailures) {
            $intro.text('These critical issues are preventing Jetpack from connecting properly:');
        } else if (hasIssues) {
            $intro.text('These issues may affect some Jetpack functionality:');
        } else {
            $intro.text('These items don\'t affect your Jetpack connection but are worth noting:');
        }
        
        // Separate critical issues from environmental notes when there are failures
        if (hasFailures) {
            // Show failures and issues first
            var criticalItems = items.filter(function(item) { 
                return item.type === 'failure' || item.type === 'issue'; 
            });
            var environmentalItems = items.filter(function(item) { 
                return item.type === 'notice'; 
            });
            
            // Add critical items
            criticalItems.forEach(function(item) {
                var $itemDiv = $('<div class="jcc-notices-item">');
                var icon = item.type === 'failure' ? '🔴' : '🟡';
                var itemClass = 'jcc-notices-item-message ' + item.type;
                
                $itemDiv.append('<div class="jcc-notices-item-icon">' + icon + '</div>');
                $itemDiv.append('<p class="' + itemClass + '">' + $('<div>').text(item.message).html() + '</p>');
                $noticesList.append($itemDiv);
            });
            
            // Add environmental notes section if there are any
            if (environmentalItems.length > 0) {
                var $separator = $('<div class="jcc-notices-separator">');
                $separator.append('<p class="jcc-notices-separator-text">Other notes not affecting the connection:</p>');
                $noticesList.append($separator);
                
                environmentalItems.forEach(function(item) {
                    var $itemDiv = $('<div class="jcc-notices-item">');
                    $itemDiv.append('<div class="jcc-notices-item-icon">•</div>');
                    $itemDiv.append('<p class="jcc-notices-item-message">' + $('<div>').text(item.message).html() + '</p>');
                    $noticesList.append($itemDiv);
                });
            }
        } else {
            // Normal display for when connection is healthy
            items.forEach(function(item) {
                var $itemDiv = $('<div class="jcc-notices-item">');
                var icon = '•'; // Default bullet
                var itemClass = 'jcc-notices-item-message';
                
                // Use different styling based on item type
                if (item.type === 'failure') {
                    icon = '🔴';
                    itemClass = 'jcc-notices-item-message failure';
                } else if (item.type === 'issue') {
                    icon = '🟡';
                    itemClass = 'jcc-notices-item-message issue';
                }
                
                $itemDiv.append('<div class="jcc-notices-item-icon">' + icon + '</div>');
                $itemDiv.append('<p class="' + itemClass + '">' + $('<div>').text(item.message).html() + '</p>');
                $noticesList.append($itemDiv);
            });
        }
        
        // Setup accordion toggle with dynamic title
        var originalTitle = title;
        $('#jcc-toggle-notices').off('click').on('click', function() {
            var $button = $(this);
            var $content = $('#jcc-notices-content');
            
            if ($content.is(':visible')) {
                // Hide content
                $content.slideUp();
                $button.removeClass('expanded');
                $button.find('span:not(.dashicons)').text(originalTitle);
            } else {
                // Show content
                $content.slideDown();
                $button.addClass('expanded');
                $button.find('span:not(.dashicons)').text('Hide details');
            }
        });
        
        // Set initial button text
        $('#jcc-toggle-notices span:not(.dashicons)').text(originalTitle);
    }
    
    // Setup user interface after diagnostics
    function setupUserInterface() {
        // Show action buttons and guidance
        $('#jcc-action-buttons').show();
        $('#jcc-guidance').show();
        $('#jcc-advanced-toggle').show();
        
        // Setup advanced toggle
        $('#jcc-toggle-advanced').off('click').on('click', function() {
            var $button = $(this);
            var $details = $('#jcc-advanced-details');
            
            if ($details.is(':visible')) {
                // Hide advanced details
                $details.slideUp();
                $button.removeClass('expanded');
                $button.find('span:not(.dashicons)').text(jcc_ajax.strings.show_details);
            } else {
                // Show advanced details
                $details.slideDown();
                $button.addClass('expanded');
                $button.find('span:not(.dashicons)').text(jcc_ajax.strings.hide_details);
                
                // Initialize clipboard when advanced details is first shown
                if (!clipboard) {
                    initClipboard();
                }
            }
        });
        
        // Setup download buttons
        $('#jcc-copy-results').off('click').on('click', function() {
            if (!clipboard) {
                initClipboard();
            }
            // Manual trigger clipboard copy
            var textToCopy = $('#jcc-advanced-content pre').text();
            if (navigator.clipboard) {
                navigator.clipboard.writeText(textToCopy).then(function() {
                    showMessage(jcc_ajax.strings.copied, 'success');
                });
            } else {
                showMessage(jcc_ajax.strings.copy_failed, 'error');
            }
        });
        $('#jcc-download-txt').off('click').on('click', downloadTxtFile);
        $('#jcc-download-json').off('click').on('click', downloadJsonFile);
    }
    
    // Check if jcc_ajax is available
    if (typeof jcc_ajax === 'undefined') {
        console.error('jcc_ajax object is not defined - AJAX will not work');
        return;
    }
    
    // Run diagnostics
    $('#jcc-run-diagnostics').on('click', function() {
        console.log('Button clicked!');
        
        var $button = $(this);
        var $loading = $('#jcc-loading');
        var $statusSummary = $('#jcc-status-summary');
        var $actionButtons = $('#jcc-action-buttons');
        var $guidance = $('#jcc-guidance');
        var $advancedToggle = $('#jcc-advanced-toggle');
        var $advancedDetails = $('#jcc-advanced-details');
        
        // Reset UI state
        $statusSummary.hide();
        $('#jcc-details').hide();
        $('#jcc-notices-accordion').hide();
        $actionButtons.hide();
        $guidance.hide();
        $advancedToggle.hide();
        $advancedDetails.hide();
        
        // Show loading state
        $button.prop('disabled', true).text(jcc_ajax.strings.running);
        $loading.show();
        
        // Make AJAX request
        console.log('Making AJAX request to:', jcc_ajax.url);
        console.log('AJAX data:', {
            action: 'jcc_run_diagnostics',
            nonce: jcc_ajax.nonce
        });
        
        $.post(jcc_ajax.url, {
            action: 'jcc_run_diagnostics',
            nonce: jcc_ajax.nonce
        })
        .done(function(response) {
            console.log('AJAX response received:', response);
            
            if (response.success && response.data) {
                console.log('Success response with data');
                // Store data globally for download functions
                diagnosticsData = response.data;
                
                // Display status summary
                if (diagnosticsData.status) {
                    displayStatusSummary(diagnosticsData.status);
                }
                
                // Prepare advanced details
                if (diagnosticsData.detailed_report) {
                    $('#jcc-advanced-content').html('<pre>' + diagnosticsData.detailed_report + '</pre>');
                }
                
                // Setup user interface
                setupUserInterface();
                
                // Update menu indicator (optional enhancement)
                updateMenuIndicator(diagnosticsData.status);
                
            } else {
                console.log('AJAX response error - no success or data');
                showMessage(jcc_ajax.strings.error, 'error');
            }
        })
        .fail(function(xhr, status, error) {
            console.log('AJAX request failed:', status, error);
            console.log('XHR object:', xhr);
            showMessage(jcc_ajax.strings.error, 'error');
        })
        .always(function() {
            // Reset button state
            $button.prop('disabled', false).text(originalButtonText);
            $loading.hide();
        });
    });
    
    // Update menu indicator (optional enhancement)
    function updateMenuIndicator(status) {
        if (!status) return;
        
        var indicatorClass = 'jcc-health-indicator ';
        
        switch(status.level) {
            case 'success':
                indicatorClass += 'healthy';
                break;
            case 'warning':
                indicatorClass += 'warning';
                break;
            case 'error':
                indicatorClass += 'error';
                break;
            default:
                return;
        }
        
        // Find the menu item and add indicator
        var $menuItem = $('#adminmenu a[href*="jetpack-connection-checker"]');
        if ($menuItem.length) {
            // Remove any existing indicator
            $menuItem.find('.jcc-health-indicator').remove();
            
            // Add new indicator
            $menuItem.append('<span class="' + indicatorClass + '"></span>');
        }
    }
});