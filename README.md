# drop-context

A CLI that installs **AI agent skills** from a [Drupal Context](https://drupalcontext.io) site into your project. It browses the site's [entity_gateway](https://www.drupal.org/project/entity_gateway) JSON API, downloads a skill's `SKILL.md` + `references/*.md`, caches them in `~/.drupal-context/skills/`, and symlinks them into whatever agent directories your project uses (`.claude`, `.agents`, `.cursor`, `.codex`).

```
$ context skill:add boost-feeds

 Installing skill 'boost-feeds'

 ⠧ Fetching skill 'boost-feeds'…

 Skill 'boost-feeds' installed for: .claude, .agents
```

## Requirements

- PHP ≥ 8.3 with `ext-zip`
- Composer

## Installation

```bash
git clone <this repo> drop-context
cd drop-context
composer install
```

The binary is `bin/context`. For convenience, alias it:

```bash
alias context="/path/to/drop-context/bin/context"
```

On the **first run** inside a project, an interactive wizard asks which agent directories you want managed and creates `~/.drupal-context/app.json`. Commands that install/remove skills must run **inside a project containing a `composer.json`** — list/search commands work from anywhere.

## Quick start

```bash
context skill:list                     # what's available?
context skill:show boost-feeds        # what does this one do?
context skill:add boost-feeds         # install it into this project
context skill:remove boost-feeds      # remove it again
```

## Commands

| Command | Alias | Purpose |
|---|---|---|
| `skill:list` | `s:list` | List/search skills |
| `skill:show <skill>` | `s:show` | Show one skill's details or raw content |
| `skill:add <skill>` | `s:add` | Download + install a skill into the project |
| `skill:remove <skill>` | `s:rm` | Remove an installed skill from the project |
| `skill:tags` | `s:tags` | List the tags skills can be filtered by |
| `module:list [search]` | `m:list`, `module:search` | List Drupal modules that have context/skills |
| `code-agents:*` | `ca:add`, `ca:ls`, `ca:rm`, `ca:set` | Manage which agent dirs skills are installed into |

Every command also accepts `--json` (machine-readable output, see below) and `-s/--source` (default: `drupal-context`).

### `skill:list` — list and search skills

```bash
context skill:list                          # everything
context skill:list --module=feeds           # skills for a Drupal module (machine name)
context skill:list --tag="AI Integration"   # by tag (see skill:tags)
context skill:list --type=contrib_module    # by type
context skill:list --search=views           # free-text search on the title
```

Filters combine (AND). `--type` accepts: `core_module`, `contrib_module`, `core_component`, `agent_skill`, `user_skill`, `other`.

Output is a table with Title, Type, Module, Release, Tags and Description.

### `skill:show` — inspect a skill

```bash
context skill:show boost-feeds              # summary table + reference files
context skill:show boost-feeds --content    # print the raw SKILL.md (pipeable)
context skill:show <uuid>                   # UUIDs work anywhere a skill name does
```

If the name doesn't match exactly, the CLI suggests close matches and (interactively) lets you pick one.

### `skill:add` — install a skill

```bash
context skill:add boost-feeds
context skill:add boost-feeds --force       # reinstall / refresh from the server
```

What it does:

1. Resolves the skill on the gateway (exact title or UUID; ambiguous titles prompt you to pick).
2. Writes `SKILL.md` + `references/` to `~/.drupal-context/skills/<name>/` (the cache is always refreshed from the server).
3. Symlinks that directory into each managed agent dir: `<project>/.claude/skills/<name>`, `<project>/.agents/skills/<name>`, …
4. Records the install in `~/.drupal-context/app.json`.

If the skill already exists you're asked before overriding; non-interactive runs require `--force`.

### `skill:remove` — uninstall a skill

```bash
context skill:remove boost-feeds
context skill:remove boost-feeds --purge    # also delete the cached copy
```

Skills installed by this CLI are removed directly (symlinks + registry entry — the cache stays unless `--purge`).

If the skill was **not** installed by this CLI (a real directory created manually or by another tool), you get a warning — *"We didn't detect '<skill>' as installed by this CLI…"* — with the exact paths that would be permanently deleted, and a confirmation that defaults to **no**. Non-interactive runs need `--force` to proceed.

### `skill:tags` / `module:list` — discovery

```bash
context skill:tags                          # tag names usable with skill:list --tag
context module:list                         # all modules with published context
context module:list feed                    # machine-name search
context module:list "exposed" --title       # search the human title instead
```

### `code-agents:*` — which dirs get skills

```bash
context code-agents:list                    # currently configured agent dirs
context code-agents:add .cursor             # add one
context code-agents:remove .cursor          # remove one
context code-agents:set-preferable .claude,.agents   # replace the whole list
```

Known agent dirs: `.claude`, `.agents`, `.cursor`, `.codex`. On `skill:add`, dirs already present in the project are used too, and preferred dirs that are missing get created.

## Scripting with `--json`

With `--json`, stdout carries **only** JSON (no spinners, tables, or framing) and human messages go to stderr — safe to pipe:

```bash
context skill:list --type=contrib_module --json | jq '.[].title'
context skill:show boost-feeds --json | jq '.references'
context skill:add boost-feeds --json          # {"installed": "...", "agents": [".claude", ".agents"]}
context skill:remove boost-feeds --json       # {"removed": "...", "agents": [...], "purged": false}
```

Errors keep the JSON contract too — e.g. an unresolved skill name prints `{"error": "...", "suggestions": [{"uuid", "title"}, …]}` on stdout and exits non-zero.

## Configuration

The gateway endpoint is resolved in this order: **environment variable → `app.json` → built-in default**.

| Setting | Env var | `~/.drupal-context/app.json` | Default |
|---|---|---|---|
| Base URL | `DRUPAL_CONTEXT_URL` | `gateway.url` | `https://drupal-context.ddev.site` |
| TLS verification | `DRUPAL_CONTEXT_TLS_VERIFY` (`0`/`false` to disable) | `gateway.tlsVerify` | on — except `*.ddev.site` hosts (self-signed local certs) |

Example `app.json` fragment:

```json
{
  "gateway": {
    "url": "https://drupalcontext.io",
    "tlsVerify": true
  }
}
```

Point at any environment ad hoc:

```bash
DRUPAL_CONTEXT_URL=https://drupalcontext.io context skill:list
```

## Files and state

```
~/.drupal-context/
├── app.json          # setup, managed agent dirs, per-project installed skills
└── skills/<name>/    # cached skill content (SKILL.md + references/)
    
<project>/.claude/skills/<name>   → symlink into the cache
<project>/.agents/skills/<name>   → symlink into the cache
```

Removing a skill deletes the symlinks and the registry entry; the cache is only deleted with `--purge`.

## Sources

`-s/--source` selects where skills come from. `drupal-context` (default) is the entity_gateway API described above; `skill.sh` is reserved but **not implemented yet** — selecting it fails with a clear error.
