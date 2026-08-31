<?php

declare(strict_types=1);

namespace DropContext\Command\Agent;

use DropContext\Command\DropContextBaseCommand;
use DropContext\Console\Ui;
use DropContext\Gateway\Dto\AgentRow;
use DropContext\Gateway\Exception\GatewayException;
use DropContext\Gateway\Exception\ModuleNotFoundException;
use DropContext\Gateway\AgentQueryService;
use DropContext\Service\AgentService;
use DropContext\Service\AppInstaller;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
  name: 'agent:list',
  description: 'List agents from the Drop Context gateway, or the ones installed in the project',
  aliases: ['a:list'],
)]
class AgentListCommand extends DropContextBaseCommand {

  private const string FROM_GATEWAY = 'drop-context';

  private const string FROM_LOCAL = 'local';

  private const array SOURCES = [self::FROM_GATEWAY, self::FROM_LOCAL];

  public function __construct(
    AppInstaller $appInstaller,
    Ui $ui,
    protected readonly AgentQueryService $agentQueryService,
    protected readonly AgentService $agentService,
  ) {
    parent::__construct($appInstaller, $ui);
  }

  protected function requiresAppSetup(): bool {
    return false;
  }

  public function configure(): void {
    parent::configure();
    $this->addOption(
      'from',
      null,
      InputOption::VALUE_OPTIONAL,
      'Where to list from: ' . implode(', ', self::SOURCES),
      self::FROM_GATEWAY,
      self::SOURCES,
    );
    $this->addOption('module', 'm', InputOption::VALUE_OPTIONAL, 'Module machine name to filter agents by');
    $this->addOption('search', null, InputOption::VALUE_OPTIONAL, 'Free-text search on agent title');
  }

  public function execute(InputInterface $input, OutputInterface $output): int {
    $from = (string) ($input->getOption('from') ?? self::FROM_GATEWAY);
    if (!in_array($from, self::SOURCES, true)) {
      $this->ui->error("Unknown --from value '{$from}'. Valid values: " . implode(', ', self::SOURCES));
      return Command::FAILURE;
    }

    if ($from === self::FROM_LOCAL) {
      return $this->listLocal();
    }

    $module = $input->getOption('module');
    $search = $input->getOption('search');

    $this->ui->intro('Listing agents');

    try {
      $agents = $this->ui->spin(
        fn () => $this->agentQueryService->search(text: $search, module: $module),
        'Fetching agents…',
      );
    }
    catch (ModuleNotFoundException $e) {
      $this->ui->error($e->getMessage());
      return Command::FAILURE;
    }
    catch (GatewayException $e) {
      $this->ui->error($this->describeGatewayError($e));
      return Command::FAILURE;
    }

    if ($this->ui->isJson()) {
      $this->ui->json(['count' => count($agents), 'agents' => $agents]);
      return Command::SUCCESS;
    }

    if ($agents === []) {
      $this->ui->note('No agents found.');
      return Command::SUCCESS;
    }

    $rows = array_map(static function (AgentRow $agent) {
      $moduleTitles = array_map(
        static fn (array $mod) => $mod['machineName'] ?? $mod['title'] ?? $mod['label'] ?? $mod['uuid'],
        $agent->modules,
      );
      return [
        $agent->machineName ?? '',
        $agent->title,
        $agent->isDropContext ? 'yes' : '',
        $agent->version ?? '',
        implode(', ', $moduleTitles),
        self::truncate($agent->description ?? '', 60),
      ];
    }, $agents);

    $this->ui->table(['Machine name', 'Title', 'Official', 'Version', 'Modules', 'Description'], $rows);
    $this->ui->outro(count($agents) . ' agent(s)');
    return Command::SUCCESS;
  }

  private function listLocal(): int {
    $agents = $this->agentService->listInstalledAgents();
    if ($this->ui->isJson()) {
      $this->ui->json(['count' => count($agents), 'agents' => $agents]);
      return Command::SUCCESS;
    }
    if ($agents === []) {
      $this->ui->note('No agents installed by this CLI in this project.');
      return Command::SUCCESS;
    }
    $rows = array_map(static fn (array $agent) => [
      $agent['name'],
      $agent['version'],
      $agent['path'],
    ], $agents);
    $this->ui->table(['Name', 'Version', 'Path'], $rows);
    $this->ui->outro(count($agents) . ' agent(s)');
    return Command::SUCCESS;
  }

  private static function truncate(string $text, int $length): string {
    if (mb_strlen($text) <= $length) {
      return $text;
    }
    return mb_substr($text, 0, $length - 1) . '…';
  }

}
