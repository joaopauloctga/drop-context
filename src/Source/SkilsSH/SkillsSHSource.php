<?php

declare(strict_types=1);

namespace DropContext\Source\SkilsSH;

use DropContext\Gateway\Dto\SkillRow;
use DropContext\Source\SourceInterface;

class SkillsSHSource implements SourceInterface {

  public function downloadSkill(SkillRow $skill): string {
    throw new \RuntimeException('skill.sh source is not implemented yet.');
  }

  public function getSkillList(?string $module = null): array {
    throw new \RuntimeException('skill.sh source is not implemented yet.');
  }

}
