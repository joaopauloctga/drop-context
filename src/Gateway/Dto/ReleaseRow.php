<?php

declare(strict_types=1);

namespace DrupalContext\Gateway\Dto;

/** A module_release row. `module` is the bare target UUID of the module_details entity. */
final readonly class ReleaseRow implements \JsonSerializable {

  public function __construct(
    public string $uuid,
    public string $title,
    public ?string $moduleUuid,
    public ?string $releaseTag,
    public ?string $releaseLine,
  ) {}

  /** @param array<string, mixed> $data */
  public static function fromArray(array $data): self {
    return new self(
      uuid: (string) ($data['uuid'] ?? ''),
      title: (string) ($data['title'] ?? ''),
      moduleUuid: isset($data['module']) ? (string) $data['module'] : null,
      releaseTag: isset($data['releaseTag']) ? (string) $data['releaseTag'] : null,
      releaseLine: isset($data['releaseLine']) ? (string) $data['releaseLine'] : null,
    );
  }

  public function jsonSerialize(): array {
    return [
      'uuid' => $this->uuid,
      'title' => $this->title,
      'module' => $this->moduleUuid,
      'releaseTag' => $this->releaseTag,
      'releaseLine' => $this->releaseLine,
    ];
  }

}
