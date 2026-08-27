<?php

declare(strict_types=1);

namespace DropContext\Catalog;

use DropContext\Gateway\FileDownloader;
use DropContext\Gateway\GatewayClientInterface;
use DropContext\Gateway\Query;

/**
 * Raw entity_gateway access for the MCP catalogs — rows come back exactly as
 * the gateway serializes them (camelCase keys), undecoded beyond JSON.
 *
 * Wired in services.yaml over a GatewayClient/FileDownloader pair built on
 * CachingGatewayHttp, so every method here goes through the response cache.
 */
final class CatalogRepository {

  private const string ENTITY_TYPE = 'drupal_context';

  public function __construct(
    private readonly GatewayClientInterface $client,
    private readonly FileDownloader $fileDownloader,
  ) {}

  /** @return list<array<string, mixed>> */
  public function allModules(): array {
    return iterator_to_array($this->client->iterate(self::ENTITY_TYPE, 'module_details'), false);
  }

  /** @return list<array<string, mixed>> */
  public function allReleases(): array {
    return iterator_to_array($this->client->iterate(self::ENTITY_TYPE, 'module_release'), false);
  }

  /** All published module_doc rows for one release — the whole doc set's Markdown, one request (cached). */
  public function docsForRelease(string $releaseUuid): array {
    $query = (new Query())->filter('moduleRelease', $releaseUuid);
    return iterator_to_array($this->client->iterate(self::ENTITY_TYPE, 'module_doc', $query), false);
  }

  /** @return list<array<string, mixed>> */
  public function allCoreLibraries(): array {
    return iterator_to_array($this->client->iterate(self::ENTITY_TYPE, 'core_library'), false);
  }

  /** Downloads a core-library reference file's body (architecture/api/usage/topics — never inlined by the gateway). */
  public function fileBody(string $url): string {
    return $this->fileDownloader->download($url);
  }

}
