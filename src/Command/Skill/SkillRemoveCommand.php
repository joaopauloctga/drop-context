<?php

declare(strict_types=1);

namespace DropContext\Command\Skill;

use DropContext\Command\DropContextBaseCommand;
use DropContext\Console\Ui;
use DropContext\Service\AppInstaller;
use DropContext\Service\AppStoreService;
use DropContext\Service\CodeAgentsService;
use DropContext\Service\SkillFileWriter;
use DropContext\Service\SkillService;
use DropContext\SkillInstalationStatus;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(name: 'skill:remove', description: 'Remove an installed skill from the project', aliases: ['s:rm'])]
class SkillRemoveCommand extends DropContextBaseCommand {

  public function __construct(
    AppInstaller $appInstaller,
    Ui $ui,
    protected SkillService $skillService,
    protected CodeAgentsService $codeAgentsService,
    protected AppStoreService $appStoreService,
    protected Filesystem $filesystem,
  ) {
    parent::__construct($appInstaller, $ui);
  }

  public function configure(): void {
    parent::configure();
    $this->addArgument(
      name: 'skill',
      mode: InputArgument::REQUIRED,
      description: 'Skill name (directory name under the agent skills dirs)',
    );
    $this->addOption(
      name: 'force',
      mode: InputOption::VALUE_NONE,
      description: 'Remove without confirmation. Required for skills not installed by this CLI in a non-interactive run.',
    );
    $this->addOption(
      name: 'purge',
      mode: InputOption::VALUE_NONE,
      description: 'Also delete the cached copy in ~/.drop-context/skills.',
    );
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $force = (bool) $input->getOption('force');
    $purge = (bool) $input->getOption('purge');

    try {
      $skillName = SkillFileWriter::sanitizeTitle($input->getArgument('skill'));
    }
    catch (\RuntimeException $e) {
      $this->ui->error($e->getMessage());
      return Command::FAILURE;
    }

    $this->ui->intro("Removing skill '{$skillName}'");

    $projectAgents = $this->codeAgentsService->readCurrentCodeAgents();
    $installedPaths = $this->skillService->findInstalledPaths($skillName, $projectAgents);
    $status = $this->skillService->isSkillInstalled($skillName);
    $cacheDir = $this->skillService->getSkill($skillName);

    if ($installedPaths === [] && $status === SkillInstalationStatus::NOT_INSTALLED) {
      $this->ui->error("Skill '{$skillName}' is not installed in this project.");
      return Command::FAILURE;
    }

    // A skill this CLI didn't install (or lost track of) may hold real files,
    // not our symlinks — make the user own that decision.
    if ($status !== SkillInstalationStatus::INTERNAL) {
      $this->ui->warning("We didn't detect '{$skillName}' as installed by this CLI — it may have been installed manually or by another tool, and its files will be permanently deleted.");
      foreach ($installedPaths as $codeAgent => $path) {
        $this->ui->note("{$codeAgent}: {$path}");
      }

      if (!$force) {
        if (!$this->ui->isInteractive()) {
          $this->ui->error('Pass --force to remove it in a non-interactive run.');
          return Command::FAILURE;
        }
        if (!$this->ui->confirm('Do you really want to remove it?', false)) {
          $this->ui->outro('Nothing removed.');
          return Command::SUCCESS;
        }
      }
    }

    try {
      $removed = $this->skillService->removeSkill($skillName, $projectAgents);
      $this->appStoreService->skills->detach($skillName);
      if ($purge && $cacheDir !== null) {
        $this->filesystem->remove($cacheDir);
      }
    }
    catch (\RuntimeException $e) {
      $this->ui->error($e->getMessage());
      return Command::FAILURE;
    }

    $removedAgents = array_column($removed, 0);

    if ($this->ui->isJson()) {
      $this->ui->json([
        'removed' => $skillName,
        'agents' => $removedAgents,
        'purged' => $purge && $cacheDir !== null,
      ]);
      return Command::SUCCESS;
    }

    $summary = $removedAgents === []
      ? "Skill '{$skillName}' removed from the app registry (no files found in the project)."
      : "Skill '{$skillName}' removed from: " . implode(', ', $removedAgents);
    if ($purge && $cacheDir !== null) {
      $summary .= ' (cache purged)';
    }
    $this->ui->outro($summary);

    return Command::SUCCESS;
  }

}
