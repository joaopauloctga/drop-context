<?php

declare(strict_types=1);

namespace DropContext\Command\Agent;

use DropContext\Command\DropContextBaseCommand;
use DropContext\Console\Ui;
use DropContext\Gateway\Exception\GatewayException;
use DropContext\Service\AgentStatusService;
use DropContext\Service\AppInstaller;
use DropContext\ValueObject\InstalledAgentStatus;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
  name: 'agent:status',
  description: 'Check installed agents for updates and choose which ones to update',
  aliases: ['a:status'],
)]
class AgentStatusCommand extends DropContextBaseCommand {

  public function __construct(
    AppInstaller $appInstaller,
    Ui $ui,
    private readonly AgentStatusService $agentStatusService,
  ) {
    parent::__construct($appInstaller, $ui);
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $this->ui->intro('Checking installed agents');

    try {
      $statuses = $this->ui->spin(
        fn () => $this->agentStatusService->inspect(),
        'Checking for agent updates…',
      );
    }
    catch (GatewayException $e) {
      $this->ui->error($this->describeGatewayError($e));
      return Command::FAILURE;
    }

    if ($this->ui->isJson()) {
      $this->ui->json(['agents' => $statuses]);
      return Command::SUCCESS;
    }

    if ($statuses === []) {
      $this->ui->note('No agents installed by this CLI in this project.');
      return Command::SUCCESS;
    }

    $this->ui->table(
      ['Name', 'State', 'Local', 'Remote'],
      array_map(static fn (InstalledAgentStatus $status) => [
        $status->name,
        $status->state->label(),
        $status->localVersion ?? '',
        $status->remoteVersion ?? '',
      ], $statuses),
    );

    $updatable = array_values(array_filter(
      $statuses,
      static fn (InstalledAgentStatus $status) => $status->hasUpdate(),
    ));

    if ($updatable === []) {
      $this->ui->outro('All installed agents are up to date.');
      return Command::SUCCESS;
    }

    if (!$this->ui->isInteractive()) {
      $this->ui->note('Run this command interactively to choose which agents to update.');
      return Command::SUCCESS;
    }

    $options = [];
    foreach ($updatable as $status) {
      $options[$status->name] = "{$status->name} ({$status->localVersion} → {$status->remoteVersion})";
    }

    $selected = $this->ui->multiselect(
      label: 'Select the agents to update',
      options: $options,
      default: array_keys($options),
    );

    $byName = [];
    foreach ($updatable as $status) {
      $byName[$status->name] = $status;
    }

    $updated = [];
    foreach ($selected as $name) {
      $status = $byName[$name] ?? null;
      if ($status === null) {
        continue;
      }
      try {
        $agent = $this->ui->spin(
          fn () => $this->agentStatusService->update($status),
          "Updating '{$name}'…",
        );
        $updated[] = $agent->name;
      }
      catch (\RuntimeException $e) {
        $this->ui->warning($e->getMessage());
      }
    }

    if ($updated === []) {
      $this->ui->error('No agent could be updated.');
      return Command::FAILURE;
    }

    $this->ui->outro(count($updated) . ' agent(s) updated');
    return Command::SUCCESS;
  }

}
