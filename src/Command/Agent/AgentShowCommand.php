<?php

declare(strict_types=1);

namespace DropContext\Command\Agent;

use DropContext\Command\DropContextBaseCommand;
use DropContext\Console\Ui;
use DropContext\Gateway\AgentQueryService;
use DropContext\Gateway\Exception\GatewayException;
use DropContext\Service\AppInstaller;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'agent:show', description: 'Show details for an agent', aliases: ['a:show'])]
class AgentShowCommand extends DropContextBaseCommand {

  use ResolvesAgentTrait;

  public function __construct(
    AppInstaller $appInstaller,
    Ui $ui,
    protected readonly AgentQueryService $agentQueryService,
  ) {
    parent::__construct($appInstaller, $ui);
  }

  protected function requiresAppSetup(): bool {
    return false;
  }

  public function configure(): void {
    parent::configure();
    $this->addArgument('agent', InputArgument::REQUIRED, 'Agent machine name (e.g. drop-view-developer or user:slug) or UUID');
    $this->addOption('content', null, InputOption::VALUE_NONE, 'Print the raw AGENTS.md content');
  }

  public function execute(InputInterface $input, OutputInterface $output): int {
    $nameOrUuid = $input->getArgument('agent');

    try {
      $result = $this->ui->spin(
        fn () => $this->agentQueryService->findOne($nameOrUuid),
        "Fetching agent '{$nameOrUuid}'…",
      );
    }
    catch (GatewayException $e) {
      $this->ui->error($this->describeGatewayError($e));
      return Command::FAILURE;
    }

    $agent = $this->resolveAgent($nameOrUuid, $result);
    if ($agent === null) {
      return Command::FAILURE;
    }

    if ($input->getOption('content')) {
      $this->ui->raw($agent->content ?? '');
      return Command::SUCCESS;
    }

    if ($this->ui->isJson()) {
      $this->ui->json($agent);
      return Command::SUCCESS;
    }

    $moduleLabels = array_map(
      static fn (array $mod) => $mod['machineName'] ?? $mod['title'] ?? $mod['label'] ?? $mod['uuid'],
      $agent->modules,
    );

    $this->ui->table(null, [
      ['UUID', $agent->uuid],
      ['Machine name', $agent->machineName ?? ''],
      ['Title', $agent->title],
      ['Official', $agent->isDropContext ? 'Yes' : 'No'],
      ['Version', $agent->version ?? ''],
      ['Private', $agent->private ? 'Yes' : 'No'],
      ['Modules', implode(', ', $moduleLabels)],
      ['Related skills', (string) count($agent->relatedSkills)],
      ['Related libraries', (string) count($agent->relatedCoreLibraries)],
      ['Changed', $agent->changed ?? ''],
      ['Path', $agent->path ?? ''],
      ['Description', $agent->description ?? ''],
    ]);

    if ($agent->references !== []) {
      $this->ui->table(
        ['Filename', 'URL'],
        array_map(static fn ($ref) => [$ref->filename ?? '', $ref->url ?? ''], $agent->references),
      );
    }

    return Command::SUCCESS;
  }

}
