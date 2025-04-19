<?php
/**
 * MPAIM Memory Manager Class
 * 
 * Manages the Model Context Protocol (MCP) memory integration
 *
 * @package MemberPress AI Assistant Memory
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class MPAIM_Memory_Manager {
    /**
     * Instance of this class (singleton)
     * 
     * @var MPAIM_Memory_Manager
     */
    private static $instance = null;
    
    /**
     * MCP memory server status
     * 
     * @var bool
     */
    private $mcp_available = false;
    
    /**
     * Process handle for MCP memory server
     * 
     * @var resource|null
     */
    private $mcp_process = null;
    
    /**
     * Memory database status
     * 
     * @var bool
     */
    private $db_available = false;
    
    /**
     * Get instance (singleton pattern)
     * 
     * @return MPAIM_Memory_Manager
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Create memory database tables
        $this->create_database_tables();
        
        // Check database availability
        $this->db_available = $this->check_database_availability();
        
        // Try to initialize MCP memory server
        $this->initialize_mcp_server();
    }
    
    /**
     * Initialize MCP memory server
     */
    private function initialize_mcp_server() {
        // Skip in CLI environment
        if (defined('WP_CLI') && WP_CLI) {
            error_log('MPAIM: MCP initialization skipped in CLI environment');
            return false;
        }
        
        // Check if MCP is enabled in settings
        $memory_database = get_option('mpaim_memory_database', 'database');
        if ($memory_database !== 'mcp') {
            error_log('MPAIM: MCP disabled in settings, using database storage');
            return false;
        }
        
        try {
            // Check if MCP server path is available in CLAUDE.md configuration
            $mcp_path = $this->get_mcp_path_from_config();
            
            if ($mcp_path) {
                error_log('MPAIM: Using MCP server path from configuration: ' . $mcp_path);
                $this->mcp_available = true;
                return true;
            }
            
            // Try to start MCP server using npx command if no config found
            $descriptors = [
                0 => ["pipe", "r"], // stdin
                1 => ["pipe", "w"], // stdout
                2 => ["pipe", "w"]  // stderr
            ];
            
            $command = 'npx -y @modelcontextprotocol/server-memory';
            
            // Start the process
            $this->mcp_process = proc_open($command, $descriptors, $pipes, MPAIM_PLUGIN_DIR);
            
            if (is_resource($this->mcp_process)) {
                // Set pipes to non-blocking mode
                stream_set_blocking($pipes[1], 0);
                stream_set_blocking($pipes[2], 0);
                
                // Wait a short time for server to start
                usleep(500000); // 0.5 seconds
                
                // Check if the process is still running
                $status = proc_get_status($this->mcp_process);
                
                if ($status && $status['running']) {
                    error_log('MPAIM: MCP memory server started successfully');
                    $this->mcp_available = true;
                    return true;
                } else {
                    // Read error output
                    $stderr = stream_get_contents($pipes[2]);
                    error_log('MPAIM: MCP memory server failed to start: ' . $stderr);
                    
                    // Close pipes and process
                    foreach ($pipes as $pipe) {
                        fclose($pipe);
                    }
                    proc_close($this->mcp_process);
                    $this->mcp_process = null;
                    
                    return false;
                }
            } else {
                error_log('MPAIM: Failed to start MCP memory server process');
                return false;
            }
        } catch (Exception $e) {
            error_log('MPAIM: Error starting MCP memory server: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get MCP server path from CLAUDE.md configuration
     * 
     * @return string|false MCP server path or false if not found
     */
    private function get_mcp_path_from_config() {
        // Check if we're in WordPress site
        if (!defined('ABSPATH')) {
            return false;
        }
        
        // First check global configuration (user's home directory)
        $home_dir = getenv('HOME') ?: getenv('USERPROFILE');
        if ($home_dir) {
            $global_config = $home_dir . '/.claude/CLAUDE.md';
            if (file_exists($global_config)) {
                $config_content = file_get_contents($global_config);
                if (preg_match('/memory-db:\s*([^\n]+)/', $config_content, $matches)) {
                    $mcp_path = trim($matches[1]);
                    if (file_exists($mcp_path)) {
                        error_log('MPAIM: Found MCP server in global config: ' . $mcp_path);
                        return $mcp_path;
                    }
                }
            }
        }
        
        // Then check local project configuration
        $plugin_dir = dirname(dirname(__FILE__));
        $local_config = $plugin_dir . '/CLAUDE.md';
        if (file_exists($local_config)) {
            $config_content = file_get_contents($local_config);
            if (preg_match('/memory-db:\s*([^\n]+)/', $config_content, $matches)) {
                $mcp_path = trim($matches[1]);
                if (file_exists($mcp_path)) {
                    error_log('MPAIM: Found MCP server in local config: ' . $mcp_path);
                    return $mcp_path;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Store memory in MCP server
     * 
     * @param int $user_id User ID
     * @param string $content Memory content
     * @param array $metadata Additional metadata
     * @return bool Whether the operation was successful
     */
    private function store_memory_in_mcp($user_id, $content, $metadata = []) {
        if (!$this->mcp_available || !is_resource($this->mcp_process)) {
            return false;
        }
        
        try {
            // Add user ID to metadata
            $metadata['user_id'] = $user_id;
            
            // Prepare the request
            $request = [
                'jsonrpc' => '2.0',
                'method' => 'store',
                'params' => [
                    'content' => $content,
                    'metadata' => $metadata
                ],
                'id' => uniqid('mem_')
            ];
            
            // Encode the request
            $request_json = json_encode($request) . "\n";
            
            // Send the request to stdin
            $pipes = [];
            $status = proc_get_status($this->mcp_process);
            
            if (!$status || !$status['running']) {
                error_log('MPAIM: MCP server not running');
                return false;
            }
            
            // Get pipes
            $descriptors = proc_get_status($this->mcp_process)['pipes'];
            if (empty($descriptors)) {
                error_log('MPAIM: MCP server pipes not available');
                return false;
            }
            
            // Send the request
            fwrite($descriptors[0], $request_json);
            fflush($descriptors[0]);
            
            // Wait for response (with timeout)
            $response = '';
            $start_time = microtime(true);
            $timeout = 2; // 2 seconds timeout
            
            while (microtime(true) - $start_time < $timeout) {
                $response .= fgets($descriptors[1]);
                
                if (substr($response, -1) === "\n") {
                    break;
                }
                
                usleep(10000); // 10ms sleep to avoid high CPU usage
            }
            
            if (empty($response)) {
                error_log('MPAIM: No response from MCP server');
                return false;
            }
            
            // Parse the response
            $response_data = json_decode($response, true);
            
            if (isset($response_data['result']) && $response_data['result']) {
                return true;
            } else {
                error_log('MPAIM: MCP store failed: ' . json_encode($response_data));
                return false;
            }
        } catch (Exception $e) {
            error_log('MPAIM: Error storing memory in MCP: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Recall memory from MCP server
     * 
     * @param int $user_id User ID
     * @param string $query Query string for semantic search
     * @param int $limit Maximum number of items to retrieve
     * @param array $categories Categories to include
     * @return array|false Retrieved memory items or false on failure
     */
    private function recall_memory_from_mcp($user_id, $query, $limit = 5, $categories = null) {
        if (!$this->mcp_available || !is_resource($this->mcp_process)) {
            return false;
        }
        
        try {
            // If categories aren't specified, use all valid categories
            if ($categories === null) {
                $categories = $this->valid_categories;
            }
            
            // Build the filter
            $filter = [
                'metadata.user_id' => $user_id,
            ];
            
            // Only add category filter if not querying all categories
            if ($categories !== $this->valid_categories && !empty($categories)) {
                // MCP uses an "or" filter for arrays
                $filter_categories = [];
                foreach ($categories as $category) {
                    $filter_categories[] = ['metadata.category' => $category];
                }
                
                // Add the category filter
                if (!empty($filter_categories)) {
                    $filter['$or'] = $filter_categories;
                }
            }
            
            // Prepare the request
            $request = [
                'jsonrpc' => '2.0',
                'method' => 'search',
                'params' => [
                    'query' => $query,
                    'filter' => $filter,
                    'limit' => $limit
                ],
                'id' => uniqid('mem_')
            ];
            
            // Encode the request
            $request_json = json_encode($request) . "\n";
            
            // Log the request for debugging
            error_log('MPAIM: MCP search request: ' . $request_json);
            
            // Send the request to stdin
            $pipes = [];
            $status = proc_get_status($this->mcp_process);
            
            if (!$status || !$status['running']) {
                error_log('MPAIM: MCP server not running');
                return false;
            }
            
            // Get pipes
            $descriptors = proc_get_status($this->mcp_process)['pipes'];
            if (empty($descriptors)) {
                error_log('MPAIM: MCP server pipes not available');
                return false;
            }
            
            // Send the request
            fwrite($descriptors[0], $request_json);
            fflush($descriptors[0]);
            
            // Wait for response (with timeout)
            $response = '';
            $start_time = microtime(true);
            $timeout = 2; // 2 seconds timeout
            
            while (microtime(true) - $start_time < $timeout) {
                $response .= fgets($descriptors[1]);
                
                if (substr($response, -1) === "\n") {
                    break;
                }
                
                usleep(10000); // 10ms sleep to avoid high CPU usage
            }
            
            if (empty($response)) {
                error_log('MPAIM: No response from MCP server');
                return false;
            }
            
            // Parse the response
            $response_data = json_decode($response, true);
            
            if (isset($response_data['result']) && is_array($response_data['result'])) {
                error_log('MPAIM: MCP search returned ' . count($response_data['result']) . ' results');
                return $response_data['result'];
            } else {
                error_log('MPAIM: MCP search failed: ' . json_encode($response_data));
                return false;
            }
        } catch (Exception $e) {
            error_log('MPAIM: Error recalling memory from MCP: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check database availability
     * 
     * @return bool Whether database is available
     */
    private function check_database_availability() {
        global $wpdb;
        
        try {
            $table_name = $wpdb->prefix . 'mpaim_memory';
            $result = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
            
            return ($result === $table_name);
        } catch (Exception $e) {
            error_log('MPAIM: Error checking database: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create necessary database tables
     */
    private function create_database_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mpaim_memory';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            content longtext NOT NULL,
            metadata longtext,
            embedding longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        error_log('MPAIM: Database tables created');
    }
    
    /**
     * Store memory in database
     * 
     * @param int $user_id User ID
     * @param string $content Memory content
     * @param array $metadata Additional metadata
     * @return bool Whether the operation was successful
     */
    private function store_memory_in_db($user_id, $content, $metadata = []) {
        global $wpdb;
        
        if (!$this->db_available) {
            return false;
        }
        
        try {
            $table_name = $wpdb->prefix . 'mpaim_memory';
            
            // Serialize metadata
            $metadata_json = wp_json_encode($metadata);
            
            // Insert memory into database
            $result = $wpdb->insert(
                $table_name,
                array(
                    'user_id' => $user_id,
                    'content' => $content,
                    'metadata' => $metadata_json,
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%s', '%s', '%s')
            );
            
            if ($result !== false) {
                return true;
            } else {
                error_log('MPAIM: Database insert error: ' . $wpdb->last_error);
                return false;
            }
        } catch (Exception $e) {
            error_log('MPAIM: Error storing memory in database: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Recall memory from database
     * 
     * @param int $user_id User ID
     * @param string $query Query string for search
     * @param int $limit Maximum number of items to retrieve
     * @param array $categories Categories to include
     * @return array|false Retrieved memory items or false on failure
     */
    private function recall_memory_from_db($user_id, $query, $limit = 5, $categories = null) {
        global $wpdb;
        
        if (!$this->db_available) {
            return false;
        }
        
        try {
            $table_name = $wpdb->prefix . 'mpaim_memory';
            
            // If categories aren't specified, use all valid categories
            if ($categories === null) {
                $categories = $this->valid_categories;
            }
            
            // Build where clause
            $where_clauses = ["user_id = %d", "content LIKE %s"];
            $where_values = [$user_id, '%' . $wpdb->esc_like($query) . '%'];
            
            // Add category filter if not querying all categories
            if ($categories !== $this->valid_categories && !empty($categories)) {
                $category_placeholders = [];
                foreach ($categories as $category) {
                    $category_placeholders[] = "JSON_EXTRACT(metadata, '$.category') = %s";
                    $where_values[] = '"' . $category . '"';
                }
                
                if (!empty($category_placeholders)) {
                    $where_clauses[] = '(' . implode(' OR ', $category_placeholders) . ')';
                }
            }
            
            // Add limit to where values
            $where_values[] = $limit;
            
            // Build the full query
            $sql = $wpdb->prepare(
                "SELECT id, user_id, content, metadata, created_at 
                FROM {$table_name}
                WHERE " . implode(' AND ', $where_clauses) . "
                ORDER BY created_at DESC
                LIMIT %d",
                ...$where_values
            );
            
            error_log('MPAIM: Database query: ' . $sql);
            
            $results = $wpdb->get_results($sql, ARRAY_A);
            
            if (is_array($results)) {
                // Format results to match MCP format
                $formatted_results = array();
                
                foreach ($results as $row) {
                    $metadata = json_decode($row['metadata'], true);
                    
                    // Ensure category exists in metadata
                    if (!isset($metadata['category'])) {
                        $metadata['category'] = 'chat'; // Default for backward compatibility
                    }
                    
                    $formatted_results[] = array(
                        'id' => $row['id'],
                        'content' => $row['content'],
                        'metadata' => $metadata,
                        'timestamp' => strtotime($row['created_at'])
                    );
                }
                
                error_log('MPAIM: Database search returned ' . count($formatted_results) . ' results');
                return $formatted_results;
            } else {
                error_log('MPAIM: Database query error: ' . $wpdb->last_error);
                return false;
            }
        } catch (Exception $e) {
            error_log('MPAIM: Error recalling memory from database: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Valid memory categories
     * 
     * @var array
     */
    private $valid_categories = [
        'chat',         // User-assistant conversations
        'system',       // System events and changes
        'memberpress',  // MemberPress-specific events
        'user_action',  // User actions in the admin
        'contextual'    // Site-specific contextual information
    ];
    
    /**
     * Store memory
     * 
     * @param int $user_id User ID
     * @param string $content Memory content
     * @param array $metadata Additional metadata
     * @return bool Whether the operation was successful
     */
    public function store_memory($user_id, $content, $metadata = []) {
        // Check user ID
        if (empty($user_id)) {
            error_log('MPAIM: No user ID provided for memory storage');
            return false;
        }
        
        // Check content
        if (empty($content)) {
            error_log('MPAIM: No content provided for memory storage');
            return false;
        }
        
        // Ensure metadata has required fields
        if (!isset($metadata['type'])) {
            $metadata['type'] = 'user_message'; // Default type
        }
        
        if (!isset($metadata['timestamp'])) {
            $metadata['timestamp'] = time();
        }
        
        // Add category to metadata if not present
        if (!isset($metadata['category'])) {
            // Default to chat for user_message and assistant_response types
            if ($metadata['type'] === 'user_message' || $metadata['type'] === 'assistant_response') {
                $metadata['category'] = 'chat';
            } else {
                $metadata['category'] = 'system'; // Default category
            }
        }
        
        // Validate category
        if (!in_array($metadata['category'], $this->valid_categories)) {
            $metadata['category'] = 'system'; // Default to system if invalid
        }
        
        // Store in configured storage system
        $memory_database = get_option('mpaim_memory_database', 'database');
        
        if ($memory_database === 'mcp' && $this->mcp_available) {
            // Try MCP first
            $mcp_result = $this->store_memory_in_mcp($user_id, $content, $metadata);
            
            if ($mcp_result) {
                return true;
            }
            
            // Fall back to database if MCP fails
            error_log('MPAIM: MCP storage failed, falling back to database');
        }
        
        // Store in database
        return $this->store_memory_in_db($user_id, $content, $metadata);
    }
    
    /**
     * Recall memories
     * 
     * @param int $user_id User ID
     * @param string $query Query string for search
     * @param int $limit Maximum number of items to retrieve
     * @param array $categories Categories to include (default: all categories)
     * @return array Retrieved memory items
     */
    public function recall_memories($user_id, $query, $limit = 5, $categories = null) {
        // Check user ID
        if (empty($user_id)) {
            error_log('MPAIM: No user ID provided for memory recall');
            return array();
        }
        
        // Check query
        if (empty($query)) {
            error_log('MPAIM: No query provided for memory recall');
            return array();
        }
        
        // If no categories specified, use all valid categories
        if ($categories === null) {
            $categories = $this->valid_categories;
        }
        
        // If single category provided as string, convert to array
        if (is_string($categories)) {
            $categories = [$categories];
        }
        
        // Filter to valid categories
        $categories = array_intersect($categories, $this->valid_categories);
        
        // If no valid categories, use all
        if (empty($categories)) {
            $categories = $this->valid_categories;
        }
        
        // Log what we're searching for
        error_log('MPAIM: Recalling memories for user ' . $user_id . ' with query "' . $query . '", limit ' . $limit . ', categories: ' . implode(', ', $categories));
        
        // Recall from configured storage system
        $memory_database = get_option('mpaim_memory_database', 'database');
        
        if ($memory_database === 'mcp' && $this->mcp_available) {
            // Try MCP first
            $mcp_results = $this->recall_memory_from_mcp($user_id, $query, $limit, $categories);
            
            if ($mcp_results !== false) {
                return $mcp_results;
            }
            
            // Fall back to database if MCP fails
            error_log('MPAIM: MCP recall failed, falling back to database');
        }
        
        // Recall from database
        $db_results = $this->recall_memory_from_db($user_id, $query, $limit, $categories);
        
        if ($db_results !== false) {
            return $db_results;
        }
        
        // Return empty array if all methods fail
        return array();
    }
    
    /**
     * Clear memories for a user
     * 
     * @param int $user_id User ID
     * @param array|string $categories Specific categories to clear (null for all)
     * @return bool Whether the operation was successful
     */
    public function clear_memories($user_id, $categories = null) {
        global $wpdb;
        
        if (empty($user_id)) {
            error_log('MPAIM: No user ID provided for memory clearing');
            return false;
        }
        
        try {
            // If no categories specified, clear all
            if ($categories === null) {
                // Clear all memories from database
                if ($this->db_available) {
                    $table_name = $wpdb->prefix . 'mpaim_memory';
                    
                    $result = $wpdb->delete(
                        $table_name,
                        array('user_id' => $user_id),
                        array('%d')
                    );
                    
                    if ($result === false) {
                        error_log('MPAIM: Database delete error: ' . $wpdb->last_error);
                        return false;
                    }
                    
                    error_log('MPAIM: Cleared all memories for user ' . $user_id);
                }
                
                // Clear from MCP (TODO: implement when MCP supports clearing)
                
                return true;
            } else {
                // Convert single category to array
                if (is_string($categories)) {
                    $categories = [$categories];
                }
                
                // Filter to valid categories
                $categories = array_intersect($categories, $this->valid_categories);
                
                // If no valid categories, return false
                if (empty($categories)) {
                    error_log('MPAIM: No valid categories provided for memory clearing');
                    return false;
                }
                
                // Clear specific categories from database
                if ($this->db_available) {
                    $table_name = $wpdb->prefix . 'mpaim_memory';
                    $success = true;
                    
                    foreach ($categories as $category) {
                        // Build the query to delete memories with the specified category
                        $sql = $wpdb->prepare(
                            "DELETE FROM {$table_name} 
                            WHERE user_id = %d 
                            AND JSON_EXTRACT(metadata, '$.category') = %s",
                            $user_id,
                            '"' . $category . '"'
                        );
                        
                        $result = $wpdb->query($sql);
                        
                        if ($result === false) {
                            error_log('MPAIM: Database delete error for category ' . $category . ': ' . $wpdb->last_error);
                            $success = false;
                        } else {
                            error_log('MPAIM: Cleared ' . $result . ' memories in category ' . $category . ' for user ' . $user_id);
                        }
                    }
                    
                    return $success;
                }
                
                // Clear from MCP (TODO: implement when MCP supports clearing)
                
                return true;
            }
        } catch (Exception $e) {
            error_log('MPAIM: Error clearing memories: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Clean up resources
     */
    public function __destruct() {
        // Close MCP process if open
        if ($this->mcp_process && is_resource($this->mcp_process)) {
            proc_terminate($this->mcp_process);
            proc_close($this->mcp_process);
            error_log('MPAIM: MCP memory server process terminated');
        }
    }
}