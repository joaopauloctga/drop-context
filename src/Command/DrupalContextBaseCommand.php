<?php

namespace DrupalContext\Command;

use DrupalContext\Service\AppInstaller;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\multiselect;

class DrupalContextBaseCommand extends Command {

  public function __construct(private readonly AppInstaller $appInstaller) {
    parent::__construct();
  }

  public function configure(): void {
    $this->addOption(
      name: 'source',
      shortcut: 's',
      mode: InputOption::VALUE_OPTIONAL,
      description: 'Source where to download the skills from',
      default: 'drupal-context',
      suggestedValues: [
        'drupal-context',
        'skill.sh'
      ]
    );
  }

  protected function initialize(InputInterface $input, OutputInterface $output): void {
    if (!$this->appInstaller->isInstalled()) {
      $this->runSetupWizard();
    }
    else {
      $this->appInstaller->ensureProjectRegistered();
    }
  }

  private function runSetupWizard(): void {
    $detected = $this->appInstaller->detectAvailableCodeAgents();
    $allKnown = ['.agents', '.claude', '.cursor', '.codex'];
    $defaults = !empty($detected) ? $detected : ['.agents'];

    $selected = multiselect(
      label: 'Check the code agents you want to install',
      options: $allKnown,
      default: $defaults,
      required: true,
    );

    $this->appInstaller->install($selected);
  }

}
