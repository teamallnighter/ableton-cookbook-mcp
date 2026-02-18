<x-app-layout>

<div class="max-w-7xl mx-auto px-6 py-8">
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-black mb-2">Vertical Tree View Demo</h1>
                <p class="text-gray-600">Traditional tree view representation of rack structure</p>
            </div>
            <div class="flex gap-4">
                <a href="{{ route('demos.rack-tree-horizontal') }}" class="btn btn-secondary">
                    View Horizontal Tree
                </a>
                <a href="{{ route('dashboard') }}" class="btn btn-primary">
                    Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Tree View Panel -->
        <div class="card">
            <div class="card-header">
                <h2 class="text-xl font-semibold text-black">Rack Structure Tree</h2>
            </div>
            <div class="card-body p-0">
                <div id="tree-container" class="p-6 bg-gray-50 min-h-[600px] overflow-auto">
                    <div class="tree-view">
                        <!-- Root Rack -->
                        <div class="tree-node root-node">
                            <div class="tree-item-wrapper">
                                <span class="tree-toggle" onclick="toggleNode(this)">
                                    <svg class="w-4 h-4 transition-transform duration-200" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 111.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                </span>
                                <div class="tree-item rack-item">
                                    <div class="tree-icon">
                                        <i class="fas fa-th-large text-purple-600 text-xl"></i>
                                    </div>
                                    <span class="tree-label">Audio Effect Rack</span>
                                    <span class="tree-badge on">ON</span>
                                </div>
                            </div>
                            
                            <div class="tree-children">
                                <!-- Chain 1: HIGH -->
                                <div class="tree-node chain-node">
                                    <div class="tree-item-wrapper">
                                        <span class="tree-toggle" onclick="toggleNode(this)">
                                            <svg class="w-4 h-4 transition-transform duration-200" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 111.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                                            </svg>
                                        </span>
                                        <div class="tree-item chain-item">
                                            <div class="tree-icon">
                                                <i class="fas fa-link text-blue-600 text-lg"></i>
                                            </div>
                                            <span class="tree-label">Chain: HIGH</span>
                                            <span class="tree-badge normal">Active</span>
                                        </div>
                                    </div>
                                    
                                    <div class="tree-children">
                                        <!-- Nested Audio Effect Rack -->
                                        <div class="tree-node device-node">
                                            <div class="tree-item-wrapper">
                                                <span class="tree-toggle" onclick="toggleNode(this)">
                                                    <svg class="w-4 h-4 transition-transform duration-200" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 111.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                                                    </svg>
                                                </span>
                                                <div class="tree-item device-item">
                                                    <div class="tree-icon">
                                                        <i class="fas fa-layer-group text-purple-600 text-xl"></i>
                                                    </div>
                                                    <span class="tree-label">Audio Effect Rack</span>
                                                    <span class="tree-badge on">ON</span>
                                                </div>
                                            </div>
                                            
                                            <div class="tree-children">
                                                <!-- Nested Chain: DRY -->
                                                <div class="tree-node chain-node">
                                                    <div class="tree-item-wrapper">
                                                        <span class="tree-connector"></span>
                                                        <div class="tree-item chain-item">
                                                            <div class="tree-icon">
                                                                <i class="fas fa-link text-blue-600 text-lg"></i>
                                                            </div>
                                                            <span class="tree-label">Chain: DRY</span>
                                                            <span class="tree-badge empty">Empty</span>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <!-- Nested Chain: LOW -->
                                                <div class="tree-node chain-node">
                                                    <div class="tree-item-wrapper">
                                                        <span class="tree-toggle" onclick="toggleNode(this)">
                                                            <svg class="w-4 h-4 transition-transform duration-200" fill="currentColor" viewBox="0 0 20 20">
                                                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 111.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                                                            </svg>
                                                        </span>
                                                        <div class="tree-item chain-item">
                                                            <div class="tree-icon">
                                                                <i class="fas fa-link text-blue-600 text-lg"></i>
                                                            </div>
                                                            <span class="tree-label">Chain: LOW</span>
                                                            <span class="tree-badge normal">2 devices</span>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="tree-children">
                                                        <!-- EQ Eight Device -->
                                                        <div class="tree-node device-node">
                                                            <div class="tree-item-wrapper">
                                                                <span class="tree-connector"></span>
                                                                <div class="tree-item device-item">
                                                                    <div class="tree-icon">
                                                                        <i class="fas fa-sliders-h text-green-600 text-lg"></i>
                                                                    </div>
                                                                    <span class="tree-label">EQ Eight</span>
                                                                    <span class="tree-badge on">ON</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        
                                                        <!-- StereoGain Device -->
                                                        <div class="tree-node device-node">
                                                            <div class="tree-item-wrapper">
                                                                <span class="tree-connector"></span>
                                                                <div class="tree-item device-item">
                                                                    <div class="tree-icon">
                                                                        <i class="fas fa-sliders-h text-green-600 text-lg"></i>
                                                                    </div>
                                                                    <span class="tree-label">StereoGain</span>
                                                                    <span class="tree-badge on">ON</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Chain 2: LOW -->
                                <div class="tree-node chain-node">
                                    <div class="tree-item-wrapper">
                                        <span class="tree-toggle" onclick="toggleNode(this)">
                                            <svg class="w-4 h-4 transition-transform duration-200" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 111.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                                            </svg>
                                        </span>
                                        <div class="tree-item chain-item">
                                            <div class="tree-icon">
                                                <i class="fas fa-link text-blue-600 text-lg"></i>
                                            </div>
                                            <span class="tree-label">Chain: LOW</span>
                                            <span class="tree-badge normal">1 device</span>
                                        </div>
                                    </div>
                                    
                                    <div class="tree-children">
                                        <!-- EQ Eight Device -->
                                        <div class="tree-node device-node">
                                            <div class="tree-item-wrapper">
                                                <span class="tree-connector"></span>
                                                <div class="tree-item device-item">
                                                    <div class="tree-icon">
                                                        <i class="fas fa-volume-up text-green-600 text-lg"></i>
                                                    </div>
                                                    <span class="tree-label">EQ Eight</span>
                                                    <span class="tree-badge on">ON</span>
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
        </div>

        <!-- Information Panel -->
        <div class="card">
            <div class="card-header">
                <h2 class="text-xl font-semibold text-black">Rack Information</h2>
            </div>
            <div class="card-body">
                <div class="space-y-6">
                    <!-- Stats -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <div class="text-2xl font-bold text-purple-600">5</div>
                            <div class="text-sm text-gray-600">Total Devices</div>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <div class="text-2xl font-bold text-blue-600">4</div>
                            <div class="text-sm text-gray-600">Chains</div>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <div class="text-2xl font-bold"><i class="fa-kit fa-circle-ableton text-orange-500"></i></div>
                            <div class="text-sm text-gray-600">Live Suite</div>
                        </div>
                    </div>
                    
                    <!-- Legend -->
                    <div>
                        <h3 class="text-lg font-semibold mb-3">Legend</h3>
                        <div class="space-y-3">
                            <div class="flex items-center gap-3">
                                <div class="tree-icon">
                                    <i class="fas fa-th-large text-purple-600 text-xl"></i>
                                </div>
                                <span class="text-sm">Rack Device</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="tree-icon">
                                    <i class="fas fa-link text-blue-600 text-lg"></i>
                                </div>
                                <span class="text-sm">Chain</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="tree-icon">
                                    <i class="fas fa-sliders-h text-green-600 text-lg"></i>
                                </div>
                                <span class="text-sm">Device</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Features -->
                    <div>
                        <h3 class="text-lg font-semibold mb-3">Features</h3>
                        <ul class="space-y-2 text-sm text-gray-600">
                            <li class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                                Collapsible tree nodes
                            </li>
                            <li class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                                Color-coded device types
                            </li>
                            <li class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                                Status indicators
                            </li>
                            <li class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                                Hierarchical structure
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Tree View Styles */
.tree-view {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    line-height: 1.6;
}

