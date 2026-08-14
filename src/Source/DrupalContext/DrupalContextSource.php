<?php

declare(strict_types=1);

namespace DrupalContext\Source\DrupalContext;

use DrupalContext\Gateway\Dto\SkillRow;
use DrupalContext\Gateway\SkillQueryService;
use DrupalContext\Service\AppStoreService;
use DrupalContext\Service\SkillFileWriter;
use DrupalContext\Source\SourceInterface;

/** Gateway-backed skill source: no ZIP, no temp files — reads the entity_gateway API directly. */
class DrupalContextSource implements SourceInterface {

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
