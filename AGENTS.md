# Pryvora Development Rules

## Environment Setup (CRITICAL)

### WSL + Mutagen Sync Workflow
- **All development happens in WSL**, not Windows directly
- Project files are synced from Windows to WSL via Mutagen
- WSL project location: `~/Code/pryvora` (Pryvora)
- **ALWAYS** get into WSL first: `wsl` command
- **ALWAYS** navigate to project folder: `cd ~/Code/pryvora`
- **THEN** run Docker commands: `docker compose exec backend ...`

### Docker-First Development (MANDATORY)
- **NEVER** run bare-metal PHP, Node, Composer, or npm commands
- **ALL** commands must run through Docker: `docker compose exec <service> <command>`
- Services: `backend` (Symfony/PHP), `frontend` (React/Vite), `database` (MySQL), `redis`, `gateway` (WebSocket)
- Check container logs before debugging: `docker compose logs <service>`
- Restart services: `docker compose restart <service>`
- Rebuild only when config/code changes: `docker compose up --build`
- Never create new ports—reuse existing ones from `docker-compose.yml`

## Technology Stack

### Backend (Symfony API)
- Use Dependency Injection for all services
- RESTful structure; version API routes as needed
- Keep `.env`, `services.yaml`, and Docker envs in sync
- Run Maker Bundle: `docker compose exec backend php bin/console make:*`
- PHPUnit tests inside container only: `docker compose exec backend php bin/phpunit`

### Frontend (React/Vite)
- Vite dev server only via Docker container
- Shared `.env` must match container environment
- Use absolute imports via `vite.config.ts` setup
- Never mix raw Node/npm—always run inside Docker

### WebSocket Gateway
- Simple Node.js + ws + TypeScript (no NestJS or heavy frameworks)
- Configured via environment variables
- Real-time updates for EquitiesGroups and Current Session metrics

## Code Style (STRICT)

### Naming Conventions
- **snake_case** for all new functions/variables (all languages)
- **Exception:** When editing existing code, match that file's existing style exactly

### Control Structures
- **Always use braces** for if/else/try/catch (even single-line blocks)
- **Allman style:** Opening brace on new line

```php
if ($condition)
{
    do_something();
}
```

### Comments
- Minimal comments: Only for complex logic or when requested
- No obvious or redundant comments

### HTML
- Void elements: No whitespace before `/>` (e.g., `<input type="text"/>`)

## Response Guidelines

### Core Principles
- **No Hallucination:** Say "I don't know" if unsure
- **Be Specific:** Answer clearly and contextually
- **Code First:** Show code before assumptions
- **State Uncertainty:** Explain doubts or limitations
- **No Assumptions:** Always ask or verify with code/logs
- **Analyze Errors:** Provide actual reasons with stacktrace/code line
- **Confirm Setup:** Clarify Docker volumes, ports, or env if in doubt

### File Creation (CRITICAL)
- **NEVER** create files unless absolutely required for the task
- **NEVER** create documentation (.md, README, etc.) unless explicitly requested
- **NEVER** create summary files of changes made
- **ALWAYS** prefer editing existing files over creating new ones

### Completeness (CRITICAL)
After EVERY edit, use codebase-retrieval to find ALL downstream changes:
- Callers/call sites affected by API changes
- Interface implementations matching new signatures
- Subclasses needing override updates
- Existing tests affected by changes
- Type definitions/schemas needing updates
- Import statements
- Configuration files
- **ALWAYS** update existing affected tests
- **NEVER** create new test files unless explicitly requested

## Codebase Workflow

### Pre-Change Checklist
- Retrieve Docker volume paths + project file structure
- Understand Symfony services, configs, routes before edits
- Frontend: Ensure Vite config matches Docker and `.env`
- **Always inspect full file when modifying**
- Avoid blind copy—adapt examples to Docker/Symfony context
- Verify dependencies: Use `composer` and `npm` in Docker context only

### Package Management
- **ALWAYS** use package managers for dependencies
- **NEVER** manually edit `package.json`, `composer.json`, `requirements.txt`, etc.
- PHP: `docker compose exec backend composer require/remove <package>`
- Node: `docker compose exec frontend npm install/uninstall <package>`

## Command Line

### Branching (MANDATORY)
- **NEVER** commit or push to `main` or `develop` directly
- **ALWAYS** create a branch off `develop` for any change:
  `git switch -c feature/<short-name> develop`
- Prefixes: `feature/` for new work, `fix/` for bugfixes, `chore/` for tooling/docs
- Commit and push the branch only: `git push -u origin feature/<short-name>`
- **STOP there.** Jakub merges the branch into `develop` himself, and
  `develop` into `main`, on his own schedule. Never open, merge, or
  fast-forward those branches, and never push to them.

### Git Workflow
- Never add `Co-Authored-By` trailers or any AI/Claude attribution to commits
- Always use `--no-pager` with git commands
- For branch comparisons:
  1. List changed files: `git --no-pager diff --name-only origin/dev...origin/feature`
  2. Show diffs: `git --no-pager diff origin/dev...origin/feature -- file1 file2`
- Never pipe to `less`, `more`, `grep`, or `cat` on first run—show full output first

### Command Execution
- All commands run from project root—no unnecessary `cd`
- Never use pagers on first run

## Testing

### Integration Tests
- API tests should make real API calls to external APIs
- Configure credentials in `.env.test`

## Response Style

- **No flattery** ("great question", "excellent idea")
- Skip summaries unless requested
- Provide direct, concise solutions
- Explain mistakes with exact reasons (stacktrace/code line)

