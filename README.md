# Drop Context

Drop Context is a CLI for finding and installing AI agent skills from [dropcontext.dev](https://dropcontext.dev) into your projects.

It supports Claude Code, Codex, Cursor, and other agents that use project-level skill directories.

```text
$ context skill:add boost-feeds

 Installing skill 'boost-feeds'

 Skill 'boost-feeds' installed for: .claude, .agents
```

## Requirements

- PHP 8.3 or newer with `ext-zip`
- Composer

## Installation

Install Drop Context globally:

```bash
composer global require "joaopauloctga/drop-context:^0"
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
context --version
```

## Getting started

Run commands from a project containing a `composer.json`. On the first command that changes the project, Drop Context asks which coding agents you want to manage.

```bash
context skill:list
context skill:show boost-feeds
context skill:add boost-feeds
context skill:remove boost-feeds
```

Discovery commands such as `skill:list`, `skill:show`, `skill:tags`, and `module:list` can run from any directory.

## Commands

| Command | Alias | Description |
|---|---|---|
| `skill:list` | `s:list` | List and search available skills |
| `skill:show <skill>` | `s:show` | Show details or content for a skill |
| `skill:add <skill>` | `s:add` | Download and install a skill |
| `skill:remove <skill>` | `s:rm` | Remove an installed skill |
| `skill:tags` | `s:tags` | List tags available for filtering |
| `module:list [search]` | `m:list`, `module:search` | Find Drupal modules with available skills |
| `code-agents:list` | `ca:ls` | List configured coding agents |
| `code-agents:add <agent>` | `ca:add` | Add a coding agent |
| `code-agents:remove <agent>` | `ca:rm` | Remove a coding agent |
| `code-agents:set-preferable <agents>` | `ca:set` | Replace the preferred coding agents |

Run `context <command> --help` to see every argument and option.

### Find skills

```bash
context skill:list
context skill:list --search=views
context skill:list --module=feeds
context skill:list --tag="AI Integration"
context skill:list --type=contrib_module
```

Filters can be combined. Supported types are `core_module`, `contrib_module`, `core_component`, `agent_skill`, `user_skill`, and `other`.

### Inspect a skill

```bash
context skill:show boost-feeds
context skill:show boost-feeds --content
```

Skills can be selected by name or UUID. When a name is ambiguous, Drop Context offers matching suggestions.

### Install a skill

```bash
context skill:add boost-feeds
context skill:add boost-feeds --force
```

`--force` reinstalls an existing skill without confirmation. The skill is installed for the coding agents configured in the current project.

### Remove a skill

```bash
context skill:remove boost-feeds
context skill:remove boost-feeds --purge
```

`--purge` also removes the downloaded copy. Drop Context asks for confirmation before removing a skill it did not install; use `--force` only when you intend to remove it.

### Browse tags and modules

```bash
context skill:tags
context module:list
context module:list feed
context module:list "exposed" --title
```

### Configure coding agents

```bash
context code-agents:list
context code-agents:add .cursor
context code-agents:remove .cursor
context code-agents:set-preferable .claude,.agents
```

Supported directories are `.claude`, `.agents`, `.cursor`, and `.codex`.

## JSON output

Use `--json` for scripts and integrations:

```bash
context skill:list --type=contrib_module --json | jq '.skills[].title'
context skill:show boost-feeds --json | jq '.references'
context skill:add boost-feeds --json
context skill:remove boost-feeds --json
```

Machine-readable JSON is written to stdout and commands return a non-zero exit code when they fail.

## Updates

Update Drop Context with Composer:

```bash
composer global update joaopauloctga/drop-context
```

The CLI periodically notifies you when a newer stable version is available. Disable the check for a command with:

```bash
context skill:list --no-update-check
```

## License

Drop Context is open-source software licensed under the [MIT License](LICENSE).
