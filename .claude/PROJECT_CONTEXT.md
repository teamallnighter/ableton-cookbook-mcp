# Ableton Live MCP & Cookbook Project

## Project Overview

This is a comprehensive ecosystem for Ableton Live workflow analysis, version control, and collective knowledge sharing. The project enables AI assistants to interact with Ableton Live through the Model Context Protocol (MCP), analyze user workflows, track project evolution, and build a community knowledge base of production techniques.

## Vision: The Ableton Cookbook

A collaborative platform where producers share and discover Ableton workflows through:
- **Rack Analysis**: Parse and share device chains as "recipes"
- **Version Control**: Track project evolution with semantic versioning
- **Collective Intelligence**: Query anonymized data from thousands of projects
- **AI-Powered Insights**: Get mixing advice, device recommendations, and workflow patterns

Think: **Spotify Wrapped meets Stack Overflow for music production**

## Project Structure

```
M4L-MCP/
├── .claude/                    # Project context for AI
│   ├── PROJECT_CONTEXT.md     # This file
│   ├── ARCHITECTURE.md        # Technical architecture
│   └── DEVELOPMENT.md         # Development guide
│
├── mcp-server/                # Node.js MCP Server
│   ├── src/
│   │   ├── index.ts          # Main MCP server
│   │   ├── archivist.ts      # Offline .als file parsing
│   │   ├── operator.ts       # Real-time Live control
│   │   ├── historian.ts      # Version control bridge (Python)
│   │   └── analyzer.ts       # Rack/preset analysis (PHP)
│   ├── package.json
│   └── README.md
│
├── python-scripts/            # Version Control System
│   ├── ableton_version_manager.py
│   ├── ableton_visualizer.py
│   ├── ableton_diff.py
│   ├── watch_project.py
│   └── README.md
│
├── analyzers/                 # PHP Rack/Preset Analyzers
│   ├── abletonRackAnalyzer/
│   │   └── abletonRackAnalyzer-V7.php
│   ├── abletonDrumRackAnalyzer/
│   ├── abletonPresetAnalyzer/
│   └── abletonSessionAnalyzer/
│
└── cookbook-website/          # Laravel Web Platform (future)
    └── (Laravel application)
```

## Core Components

### 1. MCP Server (Node.js + TypeScript)

**Purpose**: Central hub that exposes Ableton functionality to AI assistants

**Modules**:
- **Archivist**: Offline analysis of .als project files (XML parsing, track/device extraction)
- **Operator**: Real-time control of running Ableton Live instance via ableton-js
- **Historian**: Version control wrapper around Python scripts (liveGit integration)
- **Analyzer**: PHP bridge for rack/preset analysis (recipe extraction)

**Integration**: Configured in Claude Desktop as an MCP server

### 2. Version Control System (Python)

**Purpose**: Git-like versioning for Ableton projects with automatic change tracking

**Features**:
- Semantic versioning (project_X.Y.Z.als)
- Automatic change detection (track additions/removals, device changes)
- Timeline visualization
- Detailed diff reports
- File watcher for continuous monitoring

**Files**: Located at `/Volumes/DEV/liveGit`

### 3. Rack Analyzers (PHP)

**Purpose**: Parse Ableton rack (.adg) and preset (.adv) files to extract device chains

**Capabilities**:
- Extract device chains from racks (the "recipes")
- Parse macro mappings
- Detect nested racks and parallel processing
- Identify edition requirements (Intro/Standard/Suite)
- Extract automation mappings and sample references

**Note**: Racks are bundled workflows - this is THE recipe format

### 4. Data Goldmines (Discovered)

