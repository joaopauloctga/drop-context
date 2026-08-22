<?php

namespace DropContext\Command\CodeAgents;

use DropContext\Command\DropContextBaseCommand;
use DropContext\Console\Ui;
use DropContext\Service\AppInstaller;
use DropContext\Service\AppStoreService;
use DropContext\Service\CodeAgentsService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'code-agents:remove', aliases: ['ca:rm'])]
class RemoveCodeAgent extends DropContextBaseCommand {

  public function __construct(
    AppInstaller $appInstaller,
    Ui $ui,
    protected CodeAgentsService $codeAgentsService,
    protected AppStoreService $appStoreService,
  ) {
    parent::__construct($appInstaller, $ui);
  }

  public function configure(): void {
    parent::configure();
    $this->addArgument(
      name: 'code-agent',
      mode: InputArgument::REQUIRED,
      description: 'Code agent directory name to remove (e.g. .claude or .agents).',
      suggestedValues: ['.agents', '.claude']
    );
  }

  public function execute(InputInterface $input, OutputInterface $output): int {
    $name = $input->getArgument('code-agent');
    if (!str_starts_with($name, '.')) {
      $name = '.' . $name;
    }
    $name = trim($name);

    $app = $this->appStoreService->getAppConfigFile();

    $filtered = array_values(array_filter($app->codeAgents, fn($a) => $a !== $name));

    if (count($filtered) === count($app->codeAgents)) {
      $this->ui->error("Code agent $name not found in the list.");
      return Command::FAILURE;
    }

    $app->codeAgents = $filtered;
    $this->appStoreService->writeAppConfig($app);
    $this->ui->info("Removed code agent $name.");
    return Command::SUCCESS;
  }

}