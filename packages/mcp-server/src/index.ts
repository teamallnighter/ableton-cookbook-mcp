import { Server } from "@modelcontextprotocol/sdk/server/index.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import {
    CallToolRequestSchema,
    ListToolsRequestSchema,
} from "@modelcontextprotocol/sdk/types.js";
import { Archivist } from "./archivist.js";
import { Operator } from "./operator.js";
import { Historian } from "./historian.js";
import { Analyzer } from "./analyzer.js";

const archivist = new Archivist();
const operator = new Operator();
const historian = new Historian();
const analyzer = new Analyzer();

console.error("Ableton Live MCP starting...");

const server = new Server(
    {
        name: "ableton-live-mcp",
        version: "1.0.0",
    },
    {
        capabilities: {
            tools: {},
        },
    }
);

server.setRequestHandler(ListToolsRequestSchema, async () => {
    return {
        tools: [
            // Offline Tools (XML)
            {
                name: "scan_project_files",
                description: "Recursively search a directory for Ableton Live Set (.als) files.",
                inputSchema: {
                    type: "object",
                    properties: {
                        rootPath: {
                            type: "string",
                            description: "The root directory to search in (absolute path)",
                        },
                    },
                    required: ["rootPath"],
                },
            },
            {
                name: "inspect_als",
                description: "Read, decompress, and parse an Ableton Live Set (.als) file to extract track and device info without opening Live.",
                inputSchema: {
                    type: "object",
                    properties: {
                        filePath: {
                            type: "string",
                            description: "The absolute path to the .als file",
                        },
                    },
                    required: ["filePath"],
                },
            },
            // Online Tools (Remote Script)
            {
                name: "get_live_status",
                description: "Check if Ableton Live is running and connected, and get basic transport status (Tempo, Play state).",
                inputSchema: {
                    type: "object",
                    properties: {},
                },
            },
            {
                name: "list_live_tracks",
                description: "Get a list of all tracks in the currently open Live Set (requires Live running).",
                inputSchema: {
                    type: "object",
                    properties: {},
                },
            },
            {
                name: "set_track_volume",
                description: "Set the mixer volume for a specific track in the running Live Set.",
                inputSchema: {
                    type: "object",
                    properties: {
                        trackName: {
                            type: "string",
                            description: "The exact name of the track to control",
                        },
                        volume: {
                            type: "number",
                            description: "Volume level (0.0 to 1.0)",
                        },
                    },
                    required: ["trackName", "volume"],
                },
            },
            // Version Control Tools (Historian)
            {
                name: "get_version_history",
                description: "Get the complete version history for an Ableton project with timestamps and metadata.",
                inputSchema: {
                    type: "object",
                    properties: {
                        projectPath: {
                            type: "string",
                            description: "The absolute path to the Ableton project directory",
                        },
                    },
                    required: ["projectPath"],
                },
            },
            {
                name: "scan_versions",
                description: "Scan an Ableton project directory for versioned .als files (files with _X.Y.Z.als pattern).",
                inputSchema: {
                    type: "object",
                    properties: {
                        projectPath: {
                            type: "string",
                            description: "The absolute path to the Ableton project directory",
                        },
                    },
                    required: ["projectPath"],
                },
            },
            {
                name: "compare_versions",
                description: "Compare two specific versions of an Ableton project to see what changed.",
                inputSchema: {
                    type: "object",
                    properties: {
                        oldPath: {
                            type: "string",
                            description: "The absolute path to the older .als file",
                        },
                        newPath: {
                            type: "string",
                            description: "The absolute path to the newer .als file",
                        },
                    },
                    required: ["oldPath", "newPath"],
                },
            },
            {
                name: "get_latest_changes",
                description: "Get the most recent change report for an Ableton project.",
                inputSchema: {
                    type: "object",
                    properties: {
                        projectPath: {
                            type: "string",
                            description: "The absolute path to the Ableton project directory",
                        },
                    },
                    required: ["projectPath"],
                },
            },
            {
                name: "get_change_report",
                description: "Get a specific change report between two versions.",
                inputSchema: {
                    type: "object",
                    properties: {
                        projectPath: {
                            type: "string",
                            description: "The absolute path to the Ableton project directory",
                        },
                        fromVersion: {
                            type: "string",
                            description: "The starting version number (e.g., '0.1.0')",
                        },
                        toVersion: {
                            type: "string",
                            description: "The ending version number (e.g., '0.1.2')",
                        },
                    },
                    required: ["projectPath", "fromVersion", "toVersion"],
                },
            },
            {
                name: "generate_timeline",
                description: "Generate an HTML timeline visualization of project version history.",
                inputSchema: {
                    type: "object",
                    properties: {
                        projectPath: {
                            type: "string",
                            description: "The absolute path to the Ableton project directory",
                        },
                    },
                    required: ["projectPath"],
                },
            },
            // Analyzer Tools (PHP Bridge)
            {
                name: "analyze_rack",
                description: "Analyze an Ableton rack (.adg) file to extract device chains, macros, and metadata.",
                inputSchema: {
                    type: "object",
                    properties: {
                        rackPath: {
                            type: "string",
                            description: "The absolute path to the .adg rack file",
                        },
                    },
                    required: ["rackPath"],
                },
            },
            {
                name: "analyze_drum_rack",
                description: "Analyze an Ableton drum rack file to extract pad assignments and samples.",
                inputSchema: {
                    type: "object",
                    properties: {
                        drumRackPath: {
                            type: "string",
                            description: "The absolute path to the drum rack .adg file",
                        },
                    },
                    required: ["drumRackPath"],
                },
            },
            {
                name: "analyze_preset",
                description: "Analyze an Ableton device preset (.adv) file.",
                inputSchema: {
                    type: "object",
                    properties: {
                        presetPath: {
                            type: "string",
                            description: "The absolute path to the .adv preset file",
                        },
                    },
                    required: ["presetPath"],
                },
            },
            {
                name: "scan_user_library",
                description: "Scan the User Library for all racks, drum racks, and presets.",
                inputSchema: {
                    type: "object",
                    properties: {
                        userLibraryPath: {
                            type: "string",
                            description: "The absolute path to the Ableton User Library",
                        },
                    },
                    required: ["userLibraryPath"],
                },
            },
            {
                name: "search_racks_by_device",
                description: "Search User Library racks that contain a specific device.",
                inputSchema: {
                    type: "object",
                    properties: {
                        userLibraryPath: {
                            type: "string",
                            description: "The absolute path to the Ableton User Library",
                        },
                        deviceName: {
                            type: "string",
                            description: "The device name to search for (e.g., 'Serum', 'OTT', 'Saturator')",
                        },
                    },
                    required: ["userLibraryPath", "deviceName"],
                },
            },
        ],
    };
});

