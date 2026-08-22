<?php

declare(strict_types=1);

namespace DropContext\Gateway\Exception;

/** No module_details entity matches the given machine name. */
class ModuleNotFoundException extends \RuntimeException {

  /** @param string[] $suggestions */
  public function __construct(
    public readonly string $query,
    public readonly array $suggestions = [],
  ) {
    parent::__construct("Module '{$query}' not found.");
  }

}
