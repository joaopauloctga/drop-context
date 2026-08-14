<?php

declare(strict_types=1);

namespace DrupalContext\Gateway\Dto;

/** A skill_tag term. `taxonomy_term/skill_tag` rows use `name`; inlined `skillTags` entries use `label`. */
final readonly class TagRow implements \JsonSerializable {

  public function __construct(
    public string $uuid,
    public string $name,
  ) {}

  /** @param array<string, mixed> $data */
  public static function fromArray(array $data): self {
    return new self(
      uuid: (string) ($data['uuid'] ?? ''),
      name: (string) ($data['name'] ?? $data['label'] ?? ''),
    );
  }

  public function jsonSerialize(): array {
    return [
      'uuid' => $this->uuid,
      'name' => $this->name,
    ];
  }

}
