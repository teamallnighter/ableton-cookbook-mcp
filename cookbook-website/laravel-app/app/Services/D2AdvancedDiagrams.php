<?php

namespace App\Services;

use App\Models\LearningPath;
use App\Models\EnhancedCollection;
use Illuminate\Support\Collection;

/**
 * D2 Advanced Diagrams Service
 * 
 * Generates sophisticated D2 diagrams for learning paths, bundle visualizations,
 * optimization comparisons, and system architecture overviews
 */
class D2AdvancedDiagrams 
{
    private D2StyleThemes $styleThemes;

    public function __construct(D2StyleThemes $styleThemes)
    {
        $this->styleThemes = $styleThemes;
    }

    /**
     * Generate learning path progression diagram
     */
    public function generateLearningPathDiagram(LearningPath $learningPath, string $style = 'sketch'): string
    {
        $d2 = "# Learning Path: {$learningPath->title}\n";
        $d2 .= "direction: down\n";
        $d2 .= "theme: " . $this->styleThemes->getTheme($style)['d2_theme_id'] . "\n\n";

        $d2 .= "learning_path: |\n";
        $d2 .= "  🎓 {$learningPath->title}\n";
        $d2 .= "  Difficulty: {$learningPath->difficulty_level}\n";
        $d2 .= "  Duration: {$learningPath->estimated_duration}min\n";
        $d2 .= "| {\n";
        $d2 .= "  style.fill: '#6366f1'\n";
        $d2 .= "  style.stroke: '#ffffff'\n";
        $d2 .= "  style.stroke-width: 3\n";
        $d2 .= "}\n\n";

        // Add skill progression levels
        $skillLevels = ['Beginner', 'Intermediate', 'Advanced', 'Expert'];
        $currentLevel = array_search($learningPath->difficulty_level, $skillLevels) ?: 0;

        $d2 .= "# Skill Progression\n";
        $d2 .= "progression: Skill Development {\n";
        $d2 .= "  direction: right\n\n";

        foreach ($skillLevels as $index => $level) {
            $levelId = "level_" . ($index + 1);
            $isActive = $index <= $currentLevel;
            $isCurrent = $index === $currentLevel;
            
            $d2 .= "  {$levelId}: |\n";
            $d2 .= "    {$level}\n";
            if ($isCurrent) {
                $d2 .= "    (Current Target)\n";
            }
            $d2 .= "  | {\n";
            
            if ($isActive) {
                $d2 .= "    style.fill: '#10b981'\n";
                $d2 .= "    style.stroke: '#ffffff'\n";
            } else {
                $d2 .= "    style.fill: '#6b7280'\n";
                $d2 .= "    style.opacity: 0.5\n";
            }
            
            if ($isCurrent) {
                $d2 .= "    style.stroke: '#fbbf24'\n";
                $d2 .= "    style.stroke-width: 3\n";
            }
            
            $d2 .= "  }\n\n";
            
            // Add connections
            if ($index > 0) {
                $prevLevel = "level_{$index}";
                $d2 .= "  {$prevLevel} -> {$levelId}\n";
            }
        }
        
        $d2 .= "}\n\n";

        // Add learning modules/milestones
        $d2 .= $this->generateLearningModules($learningPath);

        // Add progress tracking
        $d2 .= $this->generateProgressTracking($learningPath);

        return $d2;
    }

