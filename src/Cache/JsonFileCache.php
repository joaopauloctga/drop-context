<?php

declare(strict_types=1);

namespace DropContext\Cache;

use DropContext\Gateway\GatewaySettings;
use Symfony\Component\Filesystem\Filesystem;

/**
 * File-backed cache for entity_gateway responses, namespaced per base URL.
 *
 * Layout: ~/.drop-context/cache/gateway/<sha1(baseUrl)>/{stamp.json, entries/<sha1(key)>.json}.
 * Two layers: an in-process array (L1 — survives the whole MCP stdio session,
 * since that is one long-lived process) and the JSON files (L2, disabled
 * entirely when DROP_CONTEXT_CACHE=0 — reads then always miss and writes are
 * skipped, but L1 keeps working within the current process). Reads are
 * tolerant: invalid JSON on disk is a miss, not an error.
 */
final class JsonFileCache {

  private const int MAX_ENTRIES = 2000;

  /** @var array<string, array<string, mixed>> */
  private array $memory = [];

  private readonly string $namespaceDir;

  public function __construct(
    GatewaySettings $settings,
    ?string $cacheRoot = null,
    private readonly Filesystem $filesystem = new Filesystem(),
  ) {
    $this->namespaceDir = self::namespaceDirFor($settings->getBaseUrl(), $cacheRoot);
  }

  /** The cache root when no override is given: `~/.drop-context/cache/gateway`. */
  public static function defaultCacheRoot(): string {
    return self::homeDir() . '/.drop-context/cache/gateway';
  }

  /** Same namespacing rule the constructor uses, exposed for callers (e.g. `cache:clear --url`) that need to target a namespace without a GatewaySettings instance. */
  public static function namespaceDirFor(string $baseUrl, ?string $cacheRoot = null): string {
    $root = $cacheRoot ?? self::defaultCacheRoot();
    return rtrim($root, '/') . '/' . sha1(rtrim($baseUrl, '/'));
  }

  /** @return array<string, mixed>|null */
  public function get(string $key): ?array {
    if (array_key_exists($key, $this->memory)) {
      return $this->memory[$key];
    }
    if (!EnvFlags::l2Enabled()) {
      return null;
    }

    $path = $this->entryPath($key);
    if (!is_file($path)) {
      return null;
    }
    $raw = @file_get_contents($path);
    if ($raw === false) {
      return null;
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
      return null;
    }

    return $this->memory[$key] = $decoded;
  }

  /** @param array<string, mixed> $entry */
  public function set(string $key, array $entry): void {
    $this->memory[$key] = $entry;
    if (!EnvFlags::l2Enabled()) {
      return;
    }

    try {
      $this->filesystem->dumpFile($this->entryPath($key), self::encode($entry));
    }
    catch (\Throwable) {
      // Best-effort: an unwritable cache dir must not break a tool call.
      return;
    }
    $this->collectGarbage();
  }

  /** Drops every cached entry (both layers) for this namespace — a stamp change or `cache:clear`. Leaves stamp.json alone; callers that also want that gone use purgeAll(). */
  public function purgeEntries(): void {
    $this->memory = [];
    if (is_dir($this->entriesDir())) {
      $this->filesystem->remove($this->entriesDir());
    }
  }

  /** Drops the whole namespace directory, entries and stamp.json alike. */
  public function purgeAll(): void {
    $this->memory = [];
    if (is_dir($this->namespaceDir)) {
      $this->filesystem->remove($this->namespaceDir);
    }
  }

  /** @return array{value: string, checkedAt: int}|null */
  public function readStamp(): ?array {
    $path = $this->stampPath();
    if (!is_file($path)) {
      return null;
    }
    $raw = @file_get_contents($path);
    if ($raw === false) {
      return null;
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
  }

  /** @param array{value: string, checkedAt: int} $data */
  public function writeStamp(array $data): void {
    try {
      $this->filesystem->dumpFile($this->stampPath(), self::encode($data));
    }
    catch (\Throwable) {
      // Best-effort.
    }
  }

  public function namespaceDir(): string {
    return $this->namespaceDir;
  }

  private function stampPath(): string {
    return $this->namespaceDir . '/stamp.json';
  }

  private function entriesDir(): string {
    return $this->namespaceDir . '/entries';
  }

  private function entryPath(string $key): string {
    return $this->entriesDir() . '/' . sha1($key) . '.json';
  }

  /** Removes the oldest entries once the directory holds more than MAX_ENTRIES files. */
  private function collectGarbage(): void {
    $dir = $this->entriesDir();
    if (!is_dir($dir)) {
      return;
    }
    $files = glob($dir . '/*.json') ?: [];
    if (count($files) <= self::MAX_ENTRIES) {
      return;
    }

    usort($files, static fn (string $a, string $b): int => (filemtime($a) ?: 0) <=> (filemtime($b) ?: 0));
    foreach (array_slice($files, 0, count($files) - self::MAX_ENTRIES) as $file) {
      @unlink($file);
    }
  }

  private static function encode(array $data): string {
    return (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  }

  private static function homeDir(): string {
    $home = getenv('HOME') ?: getenv('USERPROFILE');
    return is_string($home) && $home !== '' ? $home : sys_get_temp_dir();
  }

}
