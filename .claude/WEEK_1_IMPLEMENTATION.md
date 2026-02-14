# Week 1 Implementation Plan - Desktop App Installer

## Goal
Build working Electron installer that sets up The Ableton Cookbook without terminal commands.

**Success Criteria:**
- ✅ User downloads .dmg (macOS)
- ✅ Double-clicks installer
- ✅ 5-minute guided wizard
- ✅ Everything configured automatically
- ✅ User opens Claude Desktop → MCP tools work
- ✅ File watchers active in background

---

## Day 1: Project Setup & Boilerplate

### Morning: Electron Project Initialization

**Task 1.1: Create Electron app structure**
```bash
cd /Volumes/DEV/M4L-MCP
mkdir desktop-app
cd desktop-app

npm init -y
npm install --save-dev electron electron-builder
npm install --save electron-store node-machine-id chokidar
```

**Project Structure:**
```
desktop-app/
├── package.json
├── electron-builder.yml         # Build configuration
├── src/
│   ├── main/                    # Main process (Node.js)
│   │   ├── index.js            # Entry point
│   │   ├── installer.js        # Installation logic
│   │   ├── detector.js         # Detect Ableton/paths
│   │   ├── watcher.js          # File watcher service
│   │   └── tray.js             # System tray
│   ├── renderer/                # UI (HTML/CSS/JS)
│   │   ├── index.html
│   │   ├── setup-wizard.html
│   │   ├── dashboard.html
│   │   ├── styles.css
│   │   └── app.js
│   └── preload.js              # IPC bridge
├── assets/
│   ├── icon.png
│   ├── icon.icns               # macOS icon
│   └── tray-icon.png
└── build/                       # Will contain built app
```

**Task 1.2: Basic package.json setup**
```json
{
  "name": "ableton-cookbook-desktop",
  "version": "0.1.0",
  "description": "The Ableton Cookbook - Desktop App",
  "main": "src/main/index.js",
  "scripts": {
    "start": "electron .",
    "build": "electron-builder",
    "build:mac": "electron-builder --mac"
  },
  "build": {
    "appId": "com.abletonCookbook.desktop",
    "productName": "Ableton Cookbook",
    "mac": {
      "category": "public.app-category.music",
      "icon": "assets/icon.icns",
      "target": ["dmg"]
    },
    "dmg": {
      "title": "Install Ableton Cookbook",
      "icon": "assets/icon.icns",
      "background": "assets/dmg-background.png",
      "contents": [
        { "x": 130, "y": 220 },
        { "x": 410, "y": 220, "type": "link", "path": "/Applications" }
      ]
    },
    "files": [
      "src/**/*",
      "assets/**/*",
      "!**/*.map"
    ]
  }
}
```

**Task 1.3: Create main process entry point**
```javascript
// src/main/index.js
const { app, BrowserWindow, ipcMain, Tray, Menu } = require('electron');
const path = require('path');
const Store = require('electron-store');

const store = new Store();
let mainWindow;
let tray;

function createWindow() {
  mainWindow = new BrowserWindow({
    width: 800,
    height: 600,
    webPreferences: {
      preload: path.join(__dirname, '../preload.js'),
      contextIsolation: true,
      nodeIntegration: false
    }
  });

  // Check if this is first run
  const isFirstRun = !store.get('setupCompleted');
  
  if (isFirstRun) {
    mainWindow.loadFile(path.join(__dirname, '../renderer/setup-wizard.html'));
  } else {
    mainWindow.loadFile(path.join(__dirname, '../renderer/dashboard.html'));
  }
}

app.whenReady().then(() => {
  createWindow();
  
  app.on('activate', () => {
    if (BrowserWindow.getAllWindows().length === 0) {
      createWindow();
    }
  });
});

app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') {
    app.quit();
  }
});
```

**Deliverable:** 
- Electron app opens with blank window
- `npm start` runs successfully
- File structure in place

**Time Estimate:** 2 hours

---

### Afternoon: Detection Logic

**Task 1.4: Build Ableton detector**
```javascript
// src/main/detector.js
const fs = require('fs-extra');
const path = require('path');
const { exec } = require('child_process');
const { promisify } = require('util');
const execAsync = promisify(exec);

class AbletonDetector {
  
  /**
   * Find Ableton Live installations on macOS
   */
  async findAbletonInstallations() {
    const applicationsDir = '/Applications';
    const installations = [];

    try {
      const apps = await fs.readdir(applicationsDir);
      
      for (const app of apps) {
        if (app.startsWith('Ableton Live') && app.endsWith('.app')) {
          const appPath = path.join(applicationsDir, app);
          const plistPath = path.join(appPath, 'Contents/Info.plist');
          
          if (await fs.pathExists(plistPath)) {
            // Parse version from plist
            const { stdout } = await execAsync(
              `defaults read "${plistPath.replace('.plist', '')}" CFBundleShortVersionString`
            );
            
            const version = stdout.trim();
            const edition = this.detectEdition(app);
            
            installations.push({
              name: app.replace('.app', ''),
              path: appPath,
              version,
              edition,
              remoteScriptsPath: path.join(
                appPath,
                'Contents/App-Resources/MIDI Remote Scripts'
              )
            });
          }
        }
      }
    } catch (error) {
      console.error('Error detecting Ableton:', error);
    }

    return installations;
  }

  /**
   * Detect edition (Intro, Standard, Suite)
   */
  detectEdition(appName) {
    if (appName.includes('Intro')) return 'Intro';
    if (appName.includes('Standard')) return 'Standard';
    if (appName.includes('Suite')) return 'Suite';
    return 'Unknown';
  }

  /**
   * Find User Library location
   */
  async findUserLibrary() {
    const defaultPaths = [
      path.join(process.env.HOME, 'Music/Ableton/User Library'),
      '/Volumes/ABLETON/User Library',
      path.join(process.env.HOME, 'Documents/Ableton/User Library')
    ];

    for (const libPath of defaultPaths) {
      if (await fs.pathExists(libPath)) {
        return libPath;
      }
    }

    return null;
  }

  /**
   * Find project folders (common locations)
   */
  async findProjectFolders() {
    const commonPaths = [
      path.join(process.env.HOME, 'Music/Ableton/Projects'),
      '/Volumes/ABLETON/Projects',
      path.join(process.env.HOME, 'Documents/Ableton/Projects')
    ];

    const found = [];
    
    for (const projectPath of commonPaths) {
      if (await fs.pathExists(projectPath)) {
        found.push(projectPath);
      }
    }

    return found;
  }

  /**
   * Check if dependencies are installed
   */
  async checkDependencies() {
    const deps = {
      node: false,
      python: false,
      php: false
    };

    try {
      await execAsync('node --version');
      deps.node = true;
    } catch (e) {}

    try {
      const { stdout } = await execAsync('python3 --version');
      const version = stdout.match(/Python (\d+\.\d+)/)?.[1];
      if (version && parseFloat(version) >= 3.7) {
        deps.python = true;
      }
    } catch (e) {}

    try {
      const { stdout } = await execAsync('php --version');
      const version = stdout.match(/PHP (\d+\.\d+)/)?.[1];
      if (version && parseFloat(version) >= 7.4) {
        deps.php = true;
      }
    } catch (e) {}

    return deps;
  }

  /**
   * Check if Claude Desktop is installed
   */
  async checkClaudeDesktop() {
    const claudePath = '/Applications/Claude.app';
    const configPath = path.join(
      process.env.HOME,
      'Library/Application Support/Claude/claude_desktop_config.json'
    );

    return {
      installed: await fs.pathExists(claudePath),
      configExists: await fs.pathExists(configPath)
    };
  }
}

module.exports = AbletonDetector;
```

