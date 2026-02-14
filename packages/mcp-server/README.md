# MCP Server

Node.js/TypeScript MCP server that exposes Ableton Live functionality to AI assistants.

## Features

- **Archivist**: Offline .als file parsing
- **Operator**: Real-time Live control via ableton-js
- **Historian**: Version control integration (Python scripts)
- **Analyzer**: Rack/preset parsing (PHP analyzers)

## Available MCP Tools

### Offline Analysis (Archivist)
- `scan_project_files` - Find all .als files in a directory
- `inspect_als` - Parse an .als file to extract structure

### Live Control (Operator)
- `get_live_status` - Get transport status and tempo
- `list_live_tracks` - List all tracks in the current set
- `set_track_volume` - Control track volume

### Version Control (Historian)
- `get_version_history` - Show all versions with timestamps
- `scan_versions` - Find versioned .als files
- `compare_versions` - Diff two specific versions
- `get_latest_changes` - View most recent change report
- `get_change_report` - Get changes between specific versions
- `generate_timeline` - Create HTML timeline visualization

### Rack & Preset Analysis (Analyzer)
- `analyze_rack` - Extract device chains from .adg racks
- `analyze_drum_rack` - Parse drum rack pad assignments
- `analyze_preset` - Inspect device preset settings
- `scan_user_library` - Index all racks/presets in User Library
- `search_racks_by_device` - Find racks containing specific devices

## Development

```bash
# Install dependencies
npm install

# Build
npm run build

# Run MCP server
npm start

# Watch mode
npm run dev
```

## Configuration

Add to Claude Desktop config:

```json
{
  "mcpServers": {
    "ableton-live": {
      "command": "node",
      "args": ["/path/to/packages/mcp-server/dist/index.js"]
    }
  }
}
```
