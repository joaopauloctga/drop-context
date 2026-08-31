<?php

declare(strict_types=1);

namespace DropContext\Command;

use DropContext\Console\Ui;
use DropContext\Gateway\Exception\GatewayException;
use DropContext\Gateway\Exception\GatewayNotFoundException;
use DropContext\Service\AppInstaller;
use DropContext\Service\CodeAgentsService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** Base command: wires Ui, the global --json/--source options, and first-run wizard gating. */
abstract class DropContextBaseCommand extends Command {

  public function __construct(
    private readonly AppInstaller $appInstaller,
    protected readonly Ui $ui,
  ) {
    parent::__construct();
  }

  /** Read commands override this to false so the first-run wizard never blocks list/search or --json runs. */
  protected function requiresAppSetup(): bool {
    return true;
  }

  public function configure(): void {
    $this->addOption(
      name: 'source',
      shortcut: 's',
      mode: InputOption::VALUE_OPTIONAL,
      description: 'Source where to download the skills from',
      default: 'drop-context',
      suggestedValues: [
        'drop-context',
        'skill.sh',
      ]
    );
    $this->addOption(
      name: 'json',
      mode: InputOption::VALUE_NONE,
      description: 'Print machine-readable JSON on stdout; errors go to stderr.',
    );
    $this->addOption(
      name: 'code-agents',
      mode: InputOption::VALUE_OPTIONAL,
      description: 'Comma-separated code agent dirs to install on first run in a non-interactive session ' .
        '(e.g. .claude,.agents). A leading dot is added when missing.',
    );
  }

  protected function initialize(InputInterface $input, OutputInterface $output): void {
    $json = (bool) $input->getOption('json');
    $interactive = !$json && $input->isInteractive() && stream_isatty(STDIN);
    $this->ui->configure($json, $interactive);

    if (!$this->requiresAppSetup()) {
      return;
    }

    if (!$this->appInstaller->isInstalled()) {
      if (!$interactive) {
        $codeAgents = self::parseCodeAgentsOption($input->getOption('code-agents'));
        if ($codeAgents === null) {
          throw new \RuntimeException(
            'Drop Context CLI is not set up yet. Run a command interactively first to complete setup ' .
            '(e.g. `drop-context skill:add <skill>`), or pass --code-agents=.claude,.agents to set up non-interactively.',
          );
        }
        $this->appInstaller->install($codeAgents);
        return;
      }
      $this->runSetupWizard();
      return;
    }

    $this->appInstaller->ensureProjectRegistered();
  }

  /** Renders a GatewayException with a Phase-0-config hint when the 404 looks like a missing route. */
  protected function describeGatewayError(GatewayException $e): string {
    $message = $e->getMessage();
    if ($e instanceof GatewayNotFoundException && $e->looksLikeMissingRoute()) {
      $message .= ' The site may be missing required entity_gateway resource configuration.';
    }
    return $message;
  }

  private function runSetupWizard(): void {
    $detected = $this->appInstaller->detectAvailableCodeAgents();
    $allKnown = CodeAgentsService::KNOWN_CODE_AGENTS;
    $defaults = !empty($detected) ? $detected : ['.agents'];

    $selected = $this->ui->multiselect(
      label: 'Check the code agents you want to install',
      options: $allKnown,
      default: $defaults,
    );

    $this->appInstaller->install($selected);
  }

  /** Parses `--code-agents=.claude,.agents` into a code-agent list, or null when the option is unusable. */
  private static function parseCodeAgentsOption(mixed $option): ?array {
    if (!is_string($option) || trim($option) === '') {
      return null;
    }

    $agents = array_values(array_filter(array_map(
      static function (string $agent): string {
        $agent = trim($agent);
        return $agent === '' || str_starts_with($agent, '.') ? $agent : '.' . $agent;
      },
      explode(',', $option),
    ), static fn (string $agent): bool => $agent !== ''));

    return $agents === [] ? null : $agents;
  }

}