**Task 1.5: Test detection logic**
```javascript
// Test detector
const detector = new AbletonDetector();

(async () => {
  console.log('Detecting Ableton installations...');
  const installations = await detector.findAbletonInstallations();
  console.log('Found:', installations);

  console.log('\nDetecting User Library...');
  const userLib = await detector.findUserLibrary();
  console.log('Found:', userLib);

  console.log('\nChecking dependencies...');
  const deps = await detector.checkDependencies();
  console.log('Dependencies:', deps);

  console.log('\nChecking Claude Desktop...');
  const claude = await detector.checkClaudeDesktop();
  console.log('Claude:', claude);
})();
```

**Deliverable:**
- `AbletonDetector` class working
- Detects Ableton Live installations
- Finds User Library
- Checks dependencies
- Verified on your machine

**Time Estimate:** 3 hours

---

## Day 2: Setup Wizard UI

### Morning: Wizard HTML/CSS

**Task 2.1: Create setup wizard UI**
```html
<!-- src/renderer/setup-wizard.html -->
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Ableton Cookbook Setup</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <div class="wizard-container">
    
    <!-- Step 1: Welcome -->
    <div class="wizard-step active" data-step="1">
      <div class="logo">
        <img src="../assets/icon.png" alt="Ableton Cookbook" width="120">
      </div>
      <h1>Welcome to The Ableton Cookbook</h1>
      <p>Share and discover production recipes from the community.</p>
      <p class="subtitle">Setup takes about 5 minutes.</p>
      <button class="btn-primary" onclick="nextStep()">Let's Go!</button>
    </div>

    <!-- Step 2: Detection -->
    <div class="wizard-step" data-step="2">
      <h2>Detecting your setup...</h2>
      <div class="detection-results">
        <div class="detection-item">
          <span class="icon" id="ableton-icon">⏳</span>
          <span>Ableton Live</span>
          <span class="status" id="ableton-status">Checking...</span>
        </div>
        <div class="detection-item">
          <span class="icon" id="library-icon">⏳</span>
          <span>User Library</span>
          <span class="status" id="library-status">Checking...</span>
        </div>
        <div class="detection-item">
          <span class="icon" id="deps-icon">⏳</span>
          <span>Dependencies</span>
          <span class="status" id="deps-status">Checking...</span>
        </div>
        <div class="detection-item">
          <span class="icon" id="claude-icon">⏳</span>
          <span>Claude Desktop</span>
          <span class="status" id="claude-status">Checking...</span>
        </div>
      </div>
      <button class="btn-primary" onclick="nextStep()" disabled id="detection-next">
        Continue
      </button>
    </div>

    <!-- Step 3: Ableton Configuration -->
    <div class="wizard-step" data-step="3">
      <h2>Configure Ableton Live</h2>
      <div class="config-section">
        <label>Detected Installation:</label>
        <div class="detected-install" id="detected-ableton">
          <strong>Ableton Live 11 Suite</strong>
          <span>/Applications/Ableton Live 11 Suite.app</span>
        </div>
        
        <div class="checkbox-group">
          <input type="checkbox" id="install-remote-script" checked>
          <label for="install-remote-script">
            <strong>Install AbletonJS MIDI Remote Script</strong>
            <span>Required for AI control of Live</span>
          </label>
        </div>
      </div>
      <button class="btn-primary" onclick="installAbletonJS()">Install & Continue</button>
    </div>

    <!-- Step 4: User Library -->
    <div class="wizard-step" data-step="4">
      <h2>User Library Location</h2>
      <div class="config-section">
        <label>We found your User Library at:</label>
        <input type="text" id="library-path" readonly>
        <button class="btn-secondary" onclick="browseLibrary()">Change Location</button>
        
        <div class="info-box">
          <p><strong>What we'll do:</strong></p>
          <ul>
            <li>Scan for your existing racks and presets (read-only)</li>
            <li>Watch for new recipes you create</li>
            <li>Help you share with the community (optional)</li>
          </ul>
        </div>
      </div>
      <button class="btn-primary" onclick="nextStep()">Continue</button>
    </div>

    <!-- Step 5: Project Folders -->
    <div class="wizard-step" data-step="5">
      <h2>Project Version Tracking</h2>
      <div class="config-section">
        <p>Enable automatic version tracking for your projects?</p>
        
        <div class="checkbox-group">
          <input type="checkbox" id="enable-tracking" checked>
          <label for="enable-tracking">
            <strong>Track project versions automatically</strong>
            <span>Save as project_0.1.0.als → 0.2.0.als → etc.</span>
          </label>
        </div>

        <div id="project-folders">
          <label>Folders to watch:</label>
          <div class="folder-list" id="watch-folders"></div>
          <button class="btn-secondary" onclick="addProjectFolder()">
            + Add Folder
          </button>
        </div>
      </div>
      <button class="btn-primary" onclick="nextStep()">Continue</button>
    </div>

    <!-- Step 6: Account Setup -->
    <div class="wizard-step" data-step="6">
      <h2>Create Your Account</h2>
      <div class="config-section">
        <p>Connect to share recipes and discover the community library.</p>
        
        <div class="account-options">
          <button class="btn-primary btn-large" onclick="createAccount()">
            Create New Account
          </button>
          <button class="btn-secondary btn-large" onclick="loginAccount()">
            I Already Have an Account
          </button>
          <button class="btn-text" onclick="skipAccount()">
            Skip for now (local only)
          </button>
        </div>

        <div class="info-box">
          <p><strong>Privacy First:</strong></p>
          <ul>
            <li>All recipes are private by default</li>
            <li>You control what gets shared</li>
            <li>Local features work without account</li>
          </ul>
        </div>
      </div>
    </div>

    <!-- Step 7: Installation -->
    <div class="wizard-step" data-step="7">
      <h2>Installing...</h2>
      <div class="progress-container">
        <div class="progress-bar" id="install-progress"></div>
      </div>
      <div class="install-steps">
        <div class="install-step" id="step-mcp">
          <span class="icon">⏳</span>
          <span>Setting up MCP server...</span>
        </div>
        <div class="install-step" id="step-watcher">
          <span class="icon">⏳</span>
          <span>Configuring file watcher...</span>
        </div>
        <div class="install-step" id="step-claude">
          <span class="icon">⏳</span>
          <span>Updating Claude Desktop config...</span>
        </div>
        <div class="install-step" id="step-scan">
          <span class="icon">⏳</span>
          <span>Scanning User Library...</span>
        </div>
      </div>
    </div>

    <!-- Step 8: Complete -->
    <div class="wizard-step" data-step="8">
      <div class="success-icon">🎉</div>
      <h1>Setup Complete!</h1>
      <div class="summary-box">
        <p><strong>What's ready:</strong></p>
        <ul id="setup-summary"></ul>
      </div>
      
      <div class="next-steps">
        <h3>Next Steps:</h3>
        <ol>
          <li>Open <strong>Ableton Live</strong></li>
          <li>Go to <strong>Preferences → Link/Tempo/MIDI</strong></li>
          <li>Select <strong>"AbletonJS"</strong> from Control Surface dropdown</li>
          <li>Restart Claude Desktop to load MCP tools</li>
        </ol>
      </div>

      <button class="btn-primary btn-large" onclick="openDashboard()">
        Open Dashboard
      </button>
    </div>

  </div>

  <script src="app.js"></script>
</body>
</html>
```

