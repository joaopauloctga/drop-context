<?php

namespace DropContext\Command\CodeAgents;

use DropContext\Command\DropContextBaseCommand;
use DropContext\Console\Ui;
use DropContext\Service\AppInstaller;
use DropContext\Service\AppStoreService;
use DropContext\Service\CodeAgentsService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'code-agents:list', aliases: ['ca:ls'])]
class ListCodeAgents extends DropContextBaseCommand {

  public function __construct(
    AppInstaller $appInstaller,
    Ui $ui,
    protected CodeAgentsService $codeAgentsService,
    protected AppStoreService $appStoreService,
  ) {
    parent::__construct($appInstaller, $ui);
  }

  public function execute(InputInterface $input, OutputInterface $output): int {
    $app = $this->appStoreService->getAppConfigFile();

    if ($this->ui->isJson()) {
      $this->ui->json(['codeAgents' => $app->codeAgents]);
      return Command::SUCCESS;
    }

    if (empty($app->codeAgents)) {
      $this->ui->note('No code agents configured.');
      return Command::SUCCESS;
    }

    $this->ui->table(['Code agent'], array_map(fn($agent) => [$agent], $app->codeAgents));

    return Command::SUCCESS;
  }

}