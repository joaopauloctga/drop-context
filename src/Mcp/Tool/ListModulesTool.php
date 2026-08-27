<?php

declare(strict_types=1);

namespace DropContext\Mcp\Tool;

use Mcp\Schema\Tool;
use Mcp\Schema\ToolAnnotations;
use Mcp\Server\ClientGateway;

/**
 * Lists the Drupal modules documented on this server. Ported from
 * `dc_entities`'s `Plugin\Tool\ListModulesTool` — same name, description,
 * schemas.
 *
 * The entry point of the documentation-discovery flow: an agent that only
 * knows a module by name (not its exact machine name) starts here, then
 * moves on to get_module and get_doc.
 */
final class ListModulesTool extends ModuleDocsToolBase {

  use NormalizesInputSchema;

  public function definition(): Tool {
    return new Tool(
      name: 'list_modules',
      title: 'List documented Drupal modules',
      inputSchema: self::normalizeObjectSchema([
        'properties' => [
          'query' => [
            'type' => 'string',
            'description' => 'Case-insensitive substring to match against the module title, machine name, or description.',
          ],
          'core_only' => [
            'type' => 'boolean',
            'description' => 'Only list Drupal core modules. Defaults to false.',
          ],
          'limit' => [
            'type' => 'integer',
            'minimum' => 1,
            'maximum' => 200,
            'description' => 'Maximum number of modules to return. Defaults to 50.',
          ],
          'offset' => [
            'type' => 'integer',
            'minimum' => 0,
            'description' => 'Number of matching modules to skip, for pagination. Defaults to 0.',
          ],
        ],
        'additionalProperties' => false,
      ]),
      description: "Lists the Drupal modules (contrib and core) documented on this server, optionally filtered by a case-insensitive substring match against the module title, machine name, or description. Use this to discover a module's exact machine name before calling get_module or get_doc.",
      annotations: new ToolAnnotations(
        readOnlyHint: true,
        destructiveHint: false,
        idempotentHint: true,
        openWorldHint: false,
      ),
      outputSchema: [
        'type' => 'object',
        'properties' => [
          'modules' => [
            'type' => 'array',
            'items' => [
              'type' => 'object',
              'properties' => [
                'machine_name' => ['type' => 'string'],
                'title' => ['type' => 'string'],
                'is_core' => ['type' => 'boolean'],
                'summary' => ['type' => 'string'],
                'default_release' => ['type' => 'string'],
                'releases' => ['type' => 'array', 'items' => ['type' => 'string']],
              ],
              'required' => ['machine_name', 'title', 'is_core', 'summary', 'default_release', 'releases'],
            ],
          ],
          'total' => ['type' => 'integer'],
          'limit' => ['type' => 'integer'],
          'offset' => ['type' => 'integer'],
        ],
        'required' => ['modules', 'total', 'limit', 'offset'],
      ],
    );
  }

  public function execute(array $arguments, ClientGateway $gateway): mixed {
    $query = trim((string) ($arguments['query'] ?? ''));
    $coreOnly = (bool) ($arguments['core_only'] ?? false);
    $limit = max(1, min(200, (int) ($arguments['limit'] ?? 50)));
    $offset = max(0, (int) ($arguments['offset'] ?? 0));

    $result = $this->catalog->listModules($query !== '' ? $query : null, $coreOnly, $limit, $offset);

    return [
      'modules' => array_map(fn (array $module): array => $this->catalog->moduleSummary($module), $result['modules']),
      'total' => $result['total'],
      'limit' => $limit,
      'offset' => $offset,
    ];
  }

}
