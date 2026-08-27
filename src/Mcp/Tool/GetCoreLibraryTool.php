<?php

declare(strict_types=1);

namespace DropContext\Mcp\Tool;

use Mcp\Schema\Tool;
use Mcp\Schema\ToolAnnotations;
use Mcp\Server\ClientGateway;

/**
 * Returns a core library's metadata and doc index. Ported from
 * `dc_entities`'s `Plugin\Tool\GetCoreLibraryTool`.
 */
final class GetCoreLibraryTool extends CoreLibraryToolBase {

  use NormalizesInputSchema;

  public function definition(): Tool {
    return new Tool(
      name: 'get_core_library',
      title: 'Get core library overview and doc index',
      inputSchema: self::normalizeObjectSchema([
        'properties' => [
          'library' => [
            'type' => 'string',
            'minLength' => 1,
            'description' => 'The library metadata.id (drupal.core.ajax), qualified name (Core/Ajax), or PHP namespace (Drupal\\Core\\Ajax).',
          ],
        ],
        'required' => ['library'],
        'additionalProperties' => false,
      ]),
      description: 'Returns a Drupal core framework library\'s metadata and the index of documentation files available for it. Pass metadata.id (e.g. "drupal.core.ajax"), a qualified name ("Core/Ajax"), or the PHP namespace. An ambiguous short name is an error — use list_core_libraries first. Use the returned "docs" ids with get_core_library_doc to read one file at a time.',
      annotations: new ToolAnnotations(
        readOnlyHint: true,
        destructiveHint: false,
        idempotentHint: true,
        openWorldHint: false,
      ),
      outputSchema: [
        'type' => 'object',
        'properties' => [
          'library' => [
            'type' => 'object',
            'properties' => [
              'id' => ['type' => 'string'],
              'name' => ['type' => 'string'],
              'qualified_name' => ['type' => 'string'],
              'title' => ['type' => 'string'],
              'kind' => ['type' => 'string'],
              'summary' => ['type' => 'string'],
              'description' => ['type' => 'string', 'description' => 'The library summary Markdown.'],
              'core_version' => ['type' => 'string'],
              'url' => ['type' => 'string'],
            ],
            'required' => [
              'id', 'name', 'qualified_name', 'title', 'kind', 'summary',
              'description', 'core_version', 'url',
            ],
          ],
          'docs' => [
            'type' => 'array',
            'items' => [
              'type' => 'object',
              'properties' => [
                'id' => ['type' => 'string'],
                'title' => ['type' => 'string'],
                'category' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'size' => ['type' => 'integer'],
              ],
              'required' => ['id', 'title', 'category', 'description', 'size'],
            ],
          ],
        ],
        'required' => ['library', 'docs'],
      ],
    );
  }

  public function execute(array $arguments, ClientGateway $gateway): mixed {
    $ref = trim((string) ($arguments['library'] ?? ''));
    $library = $this->requireLibrary($ref);

    return [
      'library' => $this->catalog->libraryDetails($library),
      'docs' => $this->catalog->tableOfContents($library),
    ];
  }

}