    /**
     * Generate bundle/collection overview diagram
     */
    public function generateBundleOverview(EnhancedCollection $collection, string $style = 'technical'): string
    {
        $d2 = "# Collection: {$collection->title}\n";
        $d2 .= "direction: right\n";
        $d2 .= "theme: " . $this->styleThemes->getTheme($style)['d2_theme_id'] . "\n\n";

        $d2 .= "bundle: |\n";
        $d2 .= "  📦 {$collection->title}\n";
        $d2 .= "  Type: {$collection->type}\n";
        $d2 .= "  Items: {$collection->items_count}\n";
        $d2 .= "| {\n";
        $d2 .= "  style.fill: '#8b5cf6'\n";
        $d2 .= "  style.stroke: '#ffffff'\n";
        $d2 .= "  style.stroke-width: 2\n";
        $d2 .= "}\n\n";

        // Content breakdown
        $d2 .= "contents: Collection Contents {\n";
        $d2 .= "  style.fill: '#1e293b'\n\n";

        // Add different content types
        $contentTypes = [
            'racks' => ['icon' => '🎛️', 'color' => '#ef4444'],
            'presets' => ['icon' => '🎹', 'color' => '#3b82f6'],
            'sessions' => ['icon' => '🎵', 'color' => '#10b981'],
            'articles' => ['icon' => '📝', 'color' => '#f59e0b'],
            'videos' => ['icon' => '🎬', 'color' => '#8b5cf6']
        ];

        foreach ($contentTypes as $type => $info) {
            $d2 .= "  {$type}: |\n";
            $d2 .= "    {$info['icon']} " . ucfirst($type) . "\n";
            $d2 .= "    Count: 0\n"; // Would be calculated from actual items
            $d2 .= "  | {\n";
            $d2 .= "    style.fill: '{$info['color']}'\n";
            $d2 .= "  }\n\n";
        }

        $d2 .= "}\n\n";

        // Add organizational structure
        $d2 .= $this->generateCollectionStructure($collection);

        // Add learning objectives if it's a learning collection
        if ($collection->type === 'learning') {
            $d2 .= $this->generateLearningObjectives($collection);
        }

        return $d2;
    }

    /**
     * Generate optimization comparison diagram
     */
    public function generateOptimizationComparison(array $beforeData, array $afterData): string
    {
        $d2 = "# Rack Optimization Analysis\n";
        $d2 .= "direction: right\n";
        $d2 .= "theme: 101\n\n";

        $d2 .= "optimization: Optimization Results {\n";
        $d2 .= "  style.fill: '#1e293b'\n\n";

        // Before state
        $d2 .= "  before: |\n";
        $d2 .= "    🔴 Before Optimization\n";
        $d2 .= "    Devices: {$beforeData['device_count']}\n";
        $d2 .= "    CPU: {$beforeData['cpu_usage']}\n";
        $d2 .= "    Complexity: {$beforeData['complexity_score']}/100\n";
        $d2 .= "  | {\n";
        $d2 .= "    style.fill: '#dc2626'\n";
        $d2 .= "  }\n\n";

        // Optimization process
        $d2 .= "  process: |\n";
        $d2 .= "    ⚡ Optimization Process\n";
        $d2 .= "    • Removed unused devices\n";
        $d2 .= "    • Consolidated effects\n";
        $d2 .= "    • Optimized routing\n";
        $d2 .= "  | {\n";
        $d2 .= "    style.fill: '#f59e0b'\n";
        $d2 .= "  }\n\n";

        // After state
        $d2 .= "  after: |\n";
        $d2 .= "    🟢 After Optimization\n";
        $d2 .= "    Devices: {$afterData['device_count']}\n";
        $d2 .= "    CPU: {$afterData['cpu_usage']}\n";
        $d2 .= "    Complexity: {$afterData['complexity_score']}/100\n";
        $d2 .= "  | {\n";
        $d2 .= "    style.fill: '#16a34a'\n";
        $d2 .= "  }\n\n";

        // Add flow
        $d2 .= "  before -> process -> after\n\n";

        $d2 .= "}\n\n";

        // Add improvement metrics
        $deviceReduction = $beforeData['device_count'] - $afterData['device_count'];
        $complexityReduction = $beforeData['complexity_score'] - $afterData['complexity_score'];

        $d2 .= "improvements: Performance Gains {\n";
        $d2 .= "  devices_reduced: |\n";
        $d2 .= "    📉 Device Count\n";
        $d2 .= "    Reduced by: {$deviceReduction}\n";
        $d2 .= "    Improvement: " . round(($deviceReduction / $beforeData['device_count']) * 100, 1) . "%\n";
        $d2 .= "  | {\n";
        $d2 .= "    style.fill: '#06b6d4'\n";
        $d2 .= "  }\n\n";

        $d2 .= "  complexity_reduced: |\n";
        $d2 .= "    🎯 Complexity Score\n";
        $d2 .= "    Reduced by: {$complexityReduction} points\n";
        $d2 .= "    Improvement: " . round(($complexityReduction / $beforeData['complexity_score']) * 100, 1) . "%\n";
        $d2 .= "  | {\n";
        $d2 .= "    style.fill: '#8b5cf6'\n";
        $d2 .= "  }\n\n";

        $d2 .= "}\n\n";

        return $d2;
    }

