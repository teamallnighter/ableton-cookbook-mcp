# Development Guide

## Quick Start

### Prerequisites

```bash
# Check versions
node --version   # v18.0.0+
python3 --version # 3.7+
php --version    # 7.4+

# Install Node dependencies
cd /Volumes/DEV/M4L-MCP
npm install

# Build TypeScript
npm run build
```

### Configuration

#### 1. Configure Claude Desktop

Edit `~/Library/Application Support/Claude/claude_desktop_config.json`:

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

#### 2. Install AbletonJS Remote Script

1. Download from: https://github.com/leolabs/ableton-js
2. Copy to: `~/Music/Ableton/User Library/Remote Scripts/AbletonJS/`
3. Open Ableton Live > Preferences > Link/Tempo/MIDI
4. Select "AbletonJS" from Control Surface dropdown

#### 3. Configure Python Environment

```bash
cd /Volumes/DEV/M4L-MCP/python-scripts

# No additional dependencies required
# Uses only Python standard library
```

#### 4. Configure PHP Environment

```bash
# PHP 7.4+ comes with macOS
# No additional extensions required

# Test PHP
php -v
```

### Running the MCP Server

#### Development Mode (with logging)

```bash
cd /Volumes/DEV/M4L-MCP
npm run build && node dist/index.js 2>&1 | tee mcp-server.log
```

#### Production Mode (via Claude Desktop)

1. Restart Claude Desktop
2. Check logs: `~/Library/Logs/Claude/mcp*.log`
3. Test tools in Claude chat

### Testing Individual Modules

#### Test Archivist (Offline Parsing)

```typescript
// test-archivist.ts
import { Archivist } from './src/archivist.js';

const archivist = new Archivist();
const result = await archivist.inspectAlsFile('/path/to/project.als');
console.log(JSON.stringify(result, null, 2));
```

```bash
npx tsx test-archivist.ts
```

#### Test Operator (Live Control)

```typescript
// test-operator.ts
import { Operator } from './src/operator.js';

const operator = new Operator();
await operator.initialize();
const tracks = await operator.listTracks();
console.log(tracks);
```

**Requirements**: Ableton Live must be running with AbletonJS Remote Script active

#### Test Historian (Version Control)

```bash
cd /Volumes/DEV/M4L-MCP/python-scripts

# Scan versions
python3 ableton_version_manager.py /path/to/project_0.1.0.als

# Generate timeline
python3 ableton_visualizer.py /path/to/project_dir

# Compare versions
python3 ableton_diff.py /path/to/project_0.1.0.als /path/to/project_0.2.0.als
```

#### Test Analyzer (Rack Parsing)

```bash
cd /Volumes/DEV/M4L-MCP/analyzers/abletonRackAnalyzer

# Analyze rack
php test_analyzer.php /path/to/rack.adg

# Analyze drum rack
php ../abletonDrumRackAnalyzer/test_analyzer.php /path/to/drumrack.adg
```

## Development Workflow

### 1. Adding a New MCP Tool

#### Step 1: Implement in Module Class

```typescript
// src/archivist.ts
export class Archivist {
  async newMethod(param: string): Promise<Result> {
    // Implementation
    return result;
  }
}
```

#### Step 2: Register in index.ts

```typescript
// src/index.ts
server.setRequestHandler(ListToolsRequestSchema, async () => ({
  tools: [
    // ... existing tools
    {
      name: "new_tool_name",
      description: "Clear description of what this tool does",
      inputSchema: {
        type: "object",
        properties: {
          param: {
            type: "string",
            description: "Parameter description"
          }
        },
        required: ["param"]
      }
    }
  ]
}));
```

#### Step 3: Add Tool Handler

```typescript
// src/index.ts
server.setRequestHandler(CallToolRequestSchema, async (request) => {
  switch (request.params.name) {
    // ... existing cases
    case "new_tool_name": {
      const result = await archivist.newMethod(
        request.params.arguments.param as string
      );
      return {
        content: [{
          type: "text",
          text: JSON.stringify(result, null, 2)
        }]
      };
    }
  }
});
```

#### Step 4: Build and Test

```bash
npm run build
# Restart Claude Desktop
# Test in Claude chat
```

### 2. Modifying Python Scripts

#### Edit Script

```python
# python-scripts/ableton_version_manager.py

def new_function(param):
    """New function docstring"""
    # Implementation
    return result
```

#### Update Historian Bridge

