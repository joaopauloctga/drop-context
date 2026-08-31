<?php

declare(strict_types=1);

namespace DropContext\Gateway\Dto;

/** An agent row from the `drupal_context/agent` resource. */
final readonly class AgentRow implements \JsonSerializable {

  /**
   * @param array<int, array{uuid: string, title?: string, label?: string, machineName?: string}> $modules
   * @param array<int, array{uuid: string, label?: string}> $relatedSkills
   * @param array<int, array{uuid: string, label?: string}> $relatedCoreLibraries
   * @param SkillReference[] $references
   */
  public function __construct(
    public string $uuid,
    public string $title,
    public ?string $machineName,
    public bool $isDropContext,
    public ?bool $status,
    public ?string $content,
    public ?string $description,
    public bool $private,
    public array $modules,
    public array $relatedSkills,
    public array $relatedCoreLibraries,
    public array $references,
    public bool $referencesResolved,
    public ?string $path,
    public ?string $changed,
    public ?string $version,
  ) {}

  /** @param array<string, mixed> $data */
  public static function fromArray(array $data): self {
    $rawReferences = $data['referenceFiles'] ?? [];
    $references = [];
    $referencesResolved = true;
    foreach ($rawReferences as $reference) {
      if (!is_array($reference) || ($reference['url'] ?? '') === '') {
        $referencesResolved = false;
        continue;
      }
      $references[] = SkillReference::fromArray($reference);
    }

    return new self(
      uuid: (string) ($data['uuid'] ?? ''),
      title: (string) ($data['title'] ?? ''),
      machineName: isset($data['machineName']) && (string) $data['machineName'] !== ''
        ? (string) $data['machineName']
        : null,
      isDropContext: (bool) ($data['isDropContext'] ?? false),
      status: isset($data['status']) ? (bool) $data['status'] : null,
      content: self::textValue($data['content'] ?? null),
      description: self::textSummary($data['content'] ?? null),
      private: (bool) ($data['agentPrivate'] ?? false),
      modules: self::refList($data['modules'] ?? []),
      relatedSkills: self::refList($data['relatedSkills'] ?? []),
      relatedCoreLibraries: self::refList($data['relatedCoreLibraries'] ?? []),
      references: $references,
      referencesResolved: $referencesResolved,
      path: isset($data['path']) ? (string) $data['path'] : null,
      changed: isset($data['changed']) ? (string) $data['changed'] : null,
      version: isset($data['agentVersion']) ? (string) $data['agentVersion'] : null,
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

  private static function textSummary(mixed $value): ?string {
    if (!is_array($value)) {
      return null;
    }
    if (isset($value['summary']) && (string) $value['summary'] !== '') {
      return (string) $value['summary'];
    }
    return null;
  }

  /**
   * @param mixed $value
   * @return array<int, array{uuid: string, title?: string, label?: string, machineName?: string}>
   */
  private static function refList(mixed $value): array {
    if (!is_array($value)) {
      return [];
    }
    $rows = [];
    foreach ($value as $item) {
      if (is_string($item) && $item !== '') {
        $rows[] = ['uuid' => $item];
        continue;
      }
      if (!is_array($item)) {
        continue;
      }
      $uuid = (string) ($item['uuid'] ?? '');
      if ($uuid === '') {
        continue;
      }
      $row = ['uuid' => $uuid];
      if (isset($item['title'])) {
        $row['title'] = (string) $item['title'];
      }
      if (isset($item['label'])) {
        $row['label'] = (string) $item['label'];
      }
      if (isset($item['machineName'])) {
        $row['machineName'] = (string) $item['machineName'];
      }
      $rows[] = $row;
    }
    return $rows;
  }

  public function jsonSerialize(): array {
    return [
      'uuid' => $this->uuid,
      'title' => $this->title,
      'machineName' => $this->machineName,
      'isDropContext' => $this->isDropContext,
      'status' => $this->status,
      'description' => $this->description,
      'private' => $this->private,
      'modules' => $this->modules,
      'relatedSkills' => $this->relatedSkills,
      'relatedCoreLibraries' => $this->relatedCoreLibraries,
      'references' => $this->references,
      'path' => $this->path,
      'changed' => $this->changed,
      'version' => $this->version,
    ];
  }

  /** Preferred CLI / cache key: machine name when present, else title. */
  public function installKey(): string {
    return ($this->machineName !== null && $this->machineName !== '')
      ? $this->machineName
      : $this->title;
  }

}
