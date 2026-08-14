<?php

declare(strict_types=1);

namespace DrupalContext\Gateway\Dto;

/** Result of SkillQueryService::findOne(): exactly one hit, several ambiguous candidates, or fuzzy suggestions. */
final readonly class SkillLookupResult {

  /**
   * @param SkillRow[] $candidates
   * @param SkillRow[] $suggestions
   */
  public function __construct(
    public ?SkillRow $skill = null,
    public array $candidates = [],
    public array $suggestions = [],
  ) {}

  public function isFound(): bool {
    return $this->skill !== null;
  }

  public function isAmbiguous(): bool {
    return $this->skill === null && $this->candidates !== [];
  }

}
