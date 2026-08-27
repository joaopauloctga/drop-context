<?php

declare(strict_types=1);

namespace DropContext\Catalog;

use DropContext\Gateway\GatewaySettings;

/**
 * Read-only lookups over documented Drupal core libraries — a client-side
 * port of `dc_entities`'s `Services\CoreLibraryCatalog`.
 *
 * Unlike ModuleDocsCatalog there is no release hop: one core_library row
 * carries `content` (summary.md) and `referenceFiles` (architecture/api/
 * usage/topics), the latter downloadable but never inlined by the gateway.
 *
 * Documented deviation from the site's own catalog: `tableOfContents()`'s
 * `size` for a reference-file entry is the file's byte size from its gateway
 * file metadata, not the character count of its downloaded content — the
 * site can afford to read every file just to build the table of contents
 * (local disk); doing that here would mean downloading every doc of every
 * library on a single list_core_libraries/get_core_library call.
 */
final class CoreLibraryCatalog {

  public function __construct(
    private readonly CatalogRepository $repository,
    private readonly GatewaySettings $settings,
  ) {}

  /** @return array{libraries: list<array<string, mixed>>, total: int} */
  public function listLibraries(?string $query, ?string $kind, int $limit, int $offset): array {
    $trimmedQuery = $query !== null ? trim($query) : '';

    $filtered = [];
    foreach ($this->repository->allCoreLibraries() as $library) {
      if ($kind !== null && (string) ($library['libraryKind'] ?? '') !== $kind) {
        continue;
      }
      if ($trimmedQuery !== '' && !self::matchesLibraryQuery($library, $trimmedQuery)) {
        continue;
      }
      $filtered[] = $library;
    }

    usort($filtered, static fn (array $a, array $b): int => strnatcasecmp(
      (string) ($a['title'] ?? ''),
      (string) ($b['title'] ?? ''),
    ));

    return [
      'libraries' => array_slice($filtered, $offset, $limit),
      'total' => count($filtered),
    ];
  }

  /**
   * Resolves a library reference to zero, one, or many published rows.
   *
   * Exact matches are tried in order: libraryId, libraryName, qualifiedName.
   * A bare short name matching multiple aliases (e.g. "Ajax") returns every
   * candidate — the tool turns that into an ambiguity error rather than
   * guessing.
   *
   * @return array{match: array<string, mixed>|null, candidates: list<array<string, mixed>>}
   */
  public function findLibrary(string $ref): array {
    $trimmed = trim($ref);
    if ($trimmed === '') {
      return ['match' => null, 'candidates' => []];
    }

    $libraries = $this->repository->allCoreLibraries();

    foreach (['libraryId', 'libraryName', 'qualifiedName'] as $field) {
      $found = array_values(array_filter(
        $libraries,
        static fn (array $l): bool => (string) ($l[$field] ?? '') === $trimmed,
      ));
      if (count($found) === 1) {
        return ['match' => $found[0], 'candidates' => []];
      }
      if (count($found) > 1) {
        return ['match' => null, 'candidates' => $found];
      }
    }

    $aliasMatches = array_values(array_filter($libraries, static function (array $l) use ($trimmed): bool {
      foreach (($l['libraryAliases'] ?? []) as $alias) {
        if ((string) $alias === $trimmed) {
          return true;
        }
      }
      return false;
    }));
    if (count($aliasMatches) === 1) {
      return ['match' => $aliasMatches[0], 'candidates' => []];
    }
    if (count($aliasMatches) > 1) {
      return ['match' => null, 'candidates' => $aliasMatches];
    }

    return ['match' => null, 'candidates' => []];
  }

  /** @return array{id: string, name: string, qualified_name: string, title: string, kind: string, summary: string, core_version: string} */
  public function librarySummary(array $library): array {
    return [
      'id' => (string) ($library['libraryId'] ?? ''),
      'name' => (string) ($library['libraryName'] ?? ''),
      'qualified_name' => (string) ($library['qualifiedName'] ?? ''),
      'title' => (string) ($library['title'] ?? ''),
      'kind' => (string) ($library['libraryKind'] ?? ''),
      'summary' => $this->shortSummary($library),
      'core_version' => (string) ($library['coreVersion'] ?? ''),
    ];
  }

  /** @return array{id: string, name: string, qualified_name: string, title: string, kind: string, summary: string, description: string, core_version: string, url: string} */
  public function libraryDetails(array $library): array {
    $summary = $this->librarySummary($library);
    return [
      'id' => $summary['id'],
      'name' => $summary['name'],
      'qualified_name' => $summary['qualified_name'],
      'title' => $summary['title'],
      'kind' => $summary['kind'],
      'summary' => $summary['summary'],
      'description' => TextFields::value($library['content'] ?? null),
      'core_version' => $summary['core_version'],
      'url' => $this->absoluteUrl((string) ($library['path'] ?? '')),
    ];
  }

