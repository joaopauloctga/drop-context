<?php

declare(strict_types=1);

namespace DrupalContext\Console;

use DrupalContext\Console\Exception\NonInteractivePromptException;
use Laravel\Prompts\Prompt;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error as prompt_error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\note;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

/**
 * Single entry point for all human-facing CLI output.
 *
 * Wraps laravel/prompts. In --json mode every human-facing method is a
 * no-op except json() (pure JSON on stdout) and error() (always stderr),
 * so a piped run stays machine-readable.
 */
class Ui {

  protected bool $json = false;

  protected bool $interactive = true;

  private ?ConsoleOutput $stdout = null;

  private ?OutputInterface $stderr = null;

  public function configure(bool $json, bool $interactive): void {
    $this->json = $json;
    $this->interactive = $interactive;
  }

  public function isJson(): bool {
    return $this->json;
  }

  public function isInteractive(): bool {
    return $this->interactive;
  }

  public function intro(string $message): void {
    if ($this->json) {
      return;
    }
    $this->initOutputs();
    intro($message);
  }

  public function outro(string $message): void {
    if ($this->json) {
      return;
    }
    $this->initOutputs();
    outro($message);
  }

  public function info(string $message): void {
    if ($this->json) {
      return;
    }
    $this->initOutputs();
    info($message);
  }

  public function note(string $message, ?string $type = null): void {
    if ($this->json) {
      return;
    }
    $this->initOutputs();
    note($message, $type);
  }

  public function warning(string $message): void {
    if ($this->json) {
      return;
    }
    $this->initOutputs();
    warning($message);
  }

  /** Always writes to STDERR — in --json mode and in prompt mode alike. */
  public function error(string $message): void {
    $this->initOutputs();
    Prompt::setOutput($this->stderr);
    try {
      prompt_error($message);
    }
    finally {
      Prompt::setOutput($this->stdout);
    }
  }

  /**
   * @param array<int|string, string|array<int, string>> $headers
   * @param array<int, array<int, string>> $rows
   */
  public function table(array $headers, array $rows): void {
    if ($this->json) {
      return;
    }
    $this->initOutputs();
    table($headers, $this->withRowSeparators($rows));
  }

  /**
   * Interleaves a TableSeparator between rows so each one reads as a block.
   *
   * @param array<int, array<int, string>> $rows
   * @return array<int, array<int, string>|TableSeparator>
   */
  private function withRowSeparators(array $rows): array {
    $separated = [];
    foreach (array_values($rows) as $index => $row) {
      if ($index > 0) {
        $separated[] = new TableSeparator();
      }
      $separated[] = $row;
    }
    return $separated;
  }

  public function confirm(string $label, bool $default = true): bool {
    if (!$this->interactive) {
      return $default;
    }
    $this->initOutputs();
    return confirm($label, $default);
  }

  /**
   * @param array<int|string, string> $options
   * @throws NonInteractivePromptException
   */
  public function select(string $label, array $options, int|string|null $default = null): int|string {
    if (!$this->interactive) {
      throw new NonInteractivePromptException($label);
    }
    $this->initOutputs();
    return select($label, $options, $default);
  }

  /**
   * @param array<int|string, string> $options
   * @param array<int|string> $default
   * @return array<int|string>
   * @throws NonInteractivePromptException
   */
  public function multiselect(string $label, array $options, array $default = []): array {
    if (!$this->interactive) {
      throw new NonInteractivePromptException($label);
    }
    $this->initOutputs();
    return multiselect($label, $options, $default, required: true);
  }

  /**
   * Runs a closure behind a spinner. In --json or non-interactive mode the
   * closure just runs directly — no animation, no escape codes on stdout.
   *
   * @template TReturn
   * @param \Closure(): TReturn $callback
   * @return TReturn
   */
  public function spin(\Closure $callback, string $message = ''): mixed {
    if ($this->json || !$this->interactive) {
      return $callback();
    }
    $this->initOutputs();
    return spin($callback, $message);
  }

  /** Pure JSON on stdout. The only thing --json mode prints besides error(). */
  public function json(mixed $data): void {
    fwrite(STDOUT, json_encode(
      $data,
      JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
    ) . PHP_EOL);
  }

  /** Plain, pipeable text — the sanctioned exception to the prompts rule (e.g. skill:show --content). */
  public function raw(string $content): void {
    fwrite(STDOUT, $content);
  }

  private function initOutputs(): void {
    if ($this->stdout === null) {
      $this->stdout = new ConsoleOutput();
      $this->stderr = $this->stdout->getErrorOutput();
      Prompt::setOutput($this->stdout);
    }
  }

}