**Task 2.2: Style wizard**
```css
/* src/renderer/styles.css */
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: #333;
}

.wizard-container {
  max-width: 600px;
  margin: 40px auto;
  background: white;
  border-radius: 16px;
  padding: 60px;
  box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}

.wizard-step {
  display: none;
}

.wizard-step.active {
  display: block;
  animation: fadeIn 0.3s ease-in;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}

h1 {
  font-size: 32px;
  margin-bottom: 16px;
  color: #1a202c;
}

h2 {
  font-size: 24px;
  margin-bottom: 24px;
  color: #2d3748;
}

p {
  font-size: 16px;
  line-height: 1.6;
  color: #4a5568;
  margin-bottom: 12px;
}

.subtitle {
  color: #718096;
  font-size: 14px;
}

.btn-primary {
  background: #667eea;
  color: white;
  border: none;
  padding: 16px 32px;
  font-size: 16px;
  font-weight: 600;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s;
  margin-top: 24px;
}

.btn-primary:hover {
  background: #5568d3;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.btn-primary:disabled {
  background: #cbd5e0;
  cursor: not-allowed;
  transform: none;
}

.btn-secondary {
  background: white;
  color: #667eea;
  border: 2px solid #667eea;
  padding: 12px 24px;
  font-size: 14px;
  font-weight: 600;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s;
  margin-left: 12px;
}

.btn-secondary:hover {
  background: #f7fafc;
}

.detection-results {
  margin: 32px 0;
}

.detection-item {
  display: flex;
  align-items: center;
  padding: 16px;
  background: #f7fafc;
  border-radius: 8px;
  margin-bottom: 12px;
}

.detection-item .icon {
  font-size: 24px;
  margin-right: 16px;
  width: 32px;
  text-align: center;
}

.detection-item span:nth-child(2) {
  flex: 1;
  font-weight: 600;
  color: #2d3748;
}

.detection-item .status {
  color: #718096;
  font-size: 14px;
}

.icon.success::before {
  content: '✅';
}

.icon.error::before {
  content: '❌';
}

.icon.loading::before {
  content: '⏳';
}

.info-box {
  background: #edf2f7;
  border-left: 4px solid #667eea;
  padding: 16px;
  border-radius: 4px;
  margin-top: 24px;
}

.info-box ul {
  margin: 8px 0 0 20px;
}

.info-box li {
  margin: 4px 0;
  color: #4a5568;
}

.progress-container {
  width: 100%;
  height: 8px;
  background: #e2e8f0;
  border-radius: 4px;
  overflow: hidden;
  margin: 24px 0;
}

.progress-bar {
  height: 100%;
  background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
  transition: width 0.3s ease;
  width: 0%;
}

.success-icon {
  font-size: 80px;
  text-align: center;
  margin-bottom: 24px;
}

.summary-box {
  background: #f0fff4;
  border: 2px solid #9ae6b4;
  padding: 20px;
  border-radius: 8px;
  margin: 24px 0;
}

.next-steps {
  background: #fffaf0;
  border: 2px solid #fbd38d;
  padding: 20px;
  border-radius: 8px;
  margin: 24px 0;
}

.next-steps ol {
  margin: 12px 0 0 20px;
}

.next-steps li {
  margin: 8px 0;
  color: #744210;
}
```

