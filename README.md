# Drop Context

Drop Context is a CLI for finding and installing AI agent skills and AGENTS.md files from [dropcontext.dev](https://dropcontext.dev) into your projects.

It supports Claude Code, Codex, Cursor, and other agents that use project-level skill directories and root `AGENTS.md` files.

```text
$ drop-context skill:add dc-feeds

 Installing skill 'dc-feeds'

 Skill 'dc-feeds' installed for: .claude, .agents
```

## Requirements

- PHP 8.3 or newer with `ext-zip` and `ext-fileinfo`
- Composer

## Installation

Install Drop Context globally:

```bash
composer global require "joaopauloctga/drop-context:^0"
```

Until the first tagged release is available, install the development branch instead:

```bash
composer global require "joaopauloctga/drop-context:dev-main"
```

Make sure Composer's global binary directory is in your `PATH`:

```bash
composer global config bin-dir --absolute
```

For example, on macOS or Linux:

```bash
export PATH="$(composer global config bin-dir --absolute):$PATH"
```

Confirm the installation:

```bash
drop-context --version
```

## Getting started

Run commands from a project containing a `composer.json`. On the first command that changes the project, Drop Context asks which coding agents you want to manage.

```bash
drop-context skill:list
drop-context skill:show dc-feeds
drop-context skill:add dc-feeds
drop-context skill:status
drop-context skill:remove dc-feeds
drop-context agent:list
drop-context agent:add drop-view-developer
drop-context agent:add 'joaopauloc:views-developer'
```

Discovery commands such as `skill:list`, `skill:show`, `skill:tags`, `agent:list`, `agent:show`, and `module:list` can run from any directory.

`agent:*` installs catalog AGENTS.md files. `code-agents:*` only manages which coding-agent directories (`.claude`, `.cursor`, …) receive skill symlinks — the two are unrelated.

## Commands

| Command | Alias | Description |
|---|---|---|
| `skill:list` | `s:list` | List and search available skills, or the ones installed here |
| `skill:show <skill>` | `s:show` | Show details or content for a skill |
| `skill:add <skill>` | `s:add` | Download and install a skill |
| `skill:status` | `s:status` | Check installed skills and choose available updates |
| `skill:remove <skill>` | `s:rm` | Remove an installed skill |
| `skill:remove-all` | `s:rm-all` | Remove every skill this CLI installed in the project |
| `skill:tags` | `s:tags` | List tags available for filtering |
| `agent:list` | `a:list` | List and search available AGENTS.md agents, or the ones installed here |
| `agent:show <agent>` | `a:show` | Show details or content for an agent |
| `agent:add <agent>` | `a:add` | Download and install an agent as project-root `AGENTS.md` |
| `agent:status` | `a:status` | Check installed agents and choose available updates |
| `agent:remove <agent>` | `a:rm` | Remove an installed agent |
| `dev:install` | | Install the Drop Context Developer agent, its skills and the MCP server into the project's coding agents |
| `dev:remove` | | Remove what `dev:install` added |
| `module:list [search]` | `m:list`, `module:search` | Find Drupal modules with available skills |
| `code-agents:list` | `ca:ls` | List configured coding-agent directories (`.claude`, `.cursor`, …) |
| `code-agents:add <agent>` | `ca:add` | Add a coding agent |
| `code-agents:remove <agent>` | `ca:rm` | Remove a coding agent |
| `code-agents:set-preferable <agents>` | `ca:set` | Replace the preferred coding agents |
| `mcp:serve` | | Start the MCP server over stdio (see [MCP server](#mcp-server)) |
| `cache:clear` | | Clear the gateway response cache used by the MCP server |

Run `drop-context <command> --help` to see every argument and option.

### Find skills

```bash
drop-context skill:list
drop-context skill:list --search=views
drop-context skill:list --module=feeds
drop-context skill:list --tag="AI Integration"
drop-context skill:list --type=contrib_module
```

Filters can be combined. Supported types are `core_module`, `contrib_module`, `core_component`, `agent_skill`, `user_skill`, and `other`.

`--from` chooses where the list comes from. The default, `drop-context`, queries the
site; `local` lists the skills this CLI installed in the current project, without any
network request — the filters above are gateway-only and are ignored there.

```bash
drop-context skill:list --from=local
```

### Inspect a skill

```bash
drop-context skill:show dc-feeds
drop-context skill:show dc-feeds --content
```

Skills can be selected by name or UUID. When a name is ambiguous, Drop Context offers matching suggestions.

### Install a skill

```bash
drop-context skill:add dc-feeds
drop-context skill:add dc-feeds --force
```

`--force` reinstalls an existing skill without confirmation. The skill is installed for the coding agents configured in the current project.

### Check for skill updates

```bash
drop-context skill:status
```

The command compares the versions installed in the current project with the latest
versions from Drop Context. When updates are available, every update is selected by
default and you can uncheck any skill you do not want to update. In `--json` or other
non-interactive runs, the command only reports status and does not modify the project.

### Remove a skill

```bash
drop-context skill:remove dc-feeds
drop-context skill:remove dc-feeds --purge
```

`--purge` also removes the downloaded copy. Drop Context asks for confirmation before removing a skill it did not install; use `--force` only when you intend to remove it.

### Remove every installed skill

```bash
drop-context skill:remove-all
drop-context skill:remove-all --force --purge
```

Removes only the skills this CLI installed in the current project — anything you added
by hand or with another tool is left alone. It lists what it is about to remove and asks
for confirmation; `--force` skips the prompt and is required in a non-interactive run.

### Browse tags and modules

```bash
drop-context skill:tags
drop-context module:list
drop-context module:list feed
drop-context module:list "exposed" --title
```

### Find and install agents (AGENTS.md)

```bash
drop-context agent:list
drop-context agent:list --search=drupal
drop-context agent:list --module=feeds
drop-context agent:show drop-view-developer
drop-context agent:show drop-view-developer --content
drop-context agent:add drop-view-developer
drop-context agent:add 'joaopauloc:views-developer'
drop-context agent:status
drop-context agent:remove drop-view-developer --purge
```

Agents are catalog AGENTS.md files. Install by **machine name**: official Drop Context agents use a bare slug (`drop-view-developer`); user-authored agents use `{username}:{slug}` (`joaopauloc:views-developer`). `agent:add` caches them under `~/.drop-context/agents/` and symlinks `AGENTS.md` at the project root (one active file). This is separate from `code-agents:*`, which only manages coding-agent directories.

### Install the Drop Context Developer

```bash
drop-context dev:install
drop-context dev:install --code-agents=.claude,.cursor
drop-context dev:install --ref=drop-context-dev--v1.0.0
drop-context dev:install --from=../drop-context-dev
drop-context dev:remove
```

`dev:install` installs [drop-context-dev](https://github.com/joaopauloctga/drop-context-dev)
— the `drop-context-developer` agent and its `dc-plan` / `dc-implement` skills — and
registers this package's MCP server, for every coding agent the project uses:

| Code agent | Skills | Agent | MCP server |
|---|---|---|---|
| `.claude` (Claude Code) | `.claude/skills/` | `.claude/agents/drop-context-developer.md` | `.mcp.json` |
| `.cursor` (Cursor) | `.cursor/skills/` | `.cursor/agents/drop-context-developer.md` | `.cursor/mcp.json` |
| `.codex` (Codex) | `.agents/skills/` | `.codex/agents/drop-context-developer.toml` | `.codex/config.toml` |
| `.opencode` (OpenCode) | `.opencode/skills/` | `.opencode/agents/drop-context-developer.md`, plus `/dc-plan` and `/dc-implement` commands | `opencode.json` |
| `.agents` | `.agents/skills/` | — | — |

The code agents are the ones present in the project plus your preferred ones;
`--code-agents` picks them explicitly. The repository is downloaded from GitHub
(`--ref`, default `main`) into `~/.drop-context/dev-kit/<ref>/`; `--from` uses a
local checkout instead. Skills, and the Claude Code agent, are symlinks into that
copy; the other agent formats are generated from it. MCP configs get the absolute
path of `drop-context-mcp`, so desktop apps and background services that lack
Composer's bin dir on their `PATH` still start it. Other keys in those files are
left alone.

Run `dev:install` again to update. It only replaces files it wrote itself; anything
else at an install path is reported and kept unless you pass `--force`. A project
`.mcp.json` server needs approval the first time Claude Code starts, and Codex reads
`.codex/config.toml` only in trusted projects.

### Configure coding agents

```bash
drop-context code-agents:list
drop-context code-agents:add .cursor
drop-context code-agents:remove .cursor
drop-context code-agents:set-preferable .claude,.agents
```

Supported directories are `.claude`, `.agents`, `.cursor`, `.codex`, and `.opencode`.

## MCP server

Composer installs a second binary alongside `drop-context`: `drop-context-mcp`, an [MCP](https://modelcontextprotocol.io) server that talks stdio and reads the same Drop Context catalog — module and Drupal core documentation, plus core framework libraries. Running it with no arguments starts the server directly (it is also available as `drop-context mcp:serve`, e.g. for a client that always calls the main binary):

```bash
drop-context-mcp
```

Add it to Claude Code:

```bash
claude mcp add drop-context --scope project -- drop-context-mcp
```

Or configure it directly in an `mcp.json`:

```json
{
  "mcpServers": {
    "drop-context": {
      "command": "drop-context-mcp"
    }
  }
}
```

### Tools

| Tool | Purpose |
|---|---|
| `list_modules` | Find a module's exact machine name, optionally filtered by a query substring |
| `get_module` | A module's metadata, documented releases, that release's notes, and its doc index |
| `get_doc` | Read one module documentation file's raw Markdown |
| `list_core_libraries` | Find a Drupal core framework library's `metadata.id`, optionally filtered |
| `get_core_library` | A core library's metadata and its doc index |
| `get_core_library_doc` | Read one core-library documentation file's raw Markdown |

The usual flow is discover, then overview, then read: `list_modules` (or `list_core_libraries`) to find what you're looking for, `get_module`/`get_core_library` to see what's documented for it, `get_doc`/`get_core_library_doc` to read one file at a time. An unrecognized module, release, doc id, or library reference is a clear, actionable error — never a silent fallback.

### Environment variables

| Variable | Default | Purpose |
|---|---|---|
| `DROP_CONTEXT_CACHE` | on | Set to `0` to disable the on-disk response cache (an in-process cache still applies for the life of one server session) |
| `DROP_CONTEXT_CACHE_TTL` | `86400` (24h) | Per-entry cache safety-net, in seconds |
| `DROP_CONTEXT_STAMP_TTL` | `300` (5m) | How often the server checks whether the catalog changed |
| `DROP_CONTEXT_MCP_LOG` | `warning` | Log verbosity to stderr: `debug`, `info`, `warning`, or `error` |

### Cache

Every gateway response the server reads is cached to `~/.drop-context/cache/gateway/`. A background check (`DROP_CONTEXT_STAMP_TTL`) detects when the catalog actually changed and clears the cache automatically; `DROP_CONTEXT_CACHE_TTL` is the safety-net expiry underneath that. Clear it by hand with:

```bash
drop-context cache:clear
drop-context cache:clear --url=https://dropcontext.dev
```

## JSON output

Use `--json` for scripts and integrations:

```bash
drop-context skill:list --type=contrib_module --json | jq '.skills[].title'
drop-context skill:show dc-feeds --json | jq '.references'
drop-context skill:add dc-feeds --json
drop-context skill:remove dc-feeds --json
```

Machine-readable JSON is written to stdout and commands return a non-zero exit code when they fail.

## Updates

Update Drop Context with Composer:

```bash
composer global update joaopauloctga/drop-context
```

The CLI periodically notifies you when a newer stable version is available. Disable the check for a command with:

```bash
drop-context skill:list --no-update-check
```

## License

Drop Context is open-source software licensed under the [MIT License](LICENSE).
