<?php

declare(strict_types=1);

namespace DropContext\Command\Skill;

use DropContext\Command\DropContextBaseCommand;
use DropContext\Console\Ui;
use DropContext\Service\AppInstaller;
use DropContext\Service\AppStoreService;
use DropContext\Service\SkillService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(
  name: 'skill:remove-all',
  description: 'Remove every skill this CLI installed in the project',
  aliases: ['s:rm-all'],
)]
class SkillRemoveAllCommand extends DropContextBaseCommand {

  public function __construct(
    AppInstaller $appInstaller,
    Ui $ui,
    protected SkillService $skillService,
    protected AppStoreService $appStoreService,
    protected Filesystem $filesystem,
  ) {
    parent::__construct($appInstaller, $ui);
  }

  public function configure(): void {
    parent::configure();
    $this->addOption(
      name: 'force',
      mode: InputOption::VALUE_NONE,
      description: 'Remove without confirmation. Required in a non-interactive run.',
    );
    $this->addOption(
      name: 'purge',
      mode: InputOption::VALUE_NONE,
      description: 'Also delete the cached copies in ~/.drop-context/skills.',
    );
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $force = (bool) $input->getOption('force');
    $purge = (bool) $input->getOption('purge');

    if ($this->appStoreService->getRunnableProject() === null) {
      $this->ui->error('Run this command inside a project with a composer.json.');
      return Command::FAILURE;
    }

    $this->ui->intro('Removing every skill installed by this CLI');

    // Deliberately scoped to the app registry: a skill this CLI never
    // installed holds real files, not our symlinks, and is skill:remove's
    // decision to make — never a sweep's.
    $installed = $this->skillService->listInstalledSkills();

    if ($installed === []) {
      if ($this->ui->isJson()) {
        $this->ui->json(['removed' => [], 'purged' => false]);
        return Command::SUCCESS;
      }
      $this->ui->note('No skills installed by this CLI in this project.');
      return Command::SUCCESS;
    }

    if (!$force) {
      if (!$this->ui->isJson()) {
        $this->ui->table(['Skill', 'Version', 'Agents'], array_map(
          static fn (array $skill): array => [
            $skill['name'],
            $skill['version'],
            $skill['paths'] === [] ? '(no files found)' : implode(', ', array_keys($skill['paths'])),
          ],
          $installed,
        ));
      }

      if (!$this->ui->isInteractive()) {
        $this->ui->error('Pass --force to remove them in a non-interactive run.');
        return Command::FAILURE;
      }
      $label = 'Remove ' . count($installed) . ' skill(s) from this project?';
      if (!$this->ui->confirm($purge ? $label . ' Their cached copies will be deleted too.' : $label, false)) {
        $this->ui->outro('Nothing removed.');
        return Command::SUCCESS;
      }
    }

    $removed = [];
    $failures = [];
    foreach ($installed as $skill) {
      $name = $skill['name'];
      // Resolved before removal — removeSkill() deletes the very paths that
      // getSkill() would then no longer be able to report.
      $cacheDir = $purge ? $this->skillService->getSkill($name) : null;

      try {
        $agents = array_column($this->skillService->removeSkill($name, array_keys($skill['paths'])), 0);
        // Detached even when no files were found: a registry entry whose
        // files are already gone is exactly what this sweep should clear.
        $this->appStoreService->skills->detach($name);
        if ($cacheDir !== null) {
          $this->filesystem->remove($cacheDir);
        }
        $removed[] = ['skill' => $name, 'agents' => $agents, 'purged' => $cacheDir !== null];
      }
      catch (\RuntimeException|\Exception $e) {
        $failures[] = "{$name}: {$e->getMessage()}";
        $this->ui->warning("Could not remove '{$name}': {$e->getMessage()}");
      }
    }

    if ($this->ui->isJson()) {
      $this->ui->json(['removed' => $removed, 'failed' => $failures]);
      return $failures === [] ? Command::SUCCESS : Command::FAILURE;
    }

    if ($removed === []) {
      $this->ui->error('No skill could be removed.');
      return Command::FAILURE;
    }

    $summary = count($removed) . ' skill(s) removed';
    if ($purge) {
      $summary .= ' (cache purged)';
    }
    if ($failures !== []) {
      $summary .= ', ' . count($failures) . ' failed';
    }
    $this->ui->outro($summary . '.');

    return $failures === [] ? Command::SUCCESS : Command::FAILURE;
  }

}
