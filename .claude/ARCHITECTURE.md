# Technical Architecture

## System Overview

The Ableton Live MCP ecosystem is a multi-language, multi-process system that bridges Ableton Live with AI assistants through the Model Context Protocol. It combines Node.js, Python, PHP, and MIDI Remote Scripts to provide comprehensive project analysis and control.

## Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                         Claude Desktop                          │
│                      (AI Assistant Client)                      │
└────────────────────────┬────────────────────────────────────────┘
                         │ MCP Protocol (stdio)
                         │
┌────────────────────────▼────────────────────────────────────────┐
│                     MCP Server (Node.js)                        │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │                     index.ts (Main)                      │  │
│  │  - Tool Registration (16 MCP tools)                     │  │
│  │  - Request Routing                                      │  │
│  │  - Error Handling                                       │  │
│  └─────┬──────────┬──────────┬──────────┬─────────────────┘  │
│        │          │          │          │                      │
│  ┌─────▼───┐ ┌───▼────┐ ┌──▼──────┐ ┌─▼────────┐           │
│  │Archivist│ │Operator│ │Historian│ │ Analyzer │           │
│  │         │ │        │ │         │ │          │           │
│  │ .als    │ │ Live   │ │ Python  │ │   PHP    │           │
│  │ Parsing │ │ Control│ │ Bridge  │ │  Bridge  │           │
│  └─────┬───┘ └───┬────┘ └──┬──────┘ └─┬────────┘           │
└────────┼─────────┼─────────┼───────────┼────────────────────┘
         │         │         │           │
         │         │         │           │
    ┌────▼────┐    │    ┌────▼─────┐    │
    │  .als   │    │    │ Python   │    │
    │  Files  │    │    │ Scripts  │    │
    │(Offline)│    │    │(liveGit) │    │
    └─────────┘    │    └──────────┘    │
                   │                     │
            ┌──────▼───────┐      ┌─────▼─────┐
            │ AbletonJS    │      │    PHP    │
            │ MIDI Remote  │      │ Analyzers │
            │   Script     │      │  (V7)     │
            └──────┬───────┘      └─────┬─────┘
                   │                     │
            ┌──────▼───────┐      ┌─────▼─────┐
            │ Ableton Live │      │.adg/.adv  │
            │  (Running)   │      │  Files    │
            └──────────────┘      └───────────┘
```

## Module Architecture

### 1. Archivist Module (TypeScript)

**Purpose**: Offline analysis of .als project files

**Technical Details**:
- **Input**: Gzipped XML files (.als)
- **Processing**: 
  1. Gunzip decompression
  2. XML parsing with fast-xml-parser
  3. JSON transformation
  4. Device/plugin/sample extraction
- **Output**: Structured JSON with tracks, devices, automation

**Key Methods**:
```typescript
scanProjectFiles(directory: string): Promise<FileInfo[]>
inspectAlsFile(filePath: string): Promise<ParsedALS>
simplifyLiveSet(data: ParsedALS): SimplifiedData
```

**Dependencies**:
- `zlib` (Node.js built-in)
- `fast-xml-parser` (npm)

**Performance**:
- 5-10MB .als files parse in ~200ms
- 50MB+ files: 1-2 seconds
- Memory: ~2x file size during parsing

### 2. Operator Module (TypeScript)

**Purpose**: Real-time control of running Ableton Live instance

**Technical Details**:
- **Transport**: UDP on port 39031
- **Protocol**: AbletonJS Remote Script (Python MIDI)
- **Latency**: 50-100ms typical
- **Scope**: Live Set, tracks, devices, mixer parameters

**Key Methods**:
```typescript
getStatus(): Promise<Status>
listTracks(): Promise<Track[]>
setMixer(params: MixerParams): Promise<void>
```

**Dependencies**:
- `ableton-js` (npm)
- AbletonJS MIDI Remote Script (installed in Live)

**Limitations**:
- Must have Live open and Remote Script active
- Single connection (port can't be shared)
- Limited to exposed API (not all Live features accessible)

**Connection Flow**:
1. User opens Ableton Live
2. Loads AbletonJS MIDI Remote Script
3. Script binds to UDP port 39031
4. Operator connects via `new Ableton()` from ableton-js
5. Maintains persistent connection

### 3. Historian Module (TypeScript → Python)

**Purpose**: Version control bridge to liveGit Python scripts

**Technical Details**:
- **Architecture**: Child process spawning
- **IPC**: JSON over stdout/stderr
- **Error Handling**: stderr capture + exit code checking
- **Working Directory**: `/Volumes/DEV/liveGit`

**Key Methods**:
```typescript
scanVersions(projectPath: string): Promise<Version[]>
compareVersions(file1: string, file2: string): Promise<Diff>
generateTimeline(outputPath: string): Promise<string>
```

**Process Flow**:
```
1. Historian method called
2. Spawn Python3 process: child_process.spawn()
3. Pass arguments via CLI
4. Python script executes
5. JSON written to stdout
6. TypeScript parses JSON
7. Return to caller
```

**Python Scripts**:
- `ableton_version_manager.py` - Version tracking
- `ableton_visualizer.py` - Timeline HTML generation
- `ableton_diff.py` - Change detection

**Error Handling**:
- Exit code != 0 → throw error with stderr
- JSON parse failure → throw with raw output
- Process timeout → kill after 30 seconds

### 4. Analyzer Module (TypeScript → PHP)

**Purpose**: Rack/preset analysis bridge to PHP parsers

**Technical Details**:
- **Architecture**: Temp wrapper script generation + child process
- **IPC**: JSON over stdout
- **Working Directory**: `AbletonAnalyzers/abletonRackAnalyzer/`
- **Caching**: In-memory XPath cache per process

**Key Methods**:
```typescript
analyzeRack(filePath: string): Promise<RackAnalysis>
analyzeDrumRack(filePath: string): Promise<DrumRackAnalysis>
scanUserLibrary(path: string): Promise<LibraryItem[]>
searchRacksByDevice(device: string): Promise<RackMatch[]>
```

**Process Flow**:
```
1. Analyzer method called
2. Create temp PHP wrapper script:
   <?php
   require_once 'abletonRackAnalyzer-V7.php';
   $analyzer = new AbletonRackAnalyzer();
   echo json_encode($analyzer->analyzeRack($filePath));
