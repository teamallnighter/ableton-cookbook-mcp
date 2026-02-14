# Ableton Version Control & Analysis System

A complete automated versioning and change tracking system for Ableton Live projects. Analyzes gzipped XML content to detect and visualize changes between project versions.

## Features

### Core Analysis
- Decompress and parse Ableton Live files (.als, .adg, .adv)
- Deep XML analysis with intelligent change detection
- Track fingerprinting (prevents false positives from track reordering)
- Detect changes in:
  - Tracks (added/removed/moved)
  - Devices and plugins
  - Clips and scenes
  - Parameters (volume, pan, tempo, etc.)
  - Session-level settings

### Version Management
- Automatic version discovery and tracking
- Version metadata integration (reads your .json version files)
- Change comparison between any two versions
- Complete version history database

### Visualization
- Beautiful HTML timeline of version history
- Interactive change tracking
- Project statistics dashboard
- Automatic report generation

### Automation
- File watcher for automatic version detection
- Auto-generates change reports when new versions are saved
- Updates visualization timeline automatically

## Requirements

- Python 3.7+
- No external dependencies (uses only standard library)

## Quick Start

### 1. Watch Your Project (Recommended)

The easiest way to use the system - automatically tracks all new versions:

```bash
python watch_project.py "/path/to/your/Ableton Project"
```

This will:
- Continuously monitor for new versions
- Generate change reports automatically
- Update the HTML timeline
- Display changes in real-time

### 2. Manual Version Management

#### Scan for New Versions
```bash
python ableton_version_manager.py scan "/path/to/project"
```

#### View Version History
```bash
python ableton_version_manager.py history "/path/to/project"
```

#### Compare Latest Two Versions
```bash
python ableton_version_manager.py diff-latest "/path/to/project"
```

#### Compare Specific Versions
```bash
python ableton_version_manager.py compare old.als new.als -o report.txt
```

### 3. Generate Timeline Visualization

```bash
python ableton_visualizer.py "/path/to/project" -o timeline.html
```

Opens a beautiful HTML page showing your version history with:
- Timeline of all versions
- Track/scene/tempo changes
- Version metadata
- Interactive change badges

## Example Output

### Change Report
```
================================================================================
ABLETON SESSION CHANGE REPORT
================================================================================
Old: something_right.als
New: something_right_0.0.1.als
Generated: 2026-01-10 20:21:13
================================================================================

SESSION-LEVEL CHANGES:
--------------------------------------------------------------------------------
  Track Count: 69 -> 68
  Tempo: 145.0 BPM

TRACK CHANGES:
--------------------------------------------------------------------------------

  Removed Tracks (1):
    - 80-Audio

  Modified Tracks (3):
    * Drums
        - volume: -12.00 -> -6.00
        - clips: 2 -> 3
    * Bass
        - pan: 0.00 -> -0.25
    * Synth Lead
        - devices changed

================================================================================
```

### Version History
```
Version History (2 versions):
--------------------------------------------------------------------------------
  0.0.1           2026-01-01 22:43:07
    tempo: 145.0
    scale: F#
    lastModifiedDate: 1/1/2026
```

## Your Workflow Integration

This system is designed to work with your manual versioning workflow:

### Typical Workflow

1. Work on your Ableton project normally
2. When you reach a milestone, save a versioned copy:
   - `my_project_0.0.1.als`
   - `my_project_0.0.2.als`
   - etc.
3. Optionally create metadata JSON files with version info
4. Run the watcher or manually scan for changes

### Project Structure

The system expects this structure (standard Ableton project):

```
Your Project/
├── project_name.als                    # Current/working file
├── project_name_0.0.1.als             # Version files
├── project_name_0.0.2.als
├──  project_name_0.0.1.json           # Optional metadata (can have leading space)
├── _history/                           # Auto-created
│   ├── versions.json                   # Version database
│   ├── timeline.html                   # Visual timeline
│   └── reports/                        # Generated reports
│       └── changes_0.0.1_to_0.0.2.txt
├── Backup/                             # Ableton's auto-backups
├── Samples/
└── ... (other Ableton folders)
```