    /**
     * Generate system architecture overview
     */
    public function generateSystemArchitecture(string $focus = 'full'): string
    {
        $d2 = "# Ableton Cookbook - System Architecture\n";
        $d2 .= "direction: right\n";
        $d2 .= "theme: 1\n\n";

        $d2 .= "system: Ableton Cookbook Platform {\n";
        $d2 .= "  style.fill: '#0f172a'\n\n";

        // Frontend Layer
        $d2 .= "  frontend: Frontend Layer {\n";
        $d2 .= "    style.fill: '#1e293b'\n\n";
        
        $d2 .= "    web_app: |\n";
        $d2 .= "      🌐 Web Application\n";
        $d2 .= "      Livewire 3 + Alpine.js\n";
        $d2 .= "      Tailwind CSS\n";
        $d2 .= "    | {\n";
        $d2 .= "      style.fill: '#3b82f6'\n";
        $d2 .= "    }\n\n";

        $d2 .= "    d2_viewer: |\n";
        $d2 .= "      📊 D2 Diagram Viewer\n";
        $d2 .= "      Interactive Visualization\n";
        $d2 .= "      Educational Tooltips\n";
        $d2 .= "    | {\n";
        $d2 .= "      style.fill: '#8b5cf6'\n";
        $d2 .= "    }\n\n";

        $d2 .= "  }\n\n";

        // API Layer
        $d2 .= "  api: API Layer {\n";
        $d2 .= "    style.fill: '#1e293b'\n\n";
        
        $d2 .= "    rest_api: |\n";
        $d2 .= "      🔌 REST API\n";
        $d2 .= "      Laravel 12 + Sanctum\n";
        $d2 .= "      OpenAPI Documentation\n";
        $d2 .= "    | {\n";
        $d2 .= "      style.fill: '#10b981'\n";
        $d2 .= "    }\n\n";

        $d2 .= "    d2_api: |\n";
        $d2 .= "      📈 D2 Diagram API\n";
        $d2 .= "      Real-time Generation\n";
        $d2 .= "      Multiple Formats\n";
        $d2 .= "    | {\n";
        $d2 .= "      style.fill: '#f59e0b'\n";
        $d2 .= "    }\n\n";

        $d2 .= "  }\n\n";

        // Business Logic Layer
        $d2 .= "  business: Business Logic {\n";
        $d2 .= "    style.fill: '#1e293b'\n\n";
        
        $d2 .= "    rack_analyzer: |\n";
        $d2 .= "      🔍 Rack Analyzer\n";
        $d2 .= "      .adg File Processing\n";
        $d2 .= "      Device Detection\n";
        $d2 .= "    | {\n";
        $d2 .= "      style.fill: '#ef4444'\n";
        $d2 .= "    }\n\n";

        $d2 .= "    d2_engine: |\n";
        $d2 .= "      🎨 D2 Engine\n";
        $d2 .= "      Template System\n";
        $d2 .= "      Style Themes\n";
        $d2 .= "    | {\n";
        $d2 .= "      style.fill: '#8b5cf6'\n";
        $d2 .= "    }\n\n";

        $d2 .= "    learning_engine: |\n";
        $d2 .= "      🎓 Learning Engine\n";
        $d2 .= "      Progress Tracking\n";
        $d2 .= "      Adaptive Paths\n";
        $d2 .= "    | {\n";
        $d2 .= "      style.fill: '#06b6d4'\n";
        $d2 .= "    }\n\n";

        $d2 .= "  }\n\n";

        // Data Layer
        $d2 .= "  data: Data Layer {\n";
        $d2 .= "    style.fill: '#1e293b'\n\n";
        
        $d2 .= "    database: |\n";
        $d2 .= "      🗄️ MySQL Database\n";
        $d2 .= "      Racks, Collections, Users\n";
        $d2 .= "      Learning Paths, Progress\n";
        $d2 .= "    | {\n";
        $d2 .= "      style.fill: '#f97316'\n";
        $d2 .= "    }\n\n";

        $d2 .= "    cache: |\n";
        $d2 .= "      ⚡ Redis Cache\n";
        $d2 .= "      D2 Diagram Cache\n";
        $d2 .= "      Session Storage\n";
        $d2 .= "    | {\n";
        $d2 .= "      style.fill: '#dc2626'\n";
        $d2 .= "    }\n\n";

        $d2 .= "    storage: |\n";
        $d2 .= "      💾 File Storage\n";
        $d2 .= "      .adg Files\n";
        $d2 .= "      Generated Diagrams\n";
        $d2 .= "    | {\n";
        $d2 .= "      style.fill: '#65a30d'\n";
        $d2 .= "    }\n\n";

        $d2 .= "  }\n\n";

        // Add connections
        $d2 .= "  # System Flow\n";
        $d2 .= "  frontend.web_app -> api.rest_api\n";
        $d2 .= "  frontend.d2_viewer -> api.d2_api\n";
        $d2 .= "  api.rest_api -> business.rack_analyzer\n";
        $d2 .= "  api.d2_api -> business.d2_engine\n";
        $d2 .= "  business.d2_engine -> data.cache\n";
        $d2 .= "  business.rack_analyzer -> data.database\n";
        $d2 .= "  business.learning_engine -> data.database\n";

        $d2 .= "}\n\n";

        return $d2;
    }