**User Library** (`/Volumes/ABLETON/User Library`):
- **Presets/**: All saved racks (.adg) and device presets (.adv)
- **Clips/**: Saved MIDI/audio clips
- **Defaults/**: Default device settings

**.asd Files** (Warp Analysis):
- Auto-created when previewing ANY sample
- Contains: detected tempo, warp markers, transient detection, loop points
- **Timestamp = last use date** (usage tracking!)

**.xmp Files** (Browser Metadata):
- Ableton's sample indexing system
- Contains: file paths, categories/tags, browser metadata
- Example tags: `Sounds|Bass|Heavy Bass`, `Sounds|Bass|Neuro Bass`

## Key Technologies

- **TypeScript**: MCP server implementation
- **Node.js**: Runtime for MCP server
- **Python 3.7+**: Version control system (liveGit)
- **PHP 7.4+**: Rack/preset analyzers
- **ableton-js**: MIDI Remote Script for Live control
- **fast-xml-parser**: XML parsing for .als/.adg files
- **MCP Protocol**: Communication layer for AI assistants

## Current Status (Feb 2026)

### ✅ Completed
- MCP server with 4 modules (Archivist, Operator, Historian, Analyzer)
- Version control system with automatic change tracking
- PHP rack analyzers (V7 with caching, error recovery, edition detection)
- Integration tested with Claude Desktop
- Python timeline visualizations
- Real-time Ableton control tested (80-track session)

### 🚧 In Progress
- Project consolidation and structure
- Comprehensive documentation
- Laravel cookbook website

### 📋 Planned
- Central MCP server (collective knowledge database)
- Sample usage analytics (.asd file analysis)
- Browser metadata extraction (.xmp files)
- Rack recommendation system
- Community recipe sharing platform

## Example Workflows

### For Producers
```
1. Work in Ableton
2. Save milestone: project_0.1.0.als
3. Python watcher detects new version
4. MCP analyzes changes automatically
5. Claude provides insights about workflow evolution
6. Producer asks: "What changed since last version?"
```

### For Recipe Sharing
```
1. Create custom rack in Ableton
2. Save to User Library
3. MCP analyzes rack structure
4. Upload to Cookbook (anonymized)
5. Other users query: "Show me popular bass racks"
6. Claude returns racks with similar workflows
```

### For Learning
```
1. Open someone's project
2. Ask Claude: "How did they process the vocals?"
3. Claude analyzes .als file
4. Returns detailed device chain analysis
5. Compare with collective knowledge
6. Get recommendations based on 10,000+ tracks
```

## Important Paths

**Production Environment**:
- MCP Server: `/Volumes/DEV/M4L-MCP/`
- Python Scripts: `/Volumes/DEV/liveGit/`
- Ableton Projects: `/Volumes/ABLETON/Projects/`
- User Library: `/Volumes/ABLETON/User Library/`
- Sample Library: `/Volumes/ABLETON/UR_SAMPLE_PACK/`

**Configuration**:
- Claude Desktop: `~/Library/Application Support/Claude/claude_desktop_config.json`
- MCP Config Example:
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

## Known Issues & Considerations

1. **In-Memory XPath Cache**: PHP analyzer uses per-process cache (not shared across workers)
2. **File Size Limits**: Default 100MB max for .als/.adg files (configurable via env vars)
3. **Port Conflicts**: ableton-js uses UDP port 39031 (only one instance at a time)
4. **Edition Detection**: Some Max4Live devices may not be properly categorized

## Environment Variables

```bash
# PHP Analyzer
ABLETON_MAX_FILE_SIZE=104857600        # 100MB
ABLETON_MAX_MEMORY_USAGE=536870912     # 512MB
ABLETON_ANALYSIS_TIMEOUT=300           # 5 minutes
ABLETON_STREAM_PARSING_THRESHOLD=10485760  # 10MB

# Python Scripts
# (configured in individual scripts)
```

## Development Philosophy

**Mudpie Workflow**: Effects ARE sound design. Embrace iterative processing, parallel chains, and workflow evolution. This tool documents the messy creative process, not the sanitized tutorial version.

**Data-Driven**: Extract actual workflow patterns from real projects, not theoretical best practices.

**Community-First**: Build tools that help producers learn from each other through shared workflows and collective intelligence.

## Context for AI Assistants

When working on this project:
1. The MCP server is the central integration point
2. Each module (Archivist/Operator/Historian/Analyzer) serves a specific purpose
3. Racks (.adg files) are the primary "recipe" format
4. Version control tracks creative evolution over time
5. The end goal is a community knowledge base of Ableton workflows

Always consider: How does this feature help producers share and learn workflows?
