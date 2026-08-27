<?php

declare(strict_types=1);

namespace DropContext\ValueObject;

use DropContext\Gateway\Dto\SkillRow;
use DropContext\SkillUpdateState;

final readonly class InstalledSkillStatus implements \JsonSerializable {

  /**
   * @param array<string, string> $paths
   *   Installed paths keyed by code agent directory.
   */
  public function __construct(
    public string $name,
    public ?string $uuid,
    public string $installedVersion,
    public ?string $latestVersion,
    public SkillUpdateState $state,
    public array $paths,
    public ?SkillRow $remoteSkill = null,
    public ?string $message = null,
  ) {}

  public function hasUpdate(): bool {
    return $this->state === SkillUpdateState::UPDATE_AVAILABLE && $this->remoteSkill !== null;
  }

  public function jsonSerialize(): array {
    return [
      'name' => $this->name,
      'uuid' => $this->uuid,
      'installedVersion' => $this->installedVersion,
      'latestVersion' => $this->latestVersion,
      'status' => $this->state->value,
      'agents' => array_keys($this->paths),
      'message' => $this->message,
    ];
  }

}
