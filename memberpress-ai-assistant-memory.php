<?php
/**
 * Plugin Name: MemberPress AI Assistant - Memory Extension
 * Plugin URI: https://memberpress.com/
 * Description: Memory extension for MemberPress AI Assistant that adds enhanced semantic memory capabilities using the Model Context Protocol (MCP).
 * Version: 1.0.0
 * Author: MemberPress
 * Author URI: https://memberpress.com
 * Text Domain: memberpress-ai-assistant-memory
 * Domain Path: /languages
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('MPAIM_VERSION', '1.0.0');
define('MPAIM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MPAIM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MPAIM_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Class MemberPress_AI_Assistant_Memory
 *
 * Main plugin class responsible for initializing the memory extension
 */
class MemberPress_AI_Assistant_Memory {
    /**
     * Instance of this class.
     *
     * @var object
     */
    protected static $instance = null;

    /**
     * Memory Manager instance
     * 
     * @var MPAIM_Memory_Manager
     */
    private $memory_manager = null;

    /**
     * Initialize the plugin.
     */
    private function __construct() {
        // Load dependencies
        $this->load_dependencies();

        // Check if base plugin is active
        add_action('admin_init', array($this, 'check_base_plugin'));

        // Initialize memory system - after core plugin is fully loaded
        add_action('plugins_loaded', array($this, 'init_memory_system'), 20);

        // Register hooks for integration with base plugin
        add_action('wp_ajax_mpai_process_chat', array($this, 'intercept_chat_process'), 5);
        
        // Register our own AJAX handlers for memory management
        add_action('wp_ajax_mpaim_get_memory_stats', array($this, 'ajax_get_memory_stats'));
        
        // Add settings tab to MemberPress AI Assistant settings
        add_filter('mpai_settings_tabs', array($this, 'add_memory_settings_tab'), 10, 1);
        add_action('mpai_settings_tab_memory', array($this, 'render_memory_settings_tab'));

        // Register settings
        add_action('admin_init', array($this, 'register_settings'));

        // Register admin assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // Register AJAX handlers
        add_action('wp_ajax_mpaim_test_memory', array($this, 'ajax_test_memory'));
        add_action('wp_ajax_mpaim_clear_memory', array($this, 'ajax_clear_memory'));
    }

    /**
     * Return an instance of this class.
     *
     * @return object A single instance of this class.
     */
    public static function get_instance() {
        if (null == self::$instance) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }

    /**
     * Load required dependencies
     */
    private function load_dependencies() {
        // Load the Memory Manager class
        require_once MPAIM_PLUGIN_DIR . 'includes/class-mpaim-memory-manager.php';
        
        // Load the Memory Integration class
        require_once MPAIM_PLUGIN_DIR . 'includes/class-mpaim-memory-integration.php';
        
        // Load the Memory Admin class
        require_once MPAIM_PLUGIN_DIR . 'includes/class-mpaim-memory-admin.php';
    }

    /**
     * Check if base plugin is active and display notice if not
     */
    public function check_base_plugin() {
        if (!class_exists('MemberPress_AI_Assistant')) {
            add_action('admin_notices', array($this, 'base_plugin_missing_notice'));
        }
    }

