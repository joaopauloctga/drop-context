<?php

declare(strict_types=1);

namespace DropContext\Gateway;

use DropContext\Gateway\Exception\GatewayConnectionException;
use DropContext\Gateway\Exception\GatewayException;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;

/**
 * Fetches a file the gateway pointed at by absolute URL.
 *
 * Separate from GatewayClient: that one is bound to the API's base_uri and
 * decodes JSON, while managed-file URLs are absolute, served off the site's
 * public files directory, and return raw bytes.
 */
class FileDownloader {

  private ?HttpClient $client = null;

  public function __construct(
    private readonly GatewaySettings $settings,
  ) {}

  /** @return string the response body */
  public function download(string $url): string {
    if (!preg_match('#^https?://#i', $url)) {
      throw new GatewayException("Refusing to download '{$url}' — not an http(s) URL.");
    }

    try {
      $response = $this->httpClient()->request('GET', $url);
    }
    catch (ConnectException $e) {
      throw new GatewayConnectionException("Cannot reach {$url} — is the site running?", previous: $e);
    }
    catch (BadResponseException $e) {
      throw new GatewayException(
        "Download of {$url} failed with HTTP {$e->getResponse()->getStatusCode()}.",
        $e->getResponse()->getStatusCode(),
        $e,
      );
    }
    catch (RequestException $e) {
      throw new GatewayConnectionException("Cannot reach {$url} — {$e->getMessage()}", previous: $e);
    }

    return (string) $response->getBody();
  }

  private function httpClient(): HttpClient {
    return $this->client ??= new HttpClient([
      'timeout' => 30,
      'verify' => $this->settings->tlsVerify(),
    ]);
  }

}