```typescript
// src/historian.ts
export class Historian {
  async newPythonMethod(param: string): Promise<any> {
    return new Promise((resolve, reject) => {
      const process = spawn('python3', [
        path.join(this.scriptsPath, 'ableton_version_manager.py'),
        '--new-flag',
        param
      ]);
      
      let output = '';
      process.stdout.on('data', (data) => {
        output += data.toString();
      });
      
      process.on('close', (code) => {
        if (code !== 0) reject(new Error('Python error'));
        try {
          resolve(JSON.parse(output));
        } catch (e) {
          reject(e);
        }
      });
    });
  }
}
```

#### Test Independently

```bash
# Test Python script directly
python3 python-scripts/ableton_version_manager.py --new-flag test

# Test via MCP
npm run build
# Test in Claude
```

### 3. Modifying PHP Analyzers

#### Edit Analyzer Class

```php
// analyzers/abletonRackAnalyzer/abletonRackAnalyzer-V7.php

class AbletonRackAnalyzer {
    public function newMethod($param) {
        // Implementation
        return $result;
    }
}
```

#### Update Analyzer Bridge

```typescript
// src/analyzer.ts
export class Analyzer {
  async newPhpMethod(param: string): Promise<any> {
    const wrapperScript = `<?php
      require_once '${this.analyzerPath}';
      $analyzer = new AbletonRackAnalyzer();
      $result = $analyzer->newMethod('${param}');
      echo json_encode($result);
    ?>`;
    
    const tempFile = path.join('/tmp', `analyzer_${Date.now()}.php`);
    fs.writeFileSync(tempFile, wrapperScript);
    
    return new Promise((resolve, reject) => {
      const process = spawn('php', [tempFile]);
      
      let output = '';
      process.stdout.on('data', (data) => {
        output += data.toString();
      });
      
      process.on('close', (code) => {
        fs.unlinkSync(tempFile); // Cleanup
        if (code !== 0) reject(new Error('PHP error'));
        try {
          resolve(JSON.parse(output));
        } catch (e) {
          reject(e);
        }
      });
    });
  }
}
```

#### Test Independently

```bash
# Test PHP directly
php analyzers/abletonRackAnalyzer/test_analyzer.php /path/to/rack.adg

# Test via MCP
npm run build
# Test in Claude
```

## Code Style Guidelines

### TypeScript

```typescript
// Use async/await, not callbacks
async function goodExample() {
  const result = await somePromise();
  return result;
}

// Use descriptive variable names
const projectFilePath = '/path/to/project.als';
const parsedData = await parseFile(projectFilePath);

// Handle errors explicitly
try {
  const data = await riskyOperation();
} catch (error) {
  throw new Error(`Operation failed: ${error.message}`);
}

// Type everything
interface Result {
  success: boolean;
  data: any;
}

async function operation(): Promise<Result> {
  return { success: true, data: {} };
}
```

### Python

```python
# Follow PEP 8
# Use type hints (Python 3.7+)
from typing import List, Dict, Optional

def parse_version(file_path: str) -> Dict[str, any]:
    """Parse version from filename.
    
    Args:
        file_path: Path to .als file
        
    Returns:
        Dictionary with version info
    """
    # Implementation
    return {"version": "0.1.0"}

# Use descriptive names
project_file_path = '/path/to/project.als'
version_data = parse_version(project_file_path)

# Handle errors
try:
    data = risky_operation()
except Exception as e:
    print(f"Error: {e}", file=sys.stderr)
    sys.exit(1)
```

### PHP

```php
<?php
// Use type declarations (PHP 7.4+)
class AbletonRackAnalyzer {
    public function analyzeRack(string $filePath): array {
        // Implementation
        return ['success' => true];
    }
}

// Use descriptive names
$projectFilePath = '/path/to/project.adg';
$analysisResult = $analyzer->analyzeRack($projectFilePath);

// Handle errors
try {
    $data = $this->riskyOperation();
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    return ['error' => $e->getMessage()];
}

// Document public methods
/**
 * Analyze an Ableton rack file
 * 
 * @param string $filePath Path to .adg file
 * @return array Analysis results
 * @throws Exception If file cannot be read
 */
public function analyzeRack(string $filePath): array {
    // ...
}
```

## Debugging Techniques

### MCP Server Debugging

#### Enable Verbose Logging

