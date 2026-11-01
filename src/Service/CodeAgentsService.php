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

  /**
   * Where a code agent reads project skills from, relative to the project.
   *
   * Codex reads repository skills from .agents/skills, not .codex/skills.
   */
  public static function skillsDir(string $codeAgent): string {
    return $codeAgent === '.codex' ? '.agents/skills' : $codeAgent . '/skills';
  }

  /**
   * Skills dirs earlier CLI versions installed into for a code agent.
   *
   * Still searched when finding or removing a skill, so older installs stay
   * manageable; a reinstall moves them to skillsDir().
   *
   * @return string[]
   */
  public static function legacySkillsDirs(string $codeAgent): array {
    return $codeAgent === '.codex' ? ['.codex/skills'] : [];
  }

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