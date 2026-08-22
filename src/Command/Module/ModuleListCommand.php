<?php

declare(strict_types=1);

namespace DropContext\Command\Module;

use DropContext\Command\DropContextBaseCommand;
use DropContext\Console\Ui;
use DropContext\Gateway\Dto\ModuleRow;
use DropContext\Gateway\Exception\GatewayException;
use DropContext\Gateway\Repository\ModuleRepository;
use DropContext\Service\AppInstaller;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
  name: 'module:list',
  description: 'List modules documented in the Drop Context gateway',
  aliases: ['m:list', 'module:search'],
)]
class ModuleListCommand extends DropContextBaseCommand {

  public function __construct(
    AppInstaller $appInstaller,
    Ui $ui,
    protected readonly ModuleRepository $moduleRepository,
  ) {
    parent::__construct($appInstaller, $ui);
  }

  protected function requiresAppSetup(): bool {
    return false;
  }

  public function configure(): void {
    parent::configure();
    $this->addArgument('search', InputArgument::OPTIONAL, 'Text to search for (matches the module machine name by default)');
    $this->addOption('title', null, InputOption::VALUE_NONE, 'Search the module title instead of the machine name');
  }

  public function execute(InputInterface $input, OutputInterface $output): int {
    $search = $input->getArgument('search');
    $byTitle = (bool) $input->getOption('title');

    $this->ui->intro('Listing modules');

    try {
      $modules = $this->ui->spin(
        fn () => $this->moduleRepository->search($search, $byTitle),
        'Fetching modules…',
      );
    }
    catch (GatewayException $e) {
      $this->ui->error($this->describeGatewayError($e));
      return Command::FAILURE;
    }

    if ($this->ui->isJson()) {
      $this->ui->json(['count' => count($modules), 'modules' => $modules]);
      return Command::SUCCESS;
    }

    if ($modules === []) {
      $this->ui->note('No modules found.');
      return Command::SUCCESS;
    }

    $rows = array_map(static fn (ModuleRow $module) => [
      $module->machineName,
      $module->title,
      $module->maintenanceStatus ?? '',
      $module->developmentStatus ?? '',
      $module->isCovered ? 'Yes' : 'No',
    ], $modules);

    $this->ui->table(['Machine name', 'Title', 'Maintenance', 'Development', 'Covered'], $rows);
    $this->ui->outro(count($modules) . ' module(s)');

    return Command::SUCCESS;
  }

}
