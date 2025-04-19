<?php
/**
 * MPAIM Memory Integration Class
 * 
 * Handles integration with the base MemberPress AI Assistant plugin
 *
 * @package MemberPress AI Assistant Memory
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class MPAIM_Memory_Integration {
    /**
     * Memory manager instance
     * 
     * @var MPAIM_Memory_Manager
     */
    private $memory_manager;
    
    /**
     * Constructor
     * 
     * @param MPAIM_Memory_Manager $memory_manager Memory manager instance
     */
    public function __construct($memory_manager) {
        $this->memory_manager = $memory_manager;
        
        // Register hooks
        $this->register_hooks();
    }
    
    /**
     * Register integration hooks
     */
    private function register_hooks() {
        // Hook into chat processing
        add_action('mpai_after_process_message', array($this, 'store_chat_in_memory'), 10, 3);
        
        // Hook into context generation
        add_filter('mpai_chat_context', array($this, 'add_memory_to_context'), 10, 2);
        
        // Hook into agent system
        add_filter('mpai_agent_context', array($this, 'add_memory_to_agent_context'), 10, 2);
    }
    
    /**
     * Store chat message and response in memory
     * 
     * @param string $message User message
     * @param array $response AI response
     * @param int $user_id User ID
     */
    public function store_chat_in_memory($message, $response, $user_id) {
        // Check if memory is enabled
        if (!get_option('mpaim_enable_memory', true)) {
            return;
        }
        
        // Parse response to get the actual text content
        $response_text = '';
        if (is_array($response)) {
            if (isset($response['message'])) {
                $response_text = $response['message'];
            } elseif (isset($response['response'])) {
                $response_text = $response['response'];
            } elseif (isset($response['text'])) {
                $response_text = $response['text'];
            } else {
                $response_text = wp_json_encode($response);
            }
        } elseif (is_string($response)) {
            $response_text = $response;
        } else {
            $response_text = wp_json_encode($response);
        }
        
        // Store user message
        $this->memory_manager->store_memory($user_id, $message, array(
            'type' => 'user_message',
            'timestamp' => current_time('mysql')
        ));
        
        // Store AI response
        $this->memory_manager->store_memory($user_id, $response_text, array(
            'type' => 'assistant_response',
            'timestamp' => current_time('mysql')
        ));
        
        error_log('MPAIM: Chat stored in memory for user ' . $user_id);
    }
    
    /**
     * Add relevant memories to chat context
     * 
     * @param array $context Current chat context
     * @param int $user_id User ID
     * @return array Updated chat context
     */
    public function add_memory_to_context($context, $user_id) {
        // Check if memory is enabled
        if (!get_option('mpaim_enable_memory', true)) {
            return $context;
        }
        
        // Get the latest user message if available
        $latest_message = '';
        if (isset($context['messages']) && is_array($context['messages'])) {
            foreach (array_reverse($context['messages']) as $message) {
                if (isset($message['role']) && $message['role'] === 'user' && !empty($message['content'])) {
                    $latest_message = $message['content'];
                    break;
                }
            }
        }
        
        // No message to query against
        if (empty($latest_message)) {
            return $context;
        }
        
        // Recall relevant memories
        $memories = $this->memory_manager->recall_memories($user_id, $latest_message, 5);
        
        if (empty($memories)) {
            return $context;
        }
        
        // Format memories for context
        $memory_text = "Relevant past memories:\n\n";
        
        foreach ($memories as $memory) {
            $type = isset($memory['metadata']['type']) ? $memory['metadata']['type'] : 'memory';
            $date = isset($memory['metadata']['timestamp']) ? 
                date('Y-m-d', $memory['metadata']['timestamp']) : 
                date('Y-m-d', $memory['timestamp'] ?? time());
            
            $memory_text .= "- [{$date}] ({$type}): {$memory['content']}\n";
        }
        
        // Add memories to system message
        if (isset($context['messages']) && is_array($context['messages'])) {
            foreach ($context['messages'] as &$message) {
                if (isset($message['role']) && $message['role'] === 'system') {
                    $message['content'] = $message['content'] . "\n\n" . $memory_text;
                    break;
                }
            }
        }
        
        // If no system message exists, add one
        if (!isset($context['system_message'])) {
            $context['system_message'] = $memory_text;
        } else {
            $context['system_message'] .= "\n\n" . $memory_text;
        }
        
        error_log('MPAIM: Added ' . count($memories) . ' memories to context for user ' . $user_id);
        
        return $context;
    }
    
    /**
     * Add memory to agent context
     * 
     * @param array $context Current agent context
     * @param int $user_id User ID
     * @return array Updated agent context
     */
    public function add_memory_to_agent_context($context, $user_id) {
        // Check if memory is enabled
        if (!get_option('mpaim_enable_memory', true)) {
            return $context;
        }
        
        // Get the latest message if available
        $latest_message = '';
        if (isset($context['original_message'])) {
            $latest_message = $context['original_message'];
        }
        
        // No message to query against
        if (empty($latest_message)) {
            return $context;
        }
        
        // Recall relevant memories
        $memories = $this->memory_manager->recall_memories($user_id, $latest_message, 5);
        
        if (empty($memories)) {
            return $context;
        }
        
        // Add memories to context
        if (!isset($context['memory'])) {
            $context['memory'] = array();
        }
        
        $context['memory'] = array_merge($context['memory'], $memories);
        
        error_log('MPAIM: Added ' . count($memories) . ' memories to agent context for user ' . $user_id);
        
        return $context;
    }
}