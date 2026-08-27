<?php

declare(strict_types=1);

namespace DropContext\Mcp\Tool;

use Mcp\Exception\ToolCallException;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\Tool;
use Mcp\Schema\ToolAnnotations;
use Mcp\Server\ClientGateway;

/**
 * Reads one core-library documentation file. Ported from `dc_entities`'s
 * `Plugin\Tool\GetCoreLibraryDocTool`.
 */
final class GetCoreLibraryDocTool extends CoreLibraryToolBase {

  use NormalizesInputSchema;

  /** The doc id read when the caller does not specify one. */
  private const string DEFAULT_DOC = 'usage';

  public function definition(): Tool {
    return new Tool(
      name: 'get_core_library_doc',
      title: 'Read one core library documentation file',
      inputSchema: self::normalizeObjectSchema([
        'properties' => [
          'library' => [
            'type' => 'string',
            'minLength' => 1,
            'description' => 'The library metadata.id (drupal.core.ajax), qualified name (Core/Ajax), or PHP namespace.',
          ],
          'doc' => [
            'type' => 'string',
            'description' => 'The doc id to read, e.g. "usage" or "topics/dialogs". Defaults to "usage". Also accepts a trailing ".md".',
          ],
        ],
        'required' => ['library'],
        'additionalProperties' => false,
      ]),
      description: 'Reads one Drupal core framework library documentation file, given the library reference and a doc id. Doc ids are file names without ".md": summary, architecture, api, usage, and topics/<slug>. Also accepts "<id>.md". Defaults to "usage" — the verified patterns guide; "summary" is the overview. Call get_core_library first to see which ids are available.',
      annotations: new ToolAnnotations(
        readOnlyHint: true,
        destructiveHint: false,
        idempotentHint: true,
        openWorldHint: false,
      ),
      outputSchema: [
        'type' => 'object',
        'properties' => [
          'library' => ['type' => 'string'],
          'core_version' => ['type' => 'string'],
          'doc' => [
            'type' => 'object',
            'properties' => [
              'id' => ['type' => 'string'],
              'title' => ['type' => 'string'],
              'category' => ['type' => 'string'],
              'description' => ['type' => 'string'],
            ],
            'required' => ['id', 'title', 'category', 'description'],
          ],
          'content' => ['type' => 'string', 'description' => 'The doc file\'s raw Markdown.'],
          'available_docs' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
        'required' => ['library', 'core_version', 'doc', 'content', 'available_docs'],
      ],
    );
  }

  public function execute(array $arguments, ClientGateway $gateway): mixed {
    $ref = trim((string) ($arguments['library'] ?? ''));
    $docRef = trim((string) ($arguments['doc'] ?? ''));
    if ($docRef === '') {
      $docRef = self::DEFAULT_DOC;
    }

    $library = $this->requireLibrary($ref);
    $libraryId = (string) ($library['libraryId'] ?? '');
    $coreVersion = (string) ($library['coreVersion'] ?? '');

    $doc = $this->catalog->findDoc($library, $docRef);
    if ($doc === null) {
      $ids = $this->catalog->docIds($library);
      throw new ToolCallException(sprintf(
        'Unknown doc "%s" for %s. Available doc ids: %s. Call get_core_library for titles and descriptions.',
        $docRef,
        $libraryId,
        implode(', ', $ids),
      ));
    }

    $docSummary = [
      'id' => $doc['id'],
      'title' => $doc['title'],
      'category' => $doc['category'],
      'description' => $doc['description'],
    ];
    $content = $doc['content'];
    $availableDocs = $this->catalog->docIds($library);

    $structuredContent = [
      'library' => $libraryId,
      'core_version' => $coreVersion,
      'doc' => $docSummary,
      'content' => $content,
      'available_docs' => $availableDocs,
    ];

    $header = sprintf(
      "library: %s · core: %s · doc: %s — \"%s\"\nother docs: %s\n---\n",
      $libraryId,
      $coreVersion,
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
