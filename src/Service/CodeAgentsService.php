<?php

namespace DrupalContext\Service;

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
  ];

  public function readCurrentCodeAgents(): array {
    $projectRoot = $this->appStoreService->getRunnableProject()->path;
    return array_filter(self::KNOWN_CODE_AGENTS, fn($agent) => $this->filesystem->exists($projectRoot . DIRECTORY_SEPARATOR . $agent));
  }

  public function installCodeAgents(array $missingAgents): void {
    $projectRoot = $this->appStoreService->getRunnableProject()->path;
    foreach ($missingAgents as $agent) {
      $this->filesystem->mkdir($projectRoot . DIRECTORY_SEPARATOR . $agent);
    }
  }

}