3. Spawn PHP process: child_process.spawn('php', [tempFile])
4. PHP executes, outputs JSON
5. TypeScript parses JSON
6. Cleanup temp file
7. Return to caller
```

**PHP Analyzer (V7) Features**:
- Stream-based parsing for large files (10MB+ threshold)
- XPath caching for repeated queries
- Edition detection (Intro/Standard/Suite)
- Nested rack recursion (max depth: 10)
- Device chain mapping
- Macro parameter extraction
- Error recovery with partial results

**Performance Considerations**:
- Small racks (<1MB): ~100-200ms
- Large racks (5-10MB): ~500ms-1s
- Very large (50MB+): stream parsing, 2-5s
- Memory: Configurable via `ABLETON_MAX_MEMORY_USAGE`

## Data Models

### ALS File Structure (Ableton Project)
```xml
<Ableton>
  <LiveSet>
    <Tracks>
      <AudioTrack>
        <Name>Audio 1</Name>
        <DeviceChain>
          <Devices>
            <PluginDevice>...</PluginDevice>
          </Devices>
        </DeviceChain>
        <Mixer>
          <Volume>0.85</Volume>
          <Pan>0.0</Pan>
          <Solo>false</Solo>
          <Mute>false</Mute>
        </Mixer>
      </AudioTrack>
    </Tracks>
  </LiveSet>
</Ableton>
```

### ADG File Structure (Rack)
```xml
<AudioUnitPreset>
  <Branches>
    <AudioBranchMixerDevice>
      <Branches>
        <Branch>
          <DeviceChain>
            <Devices>
              <PluginDevice>
                <PluginDesc>
                  <VstPluginInfo>
                    <PlugName>FabFilter Pro-Q 3</PlugName>
                  </VstPluginInfo>
                </PluginDesc>
              </PluginDevice>
            </Devices>
          </DeviceChain>
        </Branch>
      </Branches>
    </AudioBranchMixerDevice>
  </Branches>
  <Pointee>
    <Macros>
      <Macro>
        <Target>
          <PointeeId>12345</PointeeId>
        </Target>
      </Macro>
    </Macros>
  </Pointee>
</AudioUnitPreset>
```

### Version Metadata (Python JSON)
```json
{
  "versions": [
    {
      "version": "0.1.0",
      "file": "myproject_0.1.0.als",
      "timestamp": "2025-02-15T14:30:00",
      "changes": {
        "tracks_added": ["Bass", "Drums"],
        "tracks_removed": [],
        "devices_changed": {
          "Bass": ["Added: EQ Eight"]
        }
      }
    }
  ]
}
```

## Communication Protocols

### MCP Protocol (Claude ↔ Server)

**Transport**: stdio (stdin/stdout)

**Message Format**:
```json
{
  "jsonrpc": "2.0",
  "method": "tools/call",
  "params": {
    "name": "analyze_rack",
    "arguments": {
      "filePath": "/path/to/rack.adg"
    }
  },
  "id": 1
}
```

**Response Format**:
```json
{
  "jsonrpc": "2.0",
  "result": {
    "content": [
      {
        "type": "text",
        "text": "Analysis complete:\n..."
      }
    ]
  },
  "id": 1
}
```

### AbletonJS Protocol (Operator ↔ Live)

**Transport**: UDP on port 39031

**Example Commands**:
```javascript
// Get track info
await song.get("tracks")

// Set volume
await track.set("volume", 0.85)

