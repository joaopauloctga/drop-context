<?php

declare(strict_types=1);

namespace DropContext\Mcp;

use DropContext\Cache\EnvFlags;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

/**
 * Minimal PSR-3 logger to `php://stderr`. Nothing in this process may ever
 * write to stdout except through the MCP transport — a stray echo/print/
 * var_dump there would corrupt the JSON-RPC stream the client is reading.
 *
 * Level gated by DROP_CONTEXT_MCP_LOG (debug|info|warning|error, default
 * warning) — the SDK itself logs every message it handles at `info`, which
 * would otherwise be very chatty.
 */
final class StderrLogger extends AbstractLogger {

  /** @var array<string, int> */
  private const array LEVELS = [
    LogLevel::EMERGENCY => 0,
    LogLevel::ALERT => 1,
    LogLevel::CRITICAL => 2,
    LogLevel::ERROR => 3,
    LogLevel::WARNING => 4,
    LogLevel::NOTICE => 5,
    LogLevel::INFO => 6,
    LogLevel::DEBUG => 7,
  ];

  public function log($level, string|\Stringable $message, array $context = []): void {
    $numeric = self::LEVELS[(string) $level] ?? self::LEVELS[LogLevel::INFO];
    $threshold = self::LEVELS[EnvFlags::logLevel()] ?? self::LEVELS[LogLevel::WARNING];
    if ($numeric > $threshold) {
      return;
    }

    fwrite(STDERR, sprintf('[%s] %s%s', strtoupper((string) $level), $this->interpolate((string) $message, $context), PHP_EOL));
  }

  /** @param array<string, mixed> $context */
  private function interpolate(string $message, array $context): string {
    $replace = [];
    foreach ($context as $key => $value) {
      if (is_scalar($value) || $value instanceof \Stringable) {
        $replace['{' . $key . '}'] = (string) $value;
      }
    }
    return strtr($message, $replace);
  }

}
