<?php

namespace DrupalContext\ValueObject;

class ProjectValueObject {

  public function __construct(
    public string $name,
    public string $path,
    public string $version,
    public array $skills = [],
    public array $agents = [],
  )
  {

  }

}