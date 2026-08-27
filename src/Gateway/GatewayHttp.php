<?php

declare(strict_types=1);

namespace DropContext\Gateway;

use DropContext\Gateway\Dto\HttpResult;
use DropContext\Gateway\Exception\GatewayAccessException;
use DropContext\Gateway\Exception\GatewayBadRequestException;
use DropContext\Gateway\Exception\GatewayConnectionException;
use DropContext\Gateway\Exception\GatewayException;
use DropContext\Gateway\Exception\GatewayNotFoundException;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;

/**
 * Guzzle-backed GET fetcher. Uncached — the seam GatewayClient and FileDownloader
 * both go through, and the layer CachingGatewayHttp decorates for the MCP server.
 *
 * URL-agnostic: callers pass a full absolute URL (GatewayClient builds one from
 * GatewaySettings + path; FileDownloader's URLs already come absolute from the
 * gateway's own file field output). A 3xx status (e.g. a 304 from a conditional
 * GET) is not an error to Guzzle's default http_errors handling and is returned
 * as a normal HttpResult; only 4xx/5xx map to a GatewayException.
 */
class GatewayHttp implements GatewayHttpInterface {

  private ?HttpClient $client = null;

  public function __construct(
    private readonly GatewaySettings $settings,
  ) {}

  public function get(string $url, array $query = [], array $headers = []): HttpResult {
    try {
      $response = $this->httpClient()->request('GET', $url, ['query' => $query, 'headers' => $headers]);
    }
    catch (ConnectException $e) {
      throw new GatewayConnectionException(
        "Cannot reach {$url} — is the site running / is DROP_CONTEXT_URL correct?",
        previous: $e,
      );
    }
    catch (BadResponseException $e) {
      throw $this->toGatewayException($e);
    }
    catch (RequestException $e) {
      throw new GatewayConnectionException("Cannot reach {$url} — {$e->getMessage()}", previous: $e);
    }

    return new HttpResult(
      status: $response->getStatusCode(),
      headers: $response->getHeaders(),
      body: (string) $response->getBody(),
    );
  }

  private function toGatewayException(BadResponseException $e): GatewayException {
    $response = $e->getResponse();
    $status = $response->getStatusCode();
    // A non-JSON error body (HTML 404 page, proxy 502) must still map to a
    // GatewayException — fall back to Guzzle's message.
    $body = self::decode((string) $response->getBody()) ?? [];
    $message = is_string($body['message'] ?? null) ? $body['message'] : $e->getMessage();

    return match (true) {
      $status === 404 => new GatewayNotFoundException($message, $status, $e),
      $status === 400 => new GatewayBadRequestException($message, $status, $e),
      $status === 401 || $status === 403 => new GatewayAccessException($message, $status, $e),
      default => new GatewayException($message, $status, $e),
    };
  }

  /** @return array<string, mixed>|null null when the body is not valid JSON (or not a JSON object/array) */
  private static function decode(string $body): ?array {
    if (trim($body) === '') {
      return [];
    }
    $decoded = json_decode($body, true);
    return is_array($decoded) ? $decoded : null;
  }

  private function httpClient(): HttpClient {
    return $this->client ??= new HttpClient([
      'timeout' => 30,
      'verify' => $this->settings->tlsVerify(),
    ]);
  }

}
