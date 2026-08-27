<?php

declare(strict_types=1);

namespace DropContext\Cache;

use DropContext\Gateway\GatewayHttpInterface;
use DropContext\Gateway\GatewaySettings;
use Psr\Log\LoggerInterface;

/**
 * The primary cache-invalidation mechanism: a cheap periodic probe that
 * detects "something in the catalog changed" and purges the entry cache.
 *
 * Every STAMP_TTL seconds (persisted in stamp.json so it survives across MCP
 * sessions, not just within one), fetches `module_details?sort=-changed&
 * perPage=5` and `core_library?sort=-changed&perPage=5` — 5 rows, not 1,
 * because unpublished rows are filtered post-load, so the very newest row by
 * `changed` is not guaranteed to be the first one a client can see — always
 * bypassing the entry cache itself (via the uncached GatewayHttp, never
 * CachingGatewayHttp), and composes `md:<total>:<data[0].changed>|
 * cl:<total>:<data[0].changed>`. A different stamp than the one stored means
 * the catalog moved, so every cached entry is dropped; a matching stamp is a
 * no-op. A network failure during the probe is logged and swallowed — the
 * existing cache keeps serving rather than the whole tool call failing.
 */
final class CatalogStamp {

  public function __construct(
    private readonly GatewayHttpInterface $uncachedHttp,
    private readonly GatewaySettings $settings,
    private readonly JsonFileCache $cache,
    private readonly LoggerInterface $logger,
  ) {}

  public function checkAndMaybePurge(): void {
    if (!EnvFlags::l2Enabled()) {
      // Nothing persisted to invalidate.
      return;
    }

    $stamp = $this->cache->readStamp();
    $now = time();
    $checkedAt = is_array($stamp) ? (int) ($stamp['checkedAt'] ?? 0) : 0;
    if ($checkedAt > 0 && ($now - $checkedAt) < EnvFlags::stampTtl()) {
      return;
    }

    try {
      $current = $this->probe();
    }
    catch (\Throwable $e) {
      $this->logger->warning('catalog-stamp probe failed, serving existing cache: {message}', ['message' => $e->getMessage()]);
      return;
    }

    $previous = is_array($stamp) ? ($stamp['value'] ?? null) : null;
    if ($previous !== $current) {
      $this->logger->debug('catalog stamp changed ({previous} -> {current}), purging cached entries', [
        'previous' => $previous ?? '(none)',
        'current' => $current,
      ]);
      $this->cache->purgeEntries();
    }

    $this->cache->writeStamp(['value' => $current, 'checkedAt' => $now]);
  }

  private function probe(): string {
    return sprintf('md:%s|cl:%s', $this->probeOne('module_details'), $this->probeOne('core_library'));
  }

  private function probeOne(string $bundle): string {
    $url = rtrim($this->settings->getBaseUrl(), '/') . "/api/drupal_context/{$bundle}";
    $result = $this->uncachedHttp->get($url, ['sort' => '-changed', 'perPage' => 5], ['Accept' => 'application/json']);

    $decoded = json_decode($result->body, true);
    if (!is_array($decoded)) {
      return 'na';
    }

    $total = (int) ($decoded['total'] ?? 0);
    $changed = (string) ($decoded['data'][0]['changed'] ?? '');
    return "{$total}:{$changed}";
  }

}
