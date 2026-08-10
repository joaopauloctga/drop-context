<?php

namespace DrupalContext\Command\Skill;

use DrupalContext\Command\DrupalContextBaseCommand;
use DrupalContext\Service\AppInstaller;
use DrupalContext\Service\AppStoreService;
use DrupalContext\Service\CodeAgentsService;
use DrupalContext\Service\SkillService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'skill:source-list', description: 'List source skills available', aliases: ['s:s-list'])]
class SkillListCommand extends DrupalContextBaseCommand {

  public function __construct(
    AppInstaller $appInstaller,
    protected SkillService $skillService,
    protected CodeAgentsService $codeAgentsService,
    protected AppStoreService $appStoreService,
  ) {
    parent::__construct($appInstaller);
  }

  public function configure(): void
  {
    parent::configure();
    $this->addOption(
      name: 'module',
      shortcut: 'm',
      mode: InputOption::VALUE_OPTIONAL,
      description: 'Module name to filter skills by',
      suggestedValues: [
        'flag',
        'paragraph'
      ]
    );
  }

  public function execute(InputInterface $input, OutputInterface $output): int {
    $output->writeln(json_encode($this->skillService->listSkills($input->getOption('module') ?? '')));
    return Command::SUCCESS;
  }

}