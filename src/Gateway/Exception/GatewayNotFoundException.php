<?php

declare(strict_types=1);

namespace DropContext\Gateway\Exception;

/** A 404 from entity_gateway — either a missing entity or a missing route (unconfigured resource). */
class GatewayNotFoundException extends GatewayException {

  /** True when the 404 is Symfony's routing 404, i.e. the resource isn't exposed at all. */
  public function looksLikeMissingRoute(): bool {
    return str_contains(strtolower($this->getMessage()), 'no route found');
  }

}
