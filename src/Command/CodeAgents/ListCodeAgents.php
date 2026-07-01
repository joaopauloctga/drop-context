<?php

namespace DrupalContext\Command\CodeAgents;

use DrupalContext\Command\DrupalContextBaseCommand;
use DrupalContext\Service\AppInstaller;
use DrupalContext\Service\AppStoreService;
use DrupalContext\Service\CodeAgentsService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'code-agents:list', aliases: ['ca:ls'])]
class ListCodeAgents extends DrupalContextBaseCommand {

  public function __construct(
    AppInstaller $appInstaller,
    protected CodeAgentsService $codeAgentsService,
    protected AppStoreService $appStoreService,
  ) {
    parent::__construct($appInstaller);
  }

  public function execute(InputInterface $input, OutputInterface $output): int {
    $app = $this->appStoreService->getAppConfigFile();

    if (empty($app->codeAgents)) {
      $output->writeln('No code agents configured.');
      return Command::SUCCESS;
    }

    foreach ($app->codeAgents as $agent) {
      $output->writeln($agent);
    }

    return Command::SUCCESS;
  }

}