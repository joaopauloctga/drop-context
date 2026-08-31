<?php

declare(strict_types=1);

namespace DropContext\ValueObject;

use DropContext\AgentUpdateState;
use DropContext\Gateway\Dto\AgentRow;

final readonly class InstalledAgentStatus implements \JsonSerializable {

  public function __construct(
    public string $name,
    public AgentUpdateState $state,
    public ?string $localVersion,
    public ?string $remoteVersion,
    public ?string $uuid,
    public ?AgentRow $remoteAgent = null,
    public ?string $detail = null,
  ) {}

  public function hasUpdate(): bool {
    return $this->state === AgentUpdateState::UPDATE_AVAILABLE;
  }

  public function jsonSerialize(): array {
    return [
      'name' => $this->name,
      'state' => $this->state->value,
      'label' => $this->state->label(),
      'localVersion' => $this->localVersion,
      'remoteVersion' => $this->remoteVersion,
      'uuid' => $this->uuid,
      'detail' => $this->detail,
    ];
  }

}