server.setRequestHandler(CallToolRequestSchema, async (request) => {
    const { name, arguments: args } = request.params;

    try {
        // --- Archivist Tools ---
        if (name === "scan_project_files") {
            const { rootPath } = args as { rootPath: string };
            const files = await archivist.scanProjectFiles(rootPath);
            return {
                content: [{ type: "text", text: JSON.stringify(files, null, 2) }],
            };
        }

        if (name === "inspect_als") {
            const { filePath } = args as { filePath: string };
            const data = await archivist.inspectAlsFile(filePath);
            return {
                content: [{ type: "text", text: JSON.stringify(data, null, 2) }],
            };
        }

        // --- Operator Tools ---
        if (name === "get_live_status") {
            const status = await operator.getStatus();
            return {
                content: [{ type: "text", text: JSON.stringify(status, null, 2) }],
            };
        }

        if (name === "list_live_tracks") {
            const tracks = await operator.listTracks();
            return {
                content: [{ type: "text", text: JSON.stringify(tracks, null, 2) }],
            };
        }

        if (name === "set_track_volume") {
            const { trackName, volume } = args as { trackName: string; volume: number };
            const result = await operator.setMixer(trackName, volume);
            return {
                content: [{ type: "text", text: result }],
            };
        }

        // --- Historian Tools ---
        if (name === "get_version_history") {
            const { projectPath } = args as { projectPath: string };
            const history = await historian.getVersionHistory(projectPath);
            return {
                content: [{ type: "text", text: JSON.stringify(history, null, 2) }],
            };
        }

        if (name === "scan_versions") {
            const { projectPath } = args as { projectPath: string };
            const versions = await historian.scanVersions(projectPath);
            return {
                content: [{ type: "text", text: JSON.stringify(versions, null, 2) }],
            };
        }

        if (name === "compare_versions") {
            const { oldPath, newPath } = args as { oldPath: string; newPath: string };
            const diff = await historian.compareVersions(oldPath, newPath);
            return {
                content: [{ type: "text", text: diff }],
            };
        }

        if (name === "get_latest_changes") {
            const { projectPath } = args as { projectPath: string };
            const changes = await historian.getLatestChanges(projectPath);
            return {
                content: [{ type: "text", text: changes }],
            };
        }

        if (name === "get_change_report") {
            const { projectPath, fromVersion, toVersion } = args as {
                projectPath: string;
                fromVersion: string;
                toVersion: string;
            };
            const report = await historian.getChangeReport(projectPath, fromVersion, toVersion);
            return {
                content: [{ type: "text", text: report }],
            };
        }

        if (name === "generate_timeline") {
            const { projectPath } = args as { projectPath: string };
            const result = await historian.generateTimeline(projectPath);
            return {
                content: [{ type: "text", text: result }],
            };
        }

        // --- Analyzer Tools ---
        if (name === "analyze_rack") {
            const { rackPath } = args as { rackPath: string };
            const analysis = await analyzer.analyzeRack(rackPath);
            return {
                content: [{ type: "text", text: JSON.stringify(analysis, null, 2) }],
            };
        }

        if (name === "analyze_drum_rack") {
            const { drumRackPath } = args as { drumRackPath: string };
            const analysis = await analyzer.analyzeDrumRack(drumRackPath);
            return {
                content: [{ type: "text", text: JSON.stringify(analysis, null, 2) }],
            };
        }

        if (name === "analyze_preset") {
            const { presetPath } = args as { presetPath: string };
            const analysis = await analyzer.analyzePreset(presetPath);
            return {
                content: [{ type: "text", text: JSON.stringify(analysis, null, 2) }],
            };
        }

        if (name === "scan_user_library") {
            const { userLibraryPath } = args as { userLibraryPath: string };
            const scan = await analyzer.scanUserLibrary(userLibraryPath);
            return {
                content: [{ type: "text", text: JSON.stringify(scan, null, 2) }],
            };
        }

        if (name === "search_racks_by_device") {
            const { userLibraryPath, deviceName } = args as {
                userLibraryPath: string;
                deviceName: string;
            };
            const results = await analyzer.searchRacksByDevice(userLibraryPath, deviceName);
            return {
                content: [{ type: "text", text: JSON.stringify(results, null, 2) }],
            };
        }

        throw new Error(`Unknown tool: ${name}`);
    } catch (error: any) {
        return {
            content: [
                {
                    type: "text",
                    text: `Error: ${error.message}`,
                },
            ],
            isError: true,
        };
    }
});

async function main() {
    const transport = new StdioServerTransport();
    await server.connect(transport);
    console.error("Ableton Live MCP server connected to transport");
}

main().catch((error) => {
    console.error("Fatal error starting server:", error);
    process.exit(1);
});
