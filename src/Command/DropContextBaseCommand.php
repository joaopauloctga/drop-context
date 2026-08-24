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
        throw new \RuntimeException(
          'Drop Context CLI is not set up yet. Run a command interactively first to complete setup ' .
          '(e.g. `drop-context skill:add <skill>`).',
        );
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

}
