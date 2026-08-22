<?php

namespace DropContext\Source;

use DropContext\Source\DropContext\DropContextSource;
use DropContext\Source\SkilsSH\SkillsSHSource;

class SourceManager {

  public function __construct(
    private DropContextSource $dropContextSource,
    private SkillsSHSource $skillsSHSource,
  ) {}

  public function getSourceHandler(string $source): SourceInterface {
    return match ($source) {
      'drop-context' => $this->dropContextSource,
      'skill.sh' => $this->skillsSHSource,
      default => throw new \Exception("Source $source not found"),
    };
  }

}