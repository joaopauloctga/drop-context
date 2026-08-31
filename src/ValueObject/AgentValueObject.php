<?php

declare(strict_types=1);

namespace DropContext\ValueObject;

/** Registry entry for an installed AGENTS.md catalog agent. */
class AgentValueObject {

  public function __construct(
    public string $name,
    public string $path,
    public string $version,
    public ?int $created = null,
    public ?int $changed = null,
    public ?string $uuid = null,
  ) {
    $now = time();
    $this->created = $created ?? $now;
    $this->changed = $changed ?? $now;
  }

}
