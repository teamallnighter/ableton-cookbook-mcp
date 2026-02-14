# Ableton Cookbook - Desktop Installer

A beautiful, user-friendly Electron-based installer for The Ableton Cookbook MCP server.

## Features

- **5-Minute Setup Wizard**
  - Auto-detects Ableton Live installation
  - Installs MCP server from npm
  - Configures Claude Desktop automatically
  - No terminal commands needed!

- **User-Friendly Interface**
  - Step-by-step guided wizard
  - Progress indicators
  - Clear error messages
  - Beautiful purple gradient design

- **Cross-Platform**
  - macOS (DMG installer)
  - Windows (NSIS installer)

## Development

```bash
# Install dependencies
npm install

# Run in development mode
npm start

# Run with DevTools open
npm run dev

# Build installers
npm run build:mac    # macOS DMG
npm run build:win    # Windows NSIS
npm run build        # Both platforms
```

## Project Structure

```
desktop-app/
├── src/
│   ├── main/
│   │   └── index.js          # Main process (Node.js)
│   ├── renderer/
│   │   ├── index.html        # Setup wizard UI
│   │   ├── styles.css        # Styling
│   │   └── app.js            # Renderer logic
│   └── preload/
│       └── preload.js        # IPC bridge
├── assets/                    # Icons and images
└── package.json
```

## What It Does

### Step 1: Welcome
Shows overview of what will be installed

### Step 2: Detect Ableton
Automatically scans for Ableton Live installation on the system

### Step 3: Install MCP
Downloads and installs `ableton-cookbook-mcp` via npm

### Step 4: Configure Claude
Updates Claude Desktop config to add the MCP server

### Step 5: Complete
Shows success message and next steps

## IPC API

The main process exposes these handlers:

- `detectAbleton()` - Find Ableton Live installation
- `getClaudeConfigPath()` - Get Claude Desktop config location
- `readClaudeConfig()` - Read existing config
- `writeClaudeConfig(config)` - Write new config
- `getMcpInstallPath()` - Get npm global module path
- `checkMcpInstalled()` - Verify MCP server is installed

## Building for Distribution

```bash
# macOS DMG
npm run build:mac

# Find the DMG in:
# dist/Ableton Cookbook-0.1.0.dmg
```

The DMG includes:
- Application bundle
- Background image
- Auto-arranged icons
- License agreement

## Future Enhancements

- [ ] Auto-download and install Claude Desktop if not found
- [ ] Install AbletonJS MIDI script automatically
- [ ] File watcher setup for auto-versioning
- [ ] System tray background service
- [ ] Update checker
- [ ] Uninstaller

## License

MIT - see LICENSE file in root directory
