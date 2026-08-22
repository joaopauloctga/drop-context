<?php

declare(strict_types=1);

namespace DropContext\Gateway\Repository;

use DropContext\Gateway\Dto\TagRow;
use DropContext\Gateway\GatewayClientInterface;
use DropContext\Gateway\Query;

/** Thin typed wrapper over the `taxonomy_term/skill_tag` resource. */
class TagRepository {

  private const string ENTITY_TYPE = 'taxonomy_term';

  private const string BUNDLE = 'skill_tag';

  public function __construct(
    private readonly GatewayClientInterface $client,
  ) {}

  /** @return TagRow[] */
  public function all(): array {
    $query = (new Query())->sort('name');
    $rows = [];
    foreach ($this->client->iterate(self::ENTITY_TYPE, self::BUNDLE, $query) as $row) {
      $rows[] = TagRow::fromArray($row);
    }
    return $rows;
  }

  public function findByName(string $name): ?TagRow {
    $query = (new Query())->filter('name', $name);
    $page = $this->client->getCollection(self::ENTITY_TYPE, self::BUNDLE, $query);
    return isset($page->data[0]) ? TagRow::fromArray($page->data[0]) : null;
  }

}
