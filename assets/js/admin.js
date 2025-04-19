/**
 * MemberPress AI Assistant - Memory Admin JavaScript
 */

jQuery(document).ready(function($) {
    // Handle Memory System Settings
    const $enableMemory = $('#mpaim_enable_memory');
    const $memorySettings = $('.mpai-setting-row').not(':first');
    
    // Show/hide memory settings based on enable checkbox
    function toggleMemorySettings() {
        if ($enableMemory.is(':checked')) {
            $memorySettings.show();
        } else {
            $memorySettings.hide();
        }
    }
    
    // Initial state
    toggleMemorySettings();
    
    // Toggle on change
    $enableMemory.on('change', toggleMemorySettings);
    
    // Test Memory System
    $('#mpaim-test-memory').on('click', function(e) {
        e.preventDefault();
        
        const $button = $(this);
        
        // Disable button and show loading state
        $button.prop('disabled', true).text('Testing...');
        
        // Send AJAX request
        $.ajax({
            url: mpaim_data.ajax_url,
            method: 'POST',
            data: {
                action: 'mpaim_test_memory',
                nonce: mpaim_data.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('Memory test successful! A test memory was stored and retrieved.');
                    // Refresh memory stats
                    loadMemoryStats();
                } else {
                    alert('Memory test failed: ' + response.data);
                }
            },
            error: function() {
                alert('An error occurred while testing the memory system.');
            },
            complete: function() {
                // Reset button state
                $button.prop('disabled', false).text('Test Memory System');
            }
        });
    });
    
    // Clear Memory
    $('#mpaim-clear-memory').on('click', function(e) {
        e.preventDefault();
        
        // Confirm with user
        if (!confirm('Are you sure you want to clear all your memories? This cannot be undone.')) {
            return;
        }
        
        const $button = $(this);
        
        // Disable button and show loading state
        $button.prop('disabled', true).text('Clearing...');
        
        // Send AJAX request
        $.ajax({
            url: mpaim_data.ajax_url,
            method: 'POST',
            data: {
                action: 'mpaim_clear_memory',
                nonce: mpaim_data.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('Your memories have been cleared successfully.');
                    // Refresh memory stats
                    loadMemoryStats();
                } else {
                    alert('Failed to clear memories: ' + response.data);
                }
            },
            error: function() {
                alert('An error occurred while clearing memories.');
            },
            complete: function() {
                // Reset button state
                $button.prop('disabled', false).text('Clear Your Memories');
            }
        });
    });
    
    // Load Memory Stats
    function loadMemoryStats() {
        // Show loading indicator
        $('#mpai-memory-stats-loading').show();
        $('#mpai-memory-stats').hide();
        
        // Send AJAX request
        $.ajax({
            url: mpaim_data.ajax_url,
            method: 'POST',
            data: {
                action: 'mpaim_get_memory_stats',
                nonce: mpaim_data.nonce
            },
            success: function(response) {
                if (response.success) {
                    const data = response.data;
                    
                    // Update total count
                    $('#mpai-memory-total').text(data.total_count || 0);
                    
                    // Update user count
                    const userCount = data.user_counts ? data.user_counts.length : 0;
                    $('#mpai-memory-users').text(userCount);
                    
                    // Update memory types
                    renderMemoryTypes(data.type_counts || []);
                    
                    // Update latest memories
                    renderLatestMemories(data.latest_memories || []);
                    
                    // Hide loading, show stats
                    $('#mpai-memory-stats-loading').hide();
                    $('#mpai-memory-stats').show();
                } else {
                    $('#mpai-memory-stats-loading').text('Error loading memory statistics: ' + (response.data || 'Unknown error'));
                }
            },
            error: function() {
                $('#mpai-memory-stats-loading').text('An error occurred while loading memory statistics.');
            }
        });
    }
    
    // Render memory types
    function renderMemoryTypes(types) {
        let html = '';
        
        if (types.length > 0) {
            html = '<ul>';
            
            types.forEach(function(type) {
                // Clean up the type name (remove quotes)
                const typeName = type.type ? type.type.replace(/"/g, '') : 'Unknown';
                html += `<li><strong>${typeName}:</strong> ${type.count}</li>`;
            });
            
            html += '</ul>';
        } else {
            html = '<p>No memory types found.</p>';
        }
        
        $('#mpai-memory-types').html(html);
    }
    
    // Render latest memories
    function renderLatestMemories(memories) {
        let html = '';
        
        if (memories.length > 0) {
            html = '<table class="widefat">';
            html += '<thead><tr>';
            html += '<th>User</th>';
            html += '<th>Type</th>';
            html += '<th>Content</th>';
            html += '<th>Date</th>';
            html += '</tr></thead><tbody>';
            
            memories.forEach(function(memory) {
                // Extract type from metadata
                const type = memory.metadata && memory.metadata.type ? memory.metadata.type : 'Unknown';
                
                // Truncate content if too long
                const content = memory.content.length > 100 
                    ? memory.content.substring(0, 100) + '...' 
                    : memory.content;
                
                html += '<tr>';
                html += `<td>${memory.user_id}</td>`;
                html += `<td>${type}</td>`;
                html += `<td>${content}</td>`;
                html += `<td>${memory.created_at}</td>`;
                html += '</tr>';
            });
            
            html += '</tbody></table>';
        } else {
            html = '<p>No memories found.</p>';
        }
        
        $('#mpai-latest-memories').html(html);
    }
    
    // Load stats on page load if we're on the memory tab
    if ($('#mpaim_enable_memory').length > 0) {
        loadMemoryStats();
    }
});