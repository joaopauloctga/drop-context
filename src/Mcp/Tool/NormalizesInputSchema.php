<?php

declare(strict_types=1);

namespace DropContext\Mcp\Tool;

/**
 * Ported from `mcp_server`'s `McpServerFactory::normalizeObjectSchema()`.
 *
 * The SDK's `Mcp\Schema\Tool` constructor requires `inputSchema['type'] ===
 * 'object'` but does not itself convert an empty `properties` array to a
 * JSON object — only `Tool::fromArray()` does that. Without this, a
 * no-argument tool's schema serializes `properties` as `[]`, which the MCP
 * spec rejects (it must be `{}`).
 */
trait NormalizesInputSchema {

  /**
   * @param array<string, mixed> $schema
   *
   * @return array<string, mixed>
   */
  private static function normalizeObjectSchema(array $schema): array {
    $schema['type'] = 'object';
    $properties = $schema['properties'] ?? null;
    if ($properties === null || $properties === []) {
      $schema['properties'] = new \stdClass();
    }
    return $schema;
  }

}
