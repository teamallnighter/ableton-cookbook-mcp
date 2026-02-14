#!/usr/bin/env python3
"""
Ableton Version Manager
Monitors Ableton project folders for new versions and automatically analyzes changes.
"""

import json
import gzip
import xml.etree.ElementTree as ET
from pathlib import Path
from datetime import datetime
from typing import List, Dict, Optional, Set, Tuple
from dataclasses import dataclass, asdict
import re


@dataclass
class VersionInfo:
    """Version metadata."""
    version: str
    filepath: str
    timestamp: datetime
    metadata: Dict

    def to_dict(self):
        return {
            'version': self.version,
            'filepath': str(self.filepath),
            'timestamp': self.timestamp.isoformat(),
            'metadata': self.metadata
        }


class ProjectVersionManager:
    """Manages versions for an Ableton project."""

    def __init__(self, project_path: str):
        self.project_path = Path(project_path)
        self.history_dir = self.project_path / "_history"
        self.history_dir.mkdir(exist_ok=True)

        self.version_db_path = self.history_dir / "versions.json"
        self.versions: List[VersionInfo] = []
        self._load_version_db()

    def _load_version_db(self):
        """Load version database."""
        if self.version_db_path.exists():
            with open(self.version_db_path, 'r') as f:
                data = json.load(f)
                self.versions = [
                    VersionInfo(
                        version=v['version'],
                        filepath=v['filepath'],
                        timestamp=datetime.fromisoformat(v['timestamp']),
                        metadata=v['metadata']
                    )
                    for v in data.get('versions', [])
                ]

    def _save_version_db(self):
        """Save version database."""
        with open(self.version_db_path, 'w') as f:
            json.dump({
                'project': str(self.project_path),
                'versions': [v.to_dict() for v in self.versions]
            }, f, indent=2)

    def scan_for_versions(self) -> List[VersionInfo]:
        """Scan project folder for version files."""
        version_pattern = re.compile(r'.*_(\d+\.\d+\.\d+)\.als$')
        found_versions = []

        for als_file in self.project_path.glob('*.als'):
            match = version_pattern.match(als_file.name)
            if match:
                version_str = match.group(1)

                # Check if we already know about this version
                if not any(v.version == version_str for v in self.versions):
                    # Load metadata if exists
                    metadata = {}
                    json_file = als_file.with_suffix('.json')
                    json_file_with_space = self.project_path / f" {als_file.stem}.json"

                    if json_file.exists():
                        with open(json_file, 'r') as f:
                            metadata = json.load(f)
                    elif json_file_with_space.exists():
                        with open(json_file_with_space, 'r') as f:
                            metadata = json.load(f)

                    version_info = VersionInfo(
                        version=version_str,
                        filepath=str(als_file),
                        timestamp=datetime.fromtimestamp(als_file.stat().st_mtime),
                        metadata=metadata
                    )
                    found_versions.append(version_info)

        return found_versions

    def register_new_versions(self):
        """Scan and register any new versions found."""
        new_versions = self.scan_for_versions()

        if new_versions:
            self.versions.extend(new_versions)
            self.versions.sort(key=lambda v: v.timestamp)
            self._save_version_db()
            return new_versions

        return []

    def get_sorted_versions(self) -> List[VersionInfo]:
        """Get all versions sorted by timestamp."""
        return sorted(self.versions, key=lambda v: v.timestamp)

    def get_version_pairs(self) -> List[Tuple[VersionInfo, VersionInfo]]:
        """Get consecutive version pairs for comparison."""
        sorted_versions = self.get_sorted_versions()
        return list(zip(sorted_versions[:-1], sorted_versions[1:]))

    def get_latest_version(self) -> Optional[VersionInfo]:
        """Get the most recent version."""
        if self.versions:
            return max(self.versions, key=lambda v: v.timestamp)
        return None