**Deliverable:**
- Beautiful setup wizard UI
- 8 steps designed
- Responsive CSS
- Animations and transitions

**Time Estimate:** 3 hours

---

### Afternoon: Wizard Logic & IPC

**Task 2.3: Preload script for IPC**
```javascript
// src/preload.js
const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('electronAPI', {
  // Detection
  detectAbleton: () => ipcRenderer.invoke('detect-ableton'),
  detectUserLibrary: () => ipcRenderer.invoke('detect-library'),
  detectDependencies: () => ipcRenderer.invoke('detect-dependencies'),
  detectClaude: () => ipcRenderer.invoke('detect-claude'),
  
  // Installation
  installAbletonJS: (abletonPath) => ipcRenderer.invoke('install-abletonjs', abletonPath),
  installMCPServer: () => ipcRenderer.invoke('install-mcp-server'),
  setupFileWatcher: (folders) => ipcRenderer.invoke('setup-watcher', folders),
  updateClaudeConfig: () => ipcRenderer.invoke('update-claude-config'),
  scanUserLibrary: (libraryPath) => ipcRenderer.invoke('scan-library', libraryPath),
  
  // Settings
  saveSettings: (settings) => ipcRenderer.invoke('save-settings', settings),
  getSettings: () => ipcRenderer.invoke('get-settings'),
  
  // File dialogs
  selectFolder: () => ipcRenderer.invoke('select-folder'),
  
  // Navigation
  openDashboard: () => ipcRenderer.send('open-dashboard'),
  
  // Progress updates
  onProgress: (callback) => ipcRenderer.on('progress-update', callback)
});
```

**Task 2.4: Wizard app logic**
```javascript
// src/renderer/app.js
let currentStep = 1;
let detectionResults = {};

// Step navigation
function nextStep() {
  const currentEl = document.querySelector(`.wizard-step[data-step="${currentStep}"]`);
  currentEl.classList.remove('active');
  
  currentStep++;
  
  const nextEl = document.querySelector(`.wizard-step[data-step="${currentStep}"]`);
  nextEl.classList.add('active');
  
  // Trigger step-specific logic
  onStepEnter(currentStep);
}

function onStepEnter(step) {
  switch(step) {
    case 2:
      runDetection();
      break;
    case 7:
      runInstallation();
      break;
  }
}

// Detection logic
async function runDetection() {
  // Detect Ableton
  updateDetectionStatus('ableton', 'loading');
  try {
    const ableton = await window.electronAPI.detectAbleton();
    detectionResults.ableton = ableton;
    if (ableton && ableton.length > 0) {
      updateDetectionStatus('ableton', 'success', `Found ${ableton[0].name}`);
    } else {
      updateDetectionStatus('ableton', 'error', 'Not found');
    }
  } catch (e) {
    updateDetectionStatus('ableton', 'error', 'Error');
  }

  // Detect User Library
  updateDetectionStatus('library', 'loading');
  try {
    const library = await window.electronAPI.detectUserLibrary();
    detectionResults.library = library;
    if (library) {
      updateDetectionStatus('library', 'success', library);
      document.getElementById('library-path').value = library;
    } else {
      updateDetectionStatus('library', 'error', 'Not found');
    }
  } catch (e) {
    updateDetectionStatus('library', 'error', 'Error');
  }

  // Check dependencies
  updateDetectionStatus('deps', 'loading');
  try {
    const deps = await window.electronAPI.detectDependencies();
    detectionResults.deps = deps;
    const allGood = deps.node && deps.python && deps.php;
    if (allGood) {
      updateDetectionStatus('deps', 'success', 'All present');
    } else {
      const missing = [];
      if (!deps.python) missing.push('Python 3.7+');
      if (!deps.php) missing.push('PHP 7.4+');
      updateDetectionStatus('deps', 'error', `Missing: ${missing.join(', ')}`);
    }
  } catch (e) {
    updateDetectionStatus('deps', 'error', 'Error');
  }

  // Check Claude Desktop
  updateDetectionStatus('claude', 'loading');
  try {
    const claude = await window.electronAPI.detectClaude();
    detectionResults.claude = claude;
    if (claude.installed) {
      updateDetectionStatus('claude', 'success', 'Found');
    } else {
      updateDetectionStatus('claude', 'error', 'Not installed');
    }
  } catch (e) {
    updateDetectionStatus('claude', 'error', 'Error');
  }

  // Enable next button
  document.getElementById('detection-next').disabled = false;
}

function updateDetectionStatus(component, status, message) {
  const icon = document.getElementById(`${component}-icon`);
  const statusEl = document.getElementById(`${component}-status`);
  
  icon.className = `icon ${status}`;
  statusEl.textContent = message;
}

// AbletonJS installation
async function installAbletonJS() {
  const btn = event.target;
  btn.disabled = true;
  btn.textContent = 'Installing...';
  
  try {
    const abletonPath = detectionResults.ableton[0].remoteScriptsPath;
    await window.electronAPI.installAbletonJS(abletonPath);
    btn.textContent = 'Installed ✓';
    setTimeout(() => nextStep(), 1000);
  } catch (error) {
    alert('Installation failed: ' + error.message);
    btn.disabled = false;
    btn.textContent = 'Install & Continue';
  }
}

// Full installation
async function runInstallation() {
  let progress = 0;
  
  // Listen for progress updates
  window.electronAPI.onProgress((event, data) => {
    progress = data.percent;
    document.getElementById('install-progress').style.width = progress + '%';
    
    // Update step status
    if (data.step) {
      const stepEl = document.getElementById(`step-${data.step}`);
      if (stepEl) {
        stepEl.querySelector('.icon').className = 'icon success';
      }
    }
  });

  try {
    // Step 1: MCP Server
    await window.electronAPI.installMCPServer();
    
    // Step 2: File Watcher
    const watchFolders = getSelectedFolders();
    await window.electronAPI.setupFileWatcher(watchFolders);
    
    // Step 3: Claude Config
    await window.electronAPI.updateClaudeConfig();
    
    // Step 4: Scan Library
    await window.electronAPI.scanUserLibrary(detectionResults.library);
    
    // Complete!
    setTimeout(() => nextStep(), 500);
    
  } catch (error) {
    alert('Installation error: ' + error.message);
  }
}

function getSelectedFolders() {
  // Get folders user selected for watching
  return Array.from(document.querySelectorAll('.folder-item'))
    .map(el => el.dataset.path);
}

async function addProjectFolder() {
  const folder = await window.electronAPI.selectFolder();
  if (folder) {
    const list = document.getElementById('watch-folders');
    const item = document.createElement('div');
    item.className = 'folder-item';
    item.dataset.path = folder;
    item.innerHTML = `
      <span>${folder}</span>
      <button onclick="this.parentElement.remove()">Remove</button>
    `;
    list.appendChild(item);
  }
}

function openDashboard() {
  window.electronAPI.openDashboard();
}
```

