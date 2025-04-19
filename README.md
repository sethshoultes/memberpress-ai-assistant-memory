# MemberPress AI Assistant - Memory Extension

This extension adds enhanced memory capabilities to the MemberPress AI Assistant plugin, allowing the AI to remember past conversations and provide more contextually relevant responses.

## Features

- **Semantic Memory**: The AI can recall previous conversations based on semantic similarity, not just keywords.
- **Persistent Storage**: Memories are stored across sessions, allowing for continuity in conversations.
- **Configurable Retention**: Control how long memories are stored and how many are kept per user.
- **Multiple Storage Options**: Store memories in the WordPress database or use the Model Context Protocol (MCP) memory server for advanced semantic retrieval.
- **Admin Interface**: View memory statistics and manage the memory system through an intuitive admin interface.

## Requirements

- MemberPress AI Assistant plugin (version 1.5.0 or higher)
- WordPress 5.6 or higher
- PHP 7.4 or higher

## Installation

1. Upload the `memberpress-ai-assistant-memory` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Configure the memory settings in the MemberPress AI Assistant settings page, under the "Memory" tab.

## Usage

Once activated and configured, the memory system works automatically in the background. The AI will remember previous conversations and recall them when relevant to the current conversation.

### Storage Options

- **WordPress Database**: Simple storage in the WordPress database. This is the default option and requires no additional setup.
- **MCP Memory Server**: Advanced semantic storage using the Model Context Protocol memory server. This option requires running the MCP memory server separately.

### Running MCP Memory Server

To use the MCP Memory Server option, you have two options:

### Option 1: Using Claude Code (Recommended)

The plugin will automatically detect if you're using Claude Code with a configured MCP memory server. It reads the configuration from either:

1. Global Claude configuration (`~/.claude/CLAUDE.md`)
2. Local project configuration (`/path/to/plugin/CLAUDE.md`)

If you're using Claude Code for development, the plugin will use your configured MCP memory server automatically.

### Option 2: Manual Server Startup

You can manually run the MCP memory server by executing:

```bash
npx -y @modelcontextprotocol/server-memory
```

The plugin will automatically try to start the server if it can't find a configured MCP memory server.

## Configuration

Navigate to the MemberPress AI Assistant settings page and select the "Memory" tab to configure the memory system:

- **Enable Memory System**: Turn the memory system on or off.
- **Storage System**: Choose between WordPress Database and MCP Memory Server.
- **Maximum Memories**: Set the maximum number of memories to store per user.
- **Memory Retention**: Set how many days to retain memories before they are automatically deleted.

## Technical Details

### Memory Storage

Memories are stored with the following structure:

```
{
  "id": "unique-id",
  "content": "The actual text content of the memory",
  "metadata": {
    "type": "user_message|assistant_response|session_summary|etc",
    "timestamp": "2023-01-01T12:00:00Z",
    "user_id": 123,
    "additional_metadata": "Any other relevant metadata"
  }
}
```

### Memory Recall

When processing a user message, the system:

1. Takes the current user message.
2. Uses either keyword matching (WordPress Database) or semantic similarity (MCP Memory Server) to find relevant past memories.
3. Retrieves the most relevant memories and adds them to the context sent to the AI.
4. The AI can then incorporate these memories into its response, creating a more coherent conversation.

## Development and Extension

This extension is designed to be extensible. Developers can:

- Add custom memory types by extending the metadata structure.
- Implement additional storage backends by extending the memory manager class.
- Add hooks to store additional information as memories.

## License

This plugin is licensed under the GPL v2 or later.

## Credits

- Developed by MemberPress Team
- Uses the Model Context Protocol (MCP) for semantic memory functionality