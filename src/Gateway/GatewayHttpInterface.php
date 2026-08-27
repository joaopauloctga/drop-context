<?php

declare(strict_types=1);

namespace DropContext\Gateway;

use DropContext\Gateway\Dto\HttpResult;

/** Low-level HTTP GET seam shared by GatewayClient (JSON API) and FileDownloader (raw files) — the layer CachingGatewayHttp decorates. */
interface GatewayHttpInterface {

  /**
   * @param array<string, mixed> $query
   * @param array<string, string> $headers
   *
   * @throws \DropContext\Gateway\Exception\GatewayException
   */
  public function get(string $url, array $query = [], array $headers = []): HttpResult;

}
