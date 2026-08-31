<?php

declare(strict_types=1);

namespace DropContext\Gateway\Dto;

/** Result of AgentQueryService::findOne(). */
final readonly class AgentLookupResult {

  /**
   * @param AgentRow[] $candidates
   * @param AgentRow[] $suggestions
   */
  public function __construct(
    public ?AgentRow $agent = null,
    public array $candidates = [],
    public array $suggestions = [],
  ) {}

  public function isFound(): bool {
    return $this->agent !== null;
  }

  public function isAmbiguous(): bool {
    return $this->agent === null && $this->candidates !== [];
  }

}
