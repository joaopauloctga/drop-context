<?php

namespace DropContext\Service;

use DropContext\ValueObject\AgentValueObject;

/**
 * Handle Agent store operations (project-level AGENTS.md installs).
 */
class AppStoreAgentService {

  public function __construct(
    protected AppStore $store,
  ) {}

  public function attach(AgentValueObject $agent): void {
    $index = array_search(
      $agent->name,
      array_column($this->store->getApp()->projects[$this->store->getProjectIndex()]->agents, 'name')
    );
    if ($index !== false) {
      $this->store->getApp()->projects[$this->store->getProjectIndex()]->agents[$index] = $agent;
    }
    else {
      $this->store->getApp()->projects[$this->store->getProjectIndex()]->agents[] = $agent;
    }
    $this->store->save();
  }

  public function replace(string $installedName, AgentValueObject $agent): void {
    $projectIndex = $this->store->getProjectIndex();
    if ($projectIndex === false || $projectIndex === null) {
      throw new \RuntimeException('Run this command inside a registered project.');
    }

    $agents = $this->store->getApp()->projects[$projectIndex]->agents;
    $index = array_search($installedName, array_column($agents, 'name'), true);
    if ($index === false) {
      $agents[] = $agent;
    }
    else {
      $agents[$index] = $agent;
    }

    $this->store->getApp()->projects[$projectIndex]->agents = array_values($agents);
    $this->store->save();
  }

  /**
   * @return array<int, object>
   */
  public function all(): array {
    $index = $this->store->getProjectIndex();
    if ($index === false || $index === null) {
      return [];
    }
    return array_values($this->store->getApp()->projects[$index]->agents ?? []);
  }

  public function isInstalled(string $agentName): bool {
    $agents = $this->store->getApp()->projects[$this->store->getProjectIndex()]->agents ?? [];
    return !empty(array_filter($agents, fn($agent) => $agent->name === $agentName));
  }

  public function detach(string $agentName): void {
    $project = $this->store->getApp()->projects[$this->store->getProjectIndex()];
    $project->agents = array_values(
      array_filter($project->agents ?? [], fn($agent) => $agent->name !== $agentName)
    );
    $this->store->save();
  }

}
