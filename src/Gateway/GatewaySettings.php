<?php

declare(strict_types=1);

namespace DrupalContext\Gateway;

use DrupalContext\Service\AppStoreService;

/** Resolves the gateway base URL and TLS verification policy: env → app.json → default. */
class GatewaySettings {

  final public const string DEFAULT_BASE_URL = 'https://drupal-context.ddev.site';

  public function __construct(
    private readonly AppStoreService $appStoreService,
  ) {}

  public function getBaseUrl(): string {
    $env = getenv('DRUPAL_CONTEXT_URL');
    if (is_string($env) && $env !== '') {
      return rtrim($env, '/');
    }

    $configured = $this->appStoreService->getAppConfigFile()->gateway?->url ?? null;
    if (is_string($configured) && $configured !== '') {
      return rtrim($configured, '/');
    }

    return self::DEFAULT_BASE_URL;
  }

  /** Verify on by default, except for `.ddev.site` hosts (self-signed local certs). */
  public function tlsVerify(): bool {
    $env = getenv('DRUPAL_CONTEXT_TLS_VERIFY');
    if (is_string($env) && $env !== '') {
      return filter_var($env, FILTER_VALIDATE_BOOLEAN);
    }

    $configured = $this->appStoreService->getAppConfigFile()->gateway?->tlsVerify ?? null;
    if ($configured !== null) {
      return (bool) $configured;
    }

    $host = parse_url($this->getBaseUrl(), PHP_URL_HOST) ?: '';
    return !str_ends_with($host, '.ddev.site');
  }

}
