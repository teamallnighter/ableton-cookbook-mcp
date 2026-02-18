<x-app-layout>

<div class="max-w-full mx-auto px-6 py-8">
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-black mb-2">Horizontal Tree View Demo</h1>
                <p class="text-gray-600">Flow-chart style representation of rack structure</p>
            </div>
            <div class="flex gap-4">
                <a href="{{ route('demos.rack-tree-vertical') }}" class="btn btn-secondary">
                    View Vertical Tree
                </a>
                <a href="{{ route('dashboard') }}" class="btn btn-primary">
                    Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    <div class="space-y-8">
        <!-- Tree View Panel -->
        <div class="card">
            <div class="card-header">
                <h2 class="text-xl font-semibold text-black">Horizontal Rack Structure Flow</h2>
                <p class="text-sm text-gray-600 mt-1">Scroll horizontally to see the full rack structure</p>
            </div>
            <div class="card-body p-0">
                <div id="horizontal-tree-container" class="p-6 bg-gradient-to-r from-gray-50 to-gray-100 min-h-[600px] overflow-auto">
                    <div class="horizontal-tree-view">
                        <div class="horizontal-flow">
                            <!-- Level 0: Root Rack -->
                            <div class="flow-level">
                                <div class="flow-node rack-node" data-level="0">
                                    <div class="node-content">
                                        <div class="node-icon">
                                            <i class="fa-kit-duotone fa-duotone-solid-gear-music text-purple-600 text-2xl"></i>
                                        </div>
                                        <div class="node-info">
                                            <div class="node-title">Audio Effect Rack</div>
                                                <div class="flex items-center gap-1 mt-1">
                                                    <i class="fas fa-heart text-red-500 text-sm"></i>
                                                    <span class="text-xs text-gray-500">Favorited</span>
                                                </div>
                                            <div class="node-subtitle">Main Rack</div>
                                            <div class="flex items-center gap-1 mt-1">
                                                <i class="fas fa-crown text-yellow-500 text-xs"></i>
                                                <span class="text-xs text-yellow-600 font-medium">Premium</span>
                                            </div>
                                            <div class="node-badge on">ON</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Connector Arrow -->
                            <div class="flow-connector">
                                <svg class="w-12 h-6 text-gray-400" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M14 7l5 5-5 5M3 12h16"></path>
                                </svg>
                            </div>

                            <!-- Level 1: Main Chains -->
                            <div class="flow-level">
                                <div class="flow-column">
                                    <!-- Chain 1: HIGH -->
                                    <div class="flow-node chain-node" data-level="1">
                                        <div class="node-content">
                                            <div class="node-icon">
                                                <i class="fas fa-link text-blue-600 text-xl"></i>
                                            </div>
                                            <div class="node-info">
                                                <div class="node-title">Chain: HIGH</div>
                                                <div class="node-subtitle">Frequency Band</div>
                                                <div class="node-badge normal">Active</div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Chain 2: LOW -->
                                    <div class="flow-node chain-node" data-level="1">
                                        <div class="node-content">
                                            <div class="node-icon">
                                                <i class="fas fa-link text-blue-600 text-xl"></i>
                                            </div>
                                            <div class="node-info">
                                                <div class="node-title">Chain: LOW</div>
                                                <div class="node-subtitle">Frequency Band</div>
                                                <div class="node-badge normal">1 device</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Connector Arrow -->
                            <div class="flow-connector">
                                <svg class="w-12 h-6 text-gray-400" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M14 7l5 5-5 5M3 12h16"></path>
                                </svg>
                            </div>

                            <!-- Level 2: Nested Rack and Device -->
                            <div class="flow-level">
                                <div class="flow-column">
                                    <!-- Nested Audio Effect Rack -->
                                    <div class="flow-node rack-node" data-level="2">
                                        <div class="node-content">
                                            <div class="node-icon">
                                                <i class="fa-kit-duotone fa-duotone-regular-square-sliders-vertical-gear-bl text-purple-600 text-xl"></i>
                                            </div>
                                            <div class="node-info">
                                                <div class="node-title">Audio Effect Rack</div>
                                                <div class="node-subtitle">Nested Processing</div>
                                                <div class="node-badge on">ON</div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- EQ Eight Device (from LOW chain) -->
                                    <div class="flow-node device-node" data-level="2">
                                        <div class="node-content">
                                            <div class="node-icon">
                                                <i class="fas fa-sliders-h text-green-600 text-xl"></i>
                                            </div>
                                            <div class="node-info">
                                                <div class="node-title">EQ Eight</div>
                                                <div class="node-subtitle">Frequency Control</div>
                                                <div class="node-badge on">ON</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Connector Arrow -->
                            <div class="flow-connector">
                                <svg class="w-12 h-6 text-gray-400" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M14 7l5 5-5 5M3 12h16"></path>
                                </svg>
                            </div>

                            <!-- Level 3: Nested Chains -->
                            <div class="flow-level">
                                <div class="flow-column">
                                    <!-- Nested Chain: DRY -->
                                    <div class="flow-node chain-node" data-level="3">
                                        <div class="node-content">
                                            <div class="node-icon">
                                                <i class="fas fa-link text-blue-600 text-xl"></i>
                                            </div>
                                            <div class="node-info">
                                                <div class="node-title">Chain: DRY</div>
                                                <div class="node-subtitle">Unprocessed Signal</div>
                                                <div class="node-badge empty">Empty</div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Nested Chain: LOW -->
                                    <div class="flow-node chain-node" data-level="3">
                                        <div class="node-content">
                                            <div class="node-icon">
                                                <i class="fas fa-link text-blue-600 text-xl"></i>
                                            </div>
                                            <div class="node-info">
                                                <div class="node-title">Chain: LOW</div>
                                                <div class="node-subtitle">Low Frequency</div>
                                                <div class="node-badge normal">2 devices</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Connector Arrow -->
                            <div class="flow-connector">
                                <svg class="w-12 h-6 text-gray-400" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M14 7l5 5-5 5M3 12h16"></path>
                                </svg>
                            </div>

                            <!-- Level 4: Final Devices -->
                            <div class="flow-level">
                                <div class="flow-column">
                                    <!-- EQ Eight Device -->
                                    <div class="flow-node device-node" data-level="4">
                                        <div class="node-content">
                                            <div class="node-icon">
                                                <i class="fas fa-sliders-h text-green-600 text-xl"></i>
                                            </div>
                                            <div class="node-info">
                                                <div class="node-title">EQ Eight</div>
                                                <div class="node-subtitle">Tone Shaping</div>
                                                <div class="node-badge on">ON</div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- StereoGain Device -->
                                    <div class="flow-node device-node" data-level="4">
                                        <div class="node-content">
                                            <div class="node-icon">
                                                <i class="fas fa-sliders-h text-green-600 text-xl"></i>
                                            </div>
                                            <div class="node-info">
                                                <div class="node-title">StereoGain</div>
                                                <div class="node-subtitle">Level Control</div>
                                                <div class="node-badge on">ON</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Control Panel -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Controls -->
            <div class="card">
                <div class="card-header">
                    <h3 class="text-lg font-semibold">View Controls</h3>
                </div>
                <div class="card-body">
                    <div class="space-y-4">
                        <div>
                            <label class="flex items-center gap-3">
                                <input type="checkbox" id="show-connections" checked class="form-checkbox">
                                <span class="text-sm">Show connection lines</span>
                            </label>
                        </div>
                        <div>
                            <label class="flex items-center gap-3">
                                <input type="checkbox" id="animate-flow" class="form-checkbox">
                                <span class="text-sm">Animate signal flow</span>
                                <span id="flow-status" class="text-xs text-gray-500 ml-2 hidden">🌊 Active</span>
                            </label>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-2">Zoom Level</label>
                            <input type="range" id="zoom-slider" min="0.5" max="1.5" step="0.1" value="1.0" class="w-full">
                            <div class="text-xs text-gray-600 mt-1">50% - 150%</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Information -->
            <div class="card">
                <div class="card-header">
                    <h3 class="text-lg font-semibold">Flow Information</h3>
                </div>
                <div class="card-body">
                    <div class="space-y-4">
                        <!-- Stats -->
                        <div class="grid grid-cols-3 gap-3 text-center">
                            <div class="bg-purple-50 p-3 rounded-lg">
                                <div class="text-xl font-bold text-purple-600">5</div>
                                <div class="text-xs text-gray-600">Processing Levels</div>
                            </div>
                            <div class="bg-blue-50 p-3 rounded-lg">
                                <div class="text-xl font-bold text-blue-600">4</div>
                                <div class="text-xs text-gray-600">Chain Splits</div>
                            </div>
                            <div class="bg-green-50 p-3 rounded-lg">
                            </div>
                            <div class="bg-orange-50 p-3 rounded-lg">
                                <div class="text-xl font-bold"><i class="fa-kit fa-ableton text-orange-600"></i></div>
                                <div class="text-xs text-gray-600">Live 11.3.4</div>
                            
                                <div class="text-xl font-bold text-green-600">5</div>
                                <div class="text-xs text-gray-600">Final Devices</div>
                            </div>
                        </div>

                        <!-- Features -->
                        <div>
                            <h4 class="font-semibold mb-2">Horizontal View Benefits</h4>
                            <ul class="space-y-1 text-sm text-gray-600">
                                <li class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    Signal flow visualization
                                </li>
                                <li class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    Processing levels clarity
                                </li>
                                <li class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    Parallel processing view
                                </li>
                                <li class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    Compact overview
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Horizontal Tree View Styles */
.horizontal-tree-view {
    min-width: 1200px;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
}

