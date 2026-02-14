const { contextBridge, ipcRenderer } = require('electron');

// Expose protected methods to renderer
contextBridge.exposeInMainWorld('electronAPI', {
    detectAbleton: () => ipcRenderer.invoke('detect-ableton'),
    getClaudeConfigPath: () => ipcRenderer.invoke('get-claude-config-path'),
    readClaudeConfig: () => ipcRenderer.invoke('read-claude-config'),
    writeClaudeConfig: (config) => ipcRenderer.invoke('write-claude-config', config),
    getMcpInstallPath: () => ipcRenderer.invoke('get-mcp-install-path'),
    checkMcpInstalled: () => ipcRenderer.invoke('check-mcp-installed')
});
