<?php

namespace DropContext\ValueObject;

class RunnableProjectValueObject {

  public function __construct(
    public string $name,
    public string $path,
  ) {

  }

}