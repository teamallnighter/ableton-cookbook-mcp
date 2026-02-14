import { spawn } from "child_process";
import path from "path";
import fs from "fs-extra";

/**
 * Historian class - bridges to the liveGit Python version control system
 */
export class Historian {
    private liveGitPath: string;

    constructor(liveGitPath: string = "/Volumes/DEV/M4L-MCP/packages/python-scripts") {
        this.liveGitPath = liveGitPath;
    }

    /**
     * Execute a Python script from the liveGit directory
     */
    private async executePython(
        scriptName: string,
        args: string[]
    ): Promise<string> {
        return new Promise((resolve, reject) => {
            const scriptPath = path.join(this.liveGitPath, scriptName);

            if (!fs.existsSync(scriptPath)) {
                reject(new Error(`Python script not found: ${scriptPath}`));
                return;
            }

            const process = spawn("python3", [scriptPath, ...args]);
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
                    reject(new Error(`Python script failed: ${stderr || stdout}`));
                } else {
                    resolve(stdout);
                }
            });

            process.on("error", (error) => {
                reject(new Error(`Failed to execute Python script: ${error.message}`));
            });
        });
    }

    /**
     * Scan a project directory for versioned .als files
     */
    async scanVersions(projectPath: string): Promise<any> {
        const output = await this.executePython("ableton_version_manager.py", [
            "scan",
            projectPath,
        ]);
        return this.parseOutput(output);
    }

    /**
     * Get version history for a project
     */
    async getVersionHistory(projectPath: string): Promise<any> {
        const output = await this.executePython("ableton_version_manager.py", [
            "history",
            projectPath,
        ]);

        // Try to read the versions.json file directly for structured data
        const historyPath = path.join(projectPath, "_history", "versions.json");
        if (await fs.pathExists(historyPath)) {
            const data = await fs.readJson(historyPath);
            return data;
        }

        return this.parseOutput(output);
    }

    /**
     * Compare two specific versions
     */
    async compareVersions(
        oldPath: string,
        newPath: string
    ): Promise<string> {
        const output = await this.executePython("ableton_version_manager.py", [
            "compare",
            oldPath,
            newPath,
        ]);
        return output;
    }

    /**
     * Compare the latest two versions in a project
     */
    async compareLatest(projectPath: string): Promise<string> {
        const output = await this.executePython("ableton_version_manager.py", [
            "diff-latest",
            projectPath,
        ]);
        return output;
    }

    /**
     * Get the most recent change report
     */
    async getLatestChanges(projectPath: string): Promise<string> {
        const reportsPath = path.join(projectPath, "_history", "reports");

        if (!await fs.pathExists(reportsPath)) {
            return "No change reports found. Project may not have version history.";
        }

        const reports = await fs.readdir(reportsPath);
        const changeReports = reports
            .filter(f => f.startsWith("changes_") && f.endsWith(".txt"))
            .sort()
            .reverse();

        if (changeReports.length === 0) {
            return "No change reports found.";
        }

        const latestReport = path.join(reportsPath, changeReports[0]);
        return await fs.readFile(latestReport, "utf-8");
    }

    /**
     * Generate a timeline visualization
     */
    async generateTimeline(projectPath: string): Promise<string> {
        const timelinePath = path.join(projectPath, "_history", "timeline.html");

        await this.executePython("ableton_visualizer.py", [
            projectPath,
            timelinePath
        ]);

        if (await fs.pathExists(timelinePath)) {
            return `Timeline generated at: ${timelinePath}`;
        }

        return "Timeline generation completed.";
    }

    /**
     * Get detailed analysis of a specific .als file
     */
    async analyzeAls(filePath: string): Promise<any> {
        // Use the diff tool to analyze a single file
        const output = await this.executePython("ableton_diff.py", [
            filePath,
            filePath, // Same file for both - will just extract structure
        ]);

        return this.parseOutput(output);
    }

    /**
     * Check if a project has version control initialized
     */
    async isVersionControlled(projectPath: string): Promise<boolean> {
        const historyPath = path.join(projectPath, "_history");
        return await fs.pathExists(historyPath);
    }

    /**
     * Get change report between two specific versions
     */
    async getChangeReport(
        projectPath: string,
        fromVersion: string,
        toVersion: string
    ): Promise<string> {
        const reportName = `changes_${fromVersion}_to_${toVersion}.txt`;
        const reportPath = path.join(projectPath, "_history", "reports", reportName);

        if (await fs.pathExists(reportPath)) {
            return await fs.readFile(reportPath, "utf-8");
        }

        // If report doesn't exist, generate it
        const versionsData = await this.getVersionHistory(projectPath);
        const oldVersion = versionsData.versions.find((v: any) => v.version === fromVersion);
        const newVersion = versionsData.versions.find((v: any) => v.version === toVersion);

        if (!oldVersion || !newVersion) {
            throw new Error(`Version ${fromVersion} or ${toVersion} not found`);
        }

        return await this.compareVersions(oldVersion.filepath, newVersion.filepath);
    }

    /**
     * Parse Python script output (handles both JSON and plain text)
     */
    private parseOutput(output: string): any {
        try {
            return JSON.parse(output);
        } catch {
            return { output: output.trim() };
        }
    }
}
