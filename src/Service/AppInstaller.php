<?php

namespace DropContext\Service;

class AppInstaller {

  public function __construct(
    private readonly AppStore $store,
    private readonly CodeAgentsService $codeAgentsService,
  ) {}

  public function isInstalled(): bool {
    return $this->store->isInstalled();
  }

  public function detectAvailableCodeAgents(): array {
    return array_values($this->codeAgentsService->readCurrentCodeAgents());
  }

  public function install(array $codeAgents): void {
    $this->store->getApp()->codeAgents = $codeAgents;
    $this->store->setupCurrentProject();
    $this->store->save();
  }

  public function ensureProjectRegistered(): void {
    $projectIndex = $this->store->getProjectIndex();
    if ($this->store->getRunnableProject() !== null && $projectIndex === false) {
      $this->store->setupCurrentProject();
      $this->store->save();
    }
  }

}
