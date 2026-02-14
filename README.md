# M4L-MCP: Ableton Live Ecosystem

**The Ableton Cookbook Project** - A comprehensive ecosystem for Ableton Live workflow analysis, version control, and collective knowledge sharing.

This project enables AI assistants to interact with Ableton Live through the Model Context Protocol (MCP), combining real-time control, offline analysis, version tracking, and rack parsing into a unified workflow intelligence system.

## Vision

Think **Spotify Wrapped meets Stack Overflow for music production**. A platform where producers share and discover Ableton workflows through:
- 🎛️ **Rack Analysis** - Parse and share device chains as "recipes"
- 📚 **Version Control** - Track project evolution with semantic versioning
- 🌍 **Collective Intelligence** - Query anonymized data from thousands of projects
- 🤖 **AI-Powered Insights** - Get mixing advice, device recommendations, and workflow patterns

## Project Structure

```
M4L-MCP/
├── .claude/               # Project context for AI assistants
│   ├── PROJECT_CONTEXT.md
│   ├── ARCHITECTURE.md
│   └── DEVELOPMENT.md
│
├── src/                   # MCP Server (Node.js/TypeScript)
│   ├── index.ts          # Main MCP server
│   ├── archivist.ts      # Offline .als parsing
│   ├── operator.ts       # Real-time Live control
│   ├── historian.ts      # Version control bridge
│   └── analyzer.ts       # Rack/preset analysis bridge
│
├── python-scripts/        # Version Control System
│   ├── ableton_version_manager.py
│   ├── ableton_visualizer.py
│   └── ableton_diff.py
│
├── analyzers/             # PHP Rack/Preset Parsers
│   ├── abletonRackAnalyzer/
│   ├── abletonDrumRackAnalyzer/
│   ├── abletonPresetAnalyzer/
│   └── abletonSessionAnalyzer/
│
└── cookbook-website/      # Laravel Web Platform (symlink)
    └── → /Volumes/BassDaddy/projects/abletonCookbook/abletonCookbookPHP
```

## Features

### 🗄️ Archivist (Offline Analysis)
- Scan directories for Ableton Live Set (.als) files
- Parse and inspect .als files without opening Live
- Extract track, device, and scene information from project files

### 🎛️ Operator (Live Control)
- Real-time connection to running Ableton Live instance
- Query transport status (tempo, play state, song time)
- List all tracks with names, colors, and IDs
- Control mixer parameters (volume, etc.)

### 📚 Historian (Version Control)
- Track version history of Ableton projects
- Compare any two versions to see changes
- Generate detailed change reports (track additions/removals, device changes)
- Create HTML timeline visualizations
- Automatic version scanning and metadata integration

### 🔬 Analyzer (Rack & Preset Analysis)
- Parse Ableton rack files (.adg) to extract device chains and macros
- Analyze drum racks with pad assignments and sample mappings
- Inspect device presets (.adv) to see parameter settings
- Scan User Library for all racks, presets, and workflows
- Search racks by device type (find all racks using specific plugins)
- Detect edition requirements (Intro/Standard/Suite)

## Prerequisites

### 1. Node.js
Ensure you have Node.js (v18 or higher) installed.

### 2. Ableton Live & `ableton-js` Remote Script
This server uses the `ableton-js` library to communicate with Live. For this to work, you must install the official MIDI Remote Script.

1.  Locate the `node_modules/ableton-js/midi-script` folder in this project (after running `npm install`).
2.  Copy the `AbletonJS` folder to your Ableton Live "MIDI Remote Scripts" directory:
    *   **macOS:** `/Applications/Ableton Live 11 Suite.app/Contents/App-Resources/MIDI Remote Scripts/`
    *   **Windows:** `C:\ProgramData\Ableton\Live 11 Suite\Resources\MIDI Remote Scripts\`
3.  Restart Ableton Live.
4.  Open **Preferences > Link/Tempo/MIDI**.
5.  Select **AbletonJS** as a Control Surface.

### 3. Python 3 (For Version Control)
The Historian module requires Python 3.7+ to analyze version history. The Python scripts are located at `python-scripts/` (configurable in `src/historian.ts`).

### 4. PHP (For Rack & Preset Analysis)
The Analyzer module requires PHP 7.4+ to parse Ableton rack and preset files. The PHP analyzer scripts are located in `analyzers/` directory.

## Installation

```bash
npm install
npm run build
```

## Usage

To start the server stdio transport:

```bash
npm start
```

## Development

Run in watch mode:

```bash
npm run dev
```

## MCP Configuration

Add this server to your Claude Desktop configuration (`~/Library/Application Support/Claude/claude_desktop_config.json`):

```json
{
  "mcpServers": {
    "ableton-live": {
      "command": "node",
      "args": ["/Volumes/DEV/M4L-MCP/dist/index.js"]
    }
  }
}
```

After adding the configuration, restart Claude Desktop.

## Available Tools

### Offline Analysis (Archivist)
- **scan_project_files** - Find all .als files in a directory
- **inspect_als** - Parse an .als file to extract structure

### Live Control (Operator)
- **get_live_status** - Get transport status and tempo
- **list_live_tracks** - List all tracks in the current set
- **set_track_volume** - Control track volume

### Version Control (Historian)
- **get_version_history** - Show all versions with timestamps
- **scan_versions** - Find versioned .als files (_X.Y.Z.als pattern)
- **compare_versions** - Diff two specific versions
- **get_latest_changes** - View most recent change report
- **get_change_report** - Get changes between specific versions
- **generate_timeline** - Create HTML timeline visualization

### Rack & Preset Analysis (Analyzer)
- **analyze_rack** - Extract device chains, macros, and metadata from .adg racks
- **analyze_drum_rack** - Parse drum rack pad assignments and samples
- **analyze_preset** - Inspect device preset (.adv) parameter settings
- **scan_user_library** - Index all racks, drum racks, and presets in User Library
- **search_racks_by_device** - Find racks containing specific devices (e.g., "Serum")

## Example Usage

Once configured, you can ask Claude:

**Live Control:**
- "What tracks are in my current Ableton set?"
- "Set the volume of the Bass track to 0.5"
- "What's the current tempo and play state?"

**Version Control:**
- "Show me the version history for my project"
- "What changed between version 0.1.0 and 0.1.2?"
- "Generate a timeline visualization for this project"

**Rack Analysis:**
- "Analyze this rack and show me the device chain"
- "What devices are in my Bass Daddy rack?"
- "Find all racks in my User Library that use Serum"
- "Scan my User Library and show me all my custom racks"
