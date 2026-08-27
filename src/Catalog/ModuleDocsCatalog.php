<?php

declare(strict_types=1);

namespace DropContext\Catalog;

use DropContext\Gateway\GatewaySettings;

/**
 * Read-only lookups over documented modules, releases, and docs — a
 * client-side port of `dc_entities`'s `Services\ModuleDocsCatalog` +
 * `Services\ModuleReleaseResolver`, driven by CatalogRepository instead of
 * the Drupal entity API.
 *
 * Rows are plain associative arrays as the gateway serializes them; every
 * lookup here returns NULL/empty on a miss and never throws — the calling
 * MCP tool (via `Mcp\Tool\ModuleDocsToolBase`) decides how a miss becomes an
 * actionable `Mcp\Exception\ToolCallException`.
 *
 * Documented deviation from the site's own resolver: "documented" here means
 * the module has at least one published `module_release` — not "at least one
 * release with at least one published `module_doc`", which the site checks
 * because loading is free there (an entity query) but would cost one extra
 * gateway request per release here. A release with no docs simply reports an
 * empty `docs: []` from get_module/get_doc's "available doc ids" rather than
 * being hidden from `available_releases` — see `ai/ROADMAP.md`.
 */
final class ModuleDocsCatalog {

  public function __construct(
    private readonly CatalogRepository $repository,
    private readonly GatewaySettings $settings,
  ) {}

  /** @return array<string, mixed>|null */
  public function findModule(string $machineName): ?array {
    foreach ($this->repository->allModules() as $module) {
      if ((string) ($module['machineName'] ?? '') === $machineName) {
        return $module;
      }
    }
    return null;
  }

  /**
   * @return array{modules: list<array<string, mixed>>, total: int}
   */
  public function listModules(?string $query, bool $coreOnly, int $limit, int $offset): array {
    $documentedModuleUuids = [];
    foreach ($this->repository->allReleases() as $release) {
      $moduleUuid = TextFields::refUuid($release['module'] ?? null);
      if ($moduleUuid !== null) {
        $documentedModuleUuids[$moduleUuid] = true;
      }
    }

    $trimmedQuery = $query !== null ? trim($query) : '';
    $filtered = [];
    foreach ($this->repository->allModules() as $module) {
      $uuid = (string) ($module['uuid'] ?? '');
      if (!isset($documentedModuleUuids[$uuid])) {
        continue;
      }
      if ($coreOnly && !(bool) ($module['isCore'] ?? false)) {
        continue;
      }
      if ($trimmedQuery !== '' && !self::matchesModuleQuery($module, $trimmedQuery)) {
        continue;
      }
      $filtered[] = $module;
    }

    usort($filtered, static fn (array $a, array $b): int => strnatcasecmp(
      (string) ($a['title'] ?? ''),
      (string) ($b['title'] ?? ''),
    ));

    return [
      'modules' => array_slice($filtered, $offset, $limit),
      'total' => count($filtered),
    ];
  }

  /** A module's documented releases, newest first — ReleaseVersion::compare() on `releaseTag`, never a lexical sort. */
  public function releasesForModule(array $module): array {
    $moduleUuid = (string) ($module['uuid'] ?? '');
    $releases = array_values(array_filter(
      $this->repository->allReleases(),
      static fn (array $release): bool => TextFields::refUuid($release['module'] ?? null) === $moduleUuid,
    ));

    usort($releases, static fn (array $a, array $b): int => ReleaseVersion::compare(
      (string) ($b['releaseTag'] ?? ''),
      (string) ($a['releaseTag'] ?? ''),
    ));

    return $releases;
  }

  /** The highest stable release, else the highest overall — same rule as `ModuleReleaseResolver::pickDefault()`. */
  public function defaultRelease(array $module): ?array {
    $releases = $this->releasesForModule($module);
    foreach ($releases as $release) {
      if (ReleaseVersion::isStable((string) ($release['releaseTag'] ?? ''))) {
        return $release;
      }
    }
    return $releases[0] ?? null;
  }

  /** Exact `releaseTag` match — unlike the web UI's resolver, never falls back to the default. */
  public function findRelease(array $module, string $tag): ?array {
    foreach ($this->releasesForModule($module) as $release) {
      if ((string) ($release['releaseTag'] ?? '') === $tag) {
        return $release;
      }
    }
    return null;
  }

  /** @return array{machine_name: string, title: string, is_core: bool, summary: string, default_release: string, releases: string[]} */
  public function moduleSummary(array $module): array {
    $releases = $this->releasesForModule($module);
    $default = $this->defaultRelease($module);

    return [
      'machine_name' => (string) ($module['machineName'] ?? ''),
      'title' => (string) ($module['title'] ?? ''),
      'is_core' => (bool) ($module['isCore'] ?? false),
      'summary' => $this->shortSummary($module),
      'default_release' => $default !== null ? (string) ($default['releaseTag'] ?? '') : '',
      'releases' => array_map(static fn (array $r): string => (string) ($r['releaseTag'] ?? ''), $releases),
    ];
  }

  /** get_module's `module` block: the resolved release's own description wins over the module's, falling back to the module's when the release has none. */
  public function moduleDetails(array $module, array $release): array {
    $releaseDescription = trim(TextFields::value($release['description'] ?? null));
    $description = $releaseDescription !== '' ? $releaseDescription : TextFields::value($module['description'] ?? null);
    $isCore = (bool) ($module['isCore'] ?? false);
    $machineName = (string) ($module['machineName'] ?? '');

    return [
      'machine_name' => $machineName,
      'title' => (string) ($module['title'] ?? ''),
      'summary' => $this->shortSummary($module),
      'description' => $description,
      'is_core' => $isCore,
      'security_coverage' => (bool) ($module['isCovered'] ?? false),
      'maintenance_status' => (string) ($module['maintenanceStatus'] ?? ''),
      'development_status' => (string) ($module['developmentStatus'] ?? ''),
      'categories' => self::labels($module['categories'] ?? []),
      'ecosystem' => self::labels($module['ecosystem'] ?? []),
      'drupal_org_url' => (!$isCore && $machineName !== '') ? "https://www.drupal.org/project/{$machineName}" : null,
      'url' => $this->absoluteUrl((string) ($module['path'] ?? '')),
    ];
  }