// Trigger clip
await clip.fire()
```

**Message Flow**:
1. ableton-js sends JSON-RPC-like message over UDP
2. MIDI Remote Script receives, translates to Live API
3. Live executes action
4. Results sent back over UDP
5. ableton-js resolves Promise

## File System Layout

### MCP Server Files
```
mcp-server/
├── src/
│   ├── index.ts              # Main entry point (400 lines)
│   ├── archivist.ts          # Offline parsing (300 lines)
│   ├── operator.ts           # Live control (200 lines)
│   ├── historian.ts          # Python bridge (250 lines)
│   └── analyzer.ts           # PHP bridge (350 lines)
├── dist/                     # Compiled JavaScript
├── node_modules/
├── package.json
├── tsconfig.json
└── README.md
```

### Python Scripts
```
python-scripts/
├── ableton_version_manager.py    # Version tracking (500 lines)
├── ableton_visualizer.py         # Timeline HTML (300 lines)
├── ableton_diff.py               # Change detection (400 lines)
├── watch_project.py              # File watcher (200 lines)
└── README.md
```

### PHP Analyzers
```
analyzers/
├── abletonRackAnalyzer/
│   ├── abletonRackAnalyzer-V7.php        # Main analyzer (1200 lines)
│   └── test_analyzer.php
├── abletonDrumRackAnalyzer/
│   └── abletonDrumRackAnalyzer.php       # Drum rack parser
├── abletonPresetAnalyzer/
│   └── abletonRackAnalyzer-v3.php        # Preset parser
└── abletonSessionAnalyzer/
    └── (session metadata)
```

## Deployment Architecture

### Development Environment
```
Local Machine (macOS)
├── Claude Desktop (Electron app)
├── Node.js v18+
├── Python 3.7+
├── PHP 7.4+
└── Ableton Live 11+ with AbletonJS Remote Script
```

### Production Vision (Central Server)
```
Cloud Server
├── MCP Server (public endpoint)
├── PostgreSQL (collective knowledge DB)
├── Redis (caching layer)
├── Laravel Website (cookbook.ableton.community)
└── Background Workers (analysis queue)
```

## Security Considerations

### Current (Local)
- All execution is local to user machine
- No network exposure
- File access limited to user permissions

### Future (Centralized)
- Anonymize project data before upload
- Strip personal metadata (file paths, device serials)
- Rate limiting on API endpoints
- Authentication for uploads
- Public read for queries

## Performance Characteristics

### Bottlenecks
1. **Large file parsing**: 50MB+ .als files take 2-5s
2. **PHP process spawning**: ~50ms overhead per call
3. **Python process spawning**: ~100ms overhead per call
4. **AbletonJS latency**: 50-100ms per command

### Optimization Strategies
1. **Caching**: Store parsed results in memory
2. **Batch operations**: Group multiple file reads
3. **Stream parsing**: Use streams for large files (PHP V7)
4. **Connection pooling**: Reuse Python/PHP processes (future)
5. **Incremental parsing**: Only parse changed sections (future)

## Error Handling Strategy

### Graceful Degradation
- If Operator fails → Archivist still works (offline analysis)
- If Historian fails → Core MCP tools still functional
- If Analyzer fails → Return partial results with error notes

### Error Propagation
```
PHP/Python Error
→ Captured in stderr/exit code
→ Wrapped in TypeScript Error object
→ Formatted as MCP error response
→ Displayed in Claude Desktop
```

### Recovery Mechanisms
- Retry logic for transient failures (network, file locks)
- Fallback to simpler parsing methods
- Log errors for debugging without crashing server

## Testing Strategy

### Unit Tests (Future)
- Mock child_process for Historian/Analyzer
- Mock ableton-js for Operator
- Test XML parsing edge cases for Archivist

### Integration Tests (Current)
- Manual testing with real .als/.adg files
- Live Ableton testing with 80-track sessions
- End-to-end MCP testing via Claude Desktop

### Test Coverage Goals
- Critical paths: 80%+
- Error handling: 90%+
- Edge cases: 70%+

## Monitoring & Observability

### Logging
- Currently: stderr for errors
- Future: Structured logging (JSON)
- Log levels: DEBUG, INFO, WARN, ERROR

### Metrics (Future)
- Tool call frequency
- Parse times by file size
- Error rates by module
- Cache hit rates

## Scalability Considerations

### Current Limits
- Single user (local MCP server)
- Single Ableton instance
- No concurrency control

### Future Scale
- Multi-tenant architecture
- Job queue for analysis tasks
- Read replicas for database
- CDN for static assets (HTML timelines)

## Technology Choices Rationale

**Why Node.js for MCP?**
- Native MCP SDK support
- Easy integration with ableton-js
- Good child process management
- Large ecosystem

**Why Python for versioning?**
- Existing liveGit codebase
- Excellent XML/JSON libraries
- Clear, readable diff logic
- Fast prototyping

**Why PHP for parsing?**
- Existing V7 analyzer (1200+ lines, battle-tested)
- Excellent XML parsing (DOMDocument, XPath)
- Low memory footprint with streams
- Fast execution for small files

**Why Not All One Language?**
- Leverage existing codebases (don't rewrite)
- Use best tool for each job
- Minimize development time
- Easier to maintain specialized components
