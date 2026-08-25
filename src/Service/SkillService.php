<?php

declare(strict_types=1);

namespace DropContext\Service;

use DropContext\Console\Ui;
use DropContext\Gateway\Dto\SkillRow;
use DropContext\SkillInstalationStatus;
use DropContext\Source\SourceManager;
use DropContext\ValueObject\SkillValueObject;
use Symfony\Component\Filesystem\Filesystem;

class SkillService {

  public function __construct(
    protected SourceManager $sourceManager,
    protected Filesystem $filesystem,
    protected AppStoreService $appStoreService,
    protected Ui $ui,
  )
  {}

  public function isSkillInstalled(string $skillName): SkillInstalationStatus {
    // Check if the skill is installed looking by the file system.
    if (!$this->getSkill($skillName)) {
      return SkillInstalationStatus::NOT_INSTALLED;
    }
    // Check if installed by us.
    if ($this->appStoreService->skills->isInstalled($skillName)) {
      return SkillInstalationStatus::INTERNAL;
    }
    return SkillInstalationStatus::EXTERNAL;
  }

  public function installSkill(SkillRow $skill, array $codeAgents = [], string $source = "drop-context"): SkillValueObject {
    // Always (re)write the cached copy from the already-resolved row, so an
    // override/--force reinstall actually refreshes the content instead of
    // re-symlinking a stale cache dir.
    $skillDir = $this->downloadSkill($skill, $source);
    // The canonical, sanitized directory name — never the raw server title.
    $skillName = basename($skillDir);

    $runnableProject = $this->appStoreService->getRunnableProject();
    if ($runnableProject === null) {
      throw new \RuntimeException('Run this command inside a project with a composer.json.');
    }
    $projectDir = $runnableProject->path;

    $skillInstalled = new SkillValueObject(
      name: $skillName,
      agents: [],
      path: $projectDir,
      version: $skill->version ?? '0.1',
    );

    $failures = [];
    foreach ($codeAgents as $codeAgent) {
      $agentSkillsDir = implode(DIRECTORY_SEPARATOR, [$projectDir, $codeAgent, "skills"]);
      $target = $agentSkillsDir . DIRECTORY_SEPARATOR . $skillName;
      try {
        $this->filesystem->mkdir($agentSkillsDir);
        if ($this->filesystem->exists($target)) {
          $this->filesystem->remove($target);
        }
        $this->filesystem->symlink($skillDir, $target);
        $skillInstalled->agents[] = [$codeAgent, $target];
      }
      catch (\Exception $e) {
        $failures[] = "{$codeAgent}: {$e->getMessage()}";
        $this->ui->warning("Error installing skill {$skillName} for code agent {$codeAgent}: {$e->getMessage()}");
      }
    }

    if ($codeAgents !== [] && $skillInstalled->agents === []) {
      throw new \RuntimeException(
        "Skill {$skillName} could not be installed for any code agent: " . implode('; ', $failures),
      );
    }

    return $skillInstalled;
  }

  /**
   * Removes a skill from the given code agent dirs of the current project.
   *
   * @return array<int, array{0: string, 1: string}>
   *   The [codeAgent, removedPath] pairs that actually existed.
   */
  public function removeSkill(string $skillName, array $codeAgents): array {
    $runnableProject = $this->appStoreService->getRunnableProject();
    if ($runnableProject === null) {
      throw new \RuntimeException('Run this command inside a project with a composer.json.');
    }
    $projectDir = $runnableProject->path;

    $removed = [];
    foreach ($codeAgents as $codeAgent) {
      $target = implode(DIRECTORY_SEPARATOR, [$projectDir, $codeAgent, "skills", $skillName]);
      if (!$this->filesystem->exists($target) && !is_link($target)) {
        continue;
      }
      $this->filesystem->remove($target);
      $removed[] = [$codeAgent, $target];
    }
    return $removed;
  }

  /**
   * The project paths a skill occupies, keyed by code agent dir.
   *
   * @return array<string, string>
   */
  public function findInstalledPaths(string $skillName, array $codeAgents): array {
    $runnableProject = $this->appStoreService->getRunnableProject();
    if ($runnableProject === null) {
      return [];
    }

    $paths = [];
    foreach ($codeAgents as $codeAgent) {
      $target = implode(DIRECTORY_SEPARATOR, [$runnableProject->path, $codeAgent, "skills", $skillName]);
      // is_link() catches broken symlinks Filesystem::exists() reports absent.
      if ($this->filesystem->exists($target) || is_link($target)) {
        $paths[$codeAgent] = $target;
      }
    }
    return $paths;
  }

  /**
   * The skills this CLI recorded as installed in the current project, paired
   * with where they currently live on disk.
   *
   * Sweeps every *known* code agent dir rather than only the ones present in
   * the project: a dir may have been deleted since install, and its skill
   * entry still has to be findable so a sweep can clean it up.
   *
   * @return array<int, array{name: string, version: string, changed: int|null, paths: array<string, string>}>
   */
  public function listInstalledSkills(): array {
    $skills = [];
    foreach ($this->appStoreService->skills->all() as $entry) {
      $name = (string) ($entry->name ?? '');
      if ($name === '') {
        continue;
      }
      $skills[] = [
        'name' => $name,
        'version' => (string) ($entry->version ?? ''),
        'changed' => isset($entry->changed) ? (int) $entry->changed : null,
        'paths' => $this->findInstalledPaths($name, CodeAgentsService::KNOWN_CODE_AGENTS),
      ];
    }
    return $skills;
  }

  public function getSkill(string $skillName): ?string {
    $skills = $this->appStoreService->getAppSkillsDir();
    $skillDir = implode(DIRECTORY_SEPARATOR, [$skills, $skillName]);
    return $this->filesystem->exists($skillDir) ? $skillDir : null;
  }

  public function downloadSkill(SkillRow $skill, string $source = "drop-context"): string {
    $sourceHandler = $this->sourceManager->getSourceHandler($source);
    return $sourceHandler->downloadSkill($skill);
  }

  public function listSkills(?string $module = null, string $source = "drop-context"): array {
    $handler = $this->sourceManager->getSourceHandler($source);
    return $handler->getSkillList($module);
  }

}
