#!/usr/bin/env python3
"""
Ableton Live Session Analyzer
Decompresses and compares Ableton Live files (.als, .adg, .adv) to detect changes.
"""

import gzip
import xml.etree.ElementTree as ET
from pathlib import Path
from typing import Dict, List, Tuple, Optional
from dataclasses import dataclass, field
from collections import defaultdict


@dataclass
class Change:
    """Represents a detected change between two sessions."""
    change_type: str  # 'added', 'removed', 'modified'
    category: str     # 'track', 'device', 'parameter', 'clip', etc.
    path: str         # XPath-like location
    details: Dict = field(default_factory=dict)

    def __str__(self):
        if self.change_type == 'added':
            return f"+ Added {self.category}: {self.path} {self._format_details()}"
        elif self.change_type == 'removed':
            return f"- Removed {self.category}: {self.path} {self._format_details()}"
        else:
            return f"* Modified {self.category}: {self.path} {self._format_details()}"

    def _format_details(self):
        if not self.details:
            return ""
        return f"({', '.join(f'{k}={v}' for k, v in self.details.items())})"


class AbletonFile:
    """Handles reading and parsing Ableton Live files."""

    def __init__(self, file_path: str):
        self.file_path = Path(file_path)
        self.root: Optional[ET.Element] = None
        self._load()

    def _load(self):
        """Decompress and parse the Ableton file."""
        try:
            with gzip.open(self.file_path, 'rb') as f:
                xml_content = f.read()
                self.root = ET.fromstring(xml_content)
        except Exception as e:
            raise ValueError(f"Failed to load {self.file_path}: {e}")

    def get_tracks(self) -> List[ET.Element]:
        """Extract all tracks from the session."""
        tracks = []
        if self.root is not None:
            # Audio tracks
            tracks.extend(self.root.findall('.//AudioTrack'))
            # MIDI tracks
            tracks.extend(self.root.findall('.//MidiTrack'))
            # Return tracks
            tracks.extend(self.root.findall('.//ReturnTrack'))
            # Master track
            tracks.extend(self.root.findall('.//MasterTrack'))
        return tracks

    def get_track_name(self, track: ET.Element) -> str:
        """Get the name of a track."""
        name_elem = track.find('.//Name/EffectiveName')
        if name_elem is not None and name_elem.get('Value'):
            return name_elem.get('Value')
        return "Unnamed Track"

    def get_devices(self, track: ET.Element) -> List[ET.Element]:
        """Get all devices in a track."""
        devices = []
        device_chain = track.find('.//DeviceChain/DeviceChain')
        if device_chain is not None:
            devices.extend(device_chain.findall('.//AudioEffectBranch'))
            devices.extend(device_chain.findall('.//MidiEffectBranch'))
            devices.extend(device_chain.findall('.//InstrumentBranch'))
        return devices

    def get_clips(self, track: ET.Element) -> List[ET.Element]:
        """Get all clips in a track."""
        clips = []
        clip_slots = track.findall('.//ClipSlot')
        for slot in clip_slots:
            clip = slot.find('.//MidiClip')
            if clip is None:
                clip = slot.find('.//AudioClip')
            if clip is not None:
                clips.append(clip)
        return clips


