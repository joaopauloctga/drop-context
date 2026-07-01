<?php

namespace DrupalContext\Command\Skill;

use DrupalContext\Command\DrupalContextBaseCommand;
use DrupalContext\Service\AppInstaller;
use DrupalContext\Service\AppStoreService;
use DrupalContext\Service\CodeAgentsService;
use DrupalContext\Service\SkillService;
use DrupalContext\ValueObject\SkillValueObject;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\warning;

#[AsCommand(name: 'skill:add', description: 'Download and install a skill')]
class SkillAddCommand extends DrupalContextBaseCommand {

  public function __construct(
    AppInstaller $appInstaller,
    protected SkillService $skillService,
    protected CodeAgentsService $codeAgentsService,
    protected AppStoreService $appStoreService,
  ) {
    parent::__construct($appInstaller);
  }

  public function configure(): void {
    parent::configure();
    $this->addArgument(
      name: 'skill',
      mode: InputArgument::REQUIRED,
      description: 'Skill name',
      suggestedValues: [
        'boost-flag',
        'boost-{module_machine_name_you_want}',
        'some_skill_name_from_skill_sh'
      ]
    );
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $skill = $input->getArgument('skill');
    $source = $input->getOption('source');

    $app = $this->appStoreService->getAppConfigFile();

    if ($this->skillService->isSkillInstalled($skill)) {
      warning("This skill is already installed.");
      if (!confirm("Do you want to override?")) {
        return Command::SUCCESS;
      }
    }

    // Read installed code agents at the project level.
    $projectAgents = $this->codeAgentsService->readCurrentCodeAgents();

    // Get preferable code agents from the app config.
    $preferableCodeAgents = $app->codeAgents;
    $missingAgents = array_diff($preferableCodeAgents, $projectAgents);
    $codeAgents = array_unique(array_merge($projectAgents, $preferableCodeAgents));

    // Install missing code agents.
    if (!empty($missingAgents)) {
      $this->codeAgentsService->installCodeAgents($missingAgents);
    }

    // Install the skill at project level.
    $skillInstalled = $this->skillService->installSkill($skill, $codeAgents, $source);

    // Save the skill in the app store.
    $this->appStoreService
      ->skills
      ->attach($skillInstalled);

    return Command::SUCCESS;
  }
}
