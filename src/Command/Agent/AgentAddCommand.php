<?php

declare(strict_types=1);

namespace DropContext\Command\Agent;

use DropContext\Command\DropContextBaseCommand;
use DropContext\Console\Ui;
use DropContext\Gateway\AgentQueryService;
use DropContext\Gateway\Exception\GatewayException;
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

#[AsCommand(name: 'agent:add', description: 'Download and install an agent as AGENTS.md', aliases: ['a:add'])]
class AgentAddCommand extends DropContextBaseCommand {

  use ResolvesAgentTrait;

  public function __construct(
    AppInstaller $appInstaller,
    Ui $ui,
    protected AgentService $agentService,
    protected AppStoreService $appStoreService,
    protected AgentQueryService $agentQueryService,
  ) {
    parent::__construct($appInstaller, $ui);
  }

  public function configure(): void {
    parent::configure();
    $this->addArgument(
      name: 'agent',
      mode: InputArgument::REQUIRED,
      description: 'Agent machine name (e.g. drop-view-developer or user:slug) or UUID',
    );
    $this->addOption(
      name: 'force',
      mode: InputOption::VALUE_NONE,
      description: 'Reinstall without confirmation. Required to override an existing AGENTS.md in a non-interactive run.',
    );
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $agentArg = $input->getArgument('agent');
    $force = (bool) $input->getOption('force');

    $this->ui->intro("Installing agent '{$agentArg}'");

    try {
      $result = $this->ui->spin(
        fn () => $this->agentQueryService->findOne($agentArg),
        "Fetching agent '{$agentArg}'…",
      );
    }
    catch (GatewayException $e) {
      $this->ui->error($this->describeGatewayError($e));
      return Command::FAILURE;
    }

    $resolved = $this->resolveAgent($agentArg, $result);
    if ($resolved === null) {
      return Command::FAILURE;
    }

    try {
      $agentName = $resolved->installKey();
      AgentFileWriter::cacheDirName($agentName);
    }
    catch (\RuntimeException $e) {
      $this->ui->error($e->getMessage());
      return Command::FAILURE;
    }

    $status = $this->agentService->isAgentInstalled($agentName);
    if ($status !== SkillInstalationStatus::NOT_INSTALLED) {
      $message = $status === SkillInstalationStatus::INTERNAL
        ? "The {$agentName} agent is already installed."
        : "An AGENTS.md already exists and wasn't installed by us (or another agent is installed).";
      $this->ui->warning($message);

      if (!$force) {
        if (!$this->ui->isInteractive()) {
          $this->ui->error('Pass --force to reinstall in a non-interactive run.');
          return Command::FAILURE;
        }
        if (!$this->ui->confirm('Do you want to override?')) {
          return Command::SUCCESS;
        }
      }
    }

    try {
      $agentInstalled = $this->ui->spin(
        fn () => $this->agentService->installAgent($resolved),
        "Installing '{$resolved->title}'…",
      );
    }
    catch (GatewayException $e) {
      $this->ui->error($this->describeGatewayError($e));
      return Command::FAILURE;
    }
    catch (\RuntimeException $e) {
      $this->ui->error($e->getMessage());
      return Command::FAILURE;
    }

    $this->appStoreService->agents->attach($agentInstalled);

    if ($this->ui->isJson()) {
      $this->ui->json(['installed' => $agentInstalled->name, 'path' => $agentInstalled->path]);
      return Command::SUCCESS;
    }

    $this->ui->outro("Agent '{$agentInstalled->name}' installed at {$agentInstalled->path}");

    return Command::SUCCESS;
  }

}
