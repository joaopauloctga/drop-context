<?php

declare(strict_types=1);

namespace DropContext\Mcp\Tool;

use DropContext\Catalog\CoreLibraryCatalog;
use Mcp\Exception\ToolCallException;

/**
 * Shared plumbing for the read-only core-library MCP tools. Ported from
 * `dc_entities`'s `Plugin\Tool\CoreLibraryToolBase` — same messages, same
 * semantics.
 */
abstract class CoreLibraryToolBase implements ToolInterface {

  public function __construct(
    protected readonly CoreLibraryCatalog $catalog,
  ) {}

  /**
   * @return array<string, mixed>
   *
   * @throws \Mcp\Exception\ToolCallException on miss or ambiguous short name
   */
  protected function requireLibrary(string $ref): array {
    $result = $this->catalog->findLibrary($ref);
    if ($result['match'] !== null) {
      return $result['match'];
    }

    if ($result['candidates'] !== []) {
      $names = array_map(
        static fn (array $lib): string => (string) ($lib['libraryName'] ?? '') . ' (' . (string) ($lib['libraryId'] ?? '') . ')',
        $result['candidates'],
      );
      throw new ToolCallException(sprintf(
        'Ambiguous library "%s". Matches: %s. Pass a metadata.id (e.g. drupal.core.ajax) or a qualified name (e.g. Core/Ajax).',
        $ref,
        implode(', ', $names),
      ));
    }

    $message = sprintf(
      'Unknown library "%s". Call list_core_libraries (e.g. with query "%s") to discover documented libraries.',
      $ref,
      $ref,
    );
    $suggestions = $this->catalog->listLibraries($ref, null, 5, 0)['libraries'];
    $names = array_map(static fn (array $lib): string => (string) ($lib['libraryId'] ?? ''), $suggestions);
    if ($names !== []) {
      $message .= ' Did you mean: ' . implode(', ', $names) . '?';
    }

    throw new ToolCallException($message);
  }

}
