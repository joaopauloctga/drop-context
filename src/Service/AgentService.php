<?php

declare(strict_types=1);

namespace DropContext\Service;

use DropContext\Console\Ui;
use DropContext\Gateway\Dto\AgentRow;
use DropContext\SkillInstalationStatus;
use DropContext\ValueObject\AgentValueObject;
use Symfony\Component\Filesystem\Filesystem;

class AgentService {

  public function __construct(
    protected AgentFileWriter $agentFileWriter,
    protected Filesystem $filesystem,
    protected AppStoreService $appStoreService,
    protected Ui $ui,
  ) {}

  public function isAgentInstalled(string $agentName): SkillInstalationStatus {
    if (!$this->getAgent($agentName) && !$this->projectAgentsMdExists()) {
      return SkillInstalationStatus::NOT_INSTALLED;
    }
    if ($this->appStoreService->agents->isInstalled($agentName)) {
      return SkillInstalationStatus::INTERNAL;
    }
    return SkillInstalationStatus::EXTERNAL;
  }

  public function installAgent(AgentRow $agent): AgentValueObject {
    $agentDir = $this->agentFileWriter->write($agent, $this->appStoreService->getAppAgentsDir());
    $agentName = $agent->installKey();

    $runnableProject = $this->appStoreService->getRunnableProject();
    if ($runnableProject === null) {
      throw new \RuntimeException('Run this command inside a project with a composer.json.');
    }
    $projectDir = $runnableProject->path;
    $target = $projectDir . DIRECTORY_SEPARATOR . 'AGENTS.md';
    $source = $agentDir . DIRECTORY_SEPARATOR . 'AGENTS.md';

    if ($this->filesystem->exists($target) || is_link($target)) {
      $this->filesystem->remove($target);
    }
    $this->filesystem->symlink($source, $target);

    return new AgentValueObject(
      name: $agentName,
      path: $target,
      version: $agent->version ?? '0.1',
      uuid: $agent->uuid !== '' ? $agent->uuid : null,
    );
  }

  public function removeAgent(string $agentName): ?string {
    $runnableProject = $this->appStoreService->getRunnableProject();
    if ($runnableProject === null) {
      throw new \RuntimeException('Run this command inside a project with a composer.json.');
    }
    $target = $runnableProject->path . DIRECTORY_SEPARATOR . 'AGENTS.md';
    if (!$this->filesystem->exists($target) && !is_link($target)) {
      return null;
    }

    // Only remove when the symlink points at this agent's cache, or when the
    // registry claims this CLI installed it (broken symlink / moved cache).
    if (is_link($target)) {
      $link = readlink($target);
      $expected = $this->getAgent($agentName);
      if ($expected !== null && $link !== false) {
        $expectedFile = $expected . DIRECTORY_SEPARATOR . 'AGENTS.md';
        if (realpath($link) !== realpath($expectedFile) && $link !== $expectedFile) {
          // Different agent / hand-written file via unexpected link — still
          // remove when the registry says we own this name.
          if (!$this->appStoreService->agents->isInstalled($agentName)) {
            return null;
          }
        }
      }
    }

    $this->filesystem->remove($target);
    return $target;
  }

  /**
   * @return array<int, array{name: string, path: string, version: string, uuid: ?string}>
   */
  public function listInstalledAgents(): array {
    $agents = [];
    foreach ($this->appStoreService->agents->all() as $entry) {
      $name = (string) ($entry->name ?? '');
      if ($name === '') {
        continue;
      }
      $agents[] = [
        'name' => $name,
        'path' => (string) ($entry->path ?? ''),
        'version' => (string) ($entry->version ?? '0.1'),
        'uuid' => isset($entry->uuid) ? (string) $entry->uuid : null,
      ];
    }
    return $agents;
  }

  public function getAgent(string $agentName): ?string {
    $candidates = array_unique([
      $agentName,
      AgentFileWriter::cacheDirName($agentName),
    ]);
    foreach ($candidates as $dirName) {
      $dir = implode(DIRECTORY_SEPARATOR, [
        $this->appStoreService->getAppAgentsDir(),
        $dirName,
      ]);
      if ($this->filesystem->exists($dir)) {
        return $dir;
      }
    }
    return null;
  }

  private function projectAgentsMdExists(): bool {
    $runnableProject = $this->appStoreService->getRunnableProject();
    if ($runnableProject === null) {
      return false;
    }
    $target = $runnableProject->path . DIRECTORY_SEPARATOR . 'AGENTS.md';
    return $this->filesystem->exists($target) || is_link($target);
  }

}
