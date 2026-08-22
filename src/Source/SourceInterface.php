<?php

declare(strict_types=1);

namespace DropContext\Source;

use DropContext\Gateway\Dto\SkillRow;

interface SourceInterface {

  /** Writes the resolved skill's files to the app cache. @return string the cached skill directory path */
  public function downloadSkill(SkillRow $skill): string;

  /** @return SkillRow[] */
  public function getSkillList(?string $module = null): array;

}
