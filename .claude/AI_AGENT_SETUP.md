# AI Agent Capabilities & Tools

## Current Tool Stack

### ✅ Active Tools (Available Now)

**File Operations:**
- `read_file` - Read any file in workspace
- `create_file` - Create new files
- `replace_string_in_file` - Edit existing files (precise replacements)
- `multi_replace_string_in_file` - Batch edits across multiple files
- `file_search` - Find files by glob pattern
- `grep_search` - Search file contents (regex support)
- `list_dir` - List directory contents

**Code Intelligence:**
- `semantic_search` - Semantic search across codebase
- `list_code_usages` - Find all usages of functions/classes
- `get_errors` - Get TypeScript/linter errors

**Terminal:**
- `run_in_terminal` - Execute shell commands
- `get_terminal_output` - Check command output
- `kill_terminal` - Stop background processes

**Python-Specific:**
- `configure_python_environment` - Setup Python env
- `install_python_packages` - Install pip packages

**Notebooks:**
- `create_new_jupyter_notebook` - Create .ipynb files
- `edit_notebook_file` - Edit notebook cells
- `run_notebook_cell` - Execute notebook code

**VS Code:**
- `get_vscode_api` - VS Code extension API docs

**Web:**
- `fetch_webpage` - Scrape web content

### 🔒 Available But Not Activated

**GitHub Integration** (activate_repository_management_tools):
- Create/update issues
- Create pull requests  
- Create branches
- List commits, PRs, issues
- Search code/repos/users
- Workflow management
- **Should activate if**: Planning to use GitHub for project

**Memory/Knowledge Graph** (activate_entity_management_tools):
- Store persistent facts across sessions
- Create relationships between entities
- Search knowledge base
- **Should activate if**: Want to track project state long-term

**Python Advanced** (activate_python_code_validation_and_execution):
- Validate Python syntax without execution
- Run Python snippets safely
- Analyze imports and dependencies
- **Should activate if**: Doing heavy Python development

### ❌ Not Available
- Direct database access (would need via terminal)
- Native Electron/desktop app APIs (need via terminal build)
- Browser automation (would need Playwright via terminal)

## Recommended Setup

### 1. Development Tools (for consistency)

**ESLint + Prettier** (TypeScript/JavaScript):
```bash
npm install --save-dev eslint prettier eslint-config-prettier
npm install --save-dev @typescript-eslint/parser @typescript-eslint/eslint-plugin
```

**EditorConfig** (cross-editor consistency):
```ini
# .editorconfig
root = true

[*]
charset = utf-8
end_of_line = lf
insert_final_newline = true
indent_style = space
indent_size = 2

[*.{ts,js}]
indent_size = 2

[*.{php,py}]
indent_size = 4

[*.md]
trim_trailing_whitespace = false
```

**PHP Code Sniffer** (PHP style):
```bash
# Install via Composer (if you use it)
composer require --dev squizlabs/php_codesniffer
```

**Python Black** (Python formatter):
```bash
pip3 install black pylint
```

### 2. Testing Frameworks

**Jest** (TypeScript/JavaScript):
```bash
npm install --save-dev jest ts-jest @types/jest
```

**PHPUnit** (PHP):
```bash
composer require --dev phpunit/phpunit
```

**pytest** (Python):
```bash
pip3 install pytest pytest-cov
```

### 3. Git Setup

**Husky** (Git hooks):
```bash
npm install --save-dev husky lint-staged
npx husky install
```

**Conventional Commits**:
```bash
npm install --save-dev @commitlint/cli @commitlint/config-conventional
```

### 4. Project Documentation

**TypeDoc** (TypeScript API docs):
```bash
npm install --save-dev typedoc
```

### 5. GitHub Integration (Optional)

If using GitHub for:
- Issue tracking
- Pull requests
- Project management
- CI/CD workflows

**Activate GitHub MCP tools**: I can help create issues, manage PRs, etc.

### 6. Memory/Knowledge Graph (Optional)

If you want me to:
- Remember project decisions across sessions
- Track "why we did X" context
- Build knowledge base of patterns/solutions

**Activate Memory MCP tools**: I can persist facts long-term

## What Should We Install NOW?

### Immediate Setup (Recommended):

1. **Code Quality Tools**:
   ```bash
   cd /Volumes/DEV/M4L-MCP
   npm install --save-dev eslint prettier eslint-config-prettier
   npm install --save-dev @typescript-eslint/parser @typescript-eslint/eslint-plugin
   ```

2. **EditorConfig** (create `.editorconfig`)

3. **Testing** (for when we start writing tests):
   ```bash
   npm install --save-dev jest ts-jest @types/jest
   ```

4. **Git Hooks** (if we're committing):
   ```bash
   npm install --save-dev husky
   npx husky install
   ```

### Tool Activation Decisions:

**Activate GitHub Tools?**
- ✅ YES if: You're pushing to GitHub and want me to manage issues/PRs
- ❌ NO if: Just working locally for now

**Activate Memory Tools?**
- ✅ YES if: Want persistent knowledge graph across sessions
- ❌ NO if: Documentation files are enough

**Activate Python Advanced Tools?**
- ✅ YES if: Heavily modifying Python scripts
- ❌ NO if: Python scripts are mostly stable

## Questions for You:

1. **Are you using GitHub for this project?** 
   - If yes, I'll activate GitHub tools
   
2. **Do you want me to remember project context across future sessions?**
   - If yes, I'll activate Memory/Knowledge Graph tools
   
3. **Are we going to heavily develop the Python scripts?**
   - If yes, I'll activate Python advanced tools

4. **Should I install code quality tools now?**
   - ESLint, Prettier, EditorConfig
   - Testing frameworks
   - Git hooks

Let me know your preferences and I'll set everything up!
