<?php

declare(strict_types=1);

namespace DropContext\Command\Skill;

use DropContext\Command\DropContextBaseCommand;
use DropContext\Console\Ui;
use DropContext\Gateway\Exception\GatewayException;
use DropContext\Service\AppInstaller;
use DropContext\Service\AppStoreService;
use DropContext\Service\SkillStatusService;
use DropContext\SkillUpdateState;
use DropContext\ValueObject\InstalledSkillStatus;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
  name: 'skill:status',
  description: 'Check installed skills for updates and choose which ones to update',
  aliases: ['s:status'],
)]
class SkillStatusCommand extends DropContextBaseCommand {

  public function __construct(
    AppInstaller $appInstaller,
    Ui $ui,
    private readonly SkillStatusService $skillStatusService,
    private readonly AppStoreService $appStoreService,
  ) {
    parent::__construct($appInstaller, $ui);
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    if ($this->appStoreService->getRunnableProject() === null) {
      $this->ui->error('Run this command inside a project with a composer.json.');
      return Command::FAILURE;
    }

    $this->ui->intro('Checking installed skills');

    try {
      $statuses = $this->ui->spin(
        fn () => $this->skillStatusService->inspect(),
        'Checking for skill updates…',
      );
    }
    catch (GatewayException $e) {
      $this->ui->error($this->describeGatewayError($e));
      return Command::FAILURE;
    }

    $updates = array_values(array_filter(
      $statuses,
      static fn (InstalledSkillStatus $status): bool => $status->hasUpdate(),
    ));

    if ($this->ui->isJson()) {
      $this->ui->json([
        'count' => count($statuses),
        'updatesAvailable' => count($updates),
        'skills' => $statuses,
      ]);
      return Command::SUCCESS;
    }

    if ($statuses === []) {
      $this->ui->note('No skills installed by this CLI in this project.');
      return Command::SUCCESS;
    }

    $this->ui->table(['Skill', 'Installed', 'Latest', 'Status', 'Agents'], array_map(
      static fn (InstalledSkillStatus $status): array => [
        $status->name,
        $status->installedVersion !== '' ? $status->installedVersion : 'unknown',
        $status->latestVersion ?? 'unknown',
        $status->state->label(),
        $status->paths !== [] ? implode(', ', array_keys($status->paths)) : '(no files found)',
      ],
      $statuses,
    ));

    if ($updates === []) {
      $allCurrent = count(array_filter(
        $statuses,
        static fn (InstalledSkillStatus $status): bool => $status->state === SkillUpdateState::CURRENT,
      )) === count($statuses);
      if ($allCurrent) {
        $this->ui->outro('All installed skills are up to date.');
      }
      else {
        $this->ui->note('No actionable skill updates were found. Review the statuses above.');
      }
      return Command::SUCCESS;
    }

    if (!$this->ui->isInteractive()) {
      $this->ui->note('Run this command interactively to choose which skills to update.');
      return Command::SUCCESS;
    }

    $options = [];
    foreach ($updates as $status) {
      $options[$status->name] = sprintf(
        '%s (%s → %s)',
        $status->name,
        $status->installedVersion !== '' ? $status->installedVersion : 'unknown',
        $status->latestVersion,
      );
    }

    $selected = $this->ui->multiselect(
      label: 'Select the skills to update',
      options: $options,
      default: array_keys($options),
      required: false,
    );

    if ($selected === []) {
      $this->ui->outro('Nothing updated.');
      return Command::SUCCESS;
    }

    $selected = array_flip($selected);
    $updated = [];
    $failed = [];
    foreach ($updates as $status) {
      if (!isset($selected[$status->name])) {
        continue;
      }

      try {
        $skill = $this->ui->spin(
          fn () => $this->skillStatusService->update($status),
          "Updating '{$status->name}'…",
        );
        $updated[] = $skill->name;
      }
      catch (GatewayException|\RuntimeException $e) {
        $failed[] = $status->name;
        $this->ui->warning("Could not update '{$status->name}': {$e->getMessage()}");
      }
    }

    if ($updated === []) {
      $this->ui->error('No skill could be updated.');
      return Command::FAILURE;
    }

    $summary = count($updated) . ' skill(s) updated';
    if ($failed !== []) {
      $summary .= ', ' . count($failed) . ' failed';
    }
    $this->ui->outro($summary . '.');

    return $failed === [] ? Command::SUCCESS : Command::FAILURE;
  }

}
