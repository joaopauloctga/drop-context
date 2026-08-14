<?php

declare(strict_types=1);

namespace DrupalContext\Gateway;

use DrupalContext\Gateway\Dto\CollectionPage;
use DrupalContext\Gateway\Exception\GatewayAccessException;
use DrupalContext\Gateway\Exception\GatewayBadRequestException;
use DrupalContext\Gateway\Exception\GatewayConnectionException;
use DrupalContext\Gateway\Exception\GatewayException;
use DrupalContext\Gateway\Exception\GatewayNotFoundException;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;

/** Guzzle-backed entity_gateway JSON API client. Built lazily from GatewaySettings. */
class GatewayClient implements GatewayClientInterface {

  private ?HttpClient $client = null;

  public function __construct(
    private readonly GatewaySettings $settings,
  ) {}

  public function getCollection(string $entityType, string $bundle, ?Query $query = null): CollectionPage {
    $data = $this->request('GET', "/api/{$entityType}/{$bundle}", ($query ?? new Query())->toParams());
    return CollectionPage::fromArray($data);
  }

  public function getItem(string $entityType, string $bundle, string $uuid): array {
    return $this->request('GET', "/api/{$entityType}/{$bundle}/{$uuid}");
  }

  public function iterate(string $entityType, string $bundle, ?Query $query = null): \Generator {
    $base = ($query ?? new Query())->perPage(Query::MAX_PER_PAGE);
    $page = 1;

    while (true) {
      $collection = $this->getCollection($entityType, $bundle, $base->page($page));

      foreach ($collection->data as $row) {
        yield $row;
      }

      // Fall back to the requested page size when the server omits perPage
      // (CollectionPage defaults it to 0, which would never terminate), and
      // stop on any short page — it is necessarily the last one.
      $perPage = $collection->perPage > 0 ? $collection->perPage : Query::MAX_PER_PAGE;
      if ($collection->count < $perPage || $page * $perPage >= $collection->total) {
        break;
      }

      $page++;
    }
  }

  /** @return array<string, mixed> */
  private function request(string $method, string $path, array $query = []): array {
    try {
      $response = $this->httpClient()->request($method, $path, ['query' => $query]);
    }
    catch (ConnectException $e) {
      throw new GatewayConnectionException(
        "Cannot reach {$this->settings->getBaseUrl()} — is the site running / is DRUPAL_CONTEXT_URL correct?",
        previous: $e,
      );
    }
    catch (BadResponseException $e) {
      throw $this->toGatewayException($e);
    }
    catch (RequestException $e) {
      throw new GatewayConnectionException(
        "Cannot reach {$this->settings->getBaseUrl()} — {$e->getMessage()}",
        previous: $e,
      );
    }

    $data = self::decode((string) $response->getBody());
    if ($data === null) {
      throw new GatewayException(
        "Unexpected non-JSON response from {$this->settings->getBaseUrl()}{$path} — " .
        'is DRUPAL_CONTEXT_URL pointing at a Drupal Context site?',
      );
    }
    return $data;
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
      'base_uri' => $this->settings->getBaseUrl(),
      'headers' => ['Accept' => 'application/json'],
      'timeout' => 30,
      'verify' => $this->settings->tlsVerify(),
    ]);
  }

}
