<?php

declare(strict_types=1);

namespace DropContext\Cache;

/** Central parsing for the cache-related env vars — read here, nowhere else. */
final class EnvFlags {

  private const int DEFAULT_TTL = 86400;

  private const int DEFAULT_STAMP_TTL = 300;

  private const string DEFAULT_LOG_LEVEL = 'warning';

  /** DROP_CONTEXT_CACHE=0 disables L2 (the on-disk cache); unset/anything truthy leaves it on. L1 (in-process) is unaffected. */
  public static function l2Enabled(): bool {
    $env = getenv('DROP_CONTEXT_CACHE');
    if ($env === false || $env === '') {
      return true;
    }
    return filter_var($env, FILTER_VALIDATE_BOOLEAN);
  }

  /** DROP_CONTEXT_CACHE_TTL — the per-entry safety-net TTL in seconds. Default 86400 (24h). */
  public static function ttl(): int {
    return self::positiveInt('DROP_CONTEXT_CACHE_TTL', self::DEFAULT_TTL);
  }

  /** DROP_CONTEXT_STAMP_TTL — how often the catalog-changed probe re-checks. Default 300s. */
  public static function stampTtl(): int {
    return self::positiveInt('DROP_CONTEXT_STAMP_TTL', self::DEFAULT_STAMP_TTL);
  }

  /** DROP_CONTEXT_MCP_LOG — debug|info|warning|error, default warning. */
  public static function logLevel(): string {
    $env = strtolower((string) (getenv('DROP_CONTEXT_MCP_LOG') ?: self::DEFAULT_LOG_LEVEL));
    return in_array($env, ['debug', 'info', 'warning', 'error'], true) ? $env : self::DEFAULT_LOG_LEVEL;
  }

  private static function positiveInt(string $name, int $default): int {
    $env = getenv($name);
    if ($env !== false && $env !== '' && ctype_digit($env)) {
      return max(1, (int) $env);
    }
    return $default;
  }

}
