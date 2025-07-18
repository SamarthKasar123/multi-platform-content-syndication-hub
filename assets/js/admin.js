/**
 * Multi-Platform Syndication Hub Admin JavaScript
 */

(function($) {
    'use strict';
    
    // Main admin object
    window.MPCSHub = {
        init: function() {
            this.bindEvents();
            this.initComponents();
        },
        
        bindEvents: function() {
            // Sync content buttons
            $(document).on('click', '.mpcs-sync-content', this.syncContent);
            $(document).on('click', '.mpcs-retry-sync', this.retrySync);
            
            // Platform toggles
            $(document).on('change', '.mpcs-platform-toggle input', this.togglePlatform);
            
            // Auto-sync settings
            $(document).on('change', '#mpcs-auto-sync', this.toggleAutoSync);
            
            // Test connection buttons
            $(document).on('click', '.mpcs-test-connection', this.testConnection);
            
            // Save platform configuration
            $(document).on('click', '.mpcs-save-config', this.savePlatformConfig);
            
            // Real-time sync toggle
            $(document).on('change', '#mpcs-realtime-sync', this.toggleRealtimeSync);
            
            // Bulk actions
            $(document).on('change', '.mpcs-bulk-select-all', this.toggleBulkSelect);
            $(document).on('click', '.mpcs-bulk-sync', this.bulkSync);
        },
        
        initComponents: function() {
            // Initialize tooltips
            $('.mpcs-tooltip').tooltip();
            
            // Initialize analytics charts
            if (typeof Chart !== 'undefined') {
                this.initAnalyticsCharts();
            }
            
            // Initialize real-time updates
            this.initRealtimeUpdates();
        },
        
        syncContent: function(e) {
            e.preventDefault();
            
            const button = $(this);
            const postId = button.data('post-id');
            const platforms = button.data('platforms') || [];
            
            if (!postId) {
                MPCSHub.showNotification('Error: Post ID not found', 'error');
                return;
            }
            
            button.prop('disabled', true)
                  .html('<span class="mpcs-spinner"></span> Syncing...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'mpcs_sync_content',
                    post_id: postId,
                    platforms: platforms,
                    nonce: mpcsHub.nonce
                },
                success: function(response) {
                    if (response.success) {
                        MPCSHub.showNotification('Content sync initiated successfully!', 'success');
                        MPCSHub.updateSyncStatus(postId, response.data);
                    } else {
                        MPCSHub.showNotification('Sync failed: ' + response.data.message, 'error');
                    }
                },
                error: function() {
                    MPCSHub.showNotification('Network error occurred. Please try again.', 'error');
                },
                complete: function() {
                    button.prop('disabled', false)
                          .html('<span class="dashicons dashicons-share"></span> Sync Content');
                }
            });
        },
        
        retrySync: function(e) {
            e.preventDefault();
            
            const button = $(this);
            const logId = button.data('log-id');
            
            button.prop('disabled', true).text('Retrying...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'mpcs_retry_syndication',
                    log_id: logId,
                    nonce: mpcsHub.nonce
                },
                success: function(response) {
                    if (response.success) {
                        MPCSHub.showNotification('Retry initiated successfully!', 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        MPCSHub.showNotification('Retry failed: ' + response.data.message, 'error');
                    }
                },
                error: function() {
                    MPCSHub.showNotification('Network error occurred. Please try again.', 'error');
                },
                complete: function() {
                    button.prop('disabled', false).text('Retry');
                }
            });
        },
        
        togglePlatform: function() {
            const checkbox = $(this);
            const platform = checkbox.val();
            const isEnabled = checkbox.is(':checked');
            const card = checkbox.closest('.mpcs-platform-card');
            
            card.toggleClass('mpcs-platform-enabled', isEnabled);
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'mpcs_toggle_platform',
                    platform: platform,
                    enabled: isEnabled,
                    nonce: mpcsHub.nonce
                },
                success: function(response) {
                    if (response.success) {
                        const message = isEnabled ? 
                            `${platform} platform enabled` : 
                            `${platform} platform disabled`;
                        MPCSHub.showNotification(message, 'success');
                    } else {
                        // Revert toggle on failure
                        checkbox.prop('checked', !isEnabled);
                        card.toggleClass('mpcs-platform-enabled', !isEnabled);
                        MPCSHub.showNotification('Failed to update platform status', 'error');
                    }
                }
            });
        },
        
        toggleAutoSync: function() {
            const checkbox = $(this);
            const isEnabled = checkbox.is(':checked');
            const platformCheckboxes = $('.mpcs-platform-checkbox');
            
            platformCheckboxes.toggle(isEnabled);
        },
        
        testConnection: function(e) {
            e.preventDefault();
            
            const button = $(this);
            const platform = button.data('platform');
            
            button.prop('disabled', true)
                  .html('<span class="mpcs-spinner"></span> Testing...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'mpcs_test_connection',
                    platform: platform,
                    nonce: mpcsHub.nonce
                },
                success: function(response) {
                    if (response.success) {
                        MPCSHub.showNotification(`${platform} connection successful!`, 'success');
                        button.closest('.mpcs-platform-card')
                              .find('.mpcs-connection-status')
                              .removeClass('failed')
                              .addClass('success')
                              .text('Connected');
                    } else {
                        MPCSHub.showNotification(`${platform} connection failed: ${response.data.message}`, 'error');
                        button.closest('.mpcs-platform-card')
                              .find('.mpcs-connection-status')
                              .removeClass('success')
                              .addClass('failed')
                              .text('Failed');
                    }
                },
                error: function() {
                    MPCSHub.showNotification('Network error occurred during connection test', 'error');
                },
                complete: function() {
                    button.prop('disabled', false)
                          .html('<span class="dashicons dashicons-admin-tools"></span> Test Connection');
                }
            });
        },
        
        savePlatformConfig: function(e) {
            e.preventDefault();
            
            const button = $(this);
            const form = button.closest('form');
            const platform = form.data('platform');
            
            button.prop('disabled', true).text('Saving...');
            
            const formData = new FormData(form[0]);
            formData.append('action', 'mpcs_save_platform_config');
            formData.append('platform', platform);
            formData.append('nonce', mpcsHub.nonce);
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        MPCSHub.showNotification(`${platform} configuration saved successfully!`, 'success');
                    } else {
                        MPCSHub.showNotification(`Failed to save configuration: ${response.data.message}`, 'error');
                    }
                },
                error: function() {
                    MPCSHub.showNotification('Network error occurred while saving', 'error');
                },
                complete: function() {
                    button.prop('disabled', false).text('Save Configuration');
                }
            });
        },
        
        toggleRealtimeSync: function() {
            const checkbox = $(this);
            const isEnabled = checkbox.is(':checked');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'mpcs_toggle_realtime_sync',
                    enabled: isEnabled,
                    nonce: mpcsHub.nonce
                },
                success: function(response) {
                    if (response.success) {
                        const message = isEnabled ? 
                            'Real-time sync enabled' : 
                            'Real-time sync disabled';
                        MPCSHub.showNotification(message, 'success');
                    } else {
                        // Revert toggle on failure
                        checkbox.prop('checked', !isEnabled);
                        MPCSHub.showNotification('Failed to update real-time sync setting', 'error');
                    }
                }
            });
        },
        
        toggleBulkSelect: function() {
            const masterCheckbox = $(this);
            const isChecked = masterCheckbox.is(':checked');
            const rowCheckboxes = $('.mpcs-bulk-select');
            
            rowCheckboxes.prop('checked', isChecked);
            MPCSHub.updateBulkActions();
        },
        
        bulkSync: function(e) {
            e.preventDefault();
            
            const selectedItems = $('.mpcs-bulk-select:checked');
            
            if (selectedItems.length === 0) {
                MPCSHub.showNotification('Please select items to sync', 'warning');
                return;
            }
            
            const button = $(this);
            const postIds = selectedItems.map(function() {
                return $(this).val();
            }).get();
            
            button.prop('disabled', true).text('Syncing...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'mpcs_bulk_sync',
                    post_ids: postIds,
                    nonce: mpcsHub.nonce
                },
                success: function(response) {
                    if (response.success) {
                        MPCSHub.showNotification(`Bulk sync initiated for ${postIds.length} items`, 'success');
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        MPCSHub.showNotification('Bulk sync failed: ' + response.data.message, 'error');
                    }
                },
                error: function() {
                    MPCSHub.showNotification('Network error occurred during bulk sync', 'error');
                },
                complete: function() {
                    button.prop('disabled', false).text('Sync Selected');
                }
            });
        },
        
        updateBulkActions: function() {
            const selectedCount = $('.mpcs-bulk-select:checked').length;
            const bulkActions = $('.mpcs-bulk-actions');
            
            if (selectedCount > 0) {
                bulkActions.show();
                bulkActions.find('.selected-count').text(selectedCount);
            } else {
                bulkActions.hide();
            }
        },
        
        updateSyncStatus: function(postId, statusData) {
            // Update sync status in the UI
            const statusContainer = $(`.mpcs-sync-status[data-post-id="${postId}"]`);
            
            if (statusContainer.length) {
                let statusHtml = '';
                
                Object.keys(statusData).forEach(platform => {
                    const status = statusData[platform];
                    const statusClass = status.status || 'pending';
                    statusHtml += `<span class="mpcs-status-${statusClass}">${platform.toUpperCase()}</span> `;
                });
                
                statusContainer.html(statusHtml);
            }
        },
        
        showNotification: function(message, type = 'info') {
            const notification = $(`
                <div class="mpcs-notification ${type}">
                    <p>${message}</p>
                    <button class="mpcs-notification-close">&times;</button>
                </div>
            `);
            
            $('body').append(notification);
            
            // Show notification
            setTimeout(() => notification.addClass('show'), 100);
            
            // Auto hide after 5 seconds
            setTimeout(() => {
                notification.removeClass('show');
                setTimeout(() => notification.remove(), 300);
            }, 5000);
            
            // Manual close
            notification.find('.mpcs-notification-close').on('click', function() {
                notification.removeClass('show');
                setTimeout(() => notification.remove(), 300);
            });
        },
        
        initAnalyticsCharts: function() {
            // Initialize analytics charts if Chart.js is available
            const chartContainer = $('#mpcs-analytics-chart');
            
            if (chartContainer.length && typeof Chart !== 'undefined') {
                this.loadAnalyticsData();
            }
        },
        
        loadAnalyticsData: function() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'mpcs_get_analytics_data',
                    nonce: mpcsHub.nonce
                },
                success: function(response) {
                    if (response.success) {
                        MPCSHub.renderAnalyticsChart(response.data);
                    }
                }
            });
        },
        
        renderAnalyticsChart: function(data) {
            const ctx = document.getElementById('mpcs-analytics-chart');
            
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: data.datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    },
                    plugins: {
                        title: {
                            display: true,
                            text: 'Syndication Analytics'
                        },
                        legend: {
                            display: true,
                            position: 'top'
                        }
                    }
                }
            });
        },
        
        initRealtimeUpdates: function() {
            // Check for real-time updates every 30 seconds
            if (mpcsHub.realtimeUpdates) {
                setInterval(() => {
                    this.checkForUpdates();
                }, 30000);
            }
        },
        
        checkForUpdates: function() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'mpcs_check_updates',
                    nonce: mpcsHub.nonce
                },
                success: function(response) {
                    if (response.success && response.data.hasUpdates) {
                        MPCSHub.showNotification('New syndication updates available. Refresh to see changes.', 'info');
                    }
                }
            });
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        MPCSHub.init();
        
        // Update bulk actions when checkboxes change
        $(document).on('change', '.mpcs-bulk-select', MPCSHub.updateBulkActions);
    });
    
})(jQuery);
