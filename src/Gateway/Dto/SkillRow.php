<?php

declare(strict_types=1);

namespace DropContext\Gateway\Dto;

/** A skill row. Handles the text-field shape duality (bare string vs {value,summary}) and inlined tags/references. */
final readonly class SkillRow implements \JsonSerializable {

  /**
   * @param TagRow[] $tags
   * @param SkillReference[] $references
   */
  public function __construct(
    public string $uuid,
    public string $title,
    public ?bool $status,
    public ?string $content,
    public ?string $description,
    public bool $private,
    public ?string $type,
    public ?string $moduleReleaseUuid,
    public array $tags,
    public array $references,
    public bool $referencesResolved,
    public ?string $path,
    public ?string $changed,
    public ?string $version,
  ) {}

  /** @param array<string, mixed> $data */
  public static function fromArray(array $data): self {
    // `referenceFiles` is the API key for the skill's `field_reference_files`
    // file field — a list of {uuid, url, filename, mime, size, description}.
    $rawReferences = $data['referenceFiles'] ?? [];
    $references = [];
    $referencesResolved = true;
    foreach ($rawReferences as $reference) {
      if (!is_array($reference) || ($reference['url'] ?? '') === '') {
        // No download URL: the site is exposing field_reference_files through
        // the wrong entity_gateway field output plugin (it must be `file`),
        // so every item normalizes to a bare stub or an empty object.
        $referencesResolved = false;
        continue;
      }
      $references[] = SkillReference::fromArray($reference);
    }

    $rawTags = $data['skillTags'] ?? [];
    $tags = array_values(array_map(
      static fn (array $tag): TagRow => TagRow::fromArray($tag),
      array_filter($rawTags, 'is_array'),
    ));

    return new self(
      uuid: (string) ($data['uuid'] ?? ''),
      title: (string) ($data['title'] ?? ''),
      status: isset($data['status']) ? (bool) $data['status'] : null,
      content: self::textValue($data['content'] ?? null),
      description: self::textValue($data['skillDescription'] ?? null),
      private: (bool) ($data['skillPrivate'] ?? false),
      type: isset($data['skillType']) ? (string) $data['skillType'] : null,
      moduleReleaseUuid: self::refUuid($data['moduleRelease'] ?? null),
      tags: $tags,
      references: $references,
      referencesResolved: $referencesResolved,
      path: isset($data['path']) ? (string) $data['path'] : null,
      changed: isset($data['changed']) ? (string) $data['changed'] : null,
      version: isset($data['skillVersion']) ? (string) $data['skillVersion'] : null,
    );
  }

  private static function textValue(mixed $value): ?string {
    if ($value === null) {
      return null;
    }
    if (is_array($value)) {
      return isset($value['value']) ? (string) $value['value'] : (string) ($value['processed'] ?? '');
    }
    return (string) $value;
  }

  private static function refUuid(mixed $value): ?string {
    if ($value === null) {
      return null;
    }
    if (is_array($value)) {
      return isset($value['uuid']) ? (string) $value['uuid'] : null;
    }
    return (string) $value;
  }

  public function jsonSerialize(): array {
    return [
      'uuid' => $this->uuid,
      'title' => $this->title,
      'status' => $this->status,
      'type' => $this->type,
      'description' => $this->description,
      'private' => $this->private,
      'moduleRelease' => $this->moduleReleaseUuid,
      'tags' => $this->tags,
      'references' => $this->references,
      'path' => $this->path,
      'changed' => $this->changed,
      'version' => $this->version,
    ];
  }

}
