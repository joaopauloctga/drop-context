<?php

namespace DrupalContext\Command\CodeAgents;

use DrupalContext\Command\DrupalContextBaseCommand;
use DrupalContext\Service\AppInstaller;
use DrupalContext\Service\AppStoreService;
use DrupalContext\Service\CodeAgentsService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'code-agents:add', aliases: ['ca:add'])]
class AddCodeAgent extends DrupalContextBaseCommand {

  public function __construct(
    AppInstaller $appInstaller,
    protected CodeAgentsService $codeAgentsService,
    protected AppStoreService $appStoreService,
  ) {
    parent::__construct($appInstaller);
  }

  public function configure(): void {
    parent::configure();
    $this->addArgument(
      name: 'code-agent',
      mode: InputArgument::REQUIRED,
      description: 'Code agent directory name to add (e.g. .claude or .agents).',
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

    if (in_array($name, $app->codeAgents, true)) {
      $output->writeln("Code agent <info>$name</info> is already in the list.");
      return Command::SUCCESS;
    }

    $app->codeAgents[] = $name;
    $this->appStoreService->writeAppConfig($app);
    $output->writeln("Added code agent <info>$name</info>.");
    return Command::SUCCESS;
  }

}