**Deliverable:**
- Working wizard navigation
- Detection runs on step 2
- Installation runs on step 7
- IPC communication working

**Time Estimate:** 4 hours

---

## Day 3: Installation Logic

### Full Day: Implement installer backend

**Task 3.1: Create installer module**
```javascript
// src/main/installer.js
const fs = require('fs-extra');
const path = require('path');
const { exec } = require('child_process');
const { promisify } = require('util');
const execAsync = promisify(exec);

class Installer {
  constructor(mainWindow) {
    this.mainWindow = mainWindow;
    this.mcpInstallPath = path.join(process.env.HOME, 'Library/M4L-MCP');
  }

  /**
   * Send progress update to renderer
   */
  sendProgress(step, percent, message) {
    this.mainWindow.webContents.send('progress-update', {
      step, percent, message
    });
  }

  /**
   * Install AbletonJS MIDI Remote Script
   */
  async installAbletonJS(remoteScriptsPath) {
    const sourcePath = path.join(__dirname, '../../node_modules/ableton-js/midi-script/AbletonJS');
    const targetPath = path.join(remoteScriptsPath, 'AbletonJS');

    // Check if already installed
    if (await fs.pathExists(targetPath)) {
      console.log('AbletonJS already installed');
      return;
    }

    // Copy MIDI Remote Script
    await fs.copy(sourcePath, targetPath);
    console.log('AbletonJS installed to:', targetPath);
  }

  /**
   * Install MCP Server files
   */
  async installMCPServer() {
    this.sendProgress('mcp', 25, 'Installing MCP server...');

    // Create installation directory
    await fs.ensureDir(this.mcpInstallPath);

    // Copy MCP server files from project
    const sourcePath = '/Volumes/DEV/M4L-MCP';
    
    // Copy necessary files
    const filesToCopy = [
      'dist',
      'node_modules',
      'package.json',
      'python-scripts',
      'analyzers'
    ];

    for (const file of filesToCopy) {
      await fs.copy(
        path.join(sourcePath, file),
        path.join(this.mcpInstallPath, file)
      );
    }

    this.sendProgress('mcp', 50, 'MCP server installed');
  }

  /**
   * Setup file watcher service
   */
  async setupFileWatcher(watchFolders) {
    this.sendProgress('watcher', 50, 'Setting up file watcher...');

    // Create LaunchAgent plist
    const plistContent = `<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
  <key>Label</key>
  <string>com.abletonCookbook.watcher</string>
  <key>ProgramArguments</key>
  <array>
    <string>node</string>
    <string>${path.join(this.mcpInstallPath, 'src/main/watcher.js')}</string>
  </array>
  <key>RunAtLoad</key>
  <true/>
  <key>KeepAlive</key>
  <true/>
  <key>StandardOutPath</key>
  <string>${process.env.HOME}/Library/Logs/ableton-cookbook-watcher.log</string>
  <key>StandardErrorPath</key>
  <string>${process.env.HOME}/Library/Logs/ableton-cookbook-watcher-error.log</string>
</dict>
</plist>`;

    const plistPath = path.join(
      process.env.HOME,
      'Library/LaunchAgents/com.abletonCookbook.watcher.plist'
    );

    await fs.ensureDir(path.dirname(plistPath));
    await fs.writeFile(plistPath, plistContent);

    // Load LaunchAgent
    try {
      await execAsync(`launchctl load ${plistPath}`);
      console.log('File watcher service started');
    } catch (e) {
      console.error('Could not start watcher:', e);
    }

    this.sendProgress('watcher', 75, 'File watcher configured');
  }

  /**
   * Update Claude Desktop config
   */
  async updateClaudeConfig() {
    this.sendProgress('claude', 75, 'Configuring Claude Desktop...');

    const configPath = path.join(
      process.env.HOME,
      'Library/Application Support/Claude/claude_desktop_config.json'
    );

    let config = {};
    
    // Read existing config
    if (await fs.pathExists(configPath)) {
      try {
        config = await fs.readJSON(configPath);
      } catch (e) {
        console.error('Could not parse existing config');
      }
    }

    // Add/update MCP server
    if (!config.mcpServers) {
      config.mcpServers = {};
    }

    config.mcpServers['ableton-live'] = {
      command: 'node',
      args: [path.join(this.mcpInstallPath, 'dist/index.js')]
    };

    // Write config
    await fs.ensureDir(path.dirname(configPath));
    await fs.writeJSON(configPath, config, { spaces: 2 });

    this.sendProgress('claude', 90, 'Claude Desktop configured');
  }

  /**
   * Scan User Library
   */
  async scanUserLibrary(libraryPath) {
    this.sendProgress('scan', 90, 'Scanning User Library...');

    // Count .adg and .adv files
    const presetDir = path.join(libraryPath, 'Presets');
    
    if (!await fs.pathExists(presetDir)) {
      this.sendProgress('scan', 100, 'Scan complete (no presets found)');
      return { racks: 0, presets: 0 };
    }

    let rackCount = 0;
    let presetCount = 0;

    const scanDir = async (dir) => {
      const entries = await fs.readdir(dir, { withFileTypes: true });
      
      for (const entry of entries) {
        const fullPath = path.join(dir, entry.name);
        
        if (entry.isDirectory()) {
          await scanDir(fullPath);
        } else {
          if (entry.name.endsWith('.adg')) rackCount++;
          if (entry.name.endsWith('.adv')) presetCount++;
        }
      }
    };

    await scanDir(presetDir);

    this.sendProgress('scan', 100, `Found ${rackCount} racks, ${presetCount} presets`);
    
    return { racks: rackCount, presets: presetCount };
  }
(continuing installation logic)

  /**
   * Complete installation and save settings
   */
  async completeInstallation(settings) {
    const Store = require('electron-store');
    const store = new Store();

    store.set('setupCompleted', true);
    store.set('abletonPath', settings.abletonPath);
    store.set('userLibrary', settings.userLibrary);
    store.set('watchFolders', settings.watchFolders);
    store.set('versionTracking', settings.versionTracking);
    
    return {
      success: true,
      summary: {
        abletonPath: settings.abletonPath,
        userLibrary: settings.userLibrary,
        recipesFound: settings.recipesFound,
        versionTracking: settings.versionTracking
      }
    };
  }
}

module.exports = Installer;
```

