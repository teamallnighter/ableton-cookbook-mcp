import { spawn } from "child_process";
import path from "path";
import fs from "fs-extra";

/**
 * Analyzer class - bridges to PHP analyzers for .adg, .adv, and drum rack analysis
 */
export class Analyzer {
    private analyzersPath: string;

    constructor(analyzersPath: string = "/Volumes/DEV/M4L-MCP/packages/php-analyzers") {
        this.analyzersPath = analyzersPath;
    }

    /**
     * Execute a PHP script
     */
    private async executePHP(
        scriptPath: string,
        args: string[] = []
    ): Promise<string> {
        return new Promise((resolve, reject) => {
            if (!fs.existsSync(scriptPath)) {
                reject(new Error(`PHP script not found: ${scriptPath}`));
                return;
            }

            const process = spawn("php", [scriptPath, ...args]);
            let stdout = "";
            let stderr = "";

            process.stdout.on("data", (data) => {
                stdout += data.toString();
            });

            process.stderr.on("data", (data) => {
                stderr += data.toString();
            });

            process.on("close", (code) => {
                if (code !== 0) {
                    reject(new Error(`PHP script failed (exit ${code}): ${stderr || stdout}`));
                } else {
                    resolve(stdout);
                }
            });

            process.on("error", (error) => {
                reject(new Error(`Failed to execute PHP script: ${error.message}`));
            });
        });
    }

    /**
     * Analyze an Ableton rack (.adg) file
     */
    async analyzeRack(rackPath: string): Promise<any> {
        if (!await fs.pathExists(rackPath)) {
            throw new Error(`Rack file not found: ${rackPath}`);
        }

        const extension = path.extname(rackPath).toLowerCase();
        if (extension !== '.adg') {
            throw new Error(`Invalid file type. Expected .adg, got ${extension}`);
        }

        const analyzerScript = path.join(
            this.analyzersPath,
            "abletonRackAnalyzer",
            "abletonRackAnalyzer-V7.php"
        );

        // Create a small wrapper script that uses the analyzer and outputs JSON
        const wrapperScript = `<?php
require_once '${analyzerScript}';

try {
    $analyzer = new AbletonRackAnalyzer([
        'verbose' => false,
        'enable_caching' => true,
        'detect_edition' => true,
        'enable_metadata_enrichment' => true
    ]);
    
    $result = $analyzer->analyze('${rackPath.replace(/'/g, "\\'")}');
    echo json_encode($result->toArray(), JSON_PRETTY_PRINT);
} catch (Exception $e) {
    $error = [
        'error' => true,
        'message' => $e->getMessage(),
        'file' => '${path.basename(rackPath)}'
    ];
    echo json_encode($error, JSON_PRETTY_PRINT);
    exit(1);
}
`;

        const tempScript = path.join("/tmp", `analyze_rack_${Date.now()}.php`);
        await fs.writeFile(tempScript, wrapperScript);

        try {
            const output = await this.executePHP(tempScript);
            return JSON.parse(output);
        } finally {
            await fs.remove(tempScript);
        }
    }

    /**
     * Analyze a drum rack file
     */
    async analyzeDrumRack(drumRackPath: string): Promise<any> {
        if (!await fs.pathExists(drumRackPath)) {
            throw new Error(`Drum rack file not found: ${drumRackPath}`);
        }

        const analyzerScript = path.join(
            this.analyzersPath,
            "abletonDrumRackAnalyzer",
            "abletonDrumRackAnalyzer.php"
        );

        const wrapperScript = `<?php
require_once '${analyzerScript}';

try {
    $analyzer = new AbletonDrumRackAnalyzer();
    $result = $analyzer->analyze('${drumRackPath.replace(/'/g, "\\'")}');
    echo json_encode($result, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    $error = [
        'error' => true,
        'message' => $e->getMessage(),
        'file' => '${path.basename(drumRackPath)}'
    ];
    echo json_encode($error, JSON_PRETTY_PRINT);
    exit(1);
}
`;

        const tempScript = path.join("/tmp", `analyze_drum_rack_${Date.now()}.php`);
        await fs.writeFile(tempScript, wrapperScript);

        try {
            const output = await this.executePHP(tempScript);
            return JSON.parse(output);
        } finally {
            await fs.remove(tempScript);
        }
    }

    /**
     * Analyze an Ableton preset (.adv) file
     */
    async analyzePreset(presetPath: string): Promise<any> {
        if (!await fs.pathExists(presetPath)) {
            throw new Error(`Preset file not found: ${presetPath}`);
        }

        const extension = path.extname(presetPath).toLowerCase();
        if (extension !== '.adv') {
            throw new Error(`Invalid file type. Expected .adv, got ${extension}`);
        }

        const analyzerScript = path.join(
            this.analyzersPath,
            "abletonPresetAnalyzer",
            "abletonRackAnalyzer-v3.php"
        );

        const wrapperScript = `<?php
require_once '${analyzerScript}';

try {
    $analyzer = new AbletonRackAnalyzer();
    $xml = $analyzer->decompressAndParseAbletonFile('${presetPath.replace(/'/g, "\\'")}');
    
    if ($xml) {
        $result = $analyzer->parseChainsAndDevices($xml, '${presetPath.replace(/'/g, "\\'")}', false);
        echo json_encode($result, JSON_PRETTY_PRINT);
    } else {
        throw new Exception('Failed to parse preset file');
    }
} catch (Exception $e) {
    $error = [
        'error' => true,
        'message' => $e->getMessage(),
        'file' => '${path.basename(presetPath)}'
    ];
    echo json_encode($error, JSON_PRETTY_PRINT);
    exit(1);
}
`;

        const tempScript = path.join("/tmp", `analyze_preset_${Date.now()}.php`);
        await fs.writeFile(tempScript, wrapperScript);

        try {
            const output = await this.executePHP(tempScript);
            return JSON.parse(output);
        } finally {
            await fs.remove(tempScript);
        }
    }

