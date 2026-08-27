<?php

declare(strict_types=1);

namespace DropContext\Gateway;

use DropContext\Gateway\Dto\CollectionPage;
use DropContext\Gateway\Exception\GatewayException;

/** entity_gateway JSON API client, built over GatewayHttpInterface (uncached by default; the MCP server wires a cached instance — see services.yaml). */
class GatewayClient implements GatewayClientInterface {

  public function __construct(
    private readonly GatewaySettings $settings,
    private readonly GatewayHttpInterface $http,
  ) {}

  public function getCollection(string $entityType, string $bundle, ?Query $query = null): CollectionPage {
    $data = $this->request("/api/{$entityType}/{$bundle}", ($query ?? new Query())->toParams());
    return CollectionPage::fromArray($data);
  }

  public function getItem(string $entityType, string $bundle, string $uuid): array {
    return $this->request("/api/{$entityType}/{$bundle}/{$uuid}");
  }

  public function iterate(string $entityType, string $bundle, ?Query $query = null): \Generator {
    $base = ($query ?? new Query())->perPage(Query::MAX_PER_PAGE);
    $page = 1;

    while (true) {
      $collection = $this->getCollection($entityType, $bundle, $base->page($page));

      foreach ($collection->data as $row) {
        yield $row;
      }

      // Fall back to the requested page size when the server omits perPage
      // (CollectionPage defaults it to 0, which would never terminate), and
      // stop on any short page — it is necessarily the last one.
      $perPage = $collection->perPage > 0 ? $collection->perPage : Query::MAX_PER_PAGE;
      if ($collection->count < $perPage || $page * $perPage >= $collection->total) {
        break;
      }

      $page++;
    }
  }

  /** @return array<string, mixed> */
  private function request(string $path, array $query = []): array {
    $result = $this->http->get(
      $this->settings->getBaseUrl() . $path,
      $query,
      ['Accept' => 'application/json'],
    );

    $data = self::decode($result->body);
    if ($data === null) {
      throw new GatewayException(
        "Unexpected non-JSON response from {$this->settings->getBaseUrl()}{$path} — " .
        'is DROP_CONTEXT_URL pointing at a Drop Context site?',
      );
    }
    return $data;
  }

  /** @return array<string, mixed>|null null when the body is not valid JSON (or not a JSON object/array) */
  private static function decode(string $body): ?array {
    if (trim($body) === '') {
      return [];
    }
    $decoded = json_decode($body, true);
    return is_array($decoded) ? $decoded : null;
  }

}
