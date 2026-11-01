<?php

declare(strict_types=1);

namespace DropContext\Command\DevKit;

use DropContext\Command\DropContextBaseCommand;
use DropContext\Console\Ui;
use DropContext\DevKit\DevKitInstaller;
use DropContext\Service\AppInstaller;
use DropContext\Service\AppStoreService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
  name: 'dev:remove',
  description: 'Remove the drop-context-dev agent, skills and MCP server that dev:install added',
)]
class DevRemoveCommand extends DropContextBaseCommand {

  public function __construct(
    AppInstaller $appInstaller,
    Ui $ui,
    protected DevKitInstaller $installer,
    protected AppStoreService $appStoreService,
  ) {
    parent::__construct($appInstaller, $ui);
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $project = $this->appStoreService->getRunnableProject();
    if ($project === null) {
      $this->ui->error('Run this command inside a project with a composer.json.');
      return Command::FAILURE;
    }

    $record = $this->appStoreService->projects->getDevKit();
    if ($record === null) {
      if ($this->ui->isJson()) {
        $this->ui->json(['removed' => []]);
      }
      else {
        $this->ui->info('drop-context-dev is not installed in this project.');
      }
      return Command::SUCCESS;
    }

    $paths = array_values(array_map('strval', (array) ($record->paths ?? [])));
    $mcp = json_decode(json_encode($record->mcp ?? []), TRUE);
    try {
      $warnings = $this->installer->uninstall($project->path, $paths, $mcp);
      $this->appStoreService->projects->setDevKit(null);
    }
    catch (\RuntimeException $e) {
      $this->ui->error($e->getMessage());
      return Command::FAILURE;
    }

    if ($this->ui->isJson()) {
      $this->ui->json(['removed' => $paths, 'mcp' => array_column($mcp, 'file'), 'warnings' => $warnings]);
      return Command::SUCCESS;
    }

    foreach ($warnings as $warning) {
      $this->ui->warning($warning);
    }
    $files = array_column($mcp, 'file');
    $this->ui->outro('Removed drop-context-dev: ' . count($paths) . ' files'
      . ($files === [] ? '' : ', and the ' . DevKitInstaller::MCP_SERVER . ' MCP server from ' . implode(', ', $files))
      . '.');
    return Command::SUCCESS;
  }

}
