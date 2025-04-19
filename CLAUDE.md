# MemberPress AI Assistant Memory Extension - Claude Configuration

## Plugin Commands

- **Run Unit Tests**: `composer test`
- **Check PHP Compatibility**: `composer phpcs`
- **Check JavaScript Syntax**: `npm run lint`

## MCP Memory Configuration

This plugin works with Model Context Protocol (MCP) memory servers. When developing with Claude Code, the following MCP server configuration is available:

```
memory-db: /Users/sethshoultes/mcp-memory-db
```

## Default Settings

- Memory System: Enabled
- Storage System: MCP Memory Server (for better semantic retrieval)
- Maximum Memories: 50 per user
- Memory Retention: 30 days

## Development Notes

- When modifying JavaScript files, run `npm run build` to compile assets
- Store sensitive credentials in wp-config.php, never in the plugin code
- Include descriptive comments for complex functionality
- Follow WordPress coding standards

## Testing

When testing memory functionality:
1. Enable WP_DEBUG in wp-config.php
2. Check memory storage/retrieval in the database or MCP server
3. Test with different user roles to ensure permissions work correctly