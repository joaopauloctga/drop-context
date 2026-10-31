<?php

namespace DropContext\Service;

use Symfony\Component\Filesystem\Filesystem;

class CodeAgentsService {

  public function __construct(
    protected Filesystem $filesystem,
    protected AppStoreService $appStoreService,
  )
  {

  }

  const array KNOWN_CODE_AGENTS = [
    '.claude',
    '.agents',
    '.cursor',
    '.codex',
    '.opencode',
  ];

  public function readCurrentCodeAgents(): array {
    $runnableProject = $this->appStoreService->getRunnableProject();
    if ($runnableProject === null) {
      return [];
    }
    $projectRoot = $runnableProject->path;
    return array_filter(self::KNOWN_CODE_AGENTS, fn($agent) => $this->filesystem->exists($projectRoot . DIRECTORY_SEPARATOR . $agent));
  }

  public function installCodeAgents(array $missingAgents): void {
    $runnableProject = $this->appStoreService->getRunnableProject();
    if ($runnableProject === null) {
      throw new \RuntimeException('Run this command inside a project with a composer.json.');
    }
    $projectRoot = $runnableProject->path;
    foreach ($missingAgents as $agent) {
      $this->filesystem->mkdir($projectRoot . DIRECTORY_SEPARATOR . $agent);
    }
  }

}