    /**
     * Scan User Library for racks and presets
     */
    async scanUserLibrary(userLibraryPath: string): Promise<any> {
        if (!await fs.pathExists(userLibraryPath)) {
            throw new Error(`User Library not found: ${userLibraryPath}`);
        }

        const results = {
            racks: [] as string[],
            drum_racks: [] as string[],
            presets: [] as string[],
            total: 0
        };

        // Scan for .adg files (racks)
        const rackFiles = await this.findFilesByExtension(
            path.join(userLibraryPath, "Presets"),
            ".adg"
        );
        results.racks = rackFiles;

        // Scan for drum racks specifically
        const drumRackPath = path.join(userLibraryPath, "Presets", "Instruments", "Drum Rack");
        if (await fs.pathExists(drumRackPath)) {
            const drumRacks = await this.findFilesByExtension(drumRackPath, ".adg");
            results.drum_racks = drumRacks;
        }

        // Scan for .adv files (device presets)
        const presetFiles = await this.findFilesByExtension(
            path.join(userLibraryPath, "Presets"),
            ".adv"
        );
        results.presets = presetFiles;

        results.total = results.racks.length + results.drum_racks.length + results.presets.length;

        return results;
    }

    /**
     * Find files by extension recursively
     */
    private async findFilesByExtension(
        dir: string,
        extension: string
    ): Promise<string[]> {
        if (!await fs.pathExists(dir)) {
            return [];
        }

        const files: string[] = [];

        async function scan(currentDir: string): Promise<void> {
            const entries = await fs.readdir(currentDir, { withFileTypes: true });

            for (const entry of entries) {
                const fullPath = path.join(currentDir, entry.name);

                if (entry.isDirectory()) {
                    // Skip Ableton's system folders
                    if (!entry.name.startsWith('.') && entry.name !== 'Backup') {
                        await scan(fullPath);
                    }
                } else if (entry.isFile() && entry.name.toLowerCase().endsWith(extension)) {
                    files.push(fullPath);
                }
            }
        }

        await scan(dir);
        return files;
    }

    /**
     * Get rack summary (quick metadata without full analysis)
     */
    async getRackSummary(rackPath: string): Promise<any> {
        try {
            const analysis = await this.analyzeRack(rackPath);

            return {
                name: analysis.rackName || path.basename(rackPath, '.adg'),
                type: analysis.rackType,
                chains: analysis.chains?.length || 0,
                macros: analysis.macroControls?.length || 0,
                edition: analysis.requiredEdition,
                ableton_version: analysis.abletonVersion,
                devices: this.countDevices(analysis.chains || []),
                path: rackPath
            };
        } catch (error: any) {
            return {
                name: path.basename(rackPath, '.adg'),
                error: error.message,
                path: rackPath
            };
        }
    }

    /**
     * Count total devices in chains recursively
     */
    private countDevices(chains: any[]): number {
        let count = 0;
        for (const chain of chains) {
            count += chain.devices?.length || 0;
            for (const device of chain.devices || []) {
                if (device.chains) {
                    count += this.countDevices(device.chains);
                }
            }
        }
        return count;
    }

    /**
     * Search racks by device type
     */
    async searchRacksByDevice(
        userLibraryPath: string,
        deviceName: string
    ): Promise<any[]> {
        const scan = await this.scanUserLibrary(userLibraryPath);
        const results: any[] = [];

        // Search through rack files
        for (const rackPath of scan.racks.slice(0, 50)) { // Limit for performance
            try {
                const summary = await this.getRackSummary(rackPath);
                const analysis = await this.analyzeRack(rackPath);

                if (this.containsDevice(analysis.chains || [], deviceName)) {
                    results.push({
                        ...summary,
                        matched_device: deviceName
                    });
                }
            } catch (error) {
                // Skip racks that fail to parse
                continue;
            }
        }

        return results;
    }

    /**
     * Check if chains contain a specific device
     */
    private containsDevice(chains: any[], deviceName: string): boolean {
        const searchName = deviceName.toLowerCase();

        for (const chain of chains) {
            for (const device of chain.devices || []) {
                const deviceNameLower = (device.name || '').toLowerCase();
                const deviceTypeLower = (device.type || '').toLowerCase();

                if (deviceNameLower.includes(searchName) || deviceTypeLower.includes(searchName)) {
                    return true;
                }

                if (device.chains && this.containsDevice(device.chains, deviceName)) {
                    return true;
                }
            }
        }

        return false;
    }
}
