<?php

declare(strict_types=1);

namespace DropContext\Mcp\Tool;

use Mcp\Schema\Tool;
use Mcp\Schema\ToolAnnotations;
use Mcp\Server\ClientGateway;

/**
 * Lists the Drupal core libraries documented on this server. Ported from
 * `dc_entities`'s `Plugin\Tool\ListCoreLibrariesTool`.
 */
final class ListCoreLibrariesTool extends CoreLibraryToolBase {

  use NormalizesInputSchema;

  public function definition(): Tool {
    return new Tool(
      name: 'list_core_libraries',
      title: 'List documented Drupal core libraries',
      inputSchema: self::normalizeObjectSchema([
        'properties' => [
          'query' => [
            'type' => 'string',
            'description' => 'Case-insensitive substring to match against the library title, id, name, qualified name, aliases, or summary.',
          ],
          'kind' => [
            'type' => 'string',
            'enum' => ['core', 'component'],
            'description' => 'Only list Core/ or Component/ libraries. Omit for both.',
          ],
          'limit' => [
            'type' => 'integer',
            'minimum' => 1,
            'maximum' => 200,
            'description' => 'Maximum number of libraries to return. Defaults to 50.',
          ],
          'offset' => [
            'type' => 'integer',
            'minimum' => 0,
            'description' => 'Number of matching libraries to skip, for pagination. Defaults to 0.',
          ],
        ],
        'additionalProperties' => false,
      ]),
      description: "Lists the Drupal framework libraries (below core/lib/Drupal, e.g. Core/Ajax or Component/Plugin) documented on this server, optionally filtered by a case-insensitive substring against title, id, name, aliases, or summary. Use this to discover a library's exact metadata.id before calling get_core_library or get_core_library_doc.",
      annotations: new ToolAnnotations(
        readOnlyHint: true,
        destructiveHint: false,
        idempotentHint: true,
        openWorldHint: false,
      ),
      outputSchema: [
        'type' => 'object',
        'properties' => [
          'libraries' => [
            'type' => 'array',
            'items' => [
              'type' => 'object',
              'properties' => [
                'id' => ['type' => 'string'],
                'name' => ['type' => 'string'],
                'qualified_name' => ['type' => 'string'],
                'title' => ['type' => 'string'],
                'kind' => ['type' => 'string'],
                'summary' => ['type' => 'string'],
                'core_version' => ['type' => 'string'],
              ],
              'required' => ['id', 'name', 'qualified_name', 'title', 'kind', 'summary', 'core_version'],
            ],
          ],
          'total' => ['type' => 'integer'],
          'limit' => ['type' => 'integer'],
          'offset' => ['type' => 'integer'],
        ],
        'required' => ['libraries', 'total', 'limit', 'offset'],
      ],
    );
  }

  public function execute(array $arguments, ClientGateway $gateway): mixed {
    $query = trim((string) ($arguments['query'] ?? ''));
    $kind = trim((string) ($arguments['kind'] ?? ''));
    $kindFilter = ($kind === 'core' || $kind === 'component') ? $kind : null;
    $limit = max(1, min(200, (int) ($arguments['limit'] ?? 50)));
    $offset = max(0, (int) ($arguments['offset'] ?? 0));

    $result = $this->catalog->listLibraries($query !== '' ? $query : null, $kindFilter, $limit, $offset);

    return [
      'libraries' => array_map(fn (array $library): array => $this->catalog->librarySummary($library), $result['libraries']),
      'total' => $result['total'],
      'limit' => $limit,
      'offset' => $offset,
    ];
  }

}