    /**
     * Generate user journey flow diagram
     */
    public function generateUserJourneyDiagram(): string
    {
        $d2 = "# User Journey - Rack Sharing & Learning\n";
        $d2 .= "direction: down\n";
        $d2 .= "theme: 101\n\n";

        $d2 .= "journey: User Journey {\n";

        // Discovery phase
        $d2 .= "  discovery: |\n";
        $d2 .= "    🔍 Discovery\n";
        $d2 .= "    User finds interesting rack\n";
        $d2 .= "    Views D2 diagram\n";
        $d2 .= "  | {\n";
        $d2 .= "    style.fill: '#3b82f6'\n";
        $d2 .= "  }\n\n";

        // Learning phase
        $d2 .= "  learning: |\n";
        $d2 .= "    📚 Learning\n";
        $d2 .= "    Interactive tooltips\n";
        $d2 .= "    Educational content\n";
        $d2 .= "  | {\n";
        $d2 .= "    style.fill: '#10b981'\n";
        $d2 .= "  }\n\n";

        // Download phase
        $d2 .= "  download: |\n";
        $d2 .= "    📥 Download\n";
        $d2 .= "    Get .adg file\n";
        $d2 .= "    Load in Ableton\n";
        $d2 .= "  | {\n";
        $d2 .= "    style.fill: '#f59e0b'\n";
        $d2 .= "  }\n\n";

        // Experimentation phase
        $d2 .= "  experiment: |\n";
        $d2 .= "    🧪 Experiment\n";
        $d2 .= "    Modify and learn\n";
        $d2 .= "    Create variations\n";
        $d2 .= "  | {\n";
        $d2 .= "    style.fill: '#8b5cf6'\n";
        $d2 .= "  }\n\n";

        // Sharing phase
        $d2 .= "  share: |\n";
        $d2 .= "    🎁 Share Back\n";
        $d2 .= "    Upload creation\n";
        $d2 .= "    Generate new D2 diagram\n";
        $d2 .= "  | {\n";
        $d2 .= "    style.fill: '#ef4444'\n";
        $d2 .= "  }\n\n";

        // Add flow
        $d2 .= "  discovery -> learning -> download -> experiment -> share\n";
        $d2 .= "  share -> discovery: \"Cycle continues\"\n";

        $d2 .= "}\n";

        return $d2;
    }

    /**
     * Generate learning modules for a learning path
     */
    private function generateLearningModules(LearningPath $learningPath): string
    {
        $d2 = "# Learning Modules\n";
        $d2 .= "modules: Learning Modules {\n";
        $d2 .= "  direction: down\n";
        $d2 .= "  style.fill: '#1e1b4b'\n\n";

        // Sample modules - would come from actual path data
        $modules = [
            ['name' => 'Fundamentals', 'status' => 'completed', 'color' => '#10b981'],
            ['name' => 'Intermediate Techniques', 'status' => 'current', 'color' => '#f59e0b'],
            ['name' => 'Advanced Routing', 'status' => 'locked', 'color' => '#6b7280'],
            ['name' => 'Performance Optimization', 'status' => 'locked', 'color' => '#6b7280']
        ];

        foreach ($modules as $index => $module) {
            $moduleId = "module_" . ($index + 1);
            $d2 .= "  {$moduleId}: |\n";
            $d2 .= "    {$module['name']}\n";
            $d2 .= "    Status: {$module['status']}\n";
            $d2 .= "  | {\n";
            $d2 .= "    style.fill: '{$module['color']}'\n";
            
            if ($module['status'] === 'current') {
                $d2 .= "    style.stroke: '#fbbf24'\n";
                $d2 .= "    style.stroke-width: 3\n";
            }
            
            $d2 .= "  }\n\n";
            
            if ($index > 0) {
                $prevModule = "module_{$index}";
                $d2 .= "  {$prevModule} -> {$moduleId}\n";
            }
        }

        $d2 .= "}\n\n";

        return $d2;
    }

