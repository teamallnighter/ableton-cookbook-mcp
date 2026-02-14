#!/usr/bin/env python3
"""
Detailed Track Analyzer
Shows in-depth analysis of a specific track including automation and MIDI.
"""

import sys
from pathlib import Path
from ableton_version_manager import EnhancedAbletonAnalyzer


def analyze_track_detailed(file_path: str, track_name: str = None):
    """Analyze a specific track or all tracks in detail."""
    analyzer = EnhancedAbletonAnalyzer(file_path)
    tracks = analyzer.get_tracks_with_fingerprints()

    print("=" * 80)
    print(f"DETAILED TRACK ANALYSIS")
    print("=" * 80)
    print(f"File: {Path(file_path).name}")
    print("=" * 80)
    print()

    for fingerprint, track_elem in tracks.items():
        analysis = analyzer.analyze_track(track_elem)

        # If track_name specified, only show that track
        if track_name and analysis['name'].lower() != track_name.lower():
            continue

        print(f"TRACK: {analysis['name']}")
        print("-" * 80)
        print(f"  Type: {analysis['type']}")
        print(f"  Volume: {analysis['volume']:.2f} dB" if analysis['volume'] is not None else "  Volume: N/A")
        print(f"  Pan: {analysis['pan']:.2f}" if analysis['pan'] is not None else "  Pan: N/A")

        # Devices
        if analysis['devices']:
            print(f"\n  Devices ({len(analysis['devices'])}):")
            for device in analysis['devices']:
                print(f"    - {device}")

        # Clips
        if analysis['clips']:
            print(f"\n  Clips ({len(analysis['clips'])}):")
            for i, clip in enumerate(analysis['clips'], 1):
                clip_name = clip['name'] or f"Clip {i}"
                print(f"    {i}. {clip_name} ({clip['type']})")

                # MIDI analysis
                if clip['type'] == 'midi' and 'midi' in clip:
                    midi = clip['midi']
                    if midi['note_count'] > 0:
                        print(f"       Notes: {midi['note_count']}")

                        if midi['pitch_range']:
                            low, high = midi['pitch_range']
                            note_names = ['C', 'C#', 'D', 'D#', 'E', 'F', 'F#', 'G', 'G#', 'A', 'A#', 'B']
                            low_note = f"{note_names[low % 12]}{low // 12 - 2}"
                            high_note = f"{note_names[high % 12]}{high // 12 - 2}"
                            print(f"       Range: {low_note} ({low}) to {high_note} ({high})")

                        if midi.get('avg_velocity'):
                            print(f"       Avg Velocity: {midi['avg_velocity']:.2f}")

        # MIDI stats for the track
        if analysis['midi_stats'] and analysis['midi_stats'].get('total_notes', 0) > 0:
            print(f"\n  MIDI Summary:")
            print(f"    Total Notes: {analysis['midi_stats']['total_notes']}")
            print(f"    Clips with MIDI: {analysis['midi_stats']['clips_with_notes']}")

            if analysis['midi_stats']['pitch_range']:
                low, high = analysis['midi_stats']['pitch_range']
                note_names = ['C', 'C#', 'D', 'D#', 'E', 'F', 'F#', 'G', 'G#', 'A', 'A#', 'B']
                low_note = f"{note_names[low % 12]}{low // 12 - 2}"
                high_note = f"{note_names[high % 12]}{high // 12 - 2}"
                print(f"    Overall Range: {low_note} to {high_note}")

        # Automation
        if analysis['automation']:
            print(f"\n  Automation Lanes ({len(analysis['automation'])}):")
            for auto in analysis['automation']:
                param = auto['parameter']
                points = auto['point_count']
                print(f"    - {param}: {points} points", end='')

                if 'min_value' in auto and 'max_value' in auto:
                    print(f" (range: {auto['min_value']:.3f} to {auto['max_value']:.3f})")
                else:
                    print()

        print()
        print()

        # If we were looking for a specific track, we found it
        if track_name:
            break

    if track_name and not any(a['name'].lower() == track_name.lower()
                               for a in [analyzer.analyze_track(t) for t in tracks.values()]):
        print(f"Track '{track_name}' not found.")
        print(f"\nAvailable tracks:")
        for t in tracks.values():
            analysis = analyzer.analyze_track(t)
            print(f"  - {analysis['name']}")


def main():
    """CLI entry point."""
    import argparse

    parser = argparse.ArgumentParser(
        description='Detailed track analysis including automation and MIDI'
    )
    parser.add_argument('file', help='Path to .als file')
    parser.add_argument('-t', '--track', help='Specific track name to analyze')

    args = parser.parse_args()

    if not Path(args.file).exists():
        print(f"Error: File not found: {args.file}")
        sys.exit(1)

    analyze_track_detailed(args.file, args.track)


if __name__ == '__main__':
    main()
