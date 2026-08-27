<?php

declare(strict_types=1);

namespace DropContext\Mcp;

/**
 * The server's `initialize` handshake instructions — verbatim from
 * `drupal-site/web/modules/custom/dc_entities/src/Hook/McpServerHooks.php`
 * (`MODULE_INSTRUCTION_PARAGRAPHS` + `CORE_LIBRARY_INSTRUCTION_PARAGRAPHS`,
 * joined the same way: `implode("\n\n", [...])`).
 */
final class McpInstructions {

  public const string TEXT = <<<'TEXT'
This server publishes generated documentation for Drupal modules (contrib and core), organized by release. Discover it with this flow:

1. list_modules — find a module's exact machine name (optionally filtered by a "query" substring).
2. get_module — read a module's metadata, its documented releases, that release's notes, and the index of docs available for it.
3. get_doc — read one documentation file at a time. Docs can be large, so fetch only what you need.

A doc id is its doc-set file name without the ".md" extension. The 11 root doc ids are: summary, entities, plugins, services, configuration, permissions, routes, hooks, events, extension-points, ai-integration. A submodule's doc id is "submodules/<submodule-machine-name>".

"ai-integration" (get_doc's default) is an implementation guide written for AI agents; "summary" is the module's overview; every other doc id is reference material.

When "release" is omitted, the highest documented stable release is used. An unrecognized release tag is an error, not a silent fallback — check "available_releases" in get_module's response or the error message for the documented tags.

This server also publishes documentation for Drupal framework libraries below core/lib/Drupal (Core/* and Component/*). Discover them with this flow:

1. list_core_libraries — find a library's exact metadata.id (optionally filtered by a "query" substring or kind=core|component).
2. get_core_library — read a library's metadata and the index of docs available for it.
3. get_core_library_doc — read one documentation file at a time.

Pass library as metadata.id (drupal.core.ajax), a qualified name (Core/Ajax), or the PHP namespace. An ambiguous short name (e.g. Ajax matching both Core and Component) is an error — never guessed.

A doc id is its file name without the ".md" extension: summary, architecture, api, usage, and optional topics/<slug>. "usage" (get_core_library_doc's default) is the verified patterns guide; "summary" is the overview.
TEXT;

}