    /**
     * Generate progress tracking visualization
     */
    private function generateProgressTracking(LearningPath $learningPath): string
    {
        $d2 = "# Progress Tracking\n";
        $d2 .= "progress: Learning Progress {\n";
        $d2 .= "  style.fill: '#1e293b'\n\n";

        $d2 .= "  completion: |\n";
        $d2 .= "    📊 Overall Progress\n";
        $d2 .= "    65% Complete\n";
        $d2 .= "    3/5 Modules Done\n";
        $d2 .= "  | {\n";
        $d2 .= "    style.fill: '#059669'\n";
        $d2 .= "  }\n\n";

        $d2 .= "  skills: |\n";
        $d2 .= "    🎯 Skills Acquired\n";
        $d2 .= "    • Rack Building\n";
        $d2 .= "    • Device Chaining\n";
        $d2 .= "    • Macro Controls\n";
        $d2 .= "  | {\n";
        $d2 .= "    style.fill: '#7c3aed'\n";
        $d2 .= "  }\n\n";

        $d2 .= "  next_milestone: |\n";
        $d2 .= "    🎪 Next Milestone\n";
        $d2 .= "    Advanced Routing\n";
        $d2 .= "    Est. 45min remaining\n";
        $d2 .= "  | {\n";
        $d2 .= "    style.fill: '#dc2626'\n";
        $d2 .= "  }\n\n";

        $d2 .= "}\n\n";

        return $d2;
    }

    /**
     * Generate collection structure visualization
     */
    private function generateCollectionStructure(EnhancedCollection $collection): string
    {
        $d2 = "# Collection Structure\n";
        $d2 .= "structure: Organization {\n";
        $d2 .= "  style.fill: '#1e293b'\n\n";

        $d2 .= "  categories: |\n";
        $d2 .= "    📂 Categories\n";
        $d2 .= "    • Instruments\n";
        $d2 .= "    • Effects\n";
        $d2 .= "    • Drums\n";
        $d2 .= "  | {\n";
        $d2 .= "    style.fill: '#0891b2'\n";
        $d2 .= "  }\n\n";

        $d2 .= "  tags: |\n";
        $d2 .= "    🏷️ Tags\n";
        $d2 .= "    • Beginner-Friendly\n";
        $d2 .= "    • CPU-Efficient\n";
        $d2 .= "    • Creative-Tools\n";
        $d2 .= "  | {\n";
        $d2 .= "    style.fill: '#be185d'\n";
        $d2 .= "  }\n\n";

        $d2 .= "}\n\n";

        return $d2;
    }

    /**
     * Generate learning objectives
     */
    private function generateLearningObjectives(EnhancedCollection $collection): string
    {
        $d2 = "# Learning Objectives\n";
        $d2 .= "objectives: What You'll Learn {\n";
        $d2 .= "  style.fill: '#1e293b'\n\n";

        $d2 .= "  objective_1: |\n";
        $d2 .= "    🎯 Master Rack Building\n";
        $d2 .= "    Learn to create complex\n";
        $d2 .= "    multi-device racks\n";
        $d2 .= "  | {\n";
        $d2 .= "    style.fill: '#dc2626'\n";
        $d2 .= "  }\n\n";

        $d2 .= "  objective_2: |\n";
        $d2 .= "    ⚡ Optimize Performance\n";
        $d2 .= "    Understand CPU usage\n";
        $d2 .= "    and efficiency techniques\n";
        $d2 .= "  | {\n";
        $d2 .= "    style.fill: '#059669'\n";
        $d2 .= "  }\n\n";

        $d2 .= "}\n\n";

        return $d2;
    }
}