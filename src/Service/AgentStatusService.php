<?php

declare(strict_types=1);

namespace DropContext\Service;

use DropContext\AgentUpdateState;
use DropContext\Gateway\Dto\AgentRow;
use DropContext\Gateway\Repository\AgentRepository;
use DropContext\ValueObject\InstalledAgentStatus;

class AgentStatusService {

  public function __construct(
    private readonly AgentRepository $agentRepository,
    private readonly AgentService $agentService,
    private readonly AppStoreService $appStoreService,
  ) {}

  /** @return InstalledAgentStatus[] */
  public function inspect(): array {
    $installed = $this->agentService->listInstalledAgents();
    if ($installed === []) {
      return [];
    }

    $uuids = array_values(array_filter(array_column($installed, 'uuid')));
    $names = array_column($installed, 'name');
    $byUuid = $this->agentRepository->findByUuids($uuids);
    $byMachine = $this->agentRepository->findByMachineNames($names);
    $byTitle = $this->agentRepository->findByTitles($names);

    $statuses = [];
    foreach ($installed as $entry) {
      $remote = null;
      $detail = null;
      if (!empty($entry['uuid']) && isset($byUuid[$entry['uuid']])) {
        $remote = $byUuid[$entry['uuid']];
      }
      elseif (isset($byMachine[$entry['name']]) && count($byMachine[$entry['name']]) === 1) {
        $remote = $byMachine[$entry['name']][0];
      }
      elseif (isset($byTitle[$entry['name']]) && count($byTitle[$entry['name']]) === 1) {
        $remote = $byTitle[$entry['name']][0];
      }
      elseif (
        (isset($byMachine[$entry['name']]) && count($byMachine[$entry['name']]) > 1)
        || (isset($byTitle[$entry['name']]) && count($byTitle[$entry['name']]) > 1)
      ) {
        $detail = 'More than one remote agent matches this key and the local entry has no usable UUID.';
      }
      else {
        $detail = 'The agent could not be found in the Drop Context gateway.';
      }

      $cacheDir = $this->agentService->getAgent($entry['name']);
      if ($cacheDir === null) {
        $statuses[] = new InstalledAgentStatus(
          name: $entry['name'],
          state: AgentUpdateState::MISSING,
          localVersion: $entry['version'],
          remoteVersion: $remote?->version,
          uuid: $entry['uuid'],
          remoteAgent: $remote,
          detail: 'The registry entry exists, but no cached agent path was found.',
        );
        continue;
      }

      if ($remote === null) {
        $statuses[] = new InstalledAgentStatus(
          name: $entry['name'],
          state: AgentUpdateState::UNAVAILABLE,
          localVersion: $entry['version'],
          remoteVersion: null,
          uuid: $entry['uuid'],
          detail: $detail,
        );
        continue;
      }

      if ($remote->version === null || $remote->version === '') {
        $statuses[] = new InstalledAgentStatus(
          name: $entry['name'],
          state: AgentUpdateState::VERSION_UNKNOWN,
          localVersion: $entry['version'],
          remoteVersion: null,
          uuid: $entry['uuid'],
          remoteAgent: $remote,
          detail: 'The gateway did not return a version for this agent.',
        );
        continue;
      }

      $local = $entry['version'] ?: '0.1';
      $remoteVersion = $remote->version;
      if (version_compare($local, $remoteVersion, '<')) {
        $state = AgentUpdateState::UPDATE_AVAILABLE;
      }
      elseif (version_compare($local, $remoteVersion, '>')) {
        $state = AgentUpdateState::LOCAL_NEWER;
      }
      else {
        $state = AgentUpdateState::CURRENT;
      }

      $statuses[] = new InstalledAgentStatus(
        name: $entry['name'],
        state: $state,
        localVersion: $local,
        remoteVersion: $remoteVersion,
        uuid: $entry['uuid'] ?? $remote->uuid,
        remoteAgent: $remote,
      );
    }

    return $statuses;
  }

  public function update(InstalledAgentStatus $status): \DropContext\ValueObject\AgentValueObject {
    if ($status->remoteAgent === null) {
      throw new \RuntimeException("Agent '{$status->name}' has no remote row to update from.");
    }
    $updated = $this->agentService->installAgent($status->remoteAgent);
    $this->appStoreService->agents->replace($status->name, $updated);
    return $updated;
  }

}
