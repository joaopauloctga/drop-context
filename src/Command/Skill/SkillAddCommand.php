<?php

namespace DrupalContext\Command\Skill;

use DrupalContext\Command\DrupalContextBaseCommand;
use DrupalContext\Service\AppInstaller;
use DrupalContext\Service\AppStoreService;
use DrupalContext\Service\CodeAgentsService;
use DrupalContext\Service\SkillService;
use DrupalContext\SkillInstalationStatus;
use GuzzleHttp\Exception\BadResponseException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;

#[AsCommand(name: 'skill:add', description: 'Download and install a skill', aliases: ['s:add'])]
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

    $status = $this->skillService->isSkillInstalled($skill);
    if ($status !== SkillInstalationStatus::NOT_INSTALLED) {
      $message = $status === SkillInstalationStatus::INTERNAL
        ? "The $skill is already installed."
        : "The $skill already exists and wasn't installed by us.";
      warning($message);
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

    try {
      // Install the skill at project level.
      $skillInstalled = $this->skillService->installSkill($skill, $codeAgents, $source);
    }
    catch (BadResponseException $e) {
      $status = $e->getResponse()->getStatusCode();
      if ($status === 404) {
        error("Skill $skill not found.");
        info('Run `drupal-context skill:list` to see available skills.');
        return Command::FAILURE;
      }
    }

    // Save the skill in the app store.
    $this->appStoreService
      ->skills
      ->attach($skillInstalled);

    return Command::SUCCESS;
  }
}