.tree-node {
    margin: 0;
}

.tree-item-wrapper {
    display: flex;
    align-items: center;
    padding: 4px 0;
    min-height: 32px;
}

.tree-toggle {
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: #6b7280;
    margin-right: 4px;
    border-radius: 4px;
    transition: all 0.2s;
}

.tree-toggle:hover {
    background-color: #e5e7eb;
    color: #374151;
}

.tree-toggle.collapsed svg {
    transform: rotate(-90deg);
}

.tree-connector {
    width: 20px;
    height: 20px;
    margin-right: 4px;
}

.tree-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 12px;
    border-radius: 8px;
    transition: all 0.2s;
    cursor: pointer;
    min-height: 36px;
}

.tree-item:hover {
    background-color: #f3f4f6;
}

.tree-icon {
    flex-shrink: 0;
}

.tree-label {
    font-weight: 500;
    color: #111827;
    flex-grow: 1;
}

.tree-badge {
    font-size: 11px;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.tree-badge.on {
    background-color: #dcfce7;
    color: #166534;
}

.tree-badge.normal {
    background-color: #dbeafe;
    color: #1e40af;
}

.tree-badge.empty {
    background-color: #f3f4f6;
    color: #6b7280;
}

.tree-children {
    margin-left: 24px;
    border-left: 1px solid #e5e7eb;
    padding-left: 0;
}

.root-node > .tree-children {
    margin-left: 24px;
}

.chain-node .tree-item {
    background-color: #fafbff;
}

.device-node .tree-item {
    background-color: #f0fdf4;
}

/* Collapsed state */
.tree-node.collapsed > .tree-children {
    display: none;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .tree-children {
        margin-left: 16px;
    }
    
    .tree-item {
        padding: 4px 8px;
    }
}
</style>

<script>
function toggleNode(toggle) {
    const node = toggle.closest('.tree-node');
    const isCollapsed = node.classList.contains('collapsed');
    
    if (isCollapsed) {
        node.classList.remove('collapsed');
        toggle.classList.remove('collapsed');
    } else {
        node.classList.add('collapsed');
        toggle.classList.add('collapsed');
    }
}

// Initialize tree view - expand all by default
document.addEventListener('DOMContentLoaded', function() {
    // All nodes start expanded, so no initialization needed
});
</script>
</x-app-layout>
