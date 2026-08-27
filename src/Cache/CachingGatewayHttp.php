<?php

declare(strict_types=1);

namespace DropContext\Cache;

use DropContext\Gateway\Dto\HttpResult;
use DropContext\Gateway\Exception\GatewayConnectionException;
use DropContext\Gateway\GatewayHttpInterface;
use Psr\Log\LoggerInterface;

/**
 * Decorates GatewayHttpInterface with the JSON-file cache. Injected only into
 * the MCP server's own GatewayClient/FileDownloader instances (see
 * services.yaml) — the `skill:*`/`module:*` commands keep using the plain,
 * uncached GatewayHttp.
 *
 * Read policy per call:
 *  1. `now < expiresAt` on the cached entry → serve it, no request at all.
 *  2. Expired but the entry carries an ETag/Last-Modified → conditional GET
 *     (both headers, when present). A 304 renews freshness and keeps the old
 *     body; any other status replaces the entry.
 *  3. Expired with no validators (local dev, `cache.page.max_age: 0`) →
 *     ordinary GET.
 *  4. A network failure with a (possibly expired) entry on hand → serve that
 *     stale entry and log a warning, rather than failing the tool call; no
 *     entry at all → the exception propagates.
 *
 * The catalog-changed stamp (CatalogStamp) is consulted first on every call —
 * it is the primary invalidation mechanism and is what makes this safe to
 * serve stale-looking entries from cheaply in the common case.
 */
final class CachingGatewayHttp implements GatewayHttpInterface {

  public function __construct(
    private readonly GatewayHttpInterface $inner,
    private readonly JsonFileCache $cache,
    private readonly CatalogStamp $stamp,
    private readonly LoggerInterface $logger,
  ) {}

  public function get(string $url, array $query = [], array $headers = []): HttpResult {
    $this->stamp->checkAndMaybePurge();

    $key = self::cacheKey($url, $query);
    $cached = $this->cache->get($key);
    $now = time();

    if (is_array($cached) && (int) ($cached['expiresAt'] ?? 0) > $now) {
      $this->logger->debug('cache hit: {url}', ['url' => $url]);
      return self::resultFromEntry($cached);
    }

    $conditionalHeaders = $headers;
    if (is_array($cached)) {
      if (!empty($cached['etag'])) {
        $conditionalHeaders['If-None-Match'] = (string) $cached['etag'];
      }
      if (!empty($cached['lastModified'])) {
        $conditionalHeaders['If-Modified-Since'] = (string) $cached['lastModified'];
      }
    }

    try {
      $result = $this->inner->get($url, $query, $conditionalHeaders);
    }
    catch (GatewayConnectionException $e) {
      if (is_array($cached)) {
        $this->logger->warning('network error, serving stale cache for {url}: {message}', [
          'url' => $url,
          'message' => $e->getMessage(),
        ]);
        return self::resultFromEntry($cached);
      }
      throw $e;
    }

    if ($result->status === 304 && is_array($cached)) {
      $this->logger->debug('revalidated (304): {url}', ['url' => $url]);
      $this->store($key, $url, (string) ($cached['body'] ?? ''), $result, $now);
      return self::resultFromEntry(['body' => $cached['body'] ?? '']);
    }

    $this->logger->debug('cache miss, fetched: {url}', ['url' => $url]);
    $this->store($key, $url, $result->body, $result, $now);
    return $result;
  }

  private function store(string $key, string $url, string $body, HttpResult $result, int $now): void {
    $serverMaxAge = self::serverMaxAge($result);
    $ttl = EnvFlags::ttl();
    $maxAge = ($serverMaxAge !== null && $serverMaxAge > 0) ? min($serverMaxAge, $ttl) : $ttl;

    $this->cache->set($key, [
      'key' => $key,
      'url' => $url,
      'fetchedAt' => $now,
      'expiresAt' => $now + $maxAge,
      'etag' => $result->header('ETag'),
      'lastModified' => $result->header('Last-Modified'),
      'body' => $body,
    ]);
  }

  private static function serverMaxAge(HttpResult $result): ?int {
    $cacheControl = $result->header('Cache-Control');
    if ($cacheControl === null || preg_match('/max-age=(\d+)/', $cacheControl, $m) !== 1) {
      return null;
    }
    $value = (int) $m[1];
    return $value > 0 ? $value : null;
  }

  /** @param array<string, mixed> $entry */
  private static function resultFromEntry(array $entry): HttpResult {
    return new HttpResult(200, [], (string) ($entry['body'] ?? ''));
  }

  /** @param array<string, mixed> $query */
  private static function cacheKey(string $url, array $query): string {
    ksort($query);
    return $url . '?' . http_build_query($query);
  }

}
