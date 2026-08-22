<?php

namespace DropContext\ValueObject;

class AppValueObject {

  public function __construct(
    public string $version,
    public int $created,
    public int $changed,
    /** @var ProjectValueObject[] */
    public array $projects = [],
    public array $skills = [],
    public array $agents = [],
    public array $codeAgents = [
      '.agents',
    ],
    public ?object $gateway = null,
  ) {}

}