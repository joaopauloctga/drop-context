<?php

declare(strict_types=1);

namespace DrupalContext\Command\Skill;

use DrupalContext\Command\DrupalContextBaseCommand;
use DrupalContext\Console\Ui;
use DrupalContext\Gateway\Exception\GatewayException;
use DrupalContext\Gateway\SkillQueryService;
use DrupalContext\Service\AppInstaller;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'skill:show', description: 'Show details for a skill', aliases: ['s:show'])]
class SkillShowCommand extends DrupalContextBaseCommand {

  use ResolvesSkillTrait;

  public function __construct(
    AppInstaller $appInstaller,
    Ui $ui,
    protected readonly SkillQueryService $skillQueryService,
  ) {
    parent::__construct($appInstaller, $ui);
  }

  protected function requiresAppSetup(): bool {
    return false;
  }

  public function configure(): void {
    parent::configure();
    $this->addArgument('skill', InputArgument::REQUIRED, 'Skill title or UUID');
    $this->addOption('content', null, InputOption::VALUE_NONE, 'Print the raw SKILL.md content instead of the summary table');
  }

  public function execute(InputInterface $input, OutputInterface $output): int {
    $nameOrUuid = $input->getArgument('skill');

    try {
      $result = $this->ui->spin(
        fn () => $this->skillQueryService->findOne($nameOrUuid),
        "Fetching skill '{$nameOrUuid}'…",
      );
    }
    catch (GatewayException $e) {
      $this->ui->error($this->describeGatewayError($e));
      return Command::FAILURE;
    }

    $skill = $this->resolveSkill($nameOrUuid, $result);
    if ($skill === null) {
      return Command::FAILURE;
    }

    if ($input->getOption('content')) {
      $this->ui->raw($skill->content ?? '');
      return Command::SUCCESS;
    }

    if ($this->ui->isJson()) {
      $this->ui->json($skill);
      return Command::SUCCESS;
    }

    try {
      $columns = $this->ui->spin(
        fn () => $this->skillQueryService->resolveModuleColumns([$skill]),
        'Resolving module…',
      );
    }
    catch (GatewayException $e) {
      $this->ui->error($this->describeGatewayError($e));
      return Command::FAILURE;
    }
    $col = $columns[$skill->uuid] ?? ['module' => '', 'release' => ''];

    $this->ui->table(['Field', 'Value'], [
      ['UUID', $skill->uuid],
      ['Type', $skill->type ?? ''],
      ['Module', $col['module']],
      ['Release', $col['release']],
      ['Tags', implode(', ', array_map(static fn ($tag) => $tag->name, $skill->tags))],
      ['Private', $skill->private ? 'Yes' : 'No'],
      ['Changed', $skill->changed ?? ''],
      ['Path', $skill->path ?? ''],
      ['Description', $skill->description ?? ''],
    ]);

    if ($skill->references !== []) {
      $this->ui->table(['Reference'], array_map(
        static fn ($reference) => [$reference->filename ?? $reference->uuid],
        $skill->references,
      ));
    }

    return Command::SUCCESS;
  }

}
