<?php

declare(strict_types=1);

namespace DropContext\Gateway\Dto;

/**
 * One entry of a skill's `field_reference_files` file field.
 *
 * The site stores skill references as managed **files**, not as inlined text:
 * the API hands out a download URL plus metadata, and the content is fetched
 * over HTTP by FileDownloader when the skill is installed.
 */
final readonly class SkillReference implements \JsonSerializable {

  public function __construct(
    public string $uuid,
    public ?string $filename,
    public ?string $url,
    public ?string $mime,
    public ?int $size,
    public ?string $description,
  ) {}

  /** @param array<string, mixed> $data */
  public static function fromArray(array $data): self {
    return new self(
      uuid: (string) ($data['uuid'] ?? ''),
      filename: isset($data['filename']) ? (string) $data['filename'] : null,
      url: isset($data['url']) ? (string) $data['url'] : null,
      mime: isset($data['mime']) ? (string) $data['mime'] : null,
      size: isset($data['size']) ? (int) $data['size'] : null,
      description: isset($data['description']) ? (string) $data['description'] : null,
    );
  }

  /** The name to write on disk — `filename`, falling back to the per-usage description. */
  public function targetName(): ?string {
    foreach ([$this->filename, $this->description] as $candidate) {
      if (is_string($candidate) && trim($candidate) !== '') {
        return basename(str_replace('\\', '/', trim($candidate)));
      }
    }
    return null;
  }

  public function jsonSerialize(): array {
    return [
      'uuid' => $this->uuid,
      'filename' => $this->filename,
      'url' => $this->url,
      'mime' => $this->mime,
      'size' => $this->size,
      'description' => $this->description,
    ];
  }

}