### Metadata JSON Format

Optionally create JSON files alongside your versions:

```json
{
    "name": "something_right_0.0.1",
    "filepath": "/path/to/something_right_0.0.1.als",
    "tempo": 145.0,
    "lastModifiedDate": "1/1/2026",
    "lastModifiedTime": "18:56:25",
    "scale": "F#"
}
```

The system will automatically read these and include the metadata in reports.

## File Organization

### Generated Files

All generated files are stored in `_history/` to keep your project clean:

- `_history/versions.json` - Version database (tracks all discovered versions)
- `_history/timeline.html` - Visual timeline (open in browser)
- `_history/reports/changes_X_to_Y.txt` - Change reports for each version transition

### Scripts

- `ableton_version_manager.py` - Core version management and comparison
- `ableton_visualizer.py` - HTML timeline generation
- `watch_project.py` - Automated watcher
- `ableton_diff.py` - Basic diff tool (standalone)

## How It Works

### Track Fingerprinting
Instead of comparing tracks by position (which causes false positives when you reorder), the system creates "fingerprints" based on:
- Track name
- Device chain

This means if you just move Track 5 to position 3, it won't show up as removed+added.

### Change Detection
1. **Decompression**: Uses gzip to decompress Ableton files
2. **XML Parsing**: Parses the structure with ElementTree
3. **Fingerprinting**: Creates unique identifiers for tracks
4. **Deep Comparison**: Analyzes parameters, devices, clips
5. **Smart Reporting**: Only reports actual changes

### What's Detected

Currently tracks:
- Session-level: tempo, time signature, track count, scenes, locators
- Track-level: add/remove/modify, volume, pan, color
- Device-level: count changes, device names
- Clip-level: count changes, clip names
- **Automation**: automation lanes, point counts, value ranges
- **MIDI notes**: note counts, pitch ranges (with note names like "C3 to G5"), velocity analysis

### Easy to Extend

Want to track more? The code is modular and easy to extend:

```python
# In EnhancedAbletonAnalyzer.analyze_track()
# Add your custom analysis:

# Example: Track automation
automation = track.findall('.//AutomationEnvelope')
analysis['automation_count'] = len(automation)

# Example: Device parameters
for device in devices:
    params = device.findall('.//Parameter')
    # Analyze parameters...
```

## Advanced Usage

### Run Watcher in Background (macOS/Linux)

```bash
# Start in background
nohup python watch_project.py "/path/to/project" > watcher.log 2>&1 &

# Check if running
ps aux | grep watch_project

# Stop
pkill -f watch_project.py
```

### Check Once (No Watching)

```bash
python watch_project.py "/path/to/project" --once
```

### Custom Check Interval

```bash
# Check every 30 seconds instead of 10
python watch_project.py "/path/to/project" --interval 30
```

## Future Enhancement Ideas

Potential additions you could implement:
- Git integration (auto-commit versions)
- Slack/Discord notifications for new versions
- Audio analysis (compare rendered audio)
- Automation curve comparison
- MIDI note diff for clips
- Device preset comparison
- Export to PDF reports
- Web dashboard with Flask/FastAPI
- Plugin parameter deep-dive

## Troubleshooting

**Q: Not detecting my versions?**
A: Make sure version files follow the pattern `projectname_X.Y.Z.als` (e.g., `my_song_0.0.1.als`)

**Q: Getting false track changes?**
A: This should be rare with fingerprinting, but can happen if you rename tracks AND change their devices simultaneously.

**Q: Watcher not seeing new saves?**
A: Make sure you're saving with the version number pattern. The watcher looks for `_X.Y.Z.als` files.

**Q: Where's the visualization?**
A: Open `_history/timeline.html` in any web browser after running the visualizer or watcher.

## Contributing

This is a foundation for Ableton version control. Feel free to:
- Add more analysis features
- Improve the visualization
- Create plugins/integrations
- Share improvements

## License

Free to use and modify for your projects.
