<?php

declare(strict_types=1);

namespace DrupalContext\Gateway;

use DrupalContext\Gateway\Dto\CollectionPage;

interface GatewayClientInterface {

  public function getCollection(string $entityType, string $bundle, ?Query $query = null): CollectionPage;

  /** @return array<string, mixed> */
  public function getItem(string $entityType, string $bundle, string $uuid): array;

  /** Iterates every row across every page (perPage 50). @return \Generator<int, array<string, mixed>> */
  public function iterate(string $entityType, string $bundle, ?Query $query = null): \Generator;

}
