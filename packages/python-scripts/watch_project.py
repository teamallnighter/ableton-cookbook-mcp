#!/usr/bin/env python3
"""
Ableton Project Watcher
Monitors an Ableton project folder for new versions and automatically generates reports.
"""

import time
import sys
from pathlib import Path
from datetime import datetime
from ableton_version_manager import ProjectVersionManager, generate_change_report
from ableton_visualizer import generate_html_timeline


class ProjectWatcher:
    """Watches an Ableton project folder for changes."""

    def __init__(self, project_path: str, check_interval: int = 10):
        self.project_path = Path(project_path)
        self.check_interval = check_interval
        self.manager = ProjectVersionManager(str(project_path))
        self.last_version_count = len(self.manager.versions)

        # Create reports directory
        self.reports_dir = self.project_path / "_history" / "reports"
        self.reports_dir.mkdir(parents=True, exist_ok=True)

    def check_for_new_versions(self):
        """Check for new versions and process them."""
        new_versions = self.manager.register_new_versions()

        if new_versions:
            print(f"\n{'='*80}")
            print(f"[{datetime.now().strftime('%Y-%m-%d %H:%M:%S')}] New version(s) detected!")
            print(f"{'='*80}")

            for version in new_versions:
                print(f"\n  Version: {version.version}")
                print(f"  Time: {version.timestamp.strftime('%Y-%m-%d %H:%M:%S')}")
                if version.metadata:
                    for key, val in version.metadata.items():
                        if key not in ['filepath', 'name']:
                            print(f"  {key}: {val}")

            # Generate comparison report with previous version
            versions = self.manager.get_sorted_versions()
            if len(versions) >= 2:
                old_version = versions[-2]
                new_version = versions[-1]

                print(f"\n  Comparing {old_version.version} -> {new_version.version}...")

                report_file = self.reports_dir / f"changes_{old_version.version}_to_{new_version.version}.txt"
                report = generate_change_report(
                    old_version.filepath,
                    new_version.filepath,
                    str(report_file)
                )

                print(f"  Change report saved: {report_file.name}")

                # Print summary
                print("\n" + "─" * 80)
                print(report)
                print("─" * 80)

            # Update HTML timeline
            print("\n  Updating timeline visualization...")
            timeline_file = self.project_path / "_history" / "timeline.html"
            generate_html_timeline(str(self.project_path), str(timeline_file))
            print(f"  Timeline updated: {timeline_file}")

            print(f"\n{'='*80}\n")

            self.last_version_count = len(versions)
            return True

        return False

    def run(self):
        """Start watching the project folder."""
        print(f"{'='*80}")
        print(f"Watching Ableton Project: {self.project_path.name}")
        print(f"{'='*80}")
        print(f"Check interval: {self.check_interval} seconds")
        print(f"Reports directory: {self.reports_dir}")
        print(f"Press Ctrl+C to stop\n")

        # Initial scan
        self.manager.register_new_versions()
        versions = self.manager.get_sorted_versions()
        print(f"Current versions: {len(versions)}")
        for v in versions:
            print(f"  - {v.version} ({v.timestamp.strftime('%Y-%m-%d %H:%M:%S')})")

        print(f"\nWatching for changes...\n")

        try:
            while True:
                self.check_for_new_versions()
                time.sleep(self.check_interval)
        except KeyboardInterrupt:
            print("\n\nStopping watcher...")
            print("Goodbye!")


def main():
    """CLI entry point."""
    import argparse

    parser = argparse.ArgumentParser(
        description='Watch Ableton project folder for new versions',
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog="""
Examples:
  # Watch project with default 10 second interval
  %(prog)s "/path/to/project"

  # Watch with custom interval
  %(prog)s "/path/to/project" --interval 30

  # Check once and exit (no watching)
  %(prog)s "/path/to/project" --once
        """
    )

    parser.add_argument(
        'project_path',
        help='Path to Ableton project folder'
    )
    parser.add_argument(
        '-i', '--interval',
        type=int,
        default=10,
        help='Check interval in seconds (default: 10)'
    )
    parser.add_argument(
        '--once',
        action='store_true',
        help='Check once and exit (do not watch continuously)'
    )

    args = parser.parse_args()

    # Validate project path
    project_path = Path(args.project_path)
    if not project_path.exists():
        print(f"Error: Project path does not exist: {project_path}")
        sys.exit(1)

    if not project_path.is_dir():
        print(f"Error: Project path is not a directory: {project_path}")
        sys.exit(1)

    watcher = ProjectWatcher(str(project_path), args.interval)

    if args.once:
        # Just check once
        found = watcher.check_for_new_versions()
        if not found:
            print("No new versions found.")
    else:
        # Run continuously
        watcher.run()


if __name__ == '__main__':
    main()