**Task 3.2: Add IPC handlers to main process**
```javascript
// src/main/index.js (additions)
const AbletonDetector = require('./detector');
const Installer = require('./installer');

const detector = new AbletonDetector();
let installer;

app.whenReady().then(() => {
  createWindow();
  installer = new Installer(mainWindow);
  
  // Detection handlers
  ipcMain.handle('detect-ableton', async () => {
    return await detector.findAbletonInstallations();
  });

  ipcMain.handle('detect-library', async () => {
    return await detector.findUserLibrary();
  });

  ipcMain.handle('detect-dependencies', async () => {
    return await detector.checkDependencies();
  });

  ipcMain.handle('detect-claude', async () => {
    return await detector.checkClaudeDesktop();
  });

  // Installation handlers
  ipcMain.handle('install-abletonjs', async (event, remoteScriptsPath) => {
    return await installer.installAbletonJS(remoteScriptsPath);
  });

  ipcMain.handle('install-mcp-server', async () => {
    return await installer.installMCPServer();
  });

  ipcMain.handle('setup-watcher', async (event, folders) => {
    return await installer.setupFileWatcher(folders);
  });

  ipcMain.handle('update-claude-config', async () => {
    return await installer.updateClaudeConfig();
  });

  ipcMain.handle('scan-library', async (event, libraryPath) => {
    return await installer.scanUserLibrary(libraryPath);
  });

  // File dialog
  ipcMain.handle('select-folder', async () => {
    const { dialog } = require('electron');
    const result = await dialog.showOpenDialog({
      properties: ['openDirectory']
    });
    return result.canceled ? null : result.filePaths[0];
  });

  // Navigation
  ipcMain.on('open-dashboard', () => {
    mainWindow.loadFile(path.join(__dirname, '../renderer/dashboard.html'));
  });
});
```

**Deliverable:**
- Full installation logic implemented
- AbletonJS auto-install works
- MCP server copied to ~/Library/M4L-MCP
- LaunchAgent created for file watcher
- Claude Desktop config written
- User Library scanned

**Time Estimate:** 6-8 hours

---

## Day 4: File Watcher Service

### Morning: Watcher implementation

