<?php

declare(strict_types=1);

namespace DropContext\Gateway;

use DropContext\Gateway\Dto\SkillLookupResult;
use DropContext\Gateway\Dto\SkillRow;
use DropContext\Gateway\Exception\ModuleNotFoundException;
use DropContext\Gateway\Exception\TagNotFoundException;
use DropContext\Gateway\Repository\ModuleRepository;
use DropContext\Gateway\Repository\ReleaseRepository;
use DropContext\Gateway\Repository\SkillRepository;
use DropContext\Gateway\Repository\TagRepository;

/** Joins skill/module_release/module_details/skill_tag into the queries the CLI needs. */
class SkillQueryService {

  public function __construct(
    private readonly SkillRepository $skills,
    private readonly ModuleRepository $modules,
    private readonly ReleaseRepository $releases,
    private readonly TagRepository $tags,
  ) {}

  /**
   * @throws ModuleNotFoundException
   * @throws TagNotFoundException
   * @return SkillRow[]
   */
  public function search(?string $text = null, ?string $module = null, ?string $tag = null, ?string $type = null): array {
    $releaseUuids = [];

    if ($module !== null && $module !== '') {
      $moduleRow = $this->modules->findByMachineName($module);
      if ($moduleRow === null) {
        throw new ModuleNotFoundException($module, $this->suggestModules($module));
      }
      $releaseUuids = array_map(
        static fn ($release) => $release->uuid,
        $this->releases->findByModuleUuid($moduleRow->uuid),
      );
      if ($releaseUuids === []) {
        return [];
      }
    }

    $tagUuid = null;
    if ($tag !== null && $tag !== '') {
      $tagRow = $this->tags->findByName($tag);
      if ($tagRow === null) {
        throw new TagNotFoundException($tag, $this->suggestTags());
      }
      $tagUuid = $tagRow->uuid;
    }

    return $this->skills->search(text: $text, type: $type, tagUuid: $tagUuid, releaseUuids: $releaseUuids);
  }

  /**
   * Batch-resolves each skill's module_release → {module, release} for table columns.
   * Two extra requests total, regardless of row count.
   *
   * @param SkillRow[] $skillRows
   * @return array<string, array{module: string, release: string}> keyed by skill uuid
   */
  public function resolveModuleColumns(array $skillRows): array {
    $releaseUuids = array_values(array_unique(array_filter(
      array_map(static fn (SkillRow $skill) => $skill->moduleReleaseUuid, $skillRows),
    )));
    if ($releaseUuids === []) {
      return [];
    }

    $releases = $this->releases->findByUuids($releaseUuids);

    $moduleUuids = array_values(array_unique(array_filter(
      array_map(static fn ($release) => $release->moduleUuid, $releases),
    )));
    $modules = $moduleUuids !== [] ? $this->modules->findByUuids($moduleUuids) : [];

    $columns = [];
    foreach ($skillRows as $skill) {
      $release = $skill->moduleReleaseUuid !== null ? ($releases[$skill->moduleReleaseUuid] ?? null) : null;
      $module = $release?->moduleUuid !== null ? ($modules[$release->moduleUuid] ?? null) : null;
      $columns[$skill->uuid] = [
        'module' => $module?->machineName ?? '',
        'release' => $release?->releaseTag ?? '',
      ];
    }
    return $columns;
  }

  /** UUID-shaped input matches by `filter[uuid]=`, else exact title. 0 hits → suggestions, >1 → ambiguous candidates. */
  public function findOne(string $nameOrUuid): SkillLookupResult {
    if (self::looksLikeUuid($nameOrUuid)) {
      $matches = $this->skills->findByUuid($nameOrUuid);
      if (count($matches) === 1) {
        return new SkillLookupResult(skill: $matches[0]);
      }
      return new SkillLookupResult(suggestions: $this->skills->suggest($nameOrUuid));
    }

    $matches = $this->skills->findByTitle($nameOrUuid);
    if (count($matches) === 1) {
      return new SkillLookupResult(skill: $matches[0]);
    }
    if (count($matches) > 1) {
      return new SkillLookupResult(candidates: $matches);
    }

    return new SkillLookupResult(suggestions: $this->skills->suggest($nameOrUuid));
  }

  /** @return string[] */
  private function suggestModules(string $text): array {
    return array_map(static fn ($module) => $module->machineName, $this->modules->search($text));
  }

  /** @return string[] */
  private function suggestTags(): array {
    return array_map(static fn ($tag) => $tag->name, $this->tags->all());
  }

  private static function looksLikeUuid(string $value): bool {
    return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value);
  }

}
