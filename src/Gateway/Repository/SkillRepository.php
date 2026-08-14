<?php

declare(strict_types=1);

namespace DrupalContext\Gateway\Repository;

use DrupalContext\Gateway\Dto\SkillRow;
use DrupalContext\Gateway\GatewayClientInterface;
use DrupalContext\Gateway\Query;

/** Thin typed wrapper over the `drupal_context/skill` resource. */
class SkillRepository {

  private const string ENTITY_TYPE = 'drupal_context';

  private const string BUNDLE = 'skill';

  public function __construct(
    private readonly GatewayClientInterface $client,
  ) {}

  /**
   * @param string[] $releaseUuids
   * @return SkillRow[]
   */
  public function search(?string $text = null, ?string $type = null, ?string $tagUuid = null, array $releaseUuids = []): array {
    $query = new Query();

    if ($text !== null && $text !== '') {
      $query = $query->filter('title', $text, 'contains');
    }
    if ($type !== null && $type !== '') {
      $query = $query->filter('skillType', $type);
    }
    if ($tagUuid !== null && $tagUuid !== '') {
      $query = $query->filter('skillTags', $tagUuid);
    }

    if ($releaseUuids === []) {
      return $this->fetch($query);
    }

    $rows = [];
    foreach (array_chunk($releaseUuids, 50) as $chunk) {
      $rows = [...$rows, ...$this->fetch($query->filter('moduleRelease', $chunk, 'in'))];
    }
    return $rows;
  }

  /** @return SkillRow[] */
  public function findByTitle(string $title): array {
    return $this->fetch((new Query())->filter('title', $title));
  }

  /** @return SkillRow[] */
  public function findByUuid(string $uuid): array {
    return $this->fetch((new Query())->filter('uuid', $uuid));
  }

  /** Fuzzy "did you mean" candidates. @return SkillRow[] */
  public function suggest(string $text, int $limit = 5): array {
    $query = (new Query())->filter('title', $text, 'contains')->perPage($limit);
    $page = $this->client->getCollection(self::ENTITY_TYPE, self::BUNDLE, $query);
    return array_map(SkillRow::fromArray(...), $page->data);
  }

  /** @return SkillRow[] */
  private function fetch(Query $query): array {
    $rows = [];
    foreach ($this->client->iterate(self::ENTITY_TYPE, self::BUNDLE, $query) as $row) {
      $rows[] = SkillRow::fromArray($row);
    }
    return $rows;
  }

}