```typescript
// src/index.ts
server.onerror = (error) => {
  console.error('[MCP Error]', error);
  console.error('[Stack]', error.stack);
};

// Add debug logging
console.error(`[DEBUG] Tool called: ${request.params.name}`);
console.error(`[DEBUG] Arguments:`, request.params.arguments);
```

#### Check Claude Logs

```bash
# View live logs
tail -f ~/Library/Logs/Claude/mcp*.log

# Search for errors
grep -r "error" ~/Library/Logs/Claude/

# Check specific server
cat ~/Library/Logs/Claude/mcp-ableton-live-*.log
```

#### Test Outside Claude

```bash
# Run server directly and send JSON-RPC
node dist/index.js

# Then paste:
{"jsonrpc": "2.0", "method": "tools/list", "id": 1}
```

### Operator (ableton-js) Debugging

#### Check AbletonJS Connection

```typescript
// src/operator.ts
async initialize() {
  console.error('[Operator] Connecting to Ableton...');
  this.ableton = new Ableton();
  
  try {
    const status = await this.ableton.song.get("is_playing");
    console.error('[Operator] Connected! Playing:', status);
  } catch (error) {
    console.error('[Operator] Connection failed:', error);
  }
}
```

#### Verify Port

```bash
# Check if port 39031 is in use
lsof -i :39031

# Expected output:
# COMMAND   PID USER   FD   TYPE DEVICE SIZE/OFF NODE NAME
# Live     1234 user   42u  IPv4  ...          UDP *:39031
```

#### Test MIDI Remote Script

In Ableton Live:
1. Preferences > Link/Tempo/MIDI
2. Check "AbletonJS" is selected
3. Status should show "Online" or green indicator
4. Try sending simple command from Max/MSP console

### Python Script Debugging

#### Add Debug Prints

```python
# ableton_version_manager.py
import sys

def debug(msg):
    print(f"[DEBUG] {msg}", file=sys.stderr)

debug(f"Processing file: {file_path}")
debug(f"Found versions: {versions}")
```

#### Run with Python Debugger

```bash
python3 -m pdb ableton_version_manager.py /path/to/project.als

# Commands:
# n - next line
# s - step into
# c - continue
# p <var> - print variable
```

#### Check Output Format

```bash
# Python should output valid JSON
python3 ableton_version_manager.py /path/to/project.als | python3 -m json.tool

# Should pretty-print JSON, or show parse error
```

### PHP Analyzer Debugging

#### Add Debug Output

```php
// abletonRackAnalyzer-V7.php

// Use error_log for debugging (goes to stderr)
error_log("[DEBUG] Analyzing file: " . $filePath);
error_log("[DEBUG] Found devices: " . count($devices));

// Never use echo for debugging (pollutes JSON output)
// echo "[DEBUG]"; // WRONG - breaks JSON parsing
```

#### Run with Error Display

```bash
# Show all PHP errors
php -d display_errors=1 -d error_reporting=E_ALL test_analyzer.php /path/to/rack.adg
```

#### Validate JSON Output

```bash
# Check JSON is valid
php test_analyzer.php /path/to/rack.adg | python3 -m json.tool
```

#### Test Memory Limits

```bash
# Increase memory for large files
php -d memory_limit=1G test_analyzer.php /path/to/large_rack.adg
```

## Common Issues & Solutions

### Issue: "Error: Cannot find module 'zlib'"

**Cause**: Missing Node.js built-in module

**Solution**: Ensure Node.js v18+ is installed
```bash
node --version
nvm install 18  # if using nvm
```

### Issue: "Port 39031 already in use"

**Cause**: Another process is using AbletonJS port

**Solution**:
```bash
# Find process
lsof -i :39031

# Kill it
kill -9 <PID>

# Or restart Ableton Live
```

### Issue: "Python script returns empty output"

**Cause**: Script error being swallowed

**Solution**: Check stderr
```typescript
// src/historian.ts
let errorOutput = '';
process.stderr.on('data', (data) => {
  errorOutput += data.toString();
  console.error('[Python stderr]', data.toString());
});
```

### Issue: "PHP returns invalid JSON"

**Cause**: Unexpected output (errors, warnings, debug echoes)

**Solution**:
1. Check PHP error logs: `tail -f /var/log/php-errors.log`
2. Remove any `echo` statements from PHP code
3. Suppress warnings: `error_reporting(E_ERROR);`
4. Use `error_log()` for debugging

### Issue: "Claude Desktop doesn't load MCP server"

**Cause**: Config syntax error or wrong path