class EnhancedAbletonAnalyzer:
    """Deep analysis of Ableton session files."""

    def __init__(self, file_path: str):
        self.file_path = Path(file_path)
        self.root: Optional[ET.Element] = None
        self._load()

    def _load(self):
        """Decompress and parse the file."""
        with gzip.open(self.file_path, 'rb') as f:
            xml_content = f.read()
            self.root = ET.fromstring(xml_content)

    def get_session_info(self) -> Dict:
        """Extract high-level session information."""
        info = {
            'tempo': None,
            'time_signature': None,
            'track_count': 0,
            'scene_count': 0,
            'locators': [],
        }

        # Tempo
        if self.root is not None:
            tempo_elem = self.root.find('.//MasterTrack//Tempo/Manual')
            if tempo_elem is not None:
                info['tempo'] = float(tempo_elem.get('Value', 0))

            # Time signature
            ts_elem = self.root.find('.//MasterTrack//TimeSignature')
            if ts_elem is not None:
                numerator = ts_elem.find('.//TimeSignatures//RemoteableTimeSignature//Numerator')
                denominator = ts_elem.find('.//TimeSignatures//RemoteableTimeSignature//Denominator')
                if numerator is not None and denominator is not None:
                    info['time_signature'] = f"{numerator.get('Value')}/{denominator.get('Value')}"

            # Tracks
            info['track_count'] = (
                len(self.root.findall('.//AudioTrack')) +
                len(self.root.findall('.//MidiTrack')) +
                len(self.root.findall('.//ReturnTrack'))
            )

            # Scenes
            info['scene_count'] = len(self.root.findall('.//Scene'))

            # Locators
            for locator in self.root.findall('.//Locators/Locators/Locator'):
                time_elem = locator.find('.//Time')
                name_elem = locator.find('.//Name')
                if time_elem is not None and name_elem is not None:
                    info['locators'].append({
                        'time': float(time_elem.get('Value', 0)),
                        'name': name_elem.get('Value', '')
                    })

        return info

    def get_track_fingerprint(self, track: ET.Element) -> str:
        """Create a unique fingerprint for a track based on its content."""
        # Use track name + device chain as fingerprint
        name = self._get_track_name(track)
        devices = self._get_device_names(track)
        return f"{name}::{','.join(devices)}"

    def _get_track_name(self, track: ET.Element) -> str:
        """Get track name."""
        name_elem = track.find('.//Name/EffectiveName')
        if name_elem is not None and name_elem.get('Value'):
            return name_elem.get('Value')
        return "Unnamed"

    def _get_device_names(self, track: ET.Element) -> List[str]:
        """Get list of device names in a track."""
        devices = []
        device_chain = track.find('.//DeviceChain/DeviceChain')
        if device_chain is not None:
            for device in device_chain:
                # Try to get plugin name
                plugin_name = device.find('.//PluginDesc/VstPluginInfo/PlugName')
                if plugin_name is not None and plugin_name.get('Value'):
                    devices.append(plugin_name.get('Value'))
                else:
                    # Use tag name for built-in devices
                    devices.append(device.tag)
        return devices

    def get_tracks_with_fingerprints(self) -> Dict[str, ET.Element]:
        """Get all tracks with their fingerprints."""
        tracks = {}
        if self.root is not None:
            all_tracks = (
                self.root.findall('.//AudioTrack') +
                self.root.findall('.//MidiTrack') +
                self.root.findall('.//ReturnTrack')
            )
            for track in all_tracks:
                fingerprint = self.get_track_fingerprint(track)
                tracks[fingerprint] = track
        return tracks

    def analyze_track(self, track: ET.Element) -> Dict:
        """Deep analysis of a single track."""
        analysis = {
            'name': self._get_track_name(track),
            'type': track.tag,
            'color': None,
            'muted': False,
            'soloed': False,
            'armed': False,
            'devices': [],
            'clips': [],
            'volume': None,
            'pan': None,
            'automation': [],
            'midi_stats': {},
        }

        # Color
        color_elem = track.find('.//Color')
        if color_elem is not None:
            analysis['color'] = color_elem.get('Value')

        # Mute/Solo/Arm
        mute_elem = track.find('.//TrackUnfolded')
        if mute_elem is not None:
            analysis['muted'] = mute_elem.get('Value') == 'true'

        # Volume
        vol_elem = track.find('.//Volume/Manual')
        if vol_elem is not None:
            analysis['volume'] = float(vol_elem.get('Value', 0))

        # Pan
        pan_elem = track.find('.//Pan/Manual')
        if pan_elem is not None:
            analysis['pan'] = float(pan_elem.get('Value', 0))

        # Devices
        analysis['devices'] = self._get_device_names(track)

        # Automation lanes
        analysis['automation'] = self._analyze_automation(track)

        # Clips with MIDI analysis
        clip_slots = track.findall('.//ClipSlot')
        for slot in clip_slots:
            midi_clip = slot.find('.//MidiClip')
            audio_clip = slot.find('.//AudioClip')
            clip = midi_clip or audio_clip

            if clip is not None:
                clip_name_elem = clip.find('.//Name')
                clip_info = {
                    'type': 'midi' if midi_clip is not None else 'audio',
                    'name': clip_name_elem.get('Value', '') if clip_name_elem is not None else '',
                }

                # Add MIDI note analysis
                if midi_clip is not None:
                    midi_analysis = self._analyze_midi_clip(midi_clip)
                    clip_info['midi'] = midi_analysis

                    # Aggregate MIDI stats for the track
                    if 'total_notes' not in analysis['midi_stats']:
                        analysis['midi_stats'] = {
                            'total_notes': 0,
                            'clips_with_notes': 0,
                            'pitch_range': None
                        }

                    if midi_analysis['note_count'] > 0:
                        analysis['midi_stats']['total_notes'] += midi_analysis['note_count']
                        analysis['midi_stats']['clips_with_notes'] += 1

                        # Update pitch range
                        if analysis['midi_stats']['pitch_range'] is None:
                            analysis['midi_stats']['pitch_range'] = midi_analysis['pitch_range']
                        elif midi_analysis['pitch_range']:
                            old_min, old_max = analysis['midi_stats']['pitch_range']
                            new_min, new_max = midi_analysis['pitch_range']
                            analysis['midi_stats']['pitch_range'] = (
                                min(old_min, new_min),
                                max(old_max, new_max)
                            )

                analysis['clips'].append(clip_info)

        return analysis

    def _analyze_automation(self, track: ET.Element) -> List[Dict]:
        """Analyze automation lanes in a track."""
        automation_lanes = []

        # Find all automation envelopes
        envelopes = track.findall('.//AutomationEnvelopes/Envelopes/AutomationEnvelope')

        for envelope in envelopes:
            # Get the automated parameter ID
            pointee = envelope.find('.//Envelope/Automation/Pointee')
            if pointee is not None:
                param_id = pointee.get('Id', 'Unknown')

                # Count automation points
                events = envelope.findall('.//Envelope/Automation/Events/FloatEvent')

                # Get min/max values if there are points
                values = []
                for event in events:
                    value = event.get('Value')
                    if value:
                        try:
                            values.append(float(value))
                        except ValueError:
                            pass

                lane_info = {
                    'parameter': param_id,
                    'point_count': len(events),
                }

                if values:
                    lane_info['min_value'] = min(values)
                    lane_info['max_value'] = max(values)

                automation_lanes.append(lane_info)

        return automation_lanes

    def _analyze_midi_clip(self, clip: ET.Element) -> Dict:
        """Analyze MIDI notes in a clip."""
        midi_info = {
            'note_count': 0,
            'pitch_range': None,
            'velocity_range': None,
            'avg_velocity': 0,
        }

        # Find all MIDI notes
        notes = clip.findall('.//Notes/KeyTracks/KeyTrack/Notes/MidiNoteEvent')

        if not notes:
            return midi_info

        midi_info['note_count'] = len(notes)

        pitches = []
        velocities = []

        for note in notes:
            # Get pitch (MIDI note number 0-127)
            pitch = note.get('Key')
            if pitch:
                try:
                    pitches.append(int(pitch))
                except ValueError:
                    pass

            # Get velocity (0.0-1.0 in Ableton XML, usually)
            velocity = note.get('Velocity')
            if velocity:
                try:
                    velocities.append(float(velocity))
                except ValueError:
                    pass

        if pitches:
            midi_info['pitch_range'] = (min(pitches), max(pitches))

        if velocities:
            midi_info['velocity_range'] = (min(velocities), max(velocities))
            midi_info['avg_velocity'] = sum(velocities) / len(velocities)

        return midi_info


