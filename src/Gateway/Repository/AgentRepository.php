<?php

declare(strict_types=1);

namespace DropContext\Gateway\Repository;

use DropContext\Gateway\Dto\AgentRow;
use DropContext\Gateway\GatewayClientInterface;
use DropContext\Gateway\Query;

/** Thin typed wrapper over the `drupal_context/agent` resource. */
class AgentRepository {

  private const string ENTITY_TYPE = 'drupal_context';

  private const string BUNDLE = 'agent';

  public function __construct(
    private readonly GatewayClientInterface $client,
  ) {}

  /**
   * @param string[] $moduleUuids
   * @return AgentRow[]
   */
  public function search(?string $text = null, array $moduleUuids = []): array {
    $query = new Query();

    if ($text !== null && $text !== '') {
      $query = $query->filter('title', $text, 'contains');
    }

    if ($moduleUuids === []) {
      return $this->fetch($query);
    }

    $rows = [];
    foreach (array_chunk($moduleUuids, 50) as $chunk) {
      $rows = [...$rows, ...$this->fetch($query->filter('modules', $chunk, 'in'))];
    }
    return $rows;
  }

  /** @return AgentRow[] */
  public function findByMachineName(string $machineName): array {
    return $this->fetch((new Query())->filter('machineName', $machineName));
  }

  /** @return AgentRow[] */
  public function findByTitle(string $title): array {
    return $this->fetch((new Query())->filter('title', $title));
  }

  /** @return AgentRow[] */
  public function findByUuid(string $uuid): array {
    return $this->fetch((new Query())->filter('uuid', $uuid));
  }

  /** @param string[] $uuids @return array<string, AgentRow> keyed by uuid */
  public function findByUuids(array $uuids): array {
    $rows = [];
    foreach (array_chunk(array_values(array_unique($uuids)), Query::MAX_PER_PAGE) as $chunk) {
      foreach ($this->fetch((new Query())->filter('uuid', $chunk, 'in')) as $agent) {
        $rows[$agent->uuid] = $agent;
      }
    }
    return $rows;
  }

  /**
   * @param string[] $titles
   * @return array<string, AgentRow[]> keyed by exact title
   */
  public function findByTitles(array $titles): array {
    $rows = [];
    foreach (array_chunk(array_values(array_unique($titles)), Query::MAX_PER_PAGE) as $chunk) {
      foreach ($this->fetch((new Query())->filter('title', $chunk, 'in')) as $agent) {
        $rows[$agent->title][] = $agent;
      }
    }
    return $rows;
  }

  /**
   * @param string[] $machineNames
   * @return array<string, AgentRow[]> keyed by exact machine name
   */
  public function findByMachineNames(array $machineNames): array {
    $rows = [];
    foreach (array_chunk(array_values(array_unique($machineNames)), Query::MAX_PER_PAGE) as $chunk) {
      foreach ($this->fetch((new Query())->filter('machineName', $chunk, 'in')) as $agent) {
        if ($agent->machineName !== null && $agent->machineName !== '') {
          $rows[$agent->machineName][] = $agent;
        }
      }
    }
    return $rows;
  }

  /** @return AgentRow[] */
  public function suggest(string $text, int $limit = 5): array {
    // Prefer machine-name matches, then title contains.
    $byMachine = $this->fetch((new Query())->filter('machineName', $text, 'contains')->perPage($limit));
    if ($byMachine !== []) {
      return array_slice($byMachine, 0, $limit);
    }
    $query = (new Query())->filter('title', $text, 'contains')->perPage($limit);
    $page = $this->client->getCollection(self::ENTITY_TYPE, self::BUNDLE, $query);
    return array_map(AgentRow::fromArray(...), $page->data);
  }

  /** @return AgentRow[] */
  private function fetch(Query $query): array {
    $rows = [];
    foreach ($this->client->iterate(self::ENTITY_TYPE, self::BUNDLE, $query) as $row) {
      $rows[] = AgentRow::fromArray($row);
    }
    return $rows;
  }

}
