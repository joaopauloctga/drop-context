<?php

declare(strict_types=1);

namespace DropContext\Gateway\Repository;

use DropContext\Gateway\Dto\ReleaseRow;
use DropContext\Gateway\GatewayClientInterface;
use DropContext\Gateway\Query;

/** Thin typed wrapper over the `drupal_context/module_release` resource. */
class ReleaseRepository {

  private const string ENTITY_TYPE = 'drupal_context';

  private const string BUNDLE = 'module_release';

  public function __construct(
    private readonly GatewayClientInterface $client,
  ) {}

  /** @return ReleaseRow[] */
  public function findByModuleUuid(string $moduleUuid): array {
    $query = (new Query())->filter('module', $moduleUuid);
    $rows = [];
    foreach ($this->client->iterate(self::ENTITY_TYPE, self::BUNDLE, $query) as $row) {
      $rows[] = ReleaseRow::fromArray($row);
    }
    return $rows;
  }

  /**
   * @param string[] $moduleUuids
   * @return array<string, ReleaseRow[]> keyed by module uuid
   */
  public function findByModuleUuids(array $moduleUuids): array {
    $rows = [];
    foreach (array_chunk(array_values(array_unique($moduleUuids)), 50) as $chunk) {
      $query = (new Query())->filter('module', $chunk, 'in');
      foreach ($this->client->iterate(self::ENTITY_TYPE, self::BUNDLE, $query) as $row) {
        $release = ReleaseRow::fromArray($row);
        if ($release->moduleUuid !== null) {
          $rows[$release->moduleUuid][] = $release;
        }
      }
    }
    return $rows;
  }

  /** @param string[] $uuids @return array<string, ReleaseRow> keyed by uuid */
  public function findByUuids(array $uuids): array {
    $rows = [];
    foreach (array_chunk(array_values(array_unique($uuids)), 50) as $chunk) {
      $query = (new Query())->filter('uuid', $chunk, 'in');
      foreach ($this->client->iterate(self::ENTITY_TYPE, self::BUNDLE, $query) as $row) {
        $release = ReleaseRow::fromArray($row);
        $rows[$release->uuid] = $release;
      }
    }
    return $rows;
  }

}
