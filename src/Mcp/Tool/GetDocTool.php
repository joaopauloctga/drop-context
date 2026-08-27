<?php

declare(strict_types=1);

namespace DropContext\Mcp\Tool;

use DropContext\Catalog\TextFields;
use Mcp\Exception\ToolCallException;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\Tool;
use Mcp\Schema\ToolAnnotations;
use Mcp\Server\ClientGateway;

/**
 * Reads one module documentation file. Ported from `dc_entities`'s
 * `Plugin\Tool\GetDocTool`.
 *
 * The final step of the documentation-discovery flow: given a module and a
 * doc id from get_module's "docs" index (or a submodule machine name), reads
 * that single documentation file's raw Markdown. Docs can be large, so this
 * deliberately returns exactly one at a time.
 */
final class GetDocTool extends ModuleDocsToolBase {

  use NormalizesInputSchema;

  /** The doc id read when the caller does not specify one. */
  private const string DEFAULT_DOC = 'ai-integration';

  public function definition(): Tool {
    return new Tool(
      name: 'get_doc',
      title: 'Read one module documentation file',
      inputSchema: self::normalizeObjectSchema([
        'properties' => [
          'module' => [
            'type' => 'string',
            'minLength' => 1,
            'description' => 'The module machine name, e.g. "flag" or "token".',
          ],
          'doc' => [
            'type' => 'string',
            'description' => 'The doc id to read, e.g. "hooks" or "submodules/eca_base". Defaults to "ai-integration". Also accepts a trailing ".md", a category label, or (for a submodule) its bare machine name.',
          ],
          'release' => [
            'type' => 'string',
            'description' => 'Release tag to read from, e.g. "3.0.14". Defaults to the module\'s default (highest stable, else highest) documented release. An unrecognized tag is an error.',
          ],
        ],
        'required' => ['module'],
        'additionalProperties' => false,
      ]),
      description: 'Reads one Drupal module documentation file, given the module machine name and a doc id. A doc id is a doc-set file name without its ".md" extension: the 11 root ids are summary, entities, plugins, services, configuration, permissions, routes, hooks, events, extension-points, ai-integration; a submodule\'s id is "submodules/<submodule-machine-name>". Also accepts "<id>.md" or a submodule\'s bare machine name. Defaults to "ai-integration" — the implementation guide written for AI agents; "summary" is the module overview; the rest is reference material. Call get_module first to see which ids are actually available for a release.',
      annotations: new ToolAnnotations(
        readOnlyHint: true,
        destructiveHint: false,
        idempotentHint: true,
        openWorldHint: false,
      ),
      outputSchema: [
        'type' => 'object',
        'properties' => [
          'module' => ['type' => 'string'],
          'release' => ['type' => 'string'],
          'doc' => [
            'type' => 'object',
            'properties' => [
              'id' => ['type' => 'string'],
              'title' => ['type' => 'string'],
              'category' => ['type' => 'string'],
              'submodule' => ['type' => 'string'],
              'description' => ['type' => 'string'],
            ],
            'required' => ['id', 'title', 'category', 'submodule', 'description'],
          ],
          'content' => ['type' => 'string', 'description' => 'The doc file\'s raw Markdown.'],
          'available_docs' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
        'required' => ['module', 'release', 'doc', 'content', 'available_docs'],
      ],
    );
  }

  /**
   * Returns a hand-built CallToolResult rather than a plain array: the SDK
   * would otherwise JSON-pretty-print the whole structuredContent into
   * content[0].text, leaving the Markdown JSON-escaped for the model.
   * content[0] here is a short header followed by the raw Markdown;
   * structuredContent still carries the same data for clients that read it
   * instead of content.
   */
  public function execute(array $arguments, ClientGateway $gateway): mixed {
    $machineName = trim((string) ($arguments['module'] ?? ''));
    $requestedRelease = trim((string) ($arguments['release'] ?? ''));
    $docRef = trim((string) ($arguments['doc'] ?? ''));
    if ($docRef === '') {
      $docRef = self::DEFAULT_DOC;
    }

    $module = $this->requireModule($machineName);
    $release = $this->requireRelease($module, $machineName, $requestedRelease !== '' ? $requestedRelease : null);
    $tag = (string) ($release['releaseTag'] ?? '');

    $doc = $this->catalog->findDoc($release, $docRef);
    if ($doc === null) {
      $ids = $this->catalog->docIds($release);
      throw new ToolCallException(sprintf(
        'Unknown doc "%s" for %s %s. Available doc ids: %s. Call get_module for titles and descriptions.',
        $docRef,
        $machineName,
        $tag,
        implode(', ', $ids),
      ));
    }

    $docSummary = $this->catalog->docSummary($doc);
    $content = TextFields::value($doc['content'] ?? null);
    $availableDocs = $this->catalog->docIds($release);

    $structuredContent = [
      'module' => $machineName,
      'release' => $tag,
      'doc' => $docSummary,
      'content' => $content,
      'available_docs' => $availableDocs,
    ];

    $header = sprintf(
      "module: %s · release: %s · doc: %s — \"%s\"\nother docs in this release: %s\n---\n",
      $machineName,
      $tag,
      $docSummary['id'],
      $docSummary['title'],
      implode(', ', $availableDocs),
    );

    return new CallToolResult(
      content: [new TextContent($header . $content)],
      structuredContent: $structuredContent,
    );
  }

}
