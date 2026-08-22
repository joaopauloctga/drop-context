<?php

declare(strict_types=1);

namespace DrupalContext\Service;

use DrupalContext\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/** Prints a non-blocking update notice after successful interactive commands. */
final class UpdateNotifier {

  public function __construct(
    private readonly UpdateChecker $updateChecker,
  ) {}

  public function notify(InputInterface $input, OutputInterface $output): void {
    if (!$this->shouldCheck($input)) {
      return;
    }

    $currentVersion = Application::currentVersion();
    $latestVersion = $this->updateChecker->findNewerVersion($currentVersion);
    if ($latestVersion === null) {
      return;
    }

    $stderr = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
    $stderr->writeln('');
    $stderr->writeln(sprintf(
      '<info>A new Drupal Context CLI version is available: %s → %s</info>',
      $currentVersion,
      $latestVersion,
    ));
    $stderr->writeln(sprintf(
      'Run: <comment>composer global update %s</comment>',
      Application::PACKAGE_NAME,
    ));
  }

  private function shouldCheck(InputInterface $input): bool {
    if (!$input->isInteractive() || !stream_isatty(STDERR)) {
      return false;
    }

    if ($input->hasParameterOption('--json') || $input->hasParameterOption('--no-update-check')) {
      return false;
    }

    foreach (['CI', 'DRUPAL_CONTEXT_NO_UPDATE_CHECK'] as $name) {
      $value = getenv($name);
      if (is_string($value) && filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
        return false;
      }
    }

    return true;
  }

}
