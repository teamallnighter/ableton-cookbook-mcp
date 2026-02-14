# 🎛️ The Ableton Cookbook - MCP Server

[![CI/CD](https://github.com/teamallnighter/ableton-cookbook-mcp/actions/workflows/ci.yml/badge.svg)](https://github.com/teamallnighter/ableton-cookbook-mcp/actions)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Node.js Version](https://img.shields.io/badge/node-%3E%3D18.0.0-brightgreen)](https://nodejs.org/)
[![Documentation](https://img.shields.io/badge/docs-GitHub%20Pages-blue)](https://teamallnighter.github.io/ableton-cookbook-mcp/)

**Share and discover Ableton Live production recipes** - A Model Context Protocol (MCP) server that bridges AI assistants with Ableton Live, enabling version control, rack analysis, and real-time control for music production workflows.

🌐 **[View Documentation](https://teamallnighter.github.io/ableton-cookbook-mcp/)** | 📖 **[Contributing Guide](CONTRIBUTING.md)** | 🗺️ **[Vision & Roadmap](.claude/VISION_AND_ROADMAP.md)**

This project enables AI assistants to interact with Ableton Live through the Model Context Protocol (MCP), combining real-time control, offline analysis, version tracking, and rack parsing into a unified workflow intelligence system.

## Vision

Think **Spotify Wrapped meets Stack Overflow for music production**. A platform where producers share and discover Ableton workflows through:
- 🎛️ **Rack Analysis** - Parse and share device chains as "recipes"
- 📚 **Version Control** - Track project evolution with semantic versioning
- 🌍 **Collective Intelligence** - Query anonymized data from thousands of projects
- 🤖 **AI-Powered Insights** - Get mixing advice, device recommendations, and workflow patterns

## Project Structure

**Mono-repo with npm workspaces:**

```
ableton-cookbook-mcp/
├── packages/
│   ├── mcp-server/            # TypeScript MCP Server 🟢 ACTIVE
│   │   ├── src/
│   │   │   ├── index.ts       # Main MCP server (16 tools)
│   │   │   ├── archivist.ts   # Offline .als parsing
│   │   │   ├── operator.ts    # Real-time Live control
│   │   │   ├── historian.ts   # Version control bridge
│   │   │   └── analyzer.ts    # Rack/preset analysis bridge
│   │   └── dist/              # Compiled JavaScript
│   │
│   ├── python-scripts/        # Version Control System 🟢 ACTIVE
│   │   ├── ableton_version_manager.py
│   │   ├── ableton_visualizer.py
│   │   └── ableton_diff.py
│   │
│   └── php-analyzers/         # Rack/Preset Parsers 🟢 ACTIVE
│       ├── abletonRackAnalyzer/
│       ├── abletonDrumRackAnalyzer/
│       ├── abletonPresetAnalyzer/
│       └── abletonSessionAnalyzer/
│
├── .claude/                   # AI context & planning docs
│   ├── PROJECT_CONTEXT.md
│   ├── ARCHITECTURE.md
│   ├── VISION_AND_ROADMAP.md
│   └── WEEK_1_IMPLEMENTATION.md
│
├── docs/                      # GitHub Pages documentation
├── .github/
│   ├── workflows/             # CI/CD automation
│   └── ISSUE_TEMPLATE/        # Bug/feature templates
│
└── cookbook-website/          # Laravel Web Platform (symlink)
    └── → (External Laravel project)
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
   Quick Start

### Installation

```bash
# Clone the repository
git clone https://github.com/teamallnighter/ableton-cookbook-mcp.git
cd ableton-cookbook-mcp

# Install and build MCP server
cd packages/mcp-server
npm install
npm run build
```

### Configure Claude Desktop

Add to `~/Library/Application Support/Claude/claude_desktop_config.json`:

```json
{
  "mcpServers": {
    "ableton": {
      "command": "node",
      "args": ["/ABSOLUTE/PATH/TO/ableton-cookbook-mcp/packages/mcp-server/dist/index.js"]
    }
  }
}
```

**Restart Claude Desktop** and you'll see 16 Ableton tools available! 🎉

### Development

```bash
cd packages/mcp-server

# Watch mode (auto-rebuild on changes)
npm run watch

# Lint and format
npm run lint
npm run format

# Manual build
npm run build
```
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


## Contributing

We welcome contributions! Whether you're a producer, developer, or both - your input helps make this tool better for the music production community.

**Ways to contribute:**
- 🐛 [Report bugs](https://github.com/teamallnighter/ableton-cookbook-mcp/issues/new?template=bug_report.yml)
- ✨ [Request features](https://github.com/teamallnighter/ableton-cookbook-mcp/issues/new?template=feature_request.yml)
- 📚 Improve documentation
- 🔧 Submit pull requests
- 💬 [Join discussions](https://github.com/teamallnighter/ableton-cookbook-mcp/discussions)

See [CONTRIBUTING.md](CONTRIBUTING.md) for detailed guidelines.

## Documentation

- 📖 **[Full Documentation](https://teamallnighter.github.io/ableton-cookbook-mcp/)** - Setup guides and examples
- 🏗️ **[Architecture](.claude/ARCHITECTURE.md)** - Technical design and data models
- 🗺️ **[Vision & Roadmap](.claude/VISION_AND_ROADMAP.md)** - Product vision and future plans
- 🔧 **[Development Guide](.claude/DEVELOPMENT.md)** - Setup, debugging, and testing
- 📋 **[Project Context](.claude/PROJECT_CONTEXT.md)** - Complete project overview

## Roadmap

- [x] **Phase 1: Proof of Concept** - MCP server with 16 tools ✅
- [ ] **Phase 2: Easy Setup** - Desktop installer for non-technical users
- [ ] **Phase 3: Community** - Web platform for sharing workflow recipes
- [ ] **Phase 4: Discovery** - Search, recommendations, and integrations

See [VISION_AND_ROADMAP.md](.claude/VISION_AND_ROADMAP.md) for detailed plans.

## License

MIT License - see [LICENSE](LICENSE) file for details.

## Acknowledgments

Built with:
- [Model Context Protocol](https://modelcontextprotocol.io/) by Anthropic
- [ableton-js](https://github.com/leolabs/ableton-js) by Leo Bernard
- Love for music production 🎵

---

**Made with 🎵 by [Team All Nighter](https://github.com/teamallnighter)**  
*For producers who code at 3am*
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
