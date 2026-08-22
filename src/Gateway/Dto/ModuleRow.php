<?php

declare(strict_types=1);

namespace DropContext\Gateway\Dto;

/** A module_details row. */
final readonly class ModuleRow implements \JsonSerializable {

  public function __construct(
    public string $uuid,
    public string $machineName,
    public string $title,
    public ?string $maintenanceStatus,
    public ?string $developmentStatus,
    public bool $isCovered,
  ) {}

  /** @param array<string, mixed> $data */
  public static function fromArray(array $data): self {
    return new self(
      uuid: (string) ($data['uuid'] ?? ''),
      machineName: (string) ($data['machineName'] ?? ''),
      title: (string) ($data['title'] ?? ''),
      maintenanceStatus: isset($data['maintenanceStatus']) ? (string) $data['maintenanceStatus'] : null,
      developmentStatus: isset($data['developmentStatus']) ? (string) $data['developmentStatus'] : null,
      isCovered: (bool) ($data['isCovered'] ?? false),
    );
  }

  public function jsonSerialize(): array {
    return [
      'uuid' => $this->uuid,
      'machineName' => $this->machineName,
      'title' => $this->title,
      'maintenanceStatus' => $this->maintenanceStatus,
      'developmentStatus' => $this->developmentStatus,
      'isCovered' => $this->isCovered,
    ];
  }

}
