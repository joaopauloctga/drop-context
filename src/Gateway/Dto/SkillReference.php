<?php

declare(strict_types=1);

namespace DropContext\Gateway\Dto;

/** One inlined skill_reference paragraph: {uuid, referenceFilename, content}. */
final readonly class SkillReference implements \JsonSerializable {

  public function __construct(
    public string $uuid,
    public ?string $filename,
    public string $content,
  ) {}

  /** @param array<string, mixed> $data */
  public static function fromArray(array $data): self {
    return new self(
      uuid: (string) ($data['uuid'] ?? ''),
      filename: isset($data['referenceFilename']) ? (string) $data['referenceFilename'] : null,
      content: self::textValue($data['content'] ?? ''),
    );
  }

  private static function textValue(mixed $value): string {
    if (is_array($value)) {
      return (string) ($value['value'] ?? $value['processed'] ?? '');
    }
    return (string) $value;
  }

  public function jsonSerialize(): array {
    return [
      'uuid' => $this->uuid,
      'filename' => $this->filename,
      'content' => $this->content,
    ];
  }

}
