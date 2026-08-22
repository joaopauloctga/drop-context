<?php

declare(strict_types=1);

namespace DropContext\Gateway\Repository;

use DropContext\Gateway\Dto\ModuleRow;
use DropContext\Gateway\GatewayClientInterface;
use DropContext\Gateway\Query;

/** Thin typed wrapper over the `drupal_context/module_details` resource. */
class ModuleRepository {

  private const string ENTITY_TYPE = 'drupal_context';

  private const string BUNDLE = 'module_details';

  public function __construct(
    private readonly GatewayClientInterface $client,
  ) {}

  public function findByMachineName(string $machineName): ?ModuleRow {
    $query = (new Query())->filter('machineName', $machineName);
    $page = $this->client->getCollection(self::ENTITY_TYPE, self::BUNDLE, $query);
    return isset($page->data[0]) ? ModuleRow::fromArray($page->data[0]) : null;
  }

  /** @return ModuleRow[] */
  public function search(?string $text = null, bool $byTitle = false): array {
    $query = (new Query())->sort('machineName');
    if ($text !== null && $text !== '') {
      $query = $query->filter($byTitle ? 'title' : 'machineName', $text, 'contains');
    }

    $rows = [];
    foreach ($this->client->iterate(self::ENTITY_TYPE, self::BUNDLE, $query) as $row) {
      $rows[] = ModuleRow::fromArray($row);
    }
    return $rows;
  }

  /** @param string[] $uuids @return array<string, ModuleRow> keyed by uuid */
  public function findByUuids(array $uuids): array {
    $rows = [];
    foreach (array_chunk(array_values(array_unique($uuids)), 50) as $chunk) {
      $query = (new Query())->filter('uuid', $chunk, 'in');
      foreach ($this->client->iterate(self::ENTITY_TYPE, self::BUNDLE, $query) as $row) {
        $module = ModuleRow::fromArray($row);
        $rows[$module->uuid] = $module;
      }
    }
    return $rows;
  }

}
