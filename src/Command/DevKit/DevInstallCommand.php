<?php

declare(strict_types=1);

namespace DropContext\Command\DevKit;

use DropContext\Command\DropContextBaseCommand;
use DropContext\Console\Ui;
use DropContext\DevKit\DevKit;
use DropContext\DevKit\DevKitDownloader;
use DropContext\DevKit\DevKitInstaller;
use DropContext\DevKit\McpCommand;
use DropContext\DevKit\McpConfigWriter;
use DropContext\Service\AppInstaller;
use DropContext\Service\AppStoreService;
use DropContext\Service\CodeAgentsService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
  name: 'dev:install',
  description: 'Install the drop-context-dev agent, skills and MCP server into this project',
)]
class DevInstallCommand extends DropContextBaseCommand {

  public function __construct(
    AppInstaller $appInstaller,
    Ui $ui,
    protected DevKitDownloader $downloader,
    protected DevKitInstaller $installer,
    protected CodeAgentsService $codeAgentsService,
    protected AppStoreService $appStoreService,
  ) {
    parent::__construct($appInstaller, $ui);
  }

  public function configure(): void {
    parent::configure();
    $this->addOption(
      name: 'ref',
      mode: InputOption::VALUE_REQUIRED,
      description: 'Branch, tag or commit of github.com/' . DevKitDownloader::REPOSITORY . ' to install.',
      default: 'main',
    );
    $this->addOption(
      name: 'from',
      mode: InputOption::VALUE_REQUIRED,
      description: 'Install from a local drop-context-dev checkout instead of GitHub (skills link to it).',
    );
    $this->addOption(
      name: 'force',
      mode: InputOption::VALUE_NONE,
      description: 'Replace files at the install paths that dev:install did not write.',
    );
    $this->setHelp(<<<'HELP'
      Installs the drop-context-dev skills (dc-plan, dc-implement), the
      drop-context-developer agent and the drop-context MCP server for every
      code agent this project uses: Claude Code (.claude), Cursor (.cursor),
      Codex (.codex), OpenCode (.opencode) and plain Agent Skills (.agents).

      The code agents are the ones present in the project plus your preferred
      ones (code-agents:list). Pass --code-agents=.claude,.cursor to choose.

      Run it again to update. dev:remove undoes it.
      HELP);
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $project = $this->appStoreService->getRunnableProject();
    if ($project === null) {
      $this->ui->error('Run this command inside a project with a composer.json.');
      return Command::FAILURE;
    }

    $codeAgents = $this->codeAgents($input->getOption('code-agents'));
    if ($codeAgents === []) {
      $this->ui->error('No supported code agent to install for. Pass --code-agents=' .
        implode(',', DevKitInstaller::supportedCodeAgents()) . ' (or a subset).');
      return Command::FAILURE;
    }

    $from = $input->getOption('from');
    $ref = (string) $input->getOption('ref');
    $this->ui->intro('Installing drop-context-dev');

    try {
      $kit = is_string($from) && $from !== ''
        ? DevKit::open($from, (string) realpath($from))
        : $this->ui->spin(fn () => $this->downloader->download($ref), "Downloading drop-context-dev@{$ref}…");

      $previous = $this->appStoreService->projects->getDevKit();
      $owned = array_values(array_map('strval', (array) ($previous->paths ?? [])));
      $result = $this->installer->install(
        kit: $kit,
        projectDir: $project->path,
        codeAgents: $codeAgents,
        mcpCommand: McpCommand::locate(),
        owned: $owned,
        linkRoots: [$this->appStoreService->getAppRootDir() . '/dev-kit'],
        force: (bool) $input->getOption('force'),
      );

      // Clean up what a previous install wrote and this one no longer does,
      // such as the files of a code agent that was dropped.
      $ownedMcp = $result->ownedMcp();
      $mcpFiles = array_column($ownedMcp, 'file');
      $staleMcp = array_values(array_filter(
        json_decode(json_encode($previous->mcp ?? []), TRUE),
        static fn (array $entry): bool => !in_array($entry['file'], $mcpFiles, TRUE),
      ));
      $warnings = array_merge($result->warnings, $this->installer->uninstall(
        $project->path,
        array_values(array_diff($owned, $result->paths)),
        $staleMcp,
      ));

      $this->appStoreService->projects->setDevKit((object) [
        'version' => $kit->version(),
        'source' => $kit->source,
        'codeAgents' => $codeAgents,
        'installed' => time(),
        'paths' => $result->paths,
        'mcp' => $ownedMcp,
      ]);
    }
    catch (\RuntimeException $e) {
      $this->ui->error($e->getMessage());
      return Command::FAILURE;
    }

    if ($this->ui->isJson()) {
      $this->ui->json([
        'version' => $kit->version(),
        'source' => $kit->source,
        'codeAgents' => $codeAgents,
        'paths' => $result->paths,
        'skipped' => $result->skipped,
        'mcp' => $result->mcp,
        'warnings' => $warnings,
      ]);
      return Command::SUCCESS;
    }

    $rows = [];
    foreach ($result->summary as $codeAgent => $where) {
      $mcp = $where['mcp'] ?? '—';
      foreach ($result->mcp as $entry) {
        if ($entry['file'] === $where['mcp'] && $entry['status'] === McpConfigWriter::KEPT) {
          $mcp .= ' (kept existing)';
        }
      }
      $rows[] = [$codeAgent, $where['skills'], $where['agent'] ?? '—', $mcp];
    }
    $this->ui->table(['Code agent', 'Skills', 'Agent', 'MCP server'], $rows);

    if ($result->skipped !== []) {
      $this->ui->warning("Left in place, not written by dev:install (pass --force to replace):\n  "
        . implode("\n  ", $result->skipped));
    }
    foreach ($result->mcp as $entry) {
      if ($entry['status'] === McpConfigWriter::KEPT) {
        $this->ui->warning("{$entry['file']} already defines a different '" . DevKitInstaller::MCP_SERVER
          . "' MCP server; it was kept.");
      }
    }
    foreach ($warnings as $warning) {
      $this->ui->warning($warning);
    }

    $this->ui->outro("drop-context-dev {$kit->version()} installed for: " . implode(', ', $codeAgents)
      . '. Restart your coding agent to load it.');
    return Command::SUCCESS;
  }

  /** @return string[] */
  private function codeAgents(mixed $option): array {
    if (is_string($option) && trim($option) !== '') {
      $requested = array_map(
        static fn (string $agent): string => str_starts_with(trim($agent), '.') ? trim($agent) : '.' . trim($agent),
        array_filter(explode(',', $option), static fn (string $agent): bool => trim($agent) !== ''),
      );
    }
    else {
      $requested = array_merge(
        $this->codeAgentsService->readCurrentCodeAgents(),
        $this->appStoreService->getAppConfigFile()->codeAgents,
      );
    }

    $supported = DevKitInstaller::supportedCodeAgents();
    foreach (array_diff($requested, $supported) as $unsupported) {
      $this->ui->warning("{$unsupported} is not supported by dev:install; skipped.");
    }
    return array_values(array_unique(array_intersect($requested, $supported)));
  }

}