.horizontal-flow {
    display: flex;
    align-items: center;
    gap: 32px;
    min-height: 500px;
}

.flow-level {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.flow-column {
    display: flex;
    flex-direction: column;
    gap: 32px;
    align-items: center;
}

.flow-node {
    background: white;
    border-radius: 12px;
    border: 2px solid #e5e7eb;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    transition: all 0.3s;
    cursor: pointer;
    min-width: 200px;
    position: relative;
}

.flow-node:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px -5px rgba(0, 0, 0, 0.1);
}

.flow-node.rack-node {
    border-color: #a855f7;
    background: linear-gradient(135deg, #faf5ff 0%, #f3e8ff 100%);
}

.flow-node.chain-node {
    border-color: #3b82f6;
    background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
}

.flow-node.device-node {
    border-color: #10b981;
    background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
}

.node-content {
    padding: 16px;
    text-align: center;
}

.node-icon {
    display: flex;
    justify-content: center;
    margin-bottom: 8px;
}

.node-info {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
}

.node-title {
    font-weight: 600;
    font-size: 14px;
    color: #111827;
    line-height: 1.2;
}

.node-subtitle {
    font-size: 12px;
    color: #6b7280;
    font-weight: 500;
}

.node-badge {
    font-size: 10px;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 10px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 4px;
}

.node-badge.on {
    background-color: #dcfce7;
    color: #166534;
}

.node-badge.normal {
    background-color: #dbeafe;
    color: #1e40af;
}

.node-badge.empty {
    background-color: #f3f4f6;
    color: #6b7280;
}

.flow-connector {
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0.6;
    transition: opacity 0.3s;
}

.flow-connector:hover {
    opacity: 1;
}

/* Level-based styling */
.flow-node[data-level="0"] {
    transform: scale(1.1);
}

.flow-node[data-level="1"] {
    transform: scale(1.05);
}

.flow-node[data-level="2"] {
    transform: scale(1.0);
}

.flow-node[data-level="3"] {
    transform: scale(0.95);
}

.flow-node[data-level="4"] {
    transform: scale(0.9);
}


/* Enhanced Animation classes */
.animate-flow .flow-connector {
    animation: pulse 2s ease-in-out infinite;
}

.animate-flow .flow-connector svg {
    animation: flow-pulse 3s ease-in-out infinite;
}

/* Signal pulse effect for nodes */
.signal-pulse {
    animation: node-pulse 2s ease-in-out infinite;
}

/* Signal active path */
.signal-active {
    animation: signal-flow 1s ease-in-out;
}

.signal-active svg {
    color: #3b82f6 !important;
    filter: drop-shadow(0 0 8px rgba(59, 130, 246, 0.6));
}

@keyframes pulse {
    0%, 100% {
        opacity: 0.6;
        transform: scale(1);
    }
    50% {
        opacity: 1;
        transform: scale(1.05);
    }
}

@keyframes flow-pulse {
    0%, 100% {
        transform: translateX(0);
        opacity: 0.6;
    }
    25% {
        transform: translateX(2px);
        opacity: 0.8;
    }
    50% {
        transform: translateX(4px);
        opacity: 1;
    }
    75% {
        transform: translateX(2px);
        opacity: 0.8;
    }
}

@keyframes node-pulse {
    0%, 100% {
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }
    50% {
        box-shadow: 0 8px 25px -5px rgba(59, 130, 246, 0.3);
        transform: translateY(-1px) scale(1.02);
    }
}

@keyframes signal-flow {
    0% {
        opacity: 0.6;
        transform: scale(1);
    }
    50% {
        opacity: 1;
        transform: scale(1.1);
    }
    100% {
        opacity: 0.8;
        transform: scale(1.05);
    }
}

/* Enhanced connection lines */
.show-connections .flow-connector::after {
    content: '';
    position: absolute;
    top: 50%;
    left: -16px;
    right: -16px;
    height: 3px;
    background: linear-gradient(90deg, #e5e7eb 0%, #3b82f6 20%, #10b981 50%, #3b82f6 80%, #e5e7eb 100%);
    transform: translateY(-50%);
    z-index: -1;
    border-radius: 2px;
}

.animate-flow.show-connections .flow-connector::after {
    animation: connection-flow 4s ease-in-out infinite;
}

@keyframes connection-flow {
    0%, 100% {
        background: linear-gradient(90deg, #e5e7eb 0%, #3b82f6 20%, #10b981 50%, #3b82f6 80%, #e5e7eb 100%);
        opacity: 0.7;
    }
    25% {
        background: linear-gradient(90deg, #3b82f6 0%, #10b981 20%, #f59e0b 50%, #10b981 80%, #3b82f6 100%);
        opacity: 1;
    }
    50% {
        background: linear-gradient(90deg, #10b981 0%, #f59e0b 20%, #ef4444 50%, #f59e0b 80%, #10b981 100%);
        opacity: 1;
    }
    75% {
        background: linear-gradient(90deg, #f59e0b 0%, #ef4444 20%, #10b981 50%, #ef4444 80%, #f59e0b 100%);
        opacity: 1;
    }
}

/* Improved hover effects */
.flow-node:hover {
    transform: translateY(-2px) scale(1.02);
    box-shadow: 0 8px 25px -5px rgba(0, 0, 0, 0.15);
}

.animate-flow .flow-node:hover {
    box-shadow: 0 8px 25px -5px rgba(59, 130, 246, 0.4);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('horizontal-tree-container');
    const treeView = document.querySelector('.horizontal-tree-view');
    const showConnectionsCheck = document.getElementById('show-connections');
    const animateFlowCheck = document.getElementById('animate-flow');
    const zoomSlider = document.getElementById('zoom-slider');
    
    // Show/hide connections
    showConnectionsCheck.addEventListener('change', function() {
        if (this.checked) {
            container.classList.add('show-connections');
        } else {
            container.classList.remove('show-connections');
        }
    });
    
    // Enhanced Animate flow
    animateFlowCheck.addEventListener('change', function() {
        if (this.checked) {
            container.classList.add('animate-flow');
            document.getElementById("flow-status").classList.remove("hidden");
            // Add pulsing effect to nodes as well
            document.querySelectorAll('.flow-node').forEach((node, index) => {
                setTimeout(() => {
                    node.classList.add('signal-pulse');
                }, index * 200);
            });
        } else {
            container.classList.remove('animate-flow');
            document.getElementById("flow-status").classList.add("hidden");
            document.querySelectorAll('.flow-node').forEach(node => {
                node.classList.remove('signal-pulse');
            });
        }
    });
    
    // Zoom functionality
    zoomSlider.addEventListener('input', function() {
        const zoomLevel = parseFloat(this.value);
        treeView.style.transform = `scale(${zoomLevel})`;
        treeView.classList.add('zoomed');
    });
    
    // Node click interactions
    document.querySelectorAll('.flow-node').forEach(node => {
        node.addEventListener('click', function() {
            // Remove active class from all nodes
            document.querySelectorAll('.flow-node').forEach(n => n.classList.remove('active'));
            
            // Add active class to clicked node
            this.classList.add('active');
            
            // Show signal path from root to this node
            if (animateFlowCheck.checked) {
                showSignalPath(this);
            }
            
            console.log('Selected node:', this.querySelector('.node-title').textContent);
        });
    });
    
    // Signal path visualization
    function showSignalPath(targetNode) {
        // Reset all connectors
        document.querySelectorAll('.flow-connector').forEach(connector => {
            connector.classList.remove('signal-active');
        });
        
        // Add signal path effect
        const level = parseInt(targetNode.dataset.level);
        document.querySelectorAll('.flow-connector').forEach((connector, index) => {
            if (index < level) {
                setTimeout(() => {
                    connector.classList.add('signal-active');
                }, index * 300);
            }
        });
    }
    
    // Initialize with connections visible
    container.classList.add('show-connections');
});
</script>

</x-app-layout>
