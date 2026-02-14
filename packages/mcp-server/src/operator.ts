import { Ableton } from "ableton-js";

export class Operator {
    private ableton: Ableton;

    constructor() {
        this.ableton = new Ableton();
    }

    async testConnection(): Promise<string> {
        try {
            // If we can get a prop, we are connected
            const isConnected = await this.ableton.isConnected();
            if (!isConnected) return "Ableton Live is not connected.";

            // Example: Get the song version to prove it works
            // Note: isConnected is just a check, querying something proves LOM access.
            return "Connected to Ableton Live.";
        } catch (e: any) {
            return `Failed to connect: ${e.message}. (Did you install the Remote Script and select 'AbletonJS' in Preferences?)`;
        }
    }

    async getStatus() {
        if (!(await this.ableton.isConnected())) throw new Error("Not connected to Live");

        const song = this.ableton.song;
        const isPlaying = await song.get("is_playing");
        const tempo = await song.get("tempo");
        const currentSongTime = await song.get("current_song_time");

        return {
            isPlaying,
            tempo,
            currentSongTime,
            connected: true
        };
    }

    async listTracks() {
        if (!(await this.ableton.isConnected())) throw new Error("Not connected to Live");

        const tracks = await this.ableton.song.get("tracks");
        const trackList = await Promise.all(tracks.map(async (t) => {
            return {
                id: t.raw.id,
                name: await t.get("name"),
                color: await t.get("color"),
                // Can add more properties here
            };
        }));
        return trackList;
    }

    async setMixer(trackName: string, volume: number) {
        if (!(await this.ableton.isConnected())) throw new Error("Not connected to Live");

        const tracks = await this.ableton.song.get("tracks");
        const target = await Promise.all(tracks.map(async t => ({
            track: t,
            name: await t.get("name")
        }))).then(list => list.find(item => item.name === trackName));

        if (!target) throw new Error(`Track '${trackName}' not found.`);

        // Access the mixer device, then the volume parameter, then set its value
        const mixer = await target.track.get("mixer_device");
        const volumeParam = await mixer.get("volume");
        await volumeParam.set("value", volume);

        return `Set volume of ${trackName} to ${volume}`;
    }
}