**Solution**:
1. Validate JSON: `cat ~/Library/Application\ Support/Claude/claude_desktop_config.json | python3 -m json.tool`
2. Check path exists: `ls /Volumes/DEV/M4L-MCP/dist/index.js`
3. Check permissions: `ls -l /Volumes/DEV/M4L-MCP/dist/index.js`
4. Restart Claude Desktop completely

### Issue: "Build fails with 'Cannot find module'"

**Cause**: Missing dependencies or wrong tsconfig

**Solution**:
```bash
# Clean install
rm -rf node_modules package-lock.json
npm install

# Rebuild
npm run build

# Check tsconfig.json has "module": "ES2022"
```

## Performance Optimization

### Caching Strategy

```typescript
// Cache parsed .als files in memory
class Archivist {
  private cache = new Map<string, ParsedALS>();
  
  async inspectAlsFile(filePath: string): Promise<ParsedALS> {
    // Check cache first
    if (this.cache.has(filePath)) {
      return this.cache.get(filePath)!;
    }
    
    // Parse and cache
    const result = await this.parseFile(filePath);
    this.cache.set(filePath, result);
    return result;
  }
}
```

### Batch Operations

```typescript
// Instead of calling 10 times sequentially
for (const file of files) {
  await analyzeFile(file); // Slow: 10 * 200ms = 2s
}

// Use Promise.all for parallel execution
await Promise.all(
  files.map(file => analyzeFile(file)) // Fast: ~200ms total
);
```

### Stream Large Files

```php
// For files > 10MB, use stream parsing
if (filesize($filePath) > 10 * 1024 * 1024) {
    return $this->streamParseRack($filePath);
} else {
    return $this->parseRack($filePath);
}
```

## Contribution Guidelines

### Before Committing

1. Build successfully: `npm run build`
2. Test all affected modules
3. Update README.md if adding tools
4. Add/update comments for complex logic
5. Follow code style guidelines

### Commit Message Format

```
[Module] Brief description

- Detailed change 1
- Detailed change 2

Fixes: #issue-number (if applicable)
```

Examples:
```
[Archivist] Add support for Live 12 project format

- Updated XML parser to handle new namespace
- Added backwards compatibility for Live 11
- Updated tests

[Historian] Fix timeline generation path issue

- Timeline now writes to correct output directory
- Added validation for output path
```

### Branch Strategy

- `main` - production-ready code
- `develop` - integration branch
- `feature/feature-name` - new features
- `bugfix/issue-description` - bug fixes

## Environment Variables

```bash
# PHP Analyzer Configuration
export ABLETON_MAX_FILE_SIZE=104857600        # 100MB
export ABLETON_MAX_MEMORY_USAGE=536870912     # 512MB
export ABLETON_ANALYSIS_TIMEOUT=300           # 5 minutes
export ABLETON_STREAM_PARSING_THRESHOLD=10485760  # 10MB

# MCP Server Configuration
export MCP_DEBUG=1                            # Enable debug logging
export MCP_PORT=39031                         # AbletonJS port

# Python Scripts Configuration
export LIVEKIT_SCRIPTS_PATH=/Volumes/DEV/M4L-MCP/python-scripts
```

## Useful Commands

```bash
# Build and watch for changes
npm run build -- --watch

# Clean build
rm -rf dist && npm run build

# Check TypeScript errors without building
npx tsc --noEmit

# Format code (if prettier is installed)
npx prettier --write "src/**/*.ts"

# Count lines of code
find src -name "*.ts" | xargs wc -l

# Search codebase
grep -r "function_name" src/

# Find large files in project
find . -type f -size +1M
```

## Resources

### Documentation
- [MCP Protocol Spec](https://modelcontextprotocol.io)
- [ableton-js API](https://github.com/leolabs/ableton-js)
- [Ableton Live Object Model](https://docs.cycling74.com/max8/vignettes/live_object_model)
- [fast-xml-parser](https://github.com/NaturalIntelligence/fast-xml-parser)

### Community
- GitHub Issues: `/Volumes/DEV/M4L-MCP/issues`
- Discord: (future)
- Reddit: r/abletonlive

### Related Projects
- [AbletonOSC](https://github.com/ideoforms/AbletonOSC) - Alternative control surface
- [LiveGit](https://github.com/similar-project) - Version control inspiration
- [The Max Cookbook](https://github.com/Cycling74/max-cookbook) - Max/MSP reference
