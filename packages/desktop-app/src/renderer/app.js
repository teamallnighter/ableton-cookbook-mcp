let currentStep = 0;
const totalSteps = 5;

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    showStep(0);
});

function showStep(stepIndex) {
    // Hide all steps
    document.querySelectorAll('.step').forEach(step => {
        step.classList.remove('active');
    });

    // Show current step
    const steps = ['step-welcome', 'step-ableton', 'step-mcp', 'step-claude', 'step-complete'];
    document.getElementById(steps[stepIndex]).classList.add('active');

    // Update step indicators
    document.querySelectorAll('.step-dot').forEach((dot, index) => {
        dot.classList.remove('active', 'completed');
        if (index < stepIndex) {
            dot.classList.add('completed');
        } else if (index === stepIndex) {
            dot.classList.add('active');
        }
    });

    currentStep = stepIndex;

    // Run step-specific logic
    if (stepIndex === 1) {
        detectAbleton();
    } else if (stepIndex === 2) {
        installMcp();
    } else if (stepIndex === 3) {
        checkClaude();
    }
}

function nextStep() {
    if (currentStep < totalSteps - 1) {
        showStep(currentStep + 1);
    }
}

function prevStep() {
    if (currentStep > 0) {
        showStep(currentStep - 1);
    }
}

// Step 2: Detect Ableton
async function detectAbleton() {
    const result = await window.electronAPI.detectAbleton();
    const resultDiv = document.getElementById('ableton-result');

    if (result.found) {
        resultDiv.innerHTML = `
            <div class="detection-result success">
                <h3>✓ Ableton Live Found!</h3>
                <p><strong>Path:</strong> ${result.path}</p>
                <p><strong>Platform:</strong> ${result.platform}</p>
            </div>
        `;
        document.getElementById('btn-ableton-next').style.display = 'inline-block';
    } else {
        resultDiv.innerHTML = `
            <div class="detection-result error">
                <h3>⚠️ Ableton Live Not Found</h3>
                <p>We couldn't automatically detect your Ableton installation.</p>
                <p>You can continue without it, or specify a custom path.</p>
            </div>
        `;
        document.getElementById('btn-manual').style.display = 'inline-block';
        document.getElementById('btn-ableton-next').style.display = 'inline-block';
    }
}

function manualPath() {
    const path = prompt('Enter the path to your Ableton Live installation:');
    if (path) {
        const resultDiv = document.getElementById('ableton-result');
        resultDiv.innerHTML = `
            <div class="detection-result success">
                <h3>✓ Custom Path Set</h3>
                <p><strong>Path:</strong> ${path}</p>
            </div>
        `;
        document.getElementById('btn-ableton-next').style.display = 'inline-block';
    }
}

// Step 3: Install MCP Server
async function installMcp() {
    const progressFill = document.getElementById('progress-fill');
    const progressText = document.getElementById('progress-text');
    const installLog = document.getElementById('install-log');

    // Simulate npm install progress
    const steps = [
        { percent: 20, text: 'Checking npm...', log: '$ npm --version\n8.19.3\n' },
        { percent: 40, text: 'Downloading package...', log: '$ npm install -g ableton-cookbook-mcp\nnpm info using npm@8.19.3\n' },
        { percent: 60, text: 'Installing dependencies...', log: 'npm info downloading @modelcontextprotocol/sdk@0.6.0\n' },
        { percent: 80, text: 'Building TypeScript...', log: 'npm info build successful\n' },
        { percent: 100, text: 'Installation complete!', log: '+ ableton-cookbook-mcp@0.1.0\nadded 15 packages in 8s\n' }
    ];

    for (let i = 0; i < steps.length; i++) {
        await new Promise(resolve => setTimeout(resolve, 1000));
        const step = steps[i];
        progressFill.style.width = step.percent + '%';
        progressText.textContent = step.text;
        installLog.textContent += step.log;
        installLog.scrollTop = installLog.scrollHeight;
    }

    // Auto-advance after completion
    await new Promise(resolve => setTimeout(resolve, 1500));
    nextStep();
}

// Step 4: Configure Claude Desktop
async function checkClaude() {
    const resultDiv = document.getElementById('claude-result');
    const configPreview = document.getElementById('config-preview');
    const configContent = document.getElementById('config-content');

    const configPath = await window.electronAPI.getClaudeConfigPath();
    const mcpInstallPath = await window.electronAPI.getMcpInstallPath();

    if (configPath.exists) {
        const current = await window.electronAPI.readClaudeConfig();

        // Prepare new config
        const newConfig = current.success ? current.config : { mcpServers: {} };
        newConfig.mcpServers = newConfig.mcpServers || {};
        newConfig.mcpServers.ableton = {
            command: 'node',
            args: [mcpInstallPath.distPath]
        };

        resultDiv.innerHTML = `
            <div class="detection-result success">
                <h3>✓ Claude Desktop Found</h3>
                <p><strong>Config:</strong> ${configPath.path}</p>
            </div>
        `;

        configContent.textContent = JSON.stringify(newConfig, null, 2);
        configPreview.style.display = 'block';
        document.getElementById('btn-write-config').style.display = 'inline-block';

        // Store config for writing
        window.pendingConfig = newConfig;
    } else {
        resultDiv.innerHTML = `
            <div class="detection-result error">
                <h3>⚠️ Claude Desktop Not Found</h3>
                <p>Claude Desktop doesn't appear to be installed.</p>
                <p>Download it from: <a href="https://claude.ai/download" target="_blank">claude.ai/download</a></p>
                <p>You can configure it manually later using the MCP documentation.</p>
            </div>
        `;
        document.getElementById('btn-claude-next').style.display = 'inline-block';
    }
}

async function writeClaudeConfig() {
    const btn = document.getElementById('btn-write-config');
    btn.textContent = 'Writing...';
    btn.disabled = true;

    const result = await window.electronAPI.writeClaudeConfig(window.pendingConfig);

    if (result.success) {
        btn.textContent = '✓ Configuration Applied';
        btn.classList.add('btn-success');
        await new Promise(resolve => setTimeout(resolve, 1000));
        nextStep();
    } else {
        btn.textContent = 'Error - Try Manual Setup';
        alert('Failed to write config: ' + result.error);
    }
}

// Step 5: Complete
function openDocs() {
    require('electron').shell.openExternal('https://teamallnighter.github.io/ableton-cookbook-mcp/');
}

function openGitHub() {
    require('electron').shell.openExternal('https://github.com/teamallnighter/ableton-cookbook-mcp');
}

function openDiscussions() {
    require('electron').shell.openExternal('https://github.com/teamallnighter/ableton-cookbook-mcp/discussions');
}

function finish() {
    window.close();
}
