<?php

declare(strict_types=1);

namespace DropContext\Gateway;

use DropContext\Gateway\Exception\GatewayException;

/**
 * Fetches a file the gateway pointed at by absolute URL.
 *
 * Separate from GatewayClient: that one decodes JSON, while managed-file URLs
 * are absolute, served off the site's public files directory, and return raw
 * bytes. Both are built over GatewayHttpInterface, so they share one
 * exception-mapping and one cache-decoration point.
 */
class FileDownloader {

  public function __construct(
    private readonly GatewayHttpInterface $http,
  ) {}

  /** @return string the response body */
  public function download(string $url): string {
    if (!preg_match('#^https?://#i', $url)) {
      throw new GatewayException('Refusing to download a reference file from a non-http(s) URL.');
    }

    return $this->http->get($url)->body;
  }

}
