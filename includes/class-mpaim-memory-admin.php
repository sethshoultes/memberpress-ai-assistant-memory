<?php
/**
 * MPAIM Memory Admin Class
 * 
 * Handles admin interface for memory settings
 *
 * @package MemberPress AI Assistant Memory
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class MPAIM_Memory_Admin {
    /**
     * Constructor
     */
    public function __construct() {
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // Add settings page
        add_action('admin_menu', array($this, 'add_settings_page'));
        
        // Add settings link to plugins page
        add_filter('plugin_action_links_' . MPAIM_PLUGIN_BASENAME, array($this, 'add_settings_link'));
        
        // Register AJAX handlers
        add_action('wp_ajax_mpaim_test_memory', array($this, 'ajax_test_memory'));
        add_action('wp_ajax_mpaim_clear_memory', array($this, 'ajax_clear_memory'));
        add_action('wp_ajax_mpaim_get_memory_stats', array($this, 'ajax_get_memory_stats'));
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('mpaim_options', 'mpaim_enable_memory', array(
            'type' => 'boolean',
            'default' => true,
            'sanitize_callback' => array($this, 'sanitize_boolean')
        ));
        
        register_setting('mpaim_options', 'mpaim_memory_database', array(
            'type' => 'string',
            'default' => 'database',
            'sanitize_callback' => 'sanitize_text_field'
        ));
        
        register_setting('mpaim_options', 'mpaim_max_memories', array(
            'type' => 'integer',
            'default' => 50,
            'sanitize_callback' => 'absint'
        ));
        
        register_setting('mpaim_options', 'mpaim_memory_retention_days', array(
            'type' => 'integer',
            'default' => 30,
            'sanitize_callback' => 'absint'
        ));
        
        // Register setting sections
        add_settings_section(
            'mpaim_general_settings',
            __('Memory Settings', 'memberpress-ai-assistant-memory'),
            array($this, 'render_settings_description'),
            'mpaim_options'
        );
        
        // Register setting fields
        add_settings_field(
            'mpaim_enable_memory',
            __('Enable Memory', 'memberpress-ai-assistant-memory'),
            array($this, 'render_enable_memory_field'),
            'mpaim_options',
            'mpaim_general_settings'
        );
        
        add_settings_field(
            'mpaim_memory_database',
            __('Memory Storage', 'memberpress-ai-assistant-memory'),
            array($this, 'render_memory_database_field'),
            'mpaim_options',
            'mpaim_general_settings'
        );
        
        add_settings_field(
            'mpaim_max_memories',
            __('Maximum Memories', 'memberpress-ai-assistant-memory'),
            array($this, 'render_max_memories_field'),
            'mpaim_options',
            'mpaim_general_settings'
        );
        
        add_settings_field(
            'mpaim_memory_retention_days',
            __('Memory Retention (Days)', 'memberpress-ai-assistant-memory'),
            array($this, 'render_memory_retention_field'),
            'mpaim_options',
            'mpaim_general_settings'
        );
    }
    
    /**
     * Sanitize boolean setting
     * 
     * @param mixed $value Setting value
     * @return bool Sanitized boolean
     */
    public function sanitize_boolean($value) {
        return (bool) $value;
    }
    
    /**
     * Render settings description
     */
    public function render_settings_description() {
        ?>
        <p><?php _e('Configure the memory system for MemberPress AI Assistant. This extension enhances the AI with the ability to remember and recall past conversations with semantic search.', 'memberpress-ai-assistant-memory'); ?></p>
        <?php
    }
    
    /**
     * Render enable memory field
     */
    public function render_enable_memory_field() {
        $enable_memory = get_option('mpaim_enable_memory', true);
        ?>
        <label>
            <input type="checkbox" name="mpaim_enable_memory" value="1" <?php checked($enable_memory); ?> />
            <?php _e('Enable memory system', 'memberpress-ai-assistant-memory'); ?>
        </label>
        <p class="description"><?php _e('When enabled, the AI assistant will remember past conversations and recall relevant information.', 'memberpress-ai-assistant-memory'); ?></p>
        <?php
    }
    
    /**
     * Render memory database field
     */
    public function render_memory_database_field() {
        $memory_database = get_option('mpaim_memory_database', 'database');
        ?>
        <select name="mpaim_memory_database">
            <option value="database" <?php selected($memory_database, 'database'); ?>><?php _e('WordPress Database', 'memberpress-ai-assistant-memory'); ?></option>
            <option value="mcp" <?php selected($memory_database, 'mcp'); ?>><?php _e('MCP Memory Server', 'memberpress-ai-assistant-memory'); ?></option>
        </select>
        <p class="description"><?php _e('Choose where to store memories. WordPress Database is simpler, while MCP Memory Server provides better semantic search but requires the MCP server.', 'memberpress-ai-assistant-memory'); ?></p>
        <?php
    }
    
    /**
     * Render max memories field
     */
    public function render_max_memories_field() {
        $max_memories = get_option('mpaim_max_memories', 50);
        ?>
        <input type="number" name="mpaim_max_memories" value="<?php echo esc_attr($max_memories); ?>" min="10" max="500" step="10" />
        <p class="description"><?php _e('Maximum number of memories to store per user. Older memories will be deleted when this limit is reached.', 'memberpress-ai-assistant-memory'); ?></p>
        <?php
    }
    
    /**
     * Render memory retention field
     */
    public function render_memory_retention_field() {
        $memory_retention_days = get_option('mpaim_memory_retention_days', 30);
        ?>
        <input type="number" name="mpaim_memory_retention_days" value="<?php echo esc_attr($memory_retention_days); ?>" min="1" max="365" step="1" />
        <p class="description"><?php _e('Number of days to retain memories. Memories older than this will be automatically deleted.', 'memberpress-ai-assistant-memory'); ?></p>
        <?php
    }
    
    /**
     * Add settings page
     */
    public function add_settings_page() {
        // This will be integrated with the MemberPress AI Assistant settings
    }
    
    /**
     * Add settings link to plugins page
     * 
     * @param array $links Existing links
     * @return array Modified links
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=memberpress-ai-assistant-settings&tab=memory') . '">' . __('Settings', 'memberpress-ai-assistant-memory') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
    
    /**
     * AJAX handler for testing memory
     */
    public function ajax_test_memory() {
        // Check nonce
        check_ajax_referer('mpaim_nonce', 'nonce');
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        // Get memory manager
        $memory_manager = MPAIM_Memory_Manager::get_instance();
        
        try {
            // Test memory storage
            $user_id = get_current_user_id();
            $test_content = 'This is a test memory created at ' . current_time('mysql');
            
            $result = $memory_manager->store_memory($user_id, $test_content, array(
                'type' => 'test',
                'timestamp' => time()
            ));
            
            if ($result) {
                // Try to recall the test memory
                $memories = $memory_manager->recall_memories($user_id, 'test memory', 1);
                
                wp_send_json_success(array(
                    'message' => 'Memory test successful',
                    'stored' => true,
                    'retrieved' => !empty($memories),
                    'memory' => !empty($memories) ? $memories[0] : null,
                ));
            } else {
                wp_send_json_error('Failed to store test memory');
            }
        } catch (Exception $e) {
            wp_send_json_error('Error testing memory: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX handler for clearing memory
     */
    public function ajax_clear_memory() {
        // Check nonce
        check_ajax_referer('mpaim_nonce', 'nonce');
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        // Get memory manager
        $memory_manager = MPAIM_Memory_Manager::get_instance();
        
        try {
            // Get the user ID
            $user_id = get_current_user_id();
            
            // Check if specific categories should be cleared
            $categories = null; // Default to clearing all categories
            if (isset($_POST['categories']) && !empty($_POST['categories'])) {
                if (is_string($_POST['categories'])) {
                    // Single category as string
                    $categories = sanitize_text_field($_POST['categories']);
                } elseif (is_array($_POST['categories'])) {
                    // Multiple categories as array
                    $categories = array_map('sanitize_text_field', $_POST['categories']);
                }
            }
            
            // Clear the memories
            $result = $memory_manager->clear_memories($user_id, $categories);
            
            if ($result) {
                if ($categories === null) {
                    wp_send_json_success('All memories cleared successfully');
                } else {
                    $category_names = is_array($categories) ? implode(', ', $categories) : $categories;
                    wp_send_json_success('Memories in categories [' . $category_names . '] cleared successfully');
                }
            } else {
                wp_send_json_error('Failed to clear memory');
            }
        } catch (Exception $e) {
            wp_send_json_error('Error clearing memory: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX handler for getting memory stats
     */
    public function ajax_get_memory_stats() {
        // Check nonce
        check_ajax_referer('mpaim_nonce', 'nonce');
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        global $wpdb;
        
        try {
            $table_name = $wpdb->prefix . 'mpaim_memory';
            
            // Get total count
            $total_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
            
            // Get count per user
            $user_counts = $wpdb->get_results("
                SELECT user_id, COUNT(*) as count
                FROM {$table_name}
                GROUP BY user_id
                ORDER BY count DESC
                LIMIT 10
            ", ARRAY_A);
            
            // Get counts by type
            $type_counts = $wpdb->get_results("
                SELECT 
                    JSON_EXTRACT(metadata, '$.type') as type,
                    COUNT(*) as count
                FROM {$table_name}
                GROUP BY type
                ORDER BY count DESC
            ", ARRAY_A);
            
            // Get latest memories
            $latest_memories = $wpdb->get_results("
                SELECT id, user_id, content, metadata, created_at
                FROM {$table_name}
                ORDER BY created_at DESC
                LIMIT 5
            ", ARRAY_A);
            
            // Format results
            foreach ($latest_memories as &$memory) {
                $memory['metadata'] = json_decode($memory['metadata'], true);
            }
            
            wp_send_json_success(array(
                'total_count' => $total_count,
                'user_counts' => $user_counts,
                'type_counts' => $type_counts,
                'latest_memories' => $latest_memories
            ));
        } catch (Exception $e) {
            wp_send_json_error('Error getting memory stats: ' . $e->getMessage());
        }
    }
}