    /**
     * Display notice if base plugin is missing
     */
    public function base_plugin_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php _e('MemberPress AI Assistant - Memory Extension requires the MemberPress AI Assistant plugin to be installed and activated.', 'memberpress-ai-assistant-memory'); ?></p>
        </div>
        <?php
    }

    /**
     * Initialize memory system
     */
    public function init_memory_system() {
        // Check if base plugin is active
        if (!class_exists('MemberPress_AI_Assistant')) {
            return;
        }

        // Initialize the memory manager
        $this->memory_manager = MPAIM_Memory_Manager::get_instance();

        // Log memory system initialization
        error_log('MPAIM: Memory system initialized');
    }

    /**
     * Intercept chat process AJAX request
     * 
     * This hooks into the main AJAX handler for chat processing at priority 5
     * which allows us to capture the message before processing, and modify the response afterward
     */
    public function intercept_chat_process() {
        // Only continue if this is a valid chat request
        if (!isset($_POST['message']) || !isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mpai_chat_nonce')) {
            return;
        }
        
        // Log that we've intercepted the chat process
        error_log('MPAIM: Intercepting chat process AJAX request');
        
        // Check if memory manager is available
        if (!$this->memory_manager || !get_option('mpaim_enable_memory', true)) {
            return;
        }
        
        // Get the user ID and message
        $user_id = get_current_user_id();
        $message = sanitize_text_field($_POST['message']);
        
        // Store the user message in memory
        try {
            $this->memory_manager->store_memory($user_id, $message, array(
                'type' => 'user_message',
                'timestamp' => time()
            ));
            
            error_log('MPAIM: User message stored in memory');
            
            // Add hook to capture the response after processing
            add_filter('wp_send_json_success', array($this, 'capture_chat_response'), 10, 2);
            
            // Add only chat memories to the prompt (by modifying the message)
            $memories = $this->memory_manager->recall_memories($user_id, $message, 5, 'chat');
            
            if (!empty($memories)) {
                // Format memories as context
                $memory_text = "I'm providing you with some relevant information from our past conversations to help with this request:\n\n";
                
                foreach ($memories as $memory) {
                    $type = isset($memory['metadata']['type']) ? $memory['metadata']['type'] : 'memory';
                    $date = isset($memory['metadata']['timestamp']) ? 
                        date('Y-m-d', $memory['metadata']['timestamp']) : 
                        date('Y-m-d', $memory['timestamp'] ?? time());
                    
                    $memory_text .= "- [{$date}] ({$type}): {$memory['content']}\n";
                }
                
                $memory_text .= "\nNow, here's my question: " . $message;
                
                // Replace the original message with our augmented one
                $_POST['message'] = $memory_text;
                
                error_log('MPAIM: Added ' . count($memories) . ' chat memories to chat context');
            }
        } catch (Exception $e) {
            error_log('MPAIM: Error in intercept_chat_process: ' . $e->getMessage());
        }
    }
    
    /**
     * Capture and store the chat response 
     * 
     * @param array $response The response data
     * @param mixed $data Additional data
     * @return array The unmodified response
     */
    public function capture_chat_response($response, $data = null) {
        try {
            // Only process responses that contain chat content
            if (!is_array($response) || !isset($response['data']) || !isset($response['data']['response'])) {
                return $response;
            }
            
            $user_id = get_current_user_id();
            $response_content = $response['data']['response'];
            
            // Store the assistant's response in memory
            if ($this->memory_manager && get_option('mpaim_enable_memory', true)) {
                $this->memory_manager->store_memory($user_id, $response_content, array(
                    'type' => 'assistant_response',
                    'timestamp' => time()
                ));
                
                error_log('MPAIM: Assistant response stored in memory');
            }
        } catch (Exception $e) {
            error_log('MPAIM: Error in capture_chat_response: ' . $e->getMessage());
        }
        
        // Always return the original response
        return $response;
    }
    
    /**
     * AJAX handler for getting memory statistics
     */
    public function ajax_get_memory_stats() {
        // Check nonce
        check_ajax_referer('mpaim_nonce', 'nonce');
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        // Check if memory manager is available
        if (!$this->memory_manager) {
            wp_send_json_error('Memory manager not available');
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

    /**
     * Add memory settings tab
     *
     * @param array $tabs Existing tabs
     * @return array Modified tabs
     */
    public function add_memory_settings_tab($tabs) {
        $tabs['memory'] = __('Memory', 'memberpress-ai-assistant-memory');
        return $tabs;
    }

    /**
     * Render memory settings tab
     */
    public function render_memory_settings_tab() {
        // Include the settings tab template
        include MPAIM_PLUGIN_DIR . 'templates/memory-settings.php';
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting('mpai_options', 'mpaim_enable_memory', array(
            'type' => 'boolean',
            'default' => true,
        ));

        register_setting('mpai_options', 'mpaim_memory_model', array(
            'type' => 'string',
            'default' => 'all-MiniLM-L6-v2',
        ));

        register_setting('mpai_options', 'mpaim_max_memory_items', array(
            'type' => 'integer',
            'default' => 50,
        ));

        register_setting('mpai_options', 'mpaim_memory_database', array(
            'type' => 'string',
            'default' => 'database',
        ));
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on settings page
        if (strpos($hook, 'memberpress-ai-assistant-settings') === false) {
            return;
        }

        wp_enqueue_style(
            'mpaim-admin-css',
            MPAIM_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            MPAIM_VERSION
        );

        wp_enqueue_script(
            'mpaim-admin-js',
            MPAIM_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            MPAIM_VERSION,
            true
        );

        wp_localize_script(
            'mpaim-admin-js',
            'mpaim_data',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mpaim_nonce'),
            )
        );
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

        // Check if memory manager is available
        if (!$this->memory_manager) {
            wp_send_json_error('Memory manager not available');
        }

        try {
            // Test memory storage
            $user_id = get_current_user_id();
            $test_content = 'This is a test memory created at ' . current_time('mysql');
            
            $result = $this->memory_manager->store_memory($user_id, $test_content, array(
                'type' => 'test',
                'timestamp' => current_time('mysql')
            ));

            if ($result) {
                // Try to recall the test memory
                $memories = $this->memory_manager->recall_memories($user_id, 'test memory', 1);
                
                wp_send_json_success(array(
                    'message' => 'Memory test successful. Memory stored and retrieved.',
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

        // Check if memory manager is available
        if (!$this->memory_manager) {
            wp_send_json_error('Memory manager not available');
        }

        try {
            // Clear all memories for current user
            $user_id = get_current_user_id();
            $result = $this->memory_manager->clear_memories($user_id);

            if ($result) {
                wp_send_json_success('Memory cleared successfully');
            } else {
                wp_send_json_error('Failed to clear memory');
            }
        } catch (Exception $e) {
            wp_send_json_error('Error clearing memory: ' . $e->getMessage());
        }
    }
}

// Initialize the plugin
add_action('plugins_loaded', array('MemberPress_AI_Assistant_Memory', 'get_instance'));