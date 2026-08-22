<?php

declare(strict_types=1);

namespace DropContext\Source\DropContext;

use DropContext\Gateway\Dto\SkillRow;
use DropContext\Gateway\SkillQueryService;
use DropContext\Service\AppStoreService;
use DropContext\Service\SkillFileWriter;
use DropContext\Source\SourceInterface;

/** Gateway-backed skill source: no ZIP, no temp files — reads the entity_gateway API directly. */
class DropContextSource implements SourceInterface {

  public function __construct(
    protected SkillQueryService $skillQueryService,
    protected SkillFileWriter $skillFileWriter,
    protected AppStoreService $appStoreService,
  ) {}

  public function downloadSkill(SkillRow $skill): string {
    return $this->skillFileWriter->write($skill, $this->appStoreService->getAppSkillsDir());
  }

  public function getSkillList(?string $module = null): array {
    return $this->skillQueryService->search(module: $module);
  }

}
