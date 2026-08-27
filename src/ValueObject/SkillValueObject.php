<?php

namespace DropContext\ValueObject;

class SkillValueObject {

  public function __construct(
    public string $name,
    public array $agents,
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
