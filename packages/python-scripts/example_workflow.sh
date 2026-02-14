#!/bin/bash
# Example workflow script for Ableton version management
# This demonstrates how you might integrate the system into your daily workflow

PROJECT_PATH="/Volumes/ABLETON/Projects/something_right Project"

echo "=================================="
echo "Ableton Version Management"
echo "=================================="
echo ""
echo "What would you like to do?"
echo ""
echo "1. Start watching for new versions (automatic)"
echo "2. Scan for new versions (manual check)"
echo "3. View version history"
echo "4. Compare latest two versions"
echo "5. Generate timeline visualization"
echo "6. Compare specific versions"
echo ""
read -p "Enter choice [1-6]: " choice

case $choice in
    1)
        echo "Starting project watcher..."
        echo "This will monitor for new versions and auto-generate reports."
        echo "Press Ctrl+C to stop."
        echo ""
        python watch_project.py "$PROJECT_PATH"
        ;;
    2)
        echo "Scanning for new versions..."
        python ableton_version_manager.py scan "$PROJECT_PATH"
        ;;
    3)
        echo "Version History:"
        echo "=================================="
        python ableton_version_manager.py history "$PROJECT_PATH"
        ;;
    4)
        echo "Comparing latest two versions..."
        echo "=================================="
        python ableton_version_manager.py diff-latest "$PROJECT_PATH"
        ;;
    5)
        echo "Generating timeline visualization..."
        python ableton_visualizer.py "$PROJECT_PATH" -o "$PROJECT_PATH/_history/timeline.html"
        echo ""
        echo "Opening timeline in browser..."
        open "$PROJECT_PATH/_history/timeline.html"
        ;;
    6)
        echo "Enter path to old version:"
        read old_file
        echo "Enter path to new version:"
        read new_file
        echo "Comparing..."
        python ableton_version_manager.py compare "$old_file" "$new_file"
        ;;
    *)
        echo "Invalid choice"
        exit 1
        ;;
esac
