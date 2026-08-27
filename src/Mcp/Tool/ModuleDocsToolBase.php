<?php

declare(strict_types=1);

namespace DropContext\Mcp\Tool;

use DropContext\Catalog\ModuleDocsCatalog;
use Mcp\Exception\ToolCallException;

/**
 * Shared plumbing for the read-only module documentation MCP tools. Ported
 * from `dc_entities`'s `Plugin\Tool\ModuleDocsToolBase` — same messages, same
 * semantics — operating on plain gateway rows instead of Drupal entities.
 *
 * Resolves a module machine name and an optional release tag, raising an
 * actionable ToolCallException (visible to the calling model, per the MCP
 * spec) instead of a silent fallback.
 */
abstract class ModuleDocsToolBase implements ToolInterface {

  public function __construct(
    protected readonly ModuleDocsCatalog $catalog,
  ) {}

  /**
   * @return array<string, mixed>
   *
   * @throws \Mcp\Exception\ToolCallException when no published module_details matches $machineName
   */
  protected function requireModule(string $machineName): array {
    $module = $this->catalog->findModule($machineName);
    if ($module !== null) {
      return $module;
    }

    $message = sprintf(
      'Unknown module "%s". Call list_modules (e.g. with query "%s") to discover documented modules.',
      $machineName,
      $machineName,
    );

    $candidates = $this->catalog->listModules($machineName, false, 5, 0)['modules'];
    $names = array_map(static fn (array $candidate): string => (string) ($candidate['machineName'] ?? ''), $candidates);
    if ($names !== []) {
      $message .= ' Did you mean: ' . implode(', ', $names) . '?';
    }

    throw new ToolCallException($message);
  }

  /**
   * Resolves a release tag against a module, or throws an actionable error.
   *
   * Semantics: an omitted/empty $tag resolves to the module's default
   * release; a given $tag must match a documented release exactly — an
   * unrecognized tag is always an error here, never a silent fallback.
   *
   * @param array<string, mixed> $module
   *
   * @return array<string, mixed>
   *
   * @throws \Mcp\Exception\ToolCallException when the module has no documented release, or $tag matches none
   */
  protected function requireRelease(array $module, string $machineName, ?string $tag): array {
    $releases = $this->catalog->releasesForModule($module);
    if ($releases === []) {
      throw new ToolCallException(sprintf('Module "%s" has no documented release.', $machineName));
    }

    if ($tag === null || $tag === '') {
      $default = $this->catalog->defaultRelease($module);
      if ($default !== null) {
        return $default;
      }
      throw new ToolCallException(sprintf('Module "%s" has no documented release.', $machineName));
    }

    $release = $this->catalog->findRelease($module, $tag);
    if ($release !== null) {
      return $release;
    }

    $default = $this->catalog->defaultRelease($module);
    $tags = array_map(static fn (array $r): string => (string) ($r['releaseTag'] ?? ''), $releases);
    throw new ToolCallException(sprintf(
      'Release "%s" is not documented for "%s". Documented releases: %s (default: %s). Omit "release" to use the default.',
      $tag,
      $machineName,
      implode(', ', $tags),
      $default !== null ? (string) ($default['releaseTag'] ?? '') : 'n/a',
    ));
  }

}
