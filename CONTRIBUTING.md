# Contributing to The Ableton Cookbook

Thanks for your interest in contributing! This project aims to make AI-powered music production accessible and trustworthy for all producers.

## Code of Conduct

**Be respectful, inclusive, and constructive.** We're building this for the music production community - let's keep it welcoming.

## How to Contribute

### 🐛 Report Bugs

Use our [bug report template](.github/ISSUE_TEMPLATE/bug_report.yml) and include:
- Clear description of the issue
- Steps to reproduce
- Expected vs actual behavior
- Your environment (Ableton version, OS, Node version)

### ✨ Request Features

Use our [feature request template](.github/ISSUE_TEMPLATE/feature_request.yml) and explain:
- What problem you're trying to solve
- Your proposed solution
- Why it would help producers

### 🔧 Submit Code

1. **Fork the repo** and create a branch from `main`
2. **Make your changes** in the appropriate package:
   - `packages/mcp-server/` - TypeScript MCP tools
   - `packages/python-scripts/` - Version control logic
   - `packages/php-analyzers/` - Rack analysis parsers
3. **Follow code style**:
   - Run `npm run lint` and `npm run format`
   - Write clear commit messages
   - Add comments for complex logic
4. **Test your changes**:
   - Build the MCP server: `cd packages/mcp-server && npm run build`
   - Test with Claude Desktop
   - Verify Python/PHP scripts still work
5. **Submit a Pull Request**:
   - Describe what you changed and why
   - Link any related issues
   - Be ready to discuss and iterate

### 📚 Improve Documentation

Documentation lives in multiple places:
- Main README - project overview
- `.claude/` - AI agent context and planning docs
- `docs/` - GitHub Pages website
- Package READMEs - component-specific docs

Feel free to:
- Fix typos or unclear explanations
- Add examples or tutorials
- Improve setup instructions
- Translate content

## Development Setup

### Prerequisites
- Node.js 18+
- Python 3.7+
- PHP 7.4+
- Ableton Live (for testing Operator module)

### Initial Setup
```bash
# Clone your fork
git clone https://github.com/YOUR_USERNAME/ableton-cookbook-mcp.git
cd ableton-cookbook-mcp

# Install MCP server dependencies
cd packages/mcp-server
npm install

# Build TypeScript
npm run build

# Configure Claude Desktop (see README)
```

### Development Workflow
```bash
# Watch TypeScript changes
cd packages/mcp-server
npm run watch

# Lint and format
npm run lint
npm run format

# Test Python scripts
cd ../python-scripts
python ableton_version_manager.py --help

# Test PHP analyzers
cd ../php-analyzers/abletonRackAnalyzer
php abletonRackAnalyzer-V7.php path/to/rack.adg
```

## Project Structure

```
ableton-cookbook-mcp/
├── packages/
│   ├── mcp-server/         # TypeScript MCP server (main)
│   ├── python-scripts/     # Version control system
│   └── php-analyzers/      # Rack/preset parsers
├── .claude/                # AI context & planning docs
├── docs/                   # GitHub Pages website
├── .github/
│   ├── workflows/          # CI/CD automation
│   └── ISSUE_TEMPLATE/     # Bug/feature templates
└── README.md               # You are here
```

## Coding Standards

### TypeScript (MCP Server)
- Use ES Modules (`import`/`export`)
- Type everything with TypeScript
- Follow Prettier formatting (2 spaces)
- Use ESLint recommended rules
- Export classes for each module (Archivist, Operator, etc.)
- Handle errors gracefully, return user-friendly messages

### Python (Version Control)
- Python 3.7+ compatibility
- 4-space indentation (PEP 8)
- Type hints where helpful
- Docstrings for public functions
- Use standard library when possible

### PHP (Analyzers)
- PHP 7.4+ compatibility
- 4-space indentation
- Stream-based parsing for large files
- Return JSON for structured data
- Handle gzip-compressed .als/.adg files

## Testing

We don't have automated tests yet (contributions welcome!), but please manually test:

1. **MCP Server**: Build and load in Claude Desktop, verify tools work
2. **Python Scripts**: Run each script with sample .als files
3. **PHP Analyzers**: Parse various rack types, check JSON output

## Pull Request Guidelines

**Before submitting:**
- ✓ Code builds without errors
- ✓ Lint passes (`npm run lint`)
- ✓ Manually tested with real Ableton files
- ✓ Commits are clear and atomic
- ✓ Branch is up to date with `main`

**PR Description should include:**
- What changed and why
- How to test it
- Screenshots/examples if relevant
- Breaking changes (if any)

## Questions?

- 💬 [Start a discussion](https://github.com/teamallnighter/ableton-cookbook-mcp/discussions)
- 📖 Read the [PROJECT_CONTEXT.md](.claude/PROJECT_CONTEXT.md)
- 🏗️ Check [ARCHITECTURE.md](.claude/ARCHITECTURE.md)

## Recognition

Contributors will be:
- Added to the README contributors list
- Credited in release notes
- Part of music production history 🎵

Thank you for helping make AI-powered music production tools that producers actually trust!