def generate_change_report(old_file: str, new_file: str, output_file: Optional[str] = None) -> str:
    """Generate detailed change report between two versions."""
    old = EnhancedAbletonAnalyzer(old_file)
    new = EnhancedAbletonAnalyzer(new_file)

    old_info = old.get_session_info()
    new_info = new.get_session_info()

    old_tracks = old.get_tracks_with_fingerprints()
    new_tracks = new.get_tracks_with_fingerprints()

    old_fingerprints = set(old_tracks.keys())
    new_fingerprints = set(new_tracks.keys())

    report_lines = [
        "=" * 80,
        f"ABLETON SESSION CHANGE REPORT",
        "=" * 80,
        f"Old: {Path(old_file).name}",
        f"New: {Path(new_file).name}",
        f"Generated: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}",
        "=" * 80,
        "",
        "SESSION-LEVEL CHANGES:",
        "-" * 80,
    ]

    # Session info changes
    if old_info['tempo'] != new_info['tempo']:
        report_lines.append(f"  Tempo: {old_info['tempo']} -> {new_info['tempo']} BPM")

    if old_info['time_signature'] != new_info['time_signature']:
        report_lines.append(f"  Time Signature: {old_info['time_signature']} -> {new_info['time_signature']}")

    if old_info['track_count'] != new_info['track_count']:
        report_lines.append(f"  Track Count: {old_info['track_count']} -> {new_info['track_count']}")

    if old_info['scene_count'] != new_info['scene_count']:
        report_lines.append(f"  Scene Count: {old_info['scene_count']} -> {new_info['scene_count']}")

    # Track changes
    added_tracks = new_fingerprints - old_fingerprints
    removed_tracks = old_fingerprints - new_fingerprints
    common_tracks = old_fingerprints & new_fingerprints

    if added_tracks or removed_tracks or common_tracks:
        report_lines.extend(["", "TRACK CHANGES:", "-" * 80])

    if added_tracks:
        report_lines.append(f"\n  Added Tracks ({len(added_tracks)}):")
        for fp in sorted(added_tracks):
            track_name = new.get_track_fingerprint(new_tracks[fp]).split('::')[0]
            report_lines.append(f"    + {track_name}")

    if removed_tracks:
        report_lines.append(f"\n  Removed Tracks ({len(removed_tracks)}):")
        for fp in sorted(removed_tracks):
            track_name = old.get_track_fingerprint(old_tracks[fp]).split('::')[0]
            report_lines.append(f"    - {track_name}")

    # Modified tracks
    if common_tracks:
        modified = []
        for fp in common_tracks:
            old_analysis = old.analyze_track(old_tracks[fp])
            new_analysis = new.analyze_track(new_tracks[fp])

            changes = []
            if old_analysis['volume'] != new_analysis['volume']:
                changes.append(f"volume: {old_analysis['volume']:.2f} -> {new_analysis['volume']:.2f}")

            if old_analysis['pan'] != new_analysis['pan']:
                changes.append(f"pan: {old_analysis['pan']:.2f} -> {new_analysis['pan']:.2f}")

            if old_analysis['devices'] != new_analysis['devices']:
                changes.append(f"devices changed")

            if len(old_analysis['clips']) != len(new_analysis['clips']):
                changes.append(f"clips: {len(old_analysis['clips'])} -> {len(new_analysis['clips'])}")

            # Automation changes
            old_auto_count = len(old_analysis['automation'])
            new_auto_count = len(new_analysis['automation'])
            if old_auto_count != new_auto_count:
                changes.append(f"automation lanes: {old_auto_count} -> {new_auto_count}")

            # MIDI changes
            old_midi = old_analysis.get('midi_stats', {})
            new_midi = new_analysis.get('midi_stats', {})

            old_notes = old_midi.get('total_notes', 0)
            new_notes = new_midi.get('total_notes', 0)

            if old_notes != new_notes:
                changes.append(f"MIDI notes: {old_notes} -> {new_notes}")

            # Pitch range changes
            old_pitch = old_midi.get('pitch_range')
            new_pitch = new_midi.get('pitch_range')

            if old_pitch != new_pitch and new_pitch:
                pitch_low, pitch_high = new_pitch
                note_names = ['C', 'C#', 'D', 'D#', 'E', 'F', 'F#', 'G', 'G#', 'A', 'A#', 'B']
                low_note = f"{note_names[pitch_low % 12]}{pitch_low // 12 - 2}"
                high_note = f"{note_names[pitch_high % 12]}{pitch_high // 12 - 2}"
                changes.append(f"pitch range: {low_note} to {high_note}")

            if changes:
                modified.append((old_analysis['name'], changes))

        if modified:
            report_lines.append(f"\n  Modified Tracks ({len(modified)}):")
            for track_name, changes in modified:
                report_lines.append(f"    * {track_name}")
                for change in changes:
                    report_lines.append(f"        - {change}")

    report_lines.extend(["", "=" * 80])

    report = "\n".join(report_lines)

    if output_file:
        with open(output_file, 'w') as f:
            f.write(report)

    return report


