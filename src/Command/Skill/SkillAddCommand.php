<?php

declare(strict_types=1);

namespace DrupalContext\Command\Skill;

use DrupalContext\Command\DrupalContextBaseCommand;
use DrupalContext\Console\Ui;
use DrupalContext\Gateway\Exception\GatewayException;
use DrupalContext\Gateway\SkillQueryService;
use DrupalContext\Service\AppInstaller;
use DrupalContext\Service\AppStoreService;
use DrupalContext\Service\CodeAgentsService;
use DrupalContext\Service\SkillFileWriter;
use DrupalContext\Service\SkillService;
use DrupalContext\SkillInstalationStatus;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'skill:add', description: 'Download and install a skill', aliases: ['s:add'])]
class SkillAddCommand extends DrupalContextBaseCommand {

  use ResolvesSkillTrait;

  public function __construct(
    AppInstaller $appInstaller,
    Ui $ui,
    protected SkillService $skillService,
    protected CodeAgentsService $codeAgentsService,
    protected AppStoreService $appStoreService,
    protected SkillQueryService $skillQueryService,
  ) {
    parent::__construct($appInstaller, $ui);
  }

  public function configure(): void {
    parent::configure();
    $this->addArgument(
      name: 'skill',
      mode: InputArgument::REQUIRED,
      description: 'Skill title or UUID',
    );
    $this->addOption(
      name: 'force',
      mode: InputOption::VALUE_NONE,
      description: 'Reinstall without confirmation. Required to override an existing skill in a non-interactive run.',
    );
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $skill = $input->getArgument('skill');
    $source = $input->getOption('source');
    $force = (bool) $input->getOption('force');

    $this->ui->intro("Installing skill '{$skill}'");

    $app = $this->appStoreService->getAppConfigFile();

    try {
      $result = $this->ui->spin(
        fn () => $this->skillQueryService->findOne($skill),
        "Fetching skill '{$skill}'…",
      );
    }
    catch (GatewayException $e) {
      $this->ui->error($this->describeGatewayError($e));
      return Command::FAILURE;
    }

    $resolved = $this->resolveSkill($skill, $result);
    if ($resolved === null) {
      return Command::FAILURE;
    }

    // The already-installed check must use the same canonical name the
    // install writes, or a UUID/suggestion lookup would bypass the gate.
    try {
      $skillName = SkillFileWriter::sanitizeTitle($resolved->title);
    }
    catch (\RuntimeException $e) {
      $this->ui->error($e->getMessage());
      return Command::FAILURE;
    }

    $status = $this->skillService->isSkillInstalled($skillName);
    if ($status !== SkillInstalationStatus::NOT_INSTALLED) {
      $message = $status === SkillInstalationStatus::INTERNAL
        ? "The {$skillName} is already installed."
        : "The {$skillName} already exists and wasn't installed by us.";
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

    // Read installed code agents at the project level.
    $projectAgents = $this->codeAgentsService->readCurrentCodeAgents();

    // Get preferable code agents from the app config.
    $preferableCodeAgents = $app->codeAgents;
    $missingAgents = array_diff($preferableCodeAgents, $projectAgents);
    $codeAgents = array_unique(array_merge($projectAgents, $preferableCodeAgents));

    try {
      // Install missing code agents.
      if (!empty($missingAgents)) {
        $this->codeAgentsService->installCodeAgents($missingAgents);
      }

      $skillInstalled = $this->ui->spin(
        fn () => $this->skillService->installSkill($resolved, $codeAgents, $source),
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

    // Save the skill in the app store — success path only.
    $this->appStoreService->skills->attach($skillInstalled);

    if ($this->ui->isJson()) {
      $this->ui->json(['installed' => $skillInstalled->name, 'agents' => array_column($skillInstalled->agents, 0)]);
      return Command::SUCCESS;
    }

    $this->ui->outro("Skill '{$skillInstalled->name}' installed for: " . implode(', ', array_column($skillInstalled->agents, 0)));

    return Command::SUCCESS;
  }

}
