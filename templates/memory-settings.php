<?php
/**
 * Memory settings tab template
 *
 * @package MemberPress AI Assistant Memory
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="mpai-settings-section">
    <h3><?php _e('Memory System Settings', 'memberpress-ai-assistant-memory'); ?></h3>
    
    <p><?php _e('The memory system allows the AI assistant to remember past conversations and recall them when relevant to the current conversation.', 'memberpress-ai-assistant-memory'); ?></p>
    
    <form method="post" action="options.php" class="mpai-settings-form">
        <?php settings_fields('mpai_options'); ?>
        
        <div class="mpai-setting-row">
            <div class="mpai-setting-label">
                <label for="mpaim_enable_memory"><?php _e('Enable Memory System', 'memberpress-ai-assistant-memory'); ?></label>
            </div>
            <div class="mpai-setting-field">
                <label class="mpai-toggle">
                    <input type="checkbox" name="mpaim_enable_memory" id="mpaim_enable_memory" value="1" <?php checked(get_option('mpaim_enable_memory', true)); ?>>
                    <span class="mpai-toggle-slider"></span>
                </label>
                <p class="mpai-setting-description"><?php _e('When enabled, the AI assistant will remember past conversations and recall relevant information.', 'memberpress-ai-assistant-memory'); ?></p>
            </div>
        </div>
        
        <div class="mpai-setting-row">
            <div class="mpai-setting-label">
                <label for="mpaim_memory_database"><?php _e('Storage System', 'memberpress-ai-assistant-memory'); ?></label>
            </div>
            <div class="mpai-setting-field">
                <select name="mpaim_memory_database" id="mpaim_memory_database">
                    <option value="database" <?php selected(get_option('mpaim_memory_database', 'database'), 'database'); ?>><?php _e('WordPress Database', 'memberpress-ai-assistant-memory'); ?></option>
                    <option value="mcp" <?php selected(get_option('mpaim_memory_database', 'database'), 'mcp'); ?>><?php _e('MCP Memory Server', 'memberpress-ai-assistant-memory'); ?></option>
                </select>
                <p class="mpai-setting-description"><?php _e('Choose where to store memories. WordPress Database is simpler, while MCP Memory Server provides better semantic search but requires the MCP server.', 'memberpress-ai-assistant-memory'); ?></p>
            </div>
        </div>
        
        <div class="mpai-setting-row">
            <div class="mpai-setting-label">
                <label for="mpaim_max_memories"><?php _e('Maximum Memories', 'memberpress-ai-assistant-memory'); ?></label>
            </div>
            <div class="mpai-setting-field">
                <input type="number" name="mpaim_max_memories" id="mpaim_max_memories" value="<?php echo esc_attr(get_option('mpaim_max_memories', 50)); ?>" min="10" max="500" step="10">
                <p class="mpai-setting-description"><?php _e('Maximum number of memories to store per user. Older memories will be deleted when this limit is reached.', 'memberpress-ai-assistant-memory'); ?></p>
            </div>
        </div>
        
        <div class="mpai-setting-row">
            <div class="mpai-setting-label">
                <label for="mpaim_memory_retention_days"><?php _e('Memory Retention (Days)', 'memberpress-ai-assistant-memory'); ?></label>
            </div>
            <div class="mpai-setting-field">
                <input type="number" name="mpaim_memory_retention_days" id="mpaim_memory_retention_days" value="<?php echo esc_attr(get_option('mpaim_memory_retention_days', 30)); ?>" min="1" max="365" step="1">
                <p class="mpai-setting-description"><?php _e('Number of days to retain memories. Memories older than this will be automatically deleted.', 'memberpress-ai-assistant-memory'); ?></p>
            </div>
        </div>
        
        <div class="mpai-setting-submit">
            <?php submit_button(__('Save Memory Settings', 'memberpress-ai-assistant-memory'), 'primary', 'submit', false); ?>
            
            <button type="button" id="mpaim-test-memory" class="button button-secondary">
                <?php _e('Test Memory System', 'memberpress-ai-assistant-memory'); ?>
            </button>
            
            <div class="mpai-memory-clear-actions">
                <button type="button" id="mpaim-clear-memory" class="button button-secondary">
                    <?php _e('Clear All Memories', 'memberpress-ai-assistant-memory'); ?>
                </button>
                
                <div class="mpai-memory-category-clear">
                    <button type="button" id="mpaim-clear-chat-memory" class="button button-secondary">
                        <?php _e('Clear Chat Memory Only', 'memberpress-ai-assistant-memory'); ?>
                    </button>
                    
                    <button type="button" id="mpaim-clear-system-memory" class="button button-secondary">
                        <?php _e('Clear System Memory Only', 'memberpress-ai-assistant-memory'); ?>
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<div class="mpai-settings-section mpai-memory-stats-section">
    <h3><?php _e('Memory System Statistics', 'memberpress-ai-assistant-memory'); ?></h3>
    
    <div id="mpai-memory-stats-loading">
        <?php _e('Loading memory statistics...', 'memberpress-ai-assistant-memory'); ?>
    </div>
    
    <div id="mpai-memory-stats" style="display:none;">
        <div class="mpai-memory-stat-row">
            <div class="mpai-memory-stat-label"><?php _e('Total Memories:', 'memberpress-ai-assistant-memory'); ?></div>
            <div class="mpai-memory-stat-value" id="mpai-memory-total">0</div>
        </div>
        
        <div class="mpai-memory-stat-row">
            <div class="mpai-memory-stat-label"><?php _e('Users with Memories:', 'memberpress-ai-assistant-memory'); ?></div>
            <div class="mpai-memory-stat-value" id="mpai-memory-users">0</div>
        </div>
        
        <h4><?php _e('Memory Types', 'memberpress-ai-assistant-memory'); ?></h4>
        <div id="mpai-memory-types"></div>
        
        <h4><?php _e('Latest Memories', 'memberpress-ai-assistant-memory'); ?></h4>
        <div id="mpai-latest-memories"></div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Test memory
    $('#mpaim-test-memory').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        $button.prop('disabled', true).text('<?php _e('Testing...', 'memberpress-ai-assistant-memory'); ?>');
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'mpaim_test_memory',
                nonce: '<?php echo wp_create_nonce('mpaim_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert('<?php _e('Memory test successful! A test memory was stored and retrieved.', 'memberpress-ai-assistant-memory'); ?>');
                    // Refresh stats
                    loadMemoryStats();
                } else {
                    alert('<?php _e('Memory test failed: ', 'memberpress-ai-assistant-memory'); ?>' + response.data);
                }
            },
            error: function() {
                alert('<?php _e('An error occurred while testing the memory system.', 'memberpress-ai-assistant-memory'); ?>');
            },
            complete: function() {
                $button.prop('disabled', false).text('<?php _e('Test Memory System', 'memberpress-ai-assistant-memory'); ?>');
            }
        });
    });
    
    // Helper function to clear memories
    function clearMemories($button, categories = null) {
        const buttonText = $button.text();
        $button.prop('disabled', true).text('<?php _e('Clearing...', 'memberpress-ai-assistant-memory'); ?>');
        
        const data = {
            action: 'mpaim_clear_memory',
            nonce: '<?php echo wp_create_nonce('mpaim_nonce'); ?>'
        };
        
        // Add categories if specified
        if (categories) {
            data.categories = categories;
        }
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    alert(response.data);
                    // Refresh stats
                    loadMemoryStats();
                } else {
                    alert('<?php _e('Memory clearing failed: ', 'memberpress-ai-assistant-memory'); ?>' + response.data);
                }
            },
            error: function() {
                alert('<?php _e('An error occurred while clearing memories.', 'memberpress-ai-assistant-memory'); ?>');
            },
            complete: function() {
                $button.prop('disabled', false).text(buttonText);
            }
        });
    }
    
    // Clear all memories
    $('#mpaim-clear-memory').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('<?php _e('Are you sure you want to clear ALL your memories? This cannot be undone.', 'memberpress-ai-assistant-memory'); ?>')) {
            return;
        }
        
        clearMemories($(this));
    });
    
    // Clear chat memory only
    $('#mpaim-clear-chat-memory').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('<?php _e('Are you sure you want to clear your chat memories? This cannot be undone.', 'memberpress-ai-assistant-memory'); ?>')) {
            return;
        }
        
        clearMemories($(this), 'chat');
    });
    
    // Clear system memory only
    $('#mpaim-clear-system-memory').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('<?php _e('Are you sure you want to clear your system memories? This cannot be undone.', 'memberpress-ai-assistant-memory'); ?>')) {
            return;
        }
        
        clearMemories($(this), ['system', 'memberpress', 'user_action', 'contextual']);
    });
    
    // Load memory stats
    function loadMemoryStats() {
        $('#mpai-memory-stats-loading').show();
        $('#mpai-memory-stats').hide();
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'mpaim_get_memory_stats',
                nonce: '<?php echo wp_create_nonce('mpaim_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    // Update stats
                    $('#mpai-memory-total').text(response.data.total_count);
                    $('#mpai-memory-users').text(response.data.user_counts ? response.data.user_counts.length : 0);
                    
                    // Update memory types and categories
                    var typesHtml = '';
                    var categoriesHtml = '';
                    
                    // Process type counts
                    if (response.data.type_counts && response.data.type_counts.length > 0) {
                        typesHtml = '<ul>';
                        $.each(response.data.type_counts, function(idx, type) {
                            typesHtml += '<li><strong>' + (type.type ? type.type.replace(/"/g, '') : 'Unknown') + ':</strong> ' + type.count + '</li>';
                        });
                        typesHtml += '</ul>';
                    } else {
                        typesHtml = '<p><?php _e('No memory types found.', 'memberpress-ai-assistant-memory'); ?></p>';
                    }
                    $('#mpai-memory-types').html(typesHtml);
                    
                    // Process category counts (new)
                    var categoryData = {};
                    
                    // Group memories by category
                    if (response.data.latest_memories) {
                        $.each(response.data.latest_memories, function(idx, memory) {
                            var category = 'chat'; // Default category
                            
                            if (memory.metadata && memory.metadata.category) {
                                category = memory.metadata.category;
                            } else if (memory.metadata && (memory.metadata.type === 'user_message' || memory.metadata.type === 'assistant_response')) {
                                category = 'chat';
                            } else {
                                category = 'system';
                            }
                            
                            if (!categoryData[category]) {
                                categoryData[category] = 0;
                            }
                            
                            categoryData[category]++;
                        });
                        
                        // Display category counts
                        if (Object.keys(categoryData).length > 0) {
                            categoriesHtml = '<h4><?php _e('Memory Categories', 'memberpress-ai-assistant-memory'); ?></h4><ul>';
                            
                            // Sort categories alphabetically
                            var sortedCategories = Object.keys(categoryData).sort();
                            
                            $.each(sortedCategories, function(idx, category) {
                                categoriesHtml += '<li><strong>' + category + ':</strong> ' + categoryData[category] + '</li>';
                            });
                            
                            categoriesHtml += '</ul>';
                            
                            // Add after types
                            $('#mpai-memory-types').after(categoriesHtml);
                        }
                    }
                    
                    // Update latest memories
                    var memoriesHtml = '';
                    if (response.data.latest_memories && response.data.latest_memories.length > 0) {
                        memoriesHtml = '<table class="widefat">';
                        memoriesHtml += '<thead><tr>';
                        memoriesHtml += '<th><?php _e('User', 'memberpress-ai-assistant-memory'); ?></th>';
                        memoriesHtml += '<th><?php _e('Category', 'memberpress-ai-assistant-memory'); ?></th>';
                        memoriesHtml += '<th><?php _e('Type', 'memberpress-ai-assistant-memory'); ?></th>';
                        memoriesHtml += '<th><?php _e('Content', 'memberpress-ai-assistant-memory'); ?></th>';
                        memoriesHtml += '<th><?php _e('Date', 'memberpress-ai-assistant-memory'); ?></th>';
                        memoriesHtml += '</tr></thead><tbody>';
                        
                        $.each(response.data.latest_memories, function(idx, memory) {
                            var type = memory.metadata && memory.metadata.type ? memory.metadata.type : 'Unknown';
                            var category = memory.metadata && memory.metadata.category ? memory.metadata.category : 
                              (type === 'user_message' || type === 'assistant_response' ? 'chat' : 'system');
                            var content = memory.content.length > 100 ? memory.content.substring(0, 100) + '...' : memory.content;
                            
                            memoriesHtml += '<tr>';
                            memoriesHtml += '<td>' + memory.user_id + '</td>';
                            memoriesHtml += '<td>' + category + '</td>';
                            memoriesHtml += '<td>' + type + '</td>';
                            memoriesHtml += '<td>' + content + '</td>';
                            memoriesHtml += '<td>' + memory.created_at + '</td>';
                            memoriesHtml += '</tr>';
                        });
                        
                        memoriesHtml += '</tbody></table>';
                    } else {
                        memoriesHtml = '<p><?php _e('No memories found.', 'memberpress-ai-assistant-memory'); ?></p>';
                    }
                    $('#mpai-latest-memories').html(memoriesHtml);
                    
                    // Show stats
                    $('#mpai-memory-stats-loading').hide();
                    $('#mpai-memory-stats').show();
                } else {
                    $('#mpai-memory-stats-loading').text('<?php _e('Error loading memory statistics.', 'memberpress-ai-assistant-memory'); ?>');
                }
            },
            error: function() {
                $('#mpai-memory-stats-loading').text('<?php _e('Error loading memory statistics.', 'memberpress-ai-assistant-memory'); ?>');
            }
        });
    }
    
    // Load stats on page load
    loadMemoryStats();
});
</script>

<style>
.mpai-memory-stats-section {
    margin-top: 30px;
}

.mpai-memory-stat-row {
    display: flex;
    margin-bottom: 10px;
}

.mpai-memory-stat-label {
    font-weight: bold;
    width: 200px;
}

.mpai-memory-stat-value {
    font-size: 16px;
}

#mpai-memory-types ul {
    margin-left: 20px;
}

#mpai-latest-memories {
    margin-top: 10px;
}

#mpai-latest-memories table {
    width: 100%;
    border-collapse: collapse;
}

#mpai-latest-memories th,
#mpai-latest-memories td {
    padding: 8px;
    text-align: left;
}

.mpai-memory-clear-actions {
    margin-top: 10px;
}

.mpai-memory-category-clear {
    margin-top: 10px;
}

.mpai-memory-category-clear button {
    margin-right: 10px;
}
</style>