def main():
    """CLI entry point."""
    import argparse

    parser = argparse.ArgumentParser(description='Ableton Project Version Manager')
    subparsers = parser.add_subparsers(dest='command', help='Commands')

    # Scan command
    scan_parser = subparsers.add_parser('scan', help='Scan for new versions')
    scan_parser.add_argument('project_path', help='Path to Ableton project folder')

    # Compare command
    compare_parser = subparsers.add_parser('compare', help='Compare two versions')
    compare_parser.add_argument('old_file', help='Old .als file')
    compare_parser.add_argument('new_file', help='New .als file')
    compare_parser.add_argument('-o', '--output', help='Output file')

    # History command
    history_parser = subparsers.add_parser('history', help='Show version history')
    history_parser.add_argument('project_path', help='Path to Ableton project folder')

    # Diff latest command
    diff_parser = subparsers.add_parser('diff-latest', help='Compare latest two versions')
    diff_parser.add_argument('project_path', help='Path to Ableton project folder')
    diff_parser.add_argument('-o', '--output', help='Output file')

    args = parser.parse_args()

    if args.command == 'scan':
        manager = ProjectVersionManager(args.project_path)
        new_versions = manager.register_new_versions()

        if new_versions:
            print(f"Found {len(new_versions)} new version(s):")
            for v in new_versions:
                print(f"  - {v.version} ({v.timestamp.strftime('%Y-%m-%d %H:%M:%S')})")
        else:
            print("No new versions found.")

    elif args.command == 'compare':
        report = generate_change_report(args.old_file, args.new_file, args.output)
        if not args.output:
            print(report)
        else:
            print(f"Report saved to {args.output}")

    elif args.command == 'history':
        manager = ProjectVersionManager(args.project_path)
        versions = manager.get_sorted_versions()

        if versions:
            print(f"\nVersion History ({len(versions)} versions):")
            print("-" * 80)
            for v in versions:
                print(f"  {v.version:15} {v.timestamp.strftime('%Y-%m-%d %H:%M:%S')}")
                if v.metadata:
                    for key, val in v.metadata.items():
                        if key not in ['filepath', 'name']:
                            print(f"    {key}: {val}")
        else:
            print("No versions found.")

    elif args.command == 'diff-latest':
        manager = ProjectVersionManager(args.project_path)
        manager.register_new_versions()
        versions = manager.get_sorted_versions()

        if len(versions) >= 2:
            old_version = versions[-2]
            new_version = versions[-1]
            print(f"Comparing {old_version.version} -> {new_version.version}\n")
            report = generate_change_report(old_version.filepath, new_version.filepath, args.output)
            if not args.output:
                print(report)
            else:
                print(f"Report saved to {args.output}")
        else:
            print("Need at least 2 versions to compare.")

    else:
        parser.print_help()


if __name__ == '__main__':
    main()
