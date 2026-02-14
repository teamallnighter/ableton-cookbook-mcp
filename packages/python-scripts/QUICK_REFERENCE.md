# Quick Reference Guide

## One-Line Commands

### Start Automatic Monitoring
```bash
python watch_project.py "/path/to/your/Ableton Project"
```
Does everything automatically: detects versions, generates reports, updates timeline.

### Check for New Versions Once
```bash
python watch_project.py "/path/to/your/Ableton Project" --once
```

### View What Changed in Latest Version
```bash
python ableton_version_manager.py diff-latest "/path/to/project"
```

### See All Versions
```bash
python ableton_version_manager.py history "/path/to/project"
```

### Generate Visual Timeline
```bash
python ableton_visualizer.py "/path/to/project"
open timeline.html
```

### Analyze Track Details (Automation & MIDI)
```bash
# Analyze all tracks
python analyze_track.py "project.als"

# Analyze specific track
python analyze_track.py "project.als" -t "Bass"
```

## File Naming Convention

Your version files must follow this pattern:
```
projectname_X.Y.Z.als
```

Examples:
- `my_song_0.0.1.als` ✓
- `my_song_1.2.3.als` ✓
- `my_song_v1.als` ✗ (wrong format)
- `my_song.als` ✗ (no version)

## What Gets Detected

| Type | What's Tracked |
|------|----------------|
| Session | Tempo, time signature, track count, scenes |
| Tracks | Add, remove, rename, reorder |
| Devices | Count changes, plugin names |
| Clips | Count changes, clip names |
| Parameters | Volume, pan |
| Automation | Lane count, point counts, value ranges |
| MIDI | Note counts, pitch ranges (C3-G5), velocity |

## Where Files Go

All generated files are in `_history/`:
```
Your Project/
└── _history/
    ├── versions.json           # Version database
    ├── timeline.html           # Visual timeline
    └── reports/
        ├── changes_0.0.1_to_0.0.2.txt
        └── changes_0.0.2_to_0.0.3.txt
```

## Common Workflows

### Daily Production
1. Start the watcher in the morning: `python watch_project.py "/path/to/project"`
2. Work on your project normally
3. When you hit a milestone, save as `projectname_X.Y.Z.als`
4. The watcher auto-detects and generates reports
5. Check `_history/timeline.html` to see your progress

### Post-Production Review
1. Run: `python ableton_visualizer.py "/path/to/project"`
2. Open `_history/timeline.html`
3. See the full evolution of your project

### Collaboration
1. Collaborator sends you their version
2. Compare: `python ableton_version_manager.py compare my_version.als their_version.als`
3. See exactly what they changed

## Keyboard Shortcuts

When watcher is running:
- `Ctrl+C` - Stop watching

## Tips

1. **Use semantic versioning**:
   - `X.0.0` - Major changes (new sections, complete restructures)
   - `0.X.0` - Minor changes (new tracks, significant edits)
   - `0.0.X` - Patches (small tweaks, parameter changes)

2. **Add metadata JSON files** for better tracking:
   ```json
   {
       "name": "my_song_0.0.1",
       "tempo": 128.0,
       "scale": "Am",
       "notes": "Added bass and drums"
   }
   ```

3. **Run watcher in background**:
   ```bash
   nohup python watch_project.py "/path" > watcher.log 2>&1 &
   ```

4. **Check timeline regularly** - It's satisfying to see your project evolve!

## Troubleshooting

| Problem | Solution |
|---------|----------|
| Versions not detected | Check filename matches `name_X.Y.Z.als` pattern |
| False track changes | Rare with fingerprinting; check if you renamed + modified |
| Timeline not updating | Re-run visualizer manually |
| Watcher missing saves | Ensure version number in filename |

## Example Session

```bash
# Morning: Start watcher
$ python watch_project.py "/Volumes/ABLETON/Projects/my_song Project"
Watching for changes...

# You work and save: my_song_0.1.0.als
[10:30:15] New version detected!
  Version: 0.1.0
  Comparing 0.0.9 -> 0.1.0...

  Added Tracks (2):
    - Vocals
    - Bass

  Report saved: _history/reports/changes_0.0.9_to_0.1.0.txt

# Later: Another save as my_song_0.1.1.als
[14:22:33] New version detected!
  Version: 0.1.1
  Comparing 0.1.0 -> 0.1.1...

  Modified Tracks (1):
    * Drums
        - volume: -12.00 -> -6.00

# End of day: Check timeline
$ open _history/timeline.html
# See beautiful visualization of your day's work!
```

## Getting Help

Run any script with `-h` for help:
```bash
python watch_project.py -h
python ableton_version_manager.py -h
python ableton_visualizer.py -h
```