**Task 4.1: Create watcher service**
```javascript
// src/main/watcher.js
const chokidar = require('chokidar');
const Store = require('electron-store');
const { spawn } = require('child_process');
const path = require('path');

const store = new Store();

class FileWatcherService {
  constructor() {
    this.watchers = [];
  }

  start() {
    const userLibrary = store.get('userLibrary');
    const watchFolders = store.get('watchFolders', []);
    const versionTracking = store.get('versionTracking', true);

    if (userLibrary) {
      this.watchUserLibrary(userLibrary);
    }

    if (versionTracking && watchFolders.length > 0) {
      this.watchProjectFolders(watchFolders);
    }

    console.log('File watcher service started');
  }

  /**
   * Watch User Library for new racks/presets
   */
  watchUserLibrary(libraryPath) {
    const presetsPath = path.join(libraryPath, 'Presets/**/*.{adg,adv}');
    
    const watcher = chokidar.watch(presetsPath, {
      ignored: /(^|[\/\\])\../, // ignore dotfiles
      persistent: true,
      ignoreInitial: true, // don't trigger for existing files
      awaitWriteFinish: {
        stabilityThreshold: 2000,
        pollInterval: 100
      }
    });

    watcher.on('add', async (filePath) => {
      console.log('New recipe detected:', filePath);
      await this.handleNewRecipe(filePath);
    });

    this.watchers.push(watcher);
  }

  /**
   * Watch project folders for version saves
   */
  watchProjectFolders(folders) {
    for (const folder of folders) {
      const versionPattern = path.join(folder, '**/*_[0-9].[0-9].[0-9].als');
      
      const watcher = chokidar.watch(versionPattern, {
        ignored: /(^|[\/\\])\../,
        persistent: true,
        ignoreInitial: true,
        awaitWriteFinish: {
          stabilityThreshold: 2000,
          pollInterval: 100
        }
      });

      watcher.on('add', async (filePath) => {
        console.log('New version detected:', filePath);
        await this.handleNewVersion(filePath);
      });

      this.watchers.push(watcher);
    }
  }

  /**
   * Handle new recipe creation
   */
  async handleNewRecipe(filePath) {
    const mcpPath = path.join(process.env.HOME, 'Library/M4L-MCP');
    
    // Run PHP analyzer
    const analyzerScript = path.join(
      mcpPath,
      'analyzers/abletonRackAnalyzer/abletonRackAnalyzer-V7.php'
    );

    return new Promise((resolve) => {
      const process = spawn('php', [analyzerScript, filePath]);
      
      let output = '';
      process.stdout.on('data', (data) => {
        output += data.toString();
      });

      process.on('close', (code) => {
        if (code === 0) {
          try {
            const analysis = JSON.parse(output);
            console.log('Recipe analyzed:', analysis);
            
            // Send notification (via Electron if main process has reference)
            this.notifyNewRecipe(filePath, analysis);
            
            resolve(analysis);
          } catch (e) {
            console.error('Failed to parse analysis:', e);
            resolve(null);
          }
        } else {
          resolve(null);
        }
      });
    });
  }

  /**
   * Handle new version save
   */
  async handleNewVersion(filePath) {
    const mcpPath = path.join(process.env.HOME, 'Library/M4L-MCP');
    
    // Find previous version
    const match = filePath.match(/(.+)_(\d+)\.(\d+)\.(\d+)\.als$/);
    if (!match) return;

    const [, projectBase, major, minor, patch] = match;
    
    // Try to find previous version
    let previousVersion = null;
    const newPatch = parseInt(patch);
    
    if (newPatch > 0) {
      previousVersion = `${projectBase}_${major}.${minor}.${newPatch - 1}.als`;
    }

    if (!previousVersion || !require('fs').existsSync(previousVersion)) {
      console.log('No previous version found for comparison');
      return;
    }

    // Run Python diff
    const diffScript = path.join(
      mcpPath,
      'python-scripts/ableton_diff.py'
    );

    return new Promise((resolve) => {
      const process = spawn('python3', [diffScript, previousVersion, filePath]);
      
      let output = '';
      process.stdout.on('data', (data) => {
        output += data.toString();
      });

      process.on('close', (code) => {
        if (code === 0) {
          try {
            const diff = JSON.parse(output);
            console.log('Version diff:', diff);
            
            // Update timeline
            this.updateTimeline(projectBase);
            
            resolve(diff);
          } catch (e) {
            console.error('Failed to parse diff:', e);
            resolve(null);
          }
        } else {
          resolve(null);
        }
      });
    });
  }

  /**
   * Update project timeline
   */
  async updateTimeline(projectBase) {
    const mcpPath = path.join(process.env.HOME, 'Library/M4L-MCP');
    const visualizerScript = path.join(
      mcpPath,
      'python-scripts/ableton_visualizer.py'
    );

    const projectDir = path.dirname(projectBase);
    
    return new Promise((resolve) => {
      const process = spawn('python3', [visualizerScript, projectDir]);
      
      process.on('close', (code) => {
        if (code === 0) {
          console.log('Timeline updated');
        }
        resolve();
      });
    });
  }

  /**
   * Send notification about new recipe
   */
  notifyNewRecipe(filePath, analysis) {
    // This would connect to Electron notification system
    // For standalone service, we just log
    console.log('=== NEW RECIPE ===');
    console.log('Path:', filePath);
    console.log('Name:', analysis.name || path.basename(filePath));
    console.log('Devices:', analysis.devices ? analysis.devices.length : 0);
  }

  stop() {
    for (const watcher of this.watchers) {
      watcher.close();
    }
    this.watchers = [];
  }
}

// If run directly as LaunchAgent
if (require.main === module) {
  const service = new FileWatcherService();
  service.start();
  
  // Keep process alive
  process.on('SIGTERM', () => {
    service.stop();
    process.exit(0);
  });
}

module.exports = FileWatcherService;
```

**Deliverable:**
- File watcher monitors User Library for new .adg/.adv files
- File watcher monitors project folders for version saves
- Auto-runs PHP analyzer on new recipes
- Auto-runs Python diff on new versions
- Can run as LaunchAgent service

**Time Estimate:** 4 hours

---

### Afternoon: System tray & notifications

**Task 4.2: System tray implementation**
```javascript
// src/main/tray.js
const { Tray, Menu, nativeImage, Notification } = require('electron');
const path = require('path');

class TrayManager {
  constructor(mainWindow) {
    this.mainWindow = mainWindow;
    this.tray = null;
  }

  create() {
    const iconPath = path.join(__dirname, '../../assets/tray-icon.png');
    const icon = nativeImage.createFromPath(iconPath);
    
    this.tray = new Tray(icon.resize({ width: 16, height: 16 }));
    this.tray.setToolTip('Ableton Cookbook');
    
    this.updateMenu();
    
    this.tray.on('click', () => {
      this.mainWindow.show();
    });
  }

  updateMenu() {
    const contextMenu = Menu.buildFromTemplate([
      {
        label: 'Open Dashboard',
        click: () => {
          this.mainWindow.show();
        }
      },
      { type: 'separator' },
      {
        label: 'File Watcher',
        submenu: [
          {
            label: 'Running',
            type: 'checkbox',
            checked: true, // Check actual status
            click: (item) => {
              // Toggle watcher
            }
          }
        ]
      },
      {
        label: 'MCP Server',
        submenu: [
          {
            label: 'Connected',
            type: 'checkbox',
            checked: true,
            enabled: false
          }
        ]
      },
      { type: 'separator' },
      {
        label: 'Quick Upload Recipe',
        click: () => {
          // Open file picker for quick upload
        }
      },
      {
        label: 'View Recent Versions',
        click: () => {
          // Show recent version changes
        }
      },
      { type: 'separator' },
      {
        label: 'Settings',
        click: () => {
          // Open settings
        }
      },
      {
        label: 'Quit',
        click: () => {
          require('electron').app.quit();
        }
      }
    ]);
    
    this.tray.setContextMenu(contextMenu);
  }

  /**
   * Show notification for new recipe
   */
  notifyNewRecipe(recipeName, actions) {
    const notification = new Notification({
      title: 'New Recipe Created!',
      body: recipeName,
      icon: path.join(__dirname, '../../assets/icon.png'),
      actions: actions || [
        { type: 'button', text: 'Share' },
        { type: 'button', text: 'Keep Private' }
      ]
    });

    notification.on('action', (event, index) => {
      if (index === 0) {
        // Share action
        this.mainWindow.webContents.send('share-recipe', { recipeName });
      }
    });

    notification.show();
  }

  /**
   * Show notification for new version
   */
  notifyNewVersion(projectName, version, changeCount) {
    const notification = new Notification({
      title: 'New Version Saved',
      body: `${projectName} v${version} - ${changeCount} changes detected`,
      icon: path.join(__dirname, '../../assets/icon.png')
    });

    notification.on('click', () => {
      // Open timeline or change report
      this.mainWindow.webContents.send('view-version', { projectName, version });
    });

    notification.show();
  }
}

module.exports = TrayManager;
```

