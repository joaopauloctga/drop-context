<?php

namespace DrupalContext\Service;

use DrupalContext\SkillInstalationStatus;
use DrupalContext\Source\SourceManager;
use DrupalContext\ValueObject\SkillValueObject;
use Symfony\Component\Filesystem\Filesystem;
use function Laravel\Prompts\warning;


class SkillService {

  public function __construct(
    protected SourceManager $sourceManager,
    protected Filesystem $filesystem,
    protected AppStoreService $appStoreService,
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

  public function installSkill(string $skillName, array $codeAgents = [], string $source = "drupal-context"): SkillValueObject {
    $skillDir = $this->getSkill($skillName);
    if (!$skillDir) {
      $skillDir = $this->downloadSkill($skillName, $source);
    }

    $projectDir = $this->appStoreService->getRunnableProject()->path;

    $skillInstalled = new SkillValueObject(
      name: $skillName,
      agents: [],
      path: $projectDir,
      version: "1.0.0",
    );

    foreach ($codeAgents as $codeAgent) {
      $target = implode(DIRECTORY_SEPARATOR, [$projectDir, $codeAgent, "skills", $skillName]);
      try {
        if ($this->filesystem->exists($target)) {
          $this->filesystem->remove($target);
        }
        $this->filesystem->symlink($skillDir,  $target);
        $skillInstalled->agents[] = [$codeAgent, $target];
      }
      catch (\Exception $e) {
        warning("Error installing skill {$skillName} for code agent {$codeAgent}: {$e->getMessage()}");
      }
    }
    return $skillInstalled;
  }

  public function getSkill(string $skillName): ?string {
    $skills = $this->appStoreService->getAppSkillsDir();
    $skillDir = implode(DIRECTORY_SEPARATOR, [$skills, $skillName]);
    return $this->filesystem->exists($skillDir) ? $skillDir : null;
  }

  public function downloadSkill(string $skillName, string $source = "drupal-context"): bool {
    $sourceHandler = $this->sourceManager->getSourceHandler($source);
    return $sourceHandler->downloadSkill($skillName);
  }

}