  /** @return list<array{id: string, title: string, category: string, description: string, size: int}> */
  public function tableOfContents(array $library): array {
    $toc = [];
    $summaryContent = TextFields::value($library['content'] ?? null);
    $toc[] = [
      'id' => 'summary',
      'title' => (string) ($library['title'] ?? '') . ' — Summary',
      'category' => 'Summary',
      'description' => $this->shortSummary($library),
      'size' => mb_strlen($summaryContent),
    ];

    $libraryId = (string) ($library['libraryId'] ?? '');
    foreach (($library['referenceFiles'] ?? []) as $file) {
      if (!is_array($file)) {
        continue;
      }
      $source = self::relativeSourceFile($file, $libraryId);
      if ($source === '') {
        continue;
      }
      $id = ModuleDocId::fromSourceFile($source);
      $title = trim((string) ($file['description'] ?? ''));
      if ($title === '') {
        $title = (string) ($file['filename'] ?? $id);
      }
      $toc[] = [
        'id' => $id,
        'title' => $title,
        'category' => self::categoryForDocId($id),
        'description' => $title,
        'size' => (int) ($file['size'] ?? 0),
      ];
    }

    return $toc;
  }

  /** @return string[] */
  public function docIds(array $library): array {
    return array_map(static fn (array $entry): string => $entry['id'], $this->tableOfContents($library));
  }

  /** @return array{id: string, title: string, category: string, description: string, content: string}|null */
  public function findDoc(array $library, string $ref): ?array {
    $normalized = ModuleDocId::normalize($ref);
    if ($normalized === '') {
      return null;
    }

    foreach ($this->tableOfContents($library) as $entry) {
      if (strtolower($entry['id']) !== $normalized) {
        continue;
      }
      $content = $this->docContent($library, $entry['id']);
      if ($content === null) {
        return null;
      }
      return [
        'id' => $entry['id'],
        'title' => $entry['title'],
        'category' => $entry['category'],
        'description' => $entry['description'],
        'content' => $content,
      ];
    }

    return null;
  }

  private function docContent(array $library, string $docId): ?string {
    if ($docId === 'summary') {
      return TextFields::value($library['content'] ?? null);
    }

    $libraryId = (string) ($library['libraryId'] ?? '');
    foreach (($library['referenceFiles'] ?? []) as $file) {
      if (!is_array($file)) {
        continue;
      }
      $source = self::relativeSourceFile($file, $libraryId);
      if ($source === '' || strtolower(ModuleDocId::fromSourceFile($source)) !== strtolower($docId)) {
        continue;
      }
      $url = (string) ($file['url'] ?? '');
      return $url !== '' ? $this->repository->fileBody($url) : null;
    }
    return null;
  }

  /**
   * Recovers the discover-relative source path (e.g. `topics/dialogs.md`)
   * from the file's absolute download URL, since the `file` field output's
   * `filename` alone is only the basename and would lose a `topics/`
   * subdirectory. The importer always stores under
   * `public://dc-core-libraries/<libraryId>/…`, which the URL preserves
   * verbatim below `/sites/default/files/`.
   */
  private static function relativeSourceFile(array $file, string $libraryId): string {
    $url = (string) ($file['url'] ?? '');
    $marker = "dc-core-libraries/{$libraryId}/";
    $pos = strpos($url, $marker);
    if ($pos !== false) {
      return substr($url, $pos + strlen($marker));
    }
    return (string) ($file['filename'] ?? '');
  }

  private static function categoryForDocId(string $id): string {
    return match (true) {
      $id === 'summary' => 'Summary',
      $id === 'architecture' => 'Architecture',
      $id === 'api' => 'API',
      $id === 'usage' => 'Usage',
      default => 'Topic',
    };
  }

  private static function matchesLibraryQuery(array $library, string $query): bool {
    $aliases = array_map('strval', $library['libraryAliases'] ?? []);
    foreach ([
      (string) ($library['title'] ?? ''),
      (string) ($library['libraryId'] ?? ''),
      (string) ($library['libraryName'] ?? ''),
      (string) ($library['qualifiedName'] ?? ''),
      implode(' ', $aliases),
      TextFields::summary($library['content'] ?? null),
    ] as $haystack) {
      if (stripos($haystack, $query) !== false) {
        return true;
      }
    }
    return false;
  }

  private function shortSummary(array $library): string {
    $summary = TextFields::summary($library['content'] ?? null);
    if ($summary !== '') {
      return $summary;
    }
    return TextSummary::summarize(TextFields::value($library['content'] ?? null), 160);
  }

  private function absoluteUrl(string $path): string {
    return $path !== '' ? rtrim($this->settings->getBaseUrl(), '/') . $path : '';
  }

}
