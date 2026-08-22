<?php

declare(strict_types=1);

namespace DropContext\Gateway\Exception;

/** Base exception for entity_gateway HTTP/protocol errors. Carries the HTTP status and server message. */
class GatewayException extends \RuntimeException {

  public function __construct(
    string $message,
    public readonly int $status = 0,
    ?\Throwable $previous = null,
  ) {
    parent::__construct($message, $status, $previous);
  }

}
