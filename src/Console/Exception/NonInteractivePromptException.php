<?php

declare(strict_types=1);

namespace DrupalContext\Console\Exception;

/** Thrown when Ui::select()/multiselect() is invoked in a non-interactive run. */
class NonInteractivePromptException extends \RuntimeException {

  public function __construct(public readonly string $label) {
    parent::__construct("Cannot prompt '{$label}' in a non-interactive run.");
  }

}
