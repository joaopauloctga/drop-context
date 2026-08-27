<?php

declare(strict_types=1);

namespace DropContext\Gateway\Dto;

/** A raw HTTP response from GatewayHttpInterface::get(): status, headers and body — no JSON decoding. */
final readonly class HttpResult {

  /** @param array<string, string[]> $headers header name => values, as returned by the HTTP client (case as sent by the server) */
  public function __construct(
    public int $status,
    public array $headers,
    public string $body,
  ) {}

  /** Case-insensitive single header lookup; the first value when repeated. */
  public function header(string $name): ?string {
    foreach ($this->headers as $key => $values) {
      if (strcasecmp($key, $name) === 0) {
        return $values[0] ?? null;
      }
    }
    return null;
  }

}
