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

#[AsCommand(name: 'code-agents:set-preferable', aliases: ['ca:set'])]
class SetPreferableCodeAgents extends DropContextBaseCommand {

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
      name: 'code-agents',
      mode: InputArgument::REQUIRED,
      description: 'Set preferable code agents, used by skills when they are not installed.',
      suggestedValues: [
        '.agents',
        '.claude'
      ]
    );
  }

  public function execute(InputInterface $input, OutputInterface $output): int {
    $codeAgents = explode(',', $input->getArgument('code-agents'));
    $app = $this->appStoreService->getAppConfigFile();
    $app->codeAgents = array_map(function ($name) {
      if (!str_starts_with($name, '.')) {
        $name = "." . $name;
      }
      return trim($name);
    }, $codeAgents);
    $this->appStoreService->writeAppConfig($app);
    $this->ui->info('Preferable code agents set to: ' . implode(', ', $app->codeAgents));
    return Command::SUCCESS;
  }

}