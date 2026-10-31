<?php

declare(strict_types=1);

namespace DropContext\DevKit;

/** What one dev:install run wrote, skipped and registered. */
final class DevKitInstallResult {

  /** @var string[] project-relative paths linked or written */
  public array $paths = [];

  /** @var string[] project-relative paths left alone: something else is there */
  public array $skipped = [];

  /** @var array<int, array{file: string, kind: string, status: string, container?: string}> */
  public array $mcp = [];

  /** @var string[] */
  public array $warnings = [];

  /**
   * Where each code agent's pieces went, for display.
   *
   * @var array<string, array{skills: string, agent: string|null, mcp: string|null}>
   */
  public array $summary = [];

  /**
   * The MCP entries this install owns: the ones it added or found equal.
   *
   * @return array<int, array{file: string, kind: string, container?: string}>
   */
  public function ownedMcp(): array {
    $owned = [];
    foreach ($this->mcp as $entry) {
      if ($entry['status'] !== McpConfigWriter::KEPT) {
        unset($entry['status']);
        $owned[] = $entry;
      }
    }
    return $owned;
  }

}