class AbletonDiff:
    """Compares two Ableton Live sessions and identifies changes."""

    def __init__(self, old_file: str, new_file: str):
        self.old = AbletonFile(old_file)
        self.new = AbletonFile(new_file)
        self.changes: List[Change] = []

    def compare(self) -> List[Change]:
        """Perform full comparison and return list of changes."""
        self.changes = []
        self._compare_tracks()
        return self.changes

    def _element_to_dict(self, elem: ET.Element, max_depth: int = 3, current_depth: int = 0) -> Dict:
        """Convert XML element to dict for comparison."""
        if current_depth >= max_depth:
            return {'_text': elem.text or ''}

        result = {}
        if elem.attrib:
            result.update(elem.attrib)

        if elem.text and elem.text.strip():
            result['_text'] = elem.text.strip()

        if current_depth < max_depth - 1:
            for child in elem:
                tag = child.tag
                child_dict = self._element_to_dict(child, max_depth, current_depth + 1)
                if tag in result:
                    if not isinstance(result[tag], list):
                        result[tag] = [result[tag]]
                    result[tag].append(child_dict)
                else:
                    result[tag] = child_dict

        return result

    def _compare_tracks(self):
        """Compare tracks between old and new sessions."""
        old_tracks = self.old.get_tracks()
        new_tracks = self.new.get_tracks()

        # Create track mappings by name and index
        old_track_map = {(i, self.old.get_track_name(t)): t for i, t in enumerate(old_tracks)}
        new_track_map = {(i, self.new.get_track_name(t)): t for i, t in enumerate(new_tracks)}

        old_keys = set(old_track_map.keys())
        new_keys = set(new_track_map.keys())

        # Detect added tracks
        for key in new_keys - old_keys:
            idx, name = key
            self.changes.append(Change(
                change_type='added',
                category='track',
                path=f"Track[{idx}]",
                details={'name': name, 'type': new_track_map[key].tag}
            ))

        # Detect removed tracks
        for key in old_keys - new_keys:
            idx, name = key
            self.changes.append(Change(
                change_type='removed',
                category='track',
                path=f"Track[{idx}]",
                details={'name': name, 'type': old_track_map[key].tag}
            ))

        # Compare existing tracks
        for key in old_keys & new_keys:
            idx, name = key
            self._compare_track_contents(
                old_track_map[key],
                new_track_map[key],
                f"Track[{idx}]:{name}"
            )

    def _compare_track_contents(self, old_track: ET.Element, new_track: ET.Element, track_path: str):
        """Compare the contents of two tracks."""
        # Compare devices
        old_devices = self.old.get_devices(old_track)
        new_devices = self.new.get_devices(new_track)

        if len(old_devices) != len(new_devices):
            self.changes.append(Change(
                change_type='modified',
                category='track',
                path=track_path,
                details={'device_count': f"{len(old_devices)} -> {len(new_devices)}"}
            ))

        # Compare clips
        old_clips = self.old.get_clips(old_track)
        new_clips = self.new.get_clips(new_track)

        if len(old_clips) != len(new_clips):
            self.changes.append(Change(
                change_type='modified',
                category='track',
                path=track_path,
                details={'clip_count': f"{len(old_clips)} -> {len(new_clips)}"}
            ))

        # Compare track parameters (volume, pan, etc.)
        self._compare_parameters(old_track, new_track, track_path)

    def _compare_parameters(self, old_elem: ET.Element, new_elem: ET.Element, path: str):
        """Compare parameter values between two elements."""
        # Check common parameters
        param_paths = [
            ('.//Volume/Manual', 'volume'),
            ('.//Pan/Manual', 'pan'),
            ('.//Tempo/Manual', 'tempo'),
        ]

        for xpath, param_name in param_paths:
            old_param = old_elem.find(xpath)
            new_param = new_elem.find(xpath)

            if old_param is not None and new_param is not None:
                old_val = old_param.get('Value')
                new_val = new_param.get('Value')

                if old_val != new_val:
                    self.changes.append(Change(
                        change_type='modified',
                        category='parameter',
                        path=f"{path}/{param_name}",
                        details={'value': f"{old_val} -> {new_val}"}
                    ))

    def generate_report(self) -> str:
        """Generate a human-readable report of changes."""
        if not self.changes:
            return "No changes detected."

        report_lines = [
            f"Ableton Session Comparison Report",
            f"=" * 50,
            f"Old: {self.old.file_path.name}",
            f"New: {self.new.file_path.name}",
            f"Total changes: {len(self.changes)}",
            f"=" * 50,
            ""
        ]

        # Group changes by category
        by_category = defaultdict(list)
        for change in self.changes:
            by_category[change.category].append(change)

        for category in sorted(by_category.keys()):
            report_lines.append(f"\n{category.upper()} CHANGES:")
            report_lines.append("-" * 50)
            for change in by_category[category]:
                report_lines.append(str(change))

        return "\n".join(report_lines)


def main():
    """CLI entry point."""
    import argparse

    parser = argparse.ArgumentParser(
        description='Compare two Ableton Live session files and detect changes.'
    )
    parser.add_argument('old_file', help='Path to old .als file')
    parser.add_argument('new_file', help='Path to new .als file')
    parser.add_argument('-o', '--output', help='Output file for report (default: stdout)')
    parser.add_argument('-v', '--verbose', action='store_true', help='Verbose output')

    args = parser.parse_args()

    try:
        differ = AbletonDiff(args.old_file, args.new_file)
        changes = differ.compare()
        report = differ.generate_report()

        if args.output:
            with open(args.output, 'w') as f:
                f.write(report)
            print(f"Report written to {args.output}")
        else:
            print(report)

        if args.verbose:
            print(f"\nProcessed {len(changes)} changes")

    except Exception as e:
        print(f"Error: {e}")
        return 1

    return 0


if __name__ == '__main__':
    exit(main())
