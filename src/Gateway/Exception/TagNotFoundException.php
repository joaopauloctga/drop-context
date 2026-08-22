<?php

declare(strict_types=1);

namespace DropContext\Gateway\Exception;

/** No skill_tag term matches the given name. */
class TagNotFoundException extends \RuntimeException {

  /** @param string[] $suggestions */
  public function __construct(
    public readonly string $query,
    public readonly array $suggestions = [],
  ) {
    parent::__construct("Tag '{$query}' not found.");
  }

}