  /** @return array{tag: string, is_stable: bool, release_line: string, release_notes_url: string} */
  public function releaseSummary(array $release): array {
    $tag = (string) ($release['releaseTag'] ?? '');
    $notesUrl = $release['releaseNotesUrl'] ?? null;

    return [
      'tag' => $tag,
      'is_stable' => ReleaseVersion::isStable($tag),
      'release_line' => (string) ($release['releaseLine'] ?? ''),
      'release_notes_url' => is_array($notesUrl) ? (string) ($notesUrl['uri'] ?? '') : '',
    ];
  }

  /** @return list<array{tag: string, summary: string, issues: list<array{url: string, title: string}>}> */
  public function releaseNotes(array $release): array {
    $notes = [];
    foreach (($release['releaseNotes'] ?? []) as $note) {
      if (!is_array($note)) {
        continue;
      }
      $issues = [];
      foreach (($note['issueLinks'] ?? []) as $issue) {
        if (!is_array($issue)) {
          continue;
        }
        $url = (string) ($issue['uri'] ?? '');
        $title = (string) ($issue['title'] ?? '');
        $issues[] = ['url' => $url, 'title' => $title !== '' ? $title : $url];
      }
      $notes[] = [
        'tag' => (string) ($note['tagName'] ?? ''),
        'summary' => TextFields::value($note['content'] ?? null),
        'issues' => $issues,
      ];
    }
    return $notes;
  }

  /** @return list<array{id: string, title: string, category: string, submodule: string, description: string, size: int}> */
  public function tableOfContents(array $release): array {
    $docs = $this->sortedDocs($release);

    $toc = [];
    foreach ($docs as $doc) {
      $sourceFile = trim((string) ($doc['sourceFile'] ?? ''));
      if ($sourceFile === '') {
        continue;
      }
      $entry = $this->docSummary($doc);
      $entry['size'] = mb_strlen(TextFields::value($doc['content'] ?? null));
      $toc[] = $entry;
    }
    return $toc;
  }

  /** Finds a doc by id / file name / submodule name — id always wins over a same-named submodule collision, mirroring the site's two-pass lookup. */
  public function findDoc(array $release, string $ref): ?array {
    $normalized = ModuleDocId::normalize($ref);
    if ($normalized === '') {
      return null;
    }

    $docs = $this->sortedDocs($release);

    foreach ($docs as $doc) {
      $sourceFile = trim((string) ($doc['sourceFile'] ?? ''));
      if ($sourceFile !== '' && strtolower(ModuleDocId::fromSourceFile($sourceFile)) === $normalized) {
        return $doc;
      }
    }
    foreach ($docs as $doc) {
      $submodule = trim((string) ($doc['submodule'] ?? ''));
      if ($submodule !== '' && strtolower($submodule) === $normalized) {
        return $doc;
      }
    }
    return null;
  }

  /** @return array{id: string, title: string, category: string, submodule: string, description: string} */
  public function docSummary(array $doc): array {
    $sourceFile = trim((string) ($doc['sourceFile'] ?? ''));
    return [
      'id' => $sourceFile !== '' ? ModuleDocId::fromSourceFile($sourceFile) : '',
      'title' => (string) ($doc['title'] ?? ''),
      'category' => TextFields::refLabel($doc['docCategory'] ?? null),
      'submodule' => (string) ($doc['submodule'] ?? ''),
      'description' => TextFields::value($doc['description'] ?? null),
    ];
  }

  /** @return string[] */
  public function docIds(array $release): array {
    return array_map(static fn (array $entry): string => $entry['id'], $this->tableOfContents($release));
  }

  /** @return list<array<string, mixed>> a release's docs, field_weight then title — matches the site's `module_docs` view sort. */
  private function sortedDocs(array $release): array {
    $docs = $this->repository->docsForRelease((string) ($release['uuid'] ?? ''));
    usort($docs, static function (array $a, array $b): int {
      $weight = ((int) ($a['weight'] ?? 0)) <=> ((int) ($b['weight'] ?? 0));
      return $weight !== 0 ? $weight : strcmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
    });
    return $docs;
  }

  private function shortSummary(array $module): string {
    $summary = TextFields::summary($module['description'] ?? null);
    if ($summary !== '') {
      return $summary;
    }
    return TextSummary::summarize(TextFields::value($module['description'] ?? null), 160);
  }

  /**
   * @param array<int, mixed> $refs
   * @return string[]
   */
  private static function labels(array $refs): array {
    return array_values(array_map(
      static fn (array $ref): string => (string) ($ref['label'] ?? ''),
      array_filter($refs, 'is_array'),
    ));
  }

  private static function matchesModuleQuery(array $module, string $query): bool {
    foreach ([
      (string) ($module['title'] ?? ''),
      (string) ($module['machineName'] ?? ''),
      TextFields::value($module['description'] ?? null),
    ] as $haystack) {
      if (stripos($haystack, $query) !== false) {
        return true;
      }
    }
    return false;
  }

  private function absoluteUrl(string $path): string {
    return $path !== '' ? rtrim($this->settings->getBaseUrl(), '/') . $path : '';
  }

}
