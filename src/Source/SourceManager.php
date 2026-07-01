<?php

namespace DrupalContext\Source;

use DrupalContext\Source\DrupalContext\DrupalContextSource;
use DrupalContext\Source\SkilsSH\SkillsSHSource;

class SourceManager {

  public function __construct(
    private DrupalContextSource $drupalContextSource,
    private SkillsSHSource $skillsSHSource,
  ) {}

  public function getSourceHandler(string $source): SourceInterface {
    return match ($source) {
      'drupal-context' => $this->drupalContextSource,
      'skill.sh' => $this->skillsSHSource,
      default => throw new \Exception("Source $source not found"),
    };
  }

}