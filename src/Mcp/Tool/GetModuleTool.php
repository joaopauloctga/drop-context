<?php

declare(strict_types=1);

namespace DropContext\Mcp\Tool;

use DropContext\Catalog\ReleaseVersion;
use Mcp\Schema\Tool;
use Mcp\Schema\ToolAnnotations;
use Mcp\Server\ClientGateway;

/**
 * Returns a module's metadata, documented releases, and doc index. Ported
 * from `dc_entities`'s `Plugin\Tool\GetModuleTool`.
 *
 * The second step of the documentation-discovery flow: once an agent knows
 * a module's machine name (via list_modules), this returns everything
 * needed to decide which doc to read next with get_doc.
 */
final class GetModuleTool extends ModuleDocsToolBase {

  use NormalizesInputSchema;

  public function definition(): Tool {
    return new Tool(
      name: 'get_module',
      title: 'Get module overview, releases and doc index',
      inputSchema: self::normalizeObjectSchema([
        'properties' => [
          'module' => [
            'type' => 'string',
            'minLength' => 1,
            'description' => 'The module machine name, e.g. "flag" or "token".',
          ],
          'release' => [
            'type' => 'string',
            'description' => 'Release tag to load, e.g. "3.0.14". Defaults to the module\'s default (highest stable, else highest) documented release. An unrecognized tag is an error — see "available_releases" in the response.',
          ],
        ],
        'required' => ['module'],
        'additionalProperties' => false,
      ]),
      description: 'Returns a Drupal module\'s metadata, its documented releases, that release\'s notes, and the index of documentation files available for it. Given the module machine name, resolves the requested release (defaults to the highest documented stable release) — an unrecognized "release" is an error, not a silent fallback. Use the returned "docs" ids with get_doc to read one documentation file at a time.',
      annotations: new ToolAnnotations(
        readOnlyHint: true,
        destructiveHint: false,
        idempotentHint: true,
        openWorldHint: false,
      ),
      outputSchema: [
        'type' => 'object',
        'properties' => [
          'module' => [
            'type' => 'object',
            'properties' => [
              'machine_name' => ['type' => 'string'],
              'title' => ['type' => 'string'],
              'summary' => ['type' => 'string'],
              'description' => ['type' => 'string', 'description' => 'The resolved release\'s own summary, in Markdown.'],
              'is_core' => ['type' => 'boolean'],
              'security_coverage' => ['type' => 'boolean'],
              'maintenance_status' => ['type' => 'string'],
              'development_status' => ['type' => 'string'],
              'categories' => ['type' => 'array', 'items' => ['type' => 'string']],
              'ecosystem' => ['type' => 'array', 'items' => ['type' => 'string']],
              'drupal_org_url' => ['type' => ['string', 'null']],
              'url' => ['type' => 'string'],
            ],
            'required' => [
              'machine_name', 'title', 'summary', 'description', 'is_core',
              'security_coverage', 'maintenance_status', 'development_status',
              'categories', 'ecosystem', 'drupal_org_url', 'url',
            ],
          ],
          'release' => ['type' => 'string'],
          'is_stable' => ['type' => 'boolean'],
          'default_release' => ['type' => 'string'],
          'available_releases' => [
            'type' => 'array',
            'items' => [
              'type' => 'object',
              'properties' => [
                'tag' => ['type' => 'string'],
                'is_stable' => ['type' => 'boolean'],
                'release_line' => ['type' => 'string'],
                'release_notes_url' => ['type' => 'string'],
              ],
              'required' => ['tag', 'is_stable', 'release_line', 'release_notes_url'],
            ],
          ],
          'release_notes' => [
            'type' => 'array',
            'items' => [
              'type' => 'object',
              'properties' => [
                'tag' => ['type' => 'string'],
                'summary' => ['type' => 'string'],
                'issues' => [
                  'type' => 'array',
                  'items' => [
                    'type' => 'object',
                    'properties' => [
                      'url' => ['type' => 'string'],
                      'title' => ['type' => 'string'],
                    ],
                    'required' => ['url', 'title'],
                  ],
                ],
              ],
              'required' => ['tag', 'summary', 'issues'],
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
                'submodule' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'size' => ['type' => 'integer'],
              ],
              'required' => ['id', 'title', 'category', 'submodule', 'description', 'size'],
            ],
          ],
        ],
        'required' => ['module', 'release', 'is_stable', 'default_release', 'available_releases', 'release_notes', 'docs'],
      ],
    );
  }

  public function execute(array $arguments, ClientGateway $gateway): mixed {
    $machineName = trim((string) ($arguments['module'] ?? ''));
    $requestedRelease = trim((string) ($arguments['release'] ?? ''));

    $module = $this->requireModule($machineName);
    $release = $this->requireRelease($module, $machineName, $requestedRelease !== '' ? $requestedRelease : null);

    $releases = $this->catalog->releasesForModule($module);
    $defaultRelease = $this->catalog->defaultRelease($module);

    return [
      'module' => $this->catalog->moduleDetails($module, $release),
      'release' => (string) ($release['releaseTag'] ?? ''),
      'is_stable' => ReleaseVersion::isStable((string) ($release['releaseTag'] ?? '')),
      'default_release' => $defaultRelease !== null ? (string) ($defaultRelease['releaseTag'] ?? '') : '',
      'available_releases' => array_map(fn (array $r): array => $this->catalog->releaseSummary($r), $releases),
      'release_notes' => $this->catalog->releaseNotes($release),
      'docs' => $this->catalog->tableOfContents($release),
    ];
  }

}