**Task 4.3: Integrate tray into main process**
```javascript
// src/main/index.js (additions)
const TrayManager = require('./tray');

let trayManager;

app.whenReady().then(() => {
  createWindow();
  
  // Create system tray
  trayManager = new TrayManager(mainWindow);
  trayManager.create();
  
  // ... rest of setup
});
```

**Deliverable:**
- System tray icon in menu bar
- Context menu with quick actions
- Notifications for new recipes
- Notifications for new versions
- Click notifications to open details

**Time Estimate:** 3 hours

---

## Day 5: Testing & Polish

### Full Day: End-to-end testing and refinement

**Task 5.1: Manual testing checklist**
```markdown
## Installation Test

- [ ] Download .dmg builds successfully
- [ ] Drag to Applications folder works
- [ ] First launch triggers setup wizard
- [ ] Detection phase finds:
  - [ ] Ableton Live installation
  - [ ] User Library location
  - [ ] Python 3.7+
  - [ ] PHP 7.4+
  - [ ] Claude Desktop (if installed)
- [ ] AbletonJS MIDI Remote Script installs correctly
- [ ] File permissions don't cause errors
- [ ] Installation completes without crashes
- [ ] Setup completion screen shows accurate summary

## File Watcher Test

- [ ] Create new rack in Ableton → Save to User Library
- [ ] Desktop app shows notification within 5 seconds
- [ ] Notification has "Share" and "Keep Private" buttons
- [ ] Save versioned project (project_0.1.0.als)
- [ ] Save next version (project_0.2.0.als)
- [ ] Notification appears with change count
- [ ] Timeline HTML updates automatically

## MCP Integration Test

- [ ] Restart Claude Desktop after installation
- [ ] MCP tools appear in Claude
- [ ] "List Live tracks" command works (with Live open)
- [ ] "Analyze rack" command works with .adg file
- [ ] "Get version history" command works
- [ ] Error messages are user-friendly

## UI/UX Test

- [ ] Setup wizard looks good on 13" and 15" screens
- [ ] All buttons respond to clicks
- [ ] Form inputs accept text
- [ ] Progress bar animates smoothly
- [ ] Detection status icons update correctly
- [ ] No broken images
- [ ] No console errors in DevTools

## System Tray Test

- [ ] Tray icon appears in menu bar
- [ ] Click tray icon shows menu
- [ ] "Open Dashboard" opens app window
- [ ] "Quit" actually quits app
- [ ] App can be reopened after quit
- [ ] Notifications don't spam (max 1 per minute)

## Edge Cases

- [ ] No Ableton installed → Clear error message
- [ ] No User Library → Allow manual selection
- [ ] Missing Python → Show installation instructions
- [ ] Missing PHP → Show installation instructions
- [ ] No Claude Desktop → Skip MCP config, still work
- [ ] User cancels setup → Can restart later
- [ ] Installation interrupted → Can resume
```

**Task 5.2: Fix bugs found during testing**
- Address any crashes
- Fix UI glitches
- Improve error messages
- Handle edge cases

**Task 5.3: Performance optimization**
- Reduce app bundle size if possible
- Optimize file watcher polling
- Cache detection results
- Lazy-load heavy modules

**Task 5.4: Add loading states**
- Spinner during detection
- Progress indicators during install
- Disable buttons while processing
- Show estimated time remaining

**Task 5.5: Create build script**
```json
// package.json additions
{
  "scripts": {
    "build:mac": "electron-builder --mac",
    "build:mac:dmg": "electron-builder --mac --target dmg",
    "release": "electron-builder --mac --publish always"
  }
}
```

**Deliverable:**
- Fully tested desktop app
- All critical bugs fixed
- Performant and responsive
- Builds successfully as .dmg
- Ready for beta testing

**Time Estimate:** 8 hours

---

## Week 1 Deliverables Summary

By end of week, you'll have:

✅ **Desktop App v0.1.0** (.dmg installer)
- Beautiful 8-step setup wizard
- Zero terminal commands required
- Auto-detects Ableton, User Library, dependencies
- One-click AbletonJS MIDI Remote Script install
- Automatic MCP server installation
- File watcher service (LaunchAgent)
- Claude Desktop config auto-update
- System tray with quick actions
- Notifications for new recipes/versions

✅ **Tested Features**
- Installation on clean macOS system
- File watching works in background
- Notifications appear correctly
- MCP tools work in Claude Desktop
- No crashes during normal use

✅ **Ready for Beta**
- Packaged .dmg file
- Installation instructions
- Known issues documented
- Feedback collection plan

---

## Success Metrics (Week 1)

- [ ] 10 beta testers can install without help
- [ ] 90% successful installation rate
- [ ] <5 critical bugs reported
- [ ] File watcher catches 95%+ of new files
- [ ] Average install time < 5 minutes
- [ ] Zero terminal commands needed

---

## Next Steps (Week 2)

Once Week 1 deliverable is solid:

1. **Dashboard UI** - Show recipes, versions, stats
2. **Upload Flow** - Connect to Laravel backend
3. **Settings Panel** - Privacy, preferences, account
4. **Recipe Browser** - Search/filter local recipes
5. **Windows Support** - Port installer to Windows

---

## Resources Needed

**Design Assets:**
- App icon (512x512, @2x)
- Tray icon (16x16, template image)
- DMG background image
- Loading spinners/animations

**External Services:**
- Code signing certificate (Apple Developer Account - $99/year)
- Notarization for macOS distribution

**Testing:**
- 3-5 beta testers with different macOS versions
- Clean test machines (VMs or borrowed Macs)

---

Ready to start building? Let me know which day/task you want to tackle first!
