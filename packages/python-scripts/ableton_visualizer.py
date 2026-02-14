#!/usr/bin/env python3
"""
Ableton Version Visualizer
Creates HTML visualizations of version history and changes.
"""

import json
from pathlib import Path
from datetime import datetime
from typing import List, Dict
from ableton_version_manager import ProjectVersionManager, EnhancedAbletonAnalyzer


def generate_html_timeline(project_path: str, output_file: str = "timeline.html"):
    """Generate an interactive HTML timeline of version history."""
    manager = ProjectVersionManager(project_path)
    manager.register_new_versions()
    versions = manager.get_sorted_versions()

    if not versions:
        print("No versions found to visualize.")
        return

    # Analyze each version
    version_analyses = []
    for v in versions:
        try:
            analyzer = EnhancedAbletonAnalyzer(v.filepath)
            info = analyzer.get_session_info()
            tracks = analyzer.get_tracks_with_fingerprints()

            version_analyses.append({
                'version': v.version,
                'timestamp': v.timestamp.strftime('%Y-%m-%d %H:%M:%S'),
                'tempo': info['tempo'],
                'track_count': info['track_count'],
                'scene_count': info['scene_count'],
                'metadata': v.metadata,
                'tracks': list(tracks.keys())
            })
        except Exception as e:
            print(f"Warning: Could not analyze {v.version}: {e}")

    # Calculate changes between versions
    changes = []
    for i in range(len(version_analyses) - 1):
        old = version_analyses[i]
        new = version_analyses[i + 1]

        old_tracks = set(old['tracks'])
        new_tracks = set(new['tracks'])

        change_summary = {
            'from_version': old['version'],
            'to_version': new['version'],
            'timestamp': new['timestamp'],
            'added_tracks': len(new_tracks - old_tracks),
            'removed_tracks': len(old_tracks - new_tracks),
            'tempo_changed': old['tempo'] != new['tempo'],
            'track_count_delta': new['track_count'] - old['track_count'],
        }
        changes.append(change_summary)

    html = f"""<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ableton Version History - {Path(project_path).name}</title>
    <style>
        * {{
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }}

        body {{
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #0a0a0a;
            color: #e0e0e0;
            padding: 20px;
        }}

        .container {{
            max-width: 1200px;
            margin: 0 auto;
        }}

        header {{
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }}

        h1 {{
            font-size: 2.5em;
            margin-bottom: 10px;
            color: white;
        }}

        .subtitle {{
            opacity: 0.9;
            font-size: 1.1em;
        }}

        .stats {{
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }}

        .stat-card {{
            background: #1a1a1a;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #333;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
        }}

        .stat-label {{
            color: #888;
            font-size: 0.9em;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }}

        .stat-value {{
            font-size: 2em;
            font-weight: bold;
            color: #667eea;
        }}

        .timeline {{
            position: relative;
            padding-left: 40px;
        }}

        .timeline::before {{
            content: '';
            position: absolute;
            left: 20px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
        }}

        .version-item {{
            position: relative;
            margin-bottom: 30px;
            background: #1a1a1a;
            padding: 25px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
            transition: transform 0.2s, box-shadow 0.2s;
        }}

        .version-item:hover {{
            transform: translateX(5px);
            box-shadow: 0 6px 24px rgba(102, 126, 234, 0.3);
        }}

        .version-item::before {{
            content: '';
            position: absolute;
            left: -44px;
            top: 30px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #667eea;
            border: 3px solid #0a0a0a;
            box-shadow: 0 0 0 3px #667eea;
        }}

        .version-header {{
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }}

        .version-number {{
            font-size: 1.5em;
            font-weight: bold;
            color: #667eea;
        }}

        .version-date {{
            color: #888;
            font-size: 0.9em;
        }}

        .version-details {{
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }}

        .detail {{
            background: #0f0f0f;
            padding: 12px;
            border-radius: 6px;
            border: 1px solid #222;
        }}

        .detail-label {{
            font-size: 0.85em;
            color: #888;
            margin-bottom: 4px;
        }}

        .detail-value {{
            font-size: 1.1em;
            color: #e0e0e0;
            font-weight: 500;
        }}

        .changes {{
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #333;
        }}

        .change-badge {{
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            margin-right: 8px;
            margin-bottom: 8px;
        }}

        .change-add {{
            background: rgba(76, 175, 80, 0.2);
            color: #4CAF50;
            border: 1px solid #4CAF50;
        }}

        .change-remove {{
            background: rgba(244, 67, 54, 0.2);
            color: #f44336;
            border: 1px solid #f44336;
        }}

        .change-modify {{
            background: rgba(255, 152, 0, 0.2);
            color: #FF9800;
            border: 1px solid #FF9800;
        }}

        .no-changes {{
            color: #666;
            font-style: italic;
        }}
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>🎵 {Path(project_path).name}</h1>
            <p class="subtitle">Version History & Change Tracking</p>
        </header>

        <div class="stats">
            <div class="stat-card">
                <div class="stat-label">Total Versions</div>
                <div class="stat-value">{len(versions)}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Current Tempo</div>
                <div class="stat-value">{version_analyses[-1]['tempo'] if version_analyses else 'N/A'}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Track Count</div>
                <div class="stat-value">{version_analyses[-1]['track_count'] if version_analyses else 'N/A'}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Latest Version</div>
                <div class="stat-value">{versions[-1].version if versions else 'N/A'}</div>
            </div>
        </div>

        <div class="timeline">
"""

    # Add version items to timeline
    for i, v in enumerate(version_analyses):
        # Find changes for this version
        version_changes = None
        if i > 0:
            version_changes = changes[i - 1]

        html += f"""
            <div class="version-item">
                <div class="version-header">
                    <span class="version-number">v{v['version']}</span>
                    <span class="version-date">{v['timestamp']}</span>
                </div>

                <div class="version-details">
                    <div class="detail">
                        <div class="detail-label">Tempo</div>
                        <div class="detail-value">{v['tempo']} BPM</div>
                    </div>
                    <div class="detail">
                        <div class="detail-label">Tracks</div>
                        <div class="detail-value">{v['track_count']}</div>
                    </div>
                    <div class="detail">
                        <div class="detail-label">Scenes</div>
                        <div class="detail-value">{v['scene_count']}</div>
                    </div>
"""

        # Add metadata details
        if v['metadata']:
            for key, val in v['metadata'].items():
                if key not in ['filepath', 'name', 'tempo', 'lastModifiedDate', 'lastModifiedTime']:
                    html += f"""
                    <div class="detail">
                        <div class="detail-label">{key.title()}</div>
                        <div class="detail-value">{val}</div>
                    </div>
"""

        html += """
                </div>
"""

        # Add changes section
        if version_changes:
            html += """
                <div class="changes">
"""
            has_changes = False

            if version_changes['added_tracks'] > 0:
                html += f"""<span class="change-badge change-add">+{version_changes['added_tracks']} tracks</span>"""
                has_changes = True

            if version_changes['removed_tracks'] > 0:
                html += f"""<span class="change-badge change-remove">-{version_changes['removed_tracks']} tracks</span>"""
                has_changes = True

            if version_changes['tempo_changed']:
                html += f"""<span class="change-badge change-modify">Tempo changed</span>"""
                has_changes = True

            if not has_changes:
                html += """<span class="no-changes">No major changes detected</span>"""

            html += """
                </div>
"""

        html += """
            </div>
"""

    html += """
        </div>
    </div>
</body>
</html>
"""

    # Write to file
    output_path = Path(output_file)
    with open(output_path, 'w') as f:
        f.write(html)

    print(f"Timeline visualization created: {output_path.absolute()}")
    return output_path


def main():
    """CLI entry point."""
    import argparse

    parser = argparse.ArgumentParser(description='Visualize Ableton project version history')
    parser.add_argument('project_path', help='Path to Ableton project folder')
    parser.add_argument('-o', '--output', default='timeline.html', help='Output HTML file')

    args = parser.parse_args()

    generate_html_timeline(args.project_path, args.output)


if __name__ == '__main__':
    main()
