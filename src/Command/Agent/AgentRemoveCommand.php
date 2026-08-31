<?php

declare(strict_types=1);

namespace DropContext\Command\Agent;

use DropContext\Command\DropContextBaseCommand;
use DropContext\Console\Ui;
use DropContext\Service\AgentFileWriter;
use DropContext\Service\AgentService;
use DropContext\Service\AppInstaller;
use DropContext\Service\AppStoreService;
use DropContext\SkillInstalationStatus;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(name: 'agent:remove', description: 'Remove an installed agent from the project', aliases: ['a:rm'])]
class AgentRemoveCommand extends DropContextBaseCommand {

  public function __construct(
    AppInstaller $appInstaller,
    Ui $ui,
    protected AgentService $agentService,
    protected AppStoreService $appStoreService,
    protected Filesystem $filesystem,
  ) {
    parent::__construct($appInstaller, $ui);
  }

  public function configure(): void {
    parent::configure();
    $this->addArgument(
      name: 'agent',
      mode: InputArgument::REQUIRED,
      description: 'Agent name (directory name under ~/.drop-context/agents)',
    );
    $this->addOption(
      name: 'force',
      mode: InputOption::VALUE_NONE,
      description: 'Remove without confirmation. Required for agents not installed by this CLI in a non-interactive run.',
    );
    $this->addOption(
      name: 'purge',
      mode: InputOption::VALUE_NONE,
      description: 'Also delete the cached copy in ~/.drop-context/agents.',
    );
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $force = (bool) $input->getOption('force');
    $purge = (bool) $input->getOption('purge');

    try {
      $agentName = AgentFileWriter::sanitizeTitle($input->getArgument('agent'));
    }
    catch (\RuntimeException $e) {
      $this->ui->error($e->getMessage());
      return Command::FAILURE;
    }

    $this->ui->intro("Removing agent '{$agentName}'");

    $status = $this->agentService->isAgentInstalled($agentName);
    $cacheDir = $this->agentService->getAgent($agentName);

    if ($status === SkillInstalationStatus::NOT_INSTALLED && $cacheDir === null) {
      $this->ui->error("Agent '{$agentName}' is not installed in this project.");
      return Command::FAILURE;
    }

    if ($status !== SkillInstalationStatus::INTERNAL) {
      $this->ui->warning("We didn't detect '{$agentName}' as installed by this CLI — AGENTS.md may have been installed manually.");

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
      $removed = $this->agentService->removeAgent($agentName);
      if ($this->appStoreService->agents->isInstalled($agentName)) {
        $this->appStoreService->agents->detach($agentName);
      }
      if ($purge && $cacheDir !== null) {
        $this->filesystem->remove($cacheDir);
      }
    }
    catch (\RuntimeException $e) {
      $this->ui->error($e->getMessage());
      return Command::FAILURE;
    }

    if ($this->ui->isJson()) {
      $this->ui->json([
        'removed' => $agentName,
        'path' => $removed,
        'purged' => $purge && $cacheDir !== null,
      ]);
      return Command::SUCCESS;
    }

    $summary = $removed === null
      ? "Agent '{$agentName}' removed from the app registry (no AGENTS.md found in the project)."
      : "Agent '{$agentName}' removed from {$removed}";
    if ($purge && $cacheDir !== null) {
      $summary .= ' (cache purged)';
    }
    $this->ui->outro($summary);

    return Command::SUCCESS;
  }

}
