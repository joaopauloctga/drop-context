<?php

declare(strict_types=1);

namespace DropContext\Gateway;

use DropContext\Gateway\Dto\AgentLookupResult;
use DropContext\Gateway\Dto\AgentRow;
use DropContext\Gateway\Exception\ModuleNotFoundException;
use DropContext\Gateway\Repository\AgentRepository;
use DropContext\Gateway\Repository\ModuleRepository;

/** Joins agent/module_details for the queries the CLI needs. */
class AgentQueryService {

  public function __construct(
    private readonly AgentRepository $agents,
    private readonly ModuleRepository $modules,
  ) {}

  /**
   * @throws ModuleNotFoundException
   * @return AgentRow[]
   */
  public function search(?string $text = null, ?string $module = null): array {
    $moduleUuids = [];

    if ($module !== null && $module !== '') {
      $moduleRow = $this->modules->findByMachineName($module);
      if ($moduleRow === null) {
        throw new ModuleNotFoundException($module, $this->suggestModules($module));
      }
      $moduleUuids = [$moduleRow->uuid];
    }

    return $this->agents->search(text: $text, moduleUuids: $moduleUuids);
  }

  public function findOne(string $nameOrUuid): AgentLookupResult {
    if (self::looksLikeUuid($nameOrUuid)) {
      $matches = $this->agents->findByUuid($nameOrUuid);
      if (count($matches) === 1) {
        return new AgentLookupResult(agent: $matches[0]);
      }
      return new AgentLookupResult(suggestions: $this->agents->suggest($nameOrUuid));
    }

    // Official and user agents are installed by machine name
    // (drop-view-developer or joaopauloc:views-developer).
    $matches = $this->agents->findByMachineName($nameOrUuid);
    if (count($matches) === 1) {
      return new AgentLookupResult(agent: $matches[0]);
    }
    if (count($matches) > 1) {
      return new AgentLookupResult(candidates: $matches);
    }

    $matches = $this->agents->findByTitle($nameOrUuid);
    if (count($matches) === 1) {
      return new AgentLookupResult(agent: $matches[0]);
    }
    if (count($matches) > 1) {
      return new AgentLookupResult(candidates: $matches);
    }

    return new AgentLookupResult(suggestions: $this->agents->suggest($nameOrUuid));
  }

  /** @return string[] */
  private function suggestModules(string $text): array {
    return array_map(static fn ($module) => $module->machineName, $this->modules->search($text));
  }

  private static function looksLikeUuid(string $value): bool {
    return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value);
  }

}
