import fs from "fs-extra";
import zlib from "zlib";
import { XMLParser } from "fast-xml-parser";
import { glob } from "glob";

export class Archivist {
    private parser: XMLParser;

    constructor() {
        this.parser = new XMLParser({
            ignoreAttributes: false,
            attributeNamePrefix: "@_",
        });
    }

    /**
     * Scans a directory for Ableton Live Set (.als) files.
     * @param rootPath The directory to search in.
     * @returns List of file paths.
     */
    async scanProjectFiles(rootPath: string): Promise<string[]> {
        // Escape backslashes for Windows, though glob usually handles forward slashes well
        const pattern = `${rootPath.replace(/\\/g, "/")}/**/*.als`;
        const files = await glob(pattern, { ignore: "**/Backup/**" });
        return files;
    }

    /**
     * Decompresses and parses an .als file.
     * @param filePath Path to the .als file.
     * @returns Simplified JSON representation of the Live Set.
     */
    async inspectAlsFile(filePath: string): Promise<any> {
        if (!await fs.pathExists(filePath)) {
            throw new Error(`File not found: ${filePath}`);
        }

        try {
            const fileBuffer = await fs.readFile(filePath);

            // Determine if it is gzipped (checking magic numbers 1f 8b)
            const isGzipped = fileBuffer[0] === 0x1f && fileBuffer[1] === 0x8b;

            let xmlContent: string;
            if (isGzipped) {
                xmlContent = zlib.gunzipSync(fileBuffer).toString();
            } else {
                xmlContent = fileBuffer.toString();
            }

            const rawData = this.parser.parse(xmlContent);
            return this.simplifyLiveSet(rawData);
        } catch (error: any) {
            throw new Error(`Failed to parse .als file: ${error.message}`);
        }
    }

    /**
     * Simplify the raw XML->JSON structure to be more readable for the LLM.
     */
    private simplifyLiveSet(data: any): any {
        const liveSet = data?.Ableton?.LiveSet;
        if (!liveSet) return { error: "Invalid Ableton XML structure" };

        // Helper to get track name, dealing with the complexity of XML node structure
        const getTrackInfo = (track: any) => {
            const name = track.Name?.EffectiveName?.["@_Value"] || track.Name?.UserName?.["@_Value"] || "Untitled";
            const _color = track.Color?.["@_Value"];
            const deviceChain = track.DeviceChain || {};

            // Extract devices (simplified)
            const devices = [];
            const deviceMixer = deviceChain.Mixer || {};
            const chainNodes = deviceChain.DeviceChain?.Devices || {};

            // Loop through all keys in Devices object (AudioEffectGroupDevice, etc)
            for (const deviceType in chainNodes) {
                const deviceNode = chainNodes[deviceType];
                if (Array.isArray(deviceNode)) {
                    deviceNode.forEach((d: any) => {
                        devices.push({
                            type: deviceType,
                            name: d.UserName?.["@_Value"] || deviceType
                        });
                    });
                } else if (typeof deviceNode === "object") { // Single device
                    devices.push({
                        type: deviceType,
                        name: deviceNode.UserName?.["@_Value"] || deviceType
                    });
                }
            }

            return {
                name,
                volume: deviceMixer.Volume?.Manual?.["@_Value"],
                pan: deviceMixer.Pan?.Manual?.["@_Value"],
                devices
            };
        };

        const tracks = [];
        const rawTracks = liveSet.Tracks;

        // Tracks are stored in keys like 'AudioTrack', 'MidiTrack', 'GroupTrack'
        for (const trackType in rawTracks) {
            const trackNode = rawTracks[trackType];
            if (Array.isArray(trackNode)) {
                trackNode.forEach(t => tracks.push({ type: trackType, ...getTrackInfo(t) }));
            } else if (typeof trackNode === "object") {
                tracks.push({ type: trackType, ...getTrackInfo(trackNode) });
            }
        }

        return {
            tempo: liveSet.MasterTrack?.DeviceChain?.Mixer?.Tempo?.Manual?.["@_Value"],
            timeSignature: liveSet.MasterTrack?.DeviceChain?.Mixer?.TimeSignature?.Manual?.["@_Value"],
            tracks,
            version: data?.Ableton?.["@_MajorVersion"] + "." + data?.Ableton?.["@_MinorVersion"]
        };
    }
}
