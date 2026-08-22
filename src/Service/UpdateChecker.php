<?php

declare(strict_types=1);

namespace DropContext\Service;

use DropContext\Application;
use GuzzleHttp\Client as HttpClient;
use Symfony\Component\Filesystem\Filesystem;

/** Fetches and caches the latest stable CLI version published on Packagist. */
final class UpdateChecker {

  private const CACHE_FILE = 'update-check.json';

  private const CACHE_TTL = 86400;

  private const METADATA_URL = 'https://repo.packagist.org/p2/' . Application::PACKAGE_NAME . '.json';

  private ?HttpClient $client = null;

  public function __construct(
    private readonly AppStoreService $appStoreService,
    private readonly Filesystem $filesystem,
  ) {}

  public function findNewerVersion(string $currentVersion): ?string {
    if (!$this->isStableVersion($currentVersion)) {
      return null;
    }

    $latestVersion = $this->latestStableVersion();
    if ($latestVersion === null || version_compare($latestVersion, ltrim($currentVersion, 'vV'), '<=')) {
      return null;
    }

    return $latestVersion;
  }

  private function latestStableVersion(): ?string {
    $cache = $this->readCache();
    if ($this->isFresh($cache)) {
      return $this->cachedVersion($cache);
    }

    $headers = [];
    if (is_string($cache['lastModified'] ?? null)) {
      $headers['If-Modified-Since'] = $cache['lastModified'];
    }

    try {
      $response = $this->httpClient()->request('GET', self::METADATA_URL, [
        'headers' => $headers,
        'http_errors' => false,
      ]);

      if ($response->getStatusCode() === 304) {
        $cache['checkedAt'] = time();
        $this->writeCache($cache);
        return $this->cachedVersion($cache);
      }

      if ($response->getStatusCode() !== 200) {
        $this->cacheFailedCheck($cache);
        return null;
      }

      $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
      $latestVersion = $this->extractLatestStableVersion($data);
      $lastModified = $response->getHeaderLine('Last-Modified');
      $this->writeCache([
        'checkedAt' => time(),
        'latestVersion' => $latestVersion,
        'lastModified' => $lastModified !== '' ? $lastModified : null,
      ]);

      return $latestVersion;
    }
    catch (\Throwable) {
      $this->cacheFailedCheck($cache);
      return null;
    }
  }

  /** @param array<string, mixed> $data */
  private function extractLatestStableVersion(array $data): ?string {
    $releases = $data['packages'][Application::PACKAGE_NAME] ?? [];
    if (!is_array($releases)) {
      return null;
    }

    $latestVersion = null;
    foreach ($releases as $release) {
      $version = is_array($release) ? ($release['version'] ?? null) : null;
      if (!is_string($version) || !$this->isStableVersion($version)) {
        continue;
      }

      $version = ltrim($version, 'vV');
      if ($latestVersion === null || version_compare($version, $latestVersion, '>')) {
        $latestVersion = $version;
      }
    }

    return $latestVersion;
  }

  private function isStableVersion(string $version): bool {
    return preg_match('/^v?\d+\.\d+\.\d+(?:\.\d+)?$/i', $version) === 1;
  }

  /** @return array<string, mixed> */
  private function readCache(): array {
    $path = $this->cachePath();
    if (!is_file($path)) {
      return [];
    }

    try {
      $cache = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
      return is_array($cache) ? $cache : [];
    }
    catch (\JsonException) {
      return [];
    }
  }

  /** @param array<string, mixed> $cache */
  private function isFresh(array $cache): bool {
    return is_int($cache['checkedAt'] ?? null)
      && $cache['checkedAt'] > time() - self::CACHE_TTL;
  }

  /** @param array<string, mixed> $cache */
  private function cachedVersion(array $cache): ?string {
    return is_string($cache['latestVersion'] ?? null) ? $cache['latestVersion'] : null;
  }

  /** @param array<string, mixed> $cache */
  private function cacheFailedCheck(array $cache): void {
    $cache['checkedAt'] = time();
    $this->writeCache($cache);
  }

  /** @param array<string, mixed> $cache */
  private function writeCache(array $cache): void {
    try {
      $this->filesystem->mkdir($this->appStoreService->getAppRootDir());
      $this->filesystem->dumpFile(
        $this->cachePath(),
        json_encode($cache, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL,
      );
    }
    catch (\Throwable) {
      // Update checks are best-effort and must never prevent a CLI command.
    }
  }

  private function cachePath(): string {
    return $this->appStoreService->getAppRootDir() . DIRECTORY_SEPARATOR . self::CACHE_FILE;
  }

  private function httpClient(): HttpClient {
    return $this->client ??= new HttpClient([
      'connect_timeout' => 1.0,
      'timeout' => 2.0,
      'headers' => [
        'Accept' => 'application/json',
        'User-Agent' => sprintf(
          'Drop-Context-CLI/%s (+https://dropcontext.dev; mailto=joaopauloctga@gmail.com)',
          Application::currentVersion(),
        ),
      ],
    ]);
  }

}
