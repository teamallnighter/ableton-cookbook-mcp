const { app, BrowserWindow, ipcMain } = require('electron');
const path = require('path');
const fs = require('fs');
const os = require('os');

let mainWindow;

function createWindow() {
    mainWindow = new BrowserWindow({
        width: 900,
        height: 700,
        minWidth: 800,
        minHeight: 600,
        webPreferences: {
            preload: path.join(__dirname, '../preload/preload.js'),
            contextIsolation: true,
            nodeIntegration: false
        },
        titleBarStyle: 'hiddenInset',
        backgroundColor: '#667eea',
        show: false
    });

    mainWindow.loadFile(path.join(__dirname, '../renderer/index.html'));

    // Show window when ready
    mainWindow.once('ready-to-show', () => {
        mainWindow.show();
    });

    // Open DevTools in development
    if (process.argv.includes('--dev')) {
        mainWindow.webContents.openDevTools();
    }

    mainWindow.on('closed', () => {
        mainWindow = null;
    });
}

app.whenReady().then(createWindow);

app.on('window-all-closed', () => {
    if (process.platform !== 'darwin') {
        app.quit();
    }
});

app.on('activate', () => {
    if (BrowserWindow.getAllWindows().length === 0) {
        createWindow();
    }
});

// IPC Handlers

// Detect Ableton Live installation
ipcMain.handle('detect-ableton', async () => {
    const platform = process.platform;
    let abletonPath = null;
    let found = false;

    if (platform === 'darwin') {
        // macOS paths
        const possiblePaths = [
            '/Applications/Ableton Live 12 Suite.app',
            '/Applications/Ableton Live 11 Suite.app',
            '/Applications/Ableton Live 12 Standard.app',
            '/Applications/Ableton Live 11 Standard.app',
            '/Applications/Ableton Live 12 Intro.app',
            '/Applications/Ableton Live 11 Intro.app'
        ];

        for (const p of possiblePaths) {
            if (fs.existsSync(p)) {
                abletonPath = p;
                found = true;
                break;
            }
        }
    } else if (platform === 'win32') {
        // Windows paths
        const possiblePaths = [
            'C:\\ProgramData\\Ableton\\Live 12 Suite',
            'C:\\ProgramData\\Ableton\\Live 11 Suite',
            'C:\\ProgramData\\Ableton\\Live 12 Standard',
            'C:\\ProgramData\\Ableton\\Live 11 Standard'
        ];

        for (const p of possiblePaths) {
            if (fs.existsSync(p)) {
                abletonPath = p;
                found = true;
                break;
            }
        }
    }

    return {
        found,
        path: abletonPath,
        platform
    };
});

// Get Claude Desktop config path
ipcMain.handle('get-claude-config-path', () => {
    const platform = process.platform;
    let configPath;

    if (platform === 'darwin') {
        configPath = path.join(
            os.homedir(),
            'Library',
            'Application Support',
            'Claude',
            'claude_desktop_config.json'
        );
    } else if (platform === 'win32') {
        configPath = path.join(
            process.env.APPDATA,
            'Claude',
            'claude_desktop_config.json'
        );
    }

    return {
        path: configPath,
        exists: fs.existsSync(configPath)
    };
});

// Read Claude config
ipcMain.handle('read-claude-config', async () => {
    const { path: configPath, exists } = await ipcMain.handlers.get('get-claude-config-path')();

    if (!exists) {
        return { success: false, config: null };
    }

    try {
        const config = JSON.parse(fs.readFileSync(configPath, 'utf8'));
        return { success: true, config };
    } catch (error) {
        return { success: false, error: error.message };
    }
});

// Write Claude config
ipcMain.handle('write-claude-config', async (event, config) => {
    const { path: configPath } = await ipcMain.handlers.get('get-claude-config-path')();

    try {
        // Ensure directory exists
        const dir = path.dirname(configPath);
        if (!fs.existsSync(dir)) {
            fs.mkdirSync(dir, { recursive: true });
        }

        fs.writeFileSync(configPath, JSON.stringify(config, null, 2), 'utf8');
        return { success: true };
    } catch (error) {
        return { success: false, error: error.message };
    }
});

// Get MCP server install path
ipcMain.handle('get-mcp-install-path', () => {
    // Use global npm modules path
    const platform = process.platform;
    let npmGlobalPath;

    if (platform === 'darwin' || platform === 'linux') {
        npmGlobalPath = path.join(os.homedir(), '.npm-global', 'lib', 'node_modules', 'ableton-cookbook-mcp');
    } else {
        npmGlobalPath = path.join(process.env.APPDATA, 'npm', 'node_modules', 'ableton-cookbook-mcp');
    }

    return {
        path: npmGlobalPath,
        distPath: path.join(npmGlobalPath, 'dist', 'index.js')
    };
});

// Check if MCP server is installed
ipcMain.handle('check-mcp-installed', async () => {
    const { path: mcpPath } = await ipcMain.handlers.get('get-mcp-install-path')();
    return fs.existsSync(mcpPath);
});

console.log('Ableton Cookbook Desktop Installer ready!');
