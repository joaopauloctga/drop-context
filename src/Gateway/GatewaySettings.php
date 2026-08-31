<?php

declare(strict_types=1);

namespace DropContext\Gateway;

/**
 * The gateway defaults to Drop Context's production site.
 *
 * `DROP_CONTEXT_URL` overrides the base URL and `DROP_CONTEXT_TLS_VERIFY=0`
 * disables TLS verification — both already referenced by `.mcp.json` files
 * in the wild, wired here so they actually take effect.
 */
class GatewaySettings {

  final public const string DEFAULT_BASE_URL = 'https://dropcontext.dev';

  public function getBaseUrl(): string {
    $url = getenv('DROP_CONTEXT_URL');
    return is_string($url) && trim($url) !== '' ? rtrim($url, '/') : self::DEFAULT_BASE_URL;
  }

  public function tlsVerify(): bool {
    $verify = getenv('DROP_CONTEXT_TLS_VERIFY');
    return $verify !== '0';
  }

}
