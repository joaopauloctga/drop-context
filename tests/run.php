<?php

declare(strict_types=1);

use DropContext\Cache\CachingGatewayHttp;
use DropContext\Cache\CatalogStamp;
use DropContext\Cache\JsonFileCache;
use DropContext\Catalog\CatalogRepository;
use DropContext\Catalog\CoreLibraryCatalog;
use DropContext\Catalog\ModuleDocId;
use DropContext\Catalog\ModuleDocsCatalog;
use DropContext\Catalog\ReleaseVersion;
use DropContext\Catalog\TextSummary;
use DropContext\Gateway\Dto\CollectionPage;
use DropContext\Gateway\Dto\HttpResult;
use DropContext\Gateway\Dto\SkillRow;
use DropContext\Gateway\Exception\GatewayConnectionException;
use DropContext\Gateway\FileDownloader;
use DropContext\Gateway\GatewayClientInterface;
use DropContext\Gateway\GatewayHttpInterface;
use DropContext\Gateway\GatewaySettings;
use DropContext\Gateway\Query;
use DropContext\Gateway\Repository\SkillRepository;
use DropContext\Service\AppStoreService;
use DropContext\Service\AppStoreSkillService;
use DropContext\Service\SkillService;
use DropContext\Service\SkillStatusService;
use DropContext\SkillUpdateState;
use DropContext\ValueObject\SkillValueObject;
use Psr\Log\NullLogger;

require dirname(__DIR__) . '/vendor/autoload.php';

function assertSameValue(mixed $expected, mixed $actual, string $message): void {
  if ($expected === $actual) {
    return;
  }

  fwrite(STDERR, sprintf(
    "FAIL: %s\nExpected: %s\nActual: %s\n",
    $message,
    var_export($expected, true),
    var_export($actual, true),
  ));
  exit(1);
}

function remoteSkill(string $uuid, string $title, ?string $version): SkillRow {
  $data = [
    'uuid' => $uuid,
    'title' => $title,
    'content' => '# Skill',
    'referenceFiles' => [],
  ];
  if ($version !== null) {
    $data['skillVersion'] = $version;
  }
  return SkillRow::fromArray($data);
}

$current = remoteSkill('uuid-current', 'dc-current', '0.2');
$legacy = remoteSkill('uuid-legacy', 'dc-legacy', '0.1');
$missing = remoteSkill('uuid-missing', 'dc-missing', '0.4');
$unknown = remoteSkill('uuid-unknown', 'dc-unknown', null);
$older = remoteSkill('uuid-older', 'dc-older', '0.4');

$repository = new class($current, $legacy, $missing, $unknown, $older) extends SkillRepository {

  /** @var array<string, SkillRow> */
  private array $byUuid;

  /** @var array<string, SkillRow[]> */
  private array $byTitle;

  public function __construct(SkillRow ...$skills) {
    foreach ($skills as $skill) {
      $this->byUuid[$skill->uuid] = $skill;
      $this->byTitle[$skill->title][] = $skill;
    }
  }

  public function findByUuids(array $uuids): array {
    return array_intersect_key($this->byUuid, array_flip($uuids));
  }

  public function findByTitles(array $titles): array {
    return array_intersect_key($this->byTitle, array_flip($titles));
  }

};

$skillService = new class extends SkillService {

  public array $updatedAgents = [];

  public function __construct() {}

  public function listInstalledSkills(): array {
    $path = static fn (string $name): array => ['.agents' => "/project/.agents/skills/{$name}"];
    return [
      ['name' => 'dc-current', 'uuid' => 'uuid-current', 'version' => '0.2', 'changed' => null, 'paths' => $path('dc-current')],
      // Before skill versions existed, the CLI wrote a fixed 1.0.0. A
      // mismatch — even a numerically lower remote version — must migrate it.
      ['name' => 'dc-legacy', 'uuid' => null, 'version' => '1.0.0', 'changed' => null, 'paths' => $path('dc-legacy')],
      ['name' => 'dc-missing', 'uuid' => 'uuid-missing', 'version' => '0.3', 'changed' => null, 'paths' => []],
      ['name' => 'dc-unknown', 'uuid' => 'uuid-unknown', 'version' => '0.1', 'changed' => null, 'paths' => $path('dc-unknown')],
      ['name' => 'dc-gone', 'uuid' => null, 'version' => '0.1', 'changed' => null, 'paths' => $path('dc-gone')],
      ['name' => 'dc-older', 'uuid' => 'uuid-older', 'version' => '0.5', 'changed' => null, 'paths' => $path('dc-older')],
    ];
  }

  public function installSkill(SkillRow $skill, array $codeAgents = [], string $source = 'drop-context'): SkillValueObject {
    $this->updatedAgents = $codeAgents;
    return new SkillValueObject(
      name: $skill->title,
      agents: array_map(static fn (string $agent): array => [$agent, "/project/{$agent}/skills/{$skill->title}"], $codeAgents),
      path: '/project',
      version: $skill->version ?? '0.1',
      uuid: $skill->uuid,
    );
  }

};

$skillRegistry = new class extends AppStoreSkillService {

  public ?string $replacedName = null;

  public function __construct() {}

  public function replace(string $installedName, SkillValueObject $skill): void {
    $this->replacedName = $installedName;
  }

};

$appStoreService = new class($skillRegistry) extends AppStoreService {
  public function __construct(AppStoreSkillService $skills) {
    $this->skills = $skills;
  }
};

$service = new SkillStatusService($repository, $skillService, $appStoreService);
$statuses = $service->inspect();

assertSameValue(SkillUpdateState::CURRENT, $statuses[0]->state, 'matching versions are current');
assertSameValue(SkillUpdateState::UPDATE_AVAILABLE, $statuses[1]->state, 'legacy fixed version is an update');
assertSameValue('uuid-legacy', $statuses[1]->uuid, 'legacy entries acquire the remote UUID');
assertSameValue(SkillUpdateState::MISSING, $statuses[2]->state, 'registry entries without project files are missing');
assertSameValue(SkillUpdateState::VERSION_UNKNOWN, $statuses[3]->state, 'unversioned remote skills are reported');
assertSameValue(SkillUpdateState::UNAVAILABLE, $statuses[4]->state, 'removed remote skills are unavailable');
assertSameValue(SkillUpdateState::LOCAL_NEWER, $statuses[5]->state, 'a newer local version is not downgraded');
assertSameValue(1, count(array_filter($statuses, static fn ($status): bool => $status->hasUpdate())), 'only actionable updates are selected');

$updated = $service->update($statuses[1]);
assertSameValue('0.1', $updated->version, 'an update installs the remote version');
assertSameValue('uuid-legacy', $updated->uuid, 'an update persists the durable UUID');
assertSameValue(['.agents'], $skillService->updatedAgents, 'an update targets the agents where the skill exists');
assertSameValue('dc-legacy', $skillRegistry->replacedName, 'an update replaces the existing registry entry');

fwrite(STDOUT, "Skill status tests passed.\n");

// ---------------------------------------------------------------------
// MCP server: Catalog + Cache layer
// ---------------------------------------------------------------------

/** A GatewayClientInterface fake driven entirely by in-memory fixture rows, keyed by bundle. */
final class FakeGatewayClient implements GatewayClientInterface {

  /** @param array<string, list<array<string, mixed>>> $rowsByBundle */
  public function __construct(private readonly array $rowsByBundle) {}

  public function getCollection(string $entityType, string $bundle, ?Query $query = null): CollectionPage {
    $data = iterator_to_array($this->iterate($entityType, $bundle, $query), false);
    $count = count($data);
    return new CollectionPage(total: $count, page: 1, perPage: max($count, 10), count: $count, data: $data);
  }

  public function getItem(string $entityType, string $bundle, string $uuid): array {
    foreach ($this->rowsByBundle[$bundle] ?? [] as $row) {
      if (($row['uuid'] ?? null) === $uuid) {
        return $row;
      }
    }
    throw new RuntimeException("FakeGatewayClient: no {$bundle} row with uuid {$uuid}");
  }

  public function iterate(string $entityType, string $bundle, ?Query $query = null): Generator {
    $rows = $this->rowsByBundle[$bundle] ?? [];

    // Mirrors the one filter CatalogRepository::docsForRelease() actually
    // sends: ?filter[moduleRelease]=<uuid> equality.
    if ($bundle === 'module_doc' && $query instanceof Query) {
      $releaseUuid = $query->toParams()['filter']['moduleRelease'] ?? null;
      if ($releaseUuid !== null) {
        $rows = array_values(array_filter(
          $rows,
          static fn (array $row): bool => ($row['moduleRelease']['uuid'] ?? null) === $releaseUuid,
        ));
      }
    }

    foreach ($rows as $row) {
      yield $row;
    }
  }

}

/** A GatewayHttpInterface fake driven by a closure, recording every call. */
final class ScriptedGatewayHttp implements GatewayHttpInterface {

  private int $calls = 0;

  /** @var list<array{url: string, query: array<string, mixed>, headers: array<string, string>}> */
  private array $log = [];

  public function __construct(private readonly Closure $handler) {}

  public function get(string $url, array $query = [], array $headers = []): HttpResult {
    $this->calls++;
    $this->log[] = ['url' => $url, 'query' => $query, 'headers' => $headers];
    return ($this->handler)($url, $query, $headers, $this->calls);
  }

  public function callCount(): int {
    return $this->calls;
  }

  /** @return list<array{url: string, query: array<string, mixed>, headers: array<string, string>}> */
  public function log(): array {
    return $this->log;
  }

}

function fakeModuleRow(string $uuid, string $machineName, string $title, bool $isCore, array $categories = [], array $ecosystem = []): array {
  return [
    'uuid' => $uuid,
    'title' => $title,
    'changed' => '2026-01-01T00:00:00+00:00',
    'path' => "/context/{$machineName}",
    'categories' => $categories,
    'creationDate' => '2020-01-01T00:00:00+00:00',
    'description' => [
      'value' => "The {$title} module coordinates several fixture widgets purely for this test suite and carries no meaning beyond exercising truncation and fallback logic across a moderately long descriptive sentence.",
      'summary' => '',
    ],
    'developmentStatus' => 'under_active_development',
    'ecosystem' => $ecosystem,
    'icon' => null,
    'isCovered' => true,
    'machineName' => $machineName,
    'maintainerUsernames' => [],
    'maintainers' => [],
    'maintenanceStatus' => 'actively_maintained',
    'isCore' => $isCore,
    'moduleDetailsVersion' => '0.1',
  ];
}

function fakeReleaseRow(string $uuid, string $moduleUuid, string $tag, string $line, ?array $notesUrl = null, ?array $description = null, array $releaseNotes = []): array {
  return [
    'uuid' => $uuid,
    'title' => "Widget Test {$tag}",
    'changed' => '2026-01-02T00:00:00+00:00',
    'module' => $moduleUuid,
    'releaseTag' => $tag,
    'releaseLine' => $line,
    'releaseNotesUrl' => $notesUrl,
    'description' => $description ?? ['value' => '', 'summary' => ''],
    'releaseNotes' => $releaseNotes,
  ];
}

function fakeDocRow(string $uuid, string $releaseUuid, string $releaseLabel, string $title, string $sourceFile, int $weight, ?string $submodule, ?array $category, string $content): array {
  return [
    'uuid' => $uuid,
    'title' => $title,
    'moduleRelease' => ['uuid' => $releaseUuid, 'label' => $releaseLabel],
    'docCategory' => $category,
    'submodule' => $submodule,
    'description' => ['value' => "Describes {$title}.", 'summary' => ''],
    'content' => ['value' => $content, 'summary' => ''],
    'moduleDocVersion' => '0.1',
    'sourceFile' => $sourceFile,
    'weight' => $weight,
  ];
}

// A stub AppStoreService that is never actually touched: every test below
// pins DROP_CONTEXT_URL, which GatewaySettings::getBaseUrl() checks first.
$appStoreServiceStub = new class extends AppStoreService {
  public function __construct() {}
};
putenv('DROP_CONTEXT_URL=https://example.test');
putenv('DROP_CONTEXT_TLS_VERIFY=1');
$gatewaySettings = new GatewaySettings($appStoreServiceStub);

// --- ReleaseVersion ---

assertSameValue(true, ReleaseVersion::isStable('3.0.14'), '3.0.14 is stable');
assertSameValue(false, ReleaseVersion::isStable('3.1.0-beta1'), '3.1.0-beta1 is not stable');
assertSameValue(false, ReleaseVersion::isStable('2.0.0-dev'), '2.0.0-dev is not stable');
assertSameValue('3.2', ReleaseVersion::normalize('8.x-3.2'), 'normalize() strips the core-compat prefix');
assertSameValue('3.2', ReleaseVersion::normalize('3.2'), 'normalize() leaves a plain semver tag alone');
assertSameValue(true, ReleaseVersion::compare('2.0.0', '1.9.9') > 0, '2.0.0 sorts after 1.9.9');
assertSameValue(true, ReleaseVersion::compare('8.x-2.0', '8.x-10.0') < 0, 'core-compat prefixed tags compare numerically once normalized');

fwrite(STDOUT, "ReleaseVersion tests passed.\n");

// --- ModuleDocId ---

assertSameValue('hooks', ModuleDocId::fromSourceFile('hooks.md'), 'fromSourceFile() strips .md');
assertSameValue('submodules/eca_base', ModuleDocId::fromSourceFile('submodules/eca_base.md'), 'fromSourceFile() preserves the submodules/ prefix');
assertSameValue('ai-integration', ModuleDocId::normalize('AI Integration'), 'normalize() lowercases and dashes a category label');
assertSameValue('extension-points', ModuleDocId::normalize('extension-points.md'), 'normalize() strips a trailing .md');
assertSameValue('eca_base', ModuleDocId::normalize('eca_base'), 'normalize() leaves an underscore machine name alone');

fwrite(STDOUT, "ModuleDocId tests passed.\n");

// --- TextSummary ---

assertSameValue('Short text.', TextSummary::summarize('Short text.', 160), 'text under the limit is returned unchanged');
assertSameValue('Bold text', TextSummary::summarize('<strong>Bold text</strong>', 160), 'HTML tags are stripped');
$long = str_repeat('word ', 60); // 300 chars, well past an 18-char limit
$truncated = TextSummary::summarize($long, 18);
assertSameValue(true, mb_strlen($truncated) <= 18, 'a truncated summary never exceeds the requested length');
// Word-safe: cuts after the 3rd whole "word", never mid-word (a raw
// mb_substr(…, 0, 17) would instead land mid-"word", giving "…wo…").
assertSameValue('word word word…', $truncated, 'truncation lands on a word boundary, not mid-word');

fwrite(STDOUT, "TextSummary tests passed.\n");

// --- ModuleDocsCatalog ---

$widgetModuleUuid = 'module-widget';
$coreModuleUuid = 'module-core-fixture';
$categoryUuid = 'cat-uuid';
$release1Uuid = 'release-1-0-0';
$release2BetaUuid = 'release-2-0-0-beta1';

$moduleRows = [
  fakeModuleRow($widgetModuleUuid, 'widget_test', 'Widget Test', false, [['uuid' => $categoryUuid, 'label' => 'Fixtures']]),
  fakeModuleRow($coreModuleUuid, 'core_fixture_test', 'Core Fixture Test', true),
  // Never referenced by any release below — must not appear as "documented".
  fakeModuleRow('module-undocumented', 'undocumented_test', 'Undocumented Test', false),
];

$releaseRows = [
  fakeReleaseRow($release1Uuid, $widgetModuleUuid, '1.0.0', '1.x', ['uri' => 'https://example.test/releases/1.0.0'], ['value' => 'Release 1.0.0 summary.', 'summary' => '']),
  fakeReleaseRow($release2BetaUuid, $widgetModuleUuid, '2.0.0-beta1', '2.x', null, null, [
    ['tagName' => '2.0.0-beta1', 'content' => 'Adds a beta feature.', 'issueLinks' => [['uri' => 'https://example.test/issues/1', 'title' => 'Beta feature']]],
  ]),
];

$docRows = [
  fakeDocRow('doc-summary', $release1Uuid, 'Widget Test 1.0.0', 'Widget Test — Summary', 'summary.md', 0, null, ['uuid' => $categoryUuid, 'label' => 'Summary'], '# Widget Test'),
  fakeDocRow('doc-hooks', $release1Uuid, 'Widget Test 1.0.0', 'Widget Test — Hooks', 'hooks.md', 5, null, ['uuid' => $categoryUuid, 'label' => 'Hooks'], '# Hooks'),
  fakeDocRow('doc-sub', $release1Uuid, 'Widget Test 1.0.0', 'Widget Sub', 'submodules/widget_sub.md', 10, 'widget_sub', null, '# Widget Sub'),
  // No sourceFile: a hand-created doc, must be skipped by tableOfContents()/docIds() but still findable is not expected.
  fakeDocRow('doc-no-source', $release1Uuid, 'Widget Test 1.0.0', 'Hand Authored', '', 1, null, null, '# Hand Authored'),
];

$moduleGatewayClient = new FakeGatewayClient([
  'module_details' => $moduleRows,
  'module_release' => $releaseRows,
  'module_doc' => $docRows,
]);
// ModuleDocsCatalog never downloads files — field_content is always inlined —
// so a FileDownloader that throws on first use proves that.
$explodingFileDownloader = new FileDownloader(new ScriptedGatewayHttp(function (): HttpResult {
  throw new RuntimeException('ModuleDocsCatalog must never download a file.');
}));
$moduleRepository = new CatalogRepository($moduleGatewayClient, $explodingFileDownloader);
$moduleDocsCatalog = new ModuleDocsCatalog($moduleRepository, $gatewaySettings);

$listed = $moduleDocsCatalog->listModules(null, false, 50, 0);
assertSameValue(1, $listed['total'], 'only modules with >=1 release are "documented" (undocumented_test is excluded)');
assertSameValue('widget_test', $listed['modules'][0]['machineName'] ?? null, 'the one documented, non-core module is widget_test');

$coreOnly = $moduleDocsCatalog->listModules(null, true, 50, 0);
assertSameValue(0, $coreOnly['total'], 'core_fixture_test has no release, so core_only still finds nothing');

$foundModule = $moduleDocsCatalog->findModule('widget_test');
assertSameValue('Widget Test', $foundModule['title'] ?? null, 'findModule() matches by machine name');
assertSameValue(null, $moduleDocsCatalog->findModule('does_not_exist'), 'findModule() misses cleanly');

$releases = $moduleDocsCatalog->releasesForModule($foundModule);
// 2.0.0-beta1 outranks 1.0.0 (major version 2 beats major version 1,
// pre-release or not) — newest-first is a plain version sort, not
// stable-first; defaultRelease() below is what prefers stability.
assertSameValue(['2.0.0-beta1', '1.0.0'], array_map(static fn (array $r) => $r['releaseTag'], $releases), 'releasesForModule() sorts newest first by ReleaseVersion::compare()');

$default = $moduleDocsCatalog->defaultRelease($foundModule);
assertSameValue('1.0.0', $default['releaseTag'] ?? null, 'defaultRelease() prefers the highest STABLE release over the newer beta');

assertSameValue(null, $moduleDocsCatalog->findRelease($foundModule, '9.9.9'), 'findRelease() misses cleanly on an undocumented tag');

$summary = $moduleDocsCatalog->moduleSummary($foundModule);
assertSameValue(true, mb_strlen($summary['summary']) <= 160, 'moduleSummary() derives a <=160 char summary when the field carries none');
assertSameValue('1.0.0', $summary['default_release'], 'moduleSummary() reports the default release tag');

$details = $moduleDocsCatalog->moduleDetails($foundModule, $default);
assertSameValue('Release 1.0.0 summary.', $details['description'], "moduleDetails() prefers the release's own description over the module's");
assertSameValue('https://example.test/context/widget_test', $details['url'], 'moduleDetails() builds an absolute URL from GatewaySettings + path');
assertSameValue('https://www.drupal.org/project/widget_test', $details['drupal_org_url'], 'a non-core module gets a drupal.org URL');
assertSameValue(['Fixtures'], $details['categories'], 'moduleDetails() reads category labels off the inlined uuid_label stubs');

$coreDetails = $moduleDocsCatalog->moduleDetails(fakeModuleRow($coreModuleUuid, 'core_fixture_test', 'Core Fixture Test', true), $default);
assertSameValue(null, $coreDetails['drupal_org_url'], 'a core module never gets a drupal.org URL');

$releaseSummary = $moduleDocsCatalog->releaseSummary($default);
assertSameValue(true, $releaseSummary['is_stable'], '1.0.0 reports as stable');
assertSameValue('https://example.test/releases/1.0.0', $releaseSummary['release_notes_url'], 'releaseSummary() unwraps the link field\'s uri');

$betaRelease = $moduleDocsCatalog->findRelease($foundModule, '2.0.0-beta1');
$notes = $moduleDocsCatalog->releaseNotes($betaRelease);
assertSameValue('Adds a beta feature.', $notes[0]['summary'] ?? null, 'releaseNotes() reads a collapsed (bare-string) text field');
assertSameValue('Beta feature', $notes[0]['issues'][0]['title'] ?? null, 'releaseNotes() reads issue link titles');

$toc = $moduleDocsCatalog->tableOfContents($default);
assertSameValue(['summary', 'hooks', 'submodules/widget_sub'], array_column($toc, 'id'), 'tableOfContents() sorts by weight and skips docs with no sourceFile');
assertSameValue('Hooks', $toc[1]['category'], 'tableOfContents() reads the category label off docCategory');

$byId = $moduleDocsCatalog->findDoc($default, 'HOOKS.md');
assertSameValue('doc-hooks', $byId['uuid'] ?? null, 'findDoc() matches an id case-insensitively, trailing .md and all');

$bySubmodule = $moduleDocsCatalog->findDoc($default, 'widget_sub');
assertSameValue('doc-sub', $bySubmodule['uuid'] ?? null, 'findDoc() falls back to matching a bare submodule machine name');

assertSameValue(null, $moduleDocsCatalog->findDoc($default, 'nope'), 'findDoc() misses cleanly');

fwrite(STDOUT, "ModuleDocsCatalog tests passed.\n");

// --- CoreLibraryCatalog ---

$ajaxLibraryRow = [
  'uuid' => 'lib-ajax',
  'title' => 'Fixture Ajax',
  'changed' => '2026-01-01T00:00:00+00:00',
  'path' => '/context/core-lib/fixtureajax',
  'libraryId' => 'fixture.core.ajax',
  'libraryName' => 'Core/Ajax',
  'qualifiedName' => 'Drupal\\Core\\Ajax',
  'libraryKind' => 'core',
  'coreVersion' => '11.4.4',
  'coreLibraryVersion' => '0.1',
  'libraryAliases' => ['Ajax', 'Core/Ajax'],
  'content' => ['value' => "# Fixture Ajax\n\nA fixture library summary for the test suite, long enough that a 160-character derived summary would need to truncate it at a real word boundary somewhere past the halfway mark.", 'summary' => ''],
  'referenceFiles' => [
    ['uuid' => 'file-arch', 'url' => 'https://example.test/sites/default/files/dc-core-libraries/fixture.core.ajax/architecture.md', 'filename' => 'architecture.md', 'mime' => 'text/markdown', 'size' => 42, 'description' => 'Fixture Ajax — Architecture'],
    ['uuid' => 'file-topic', 'url' => 'https://example.test/sites/default/files/dc-core-libraries/fixture.core.ajax/topics/dialogs.md', 'filename' => 'dialogs.md', 'mime' => 'text/markdown', 'size' => 7, 'description' => 'Ajax Dialogs'],
  ],
];
$componentAjaxLibraryRow = [
  'uuid' => 'lib-component-ajax',
  'title' => 'Fixture Component Ajax',
  'changed' => '2026-01-01T00:00:00+00:00',
  'path' => '/context/core-lib/fixturecomponentajax',
  'libraryId' => 'fixture.component.ajax',
  'libraryName' => 'Component/Ajax',
  'qualifiedName' => 'Drupal\\Component\\Ajax',
  'libraryKind' => 'component',
  'coreVersion' => '11.4.4',
  'coreLibraryVersion' => '0.1',
  // Shares the "Ajax" alias with lib-ajax on purpose — this is the ambiguity fixture.
  'libraryAliases' => ['Ajax', 'Component/Ajax'],
  'content' => ['value' => '# Fixture Component Ajax', 'summary' => 'A component-level fixture.'],
  'referenceFiles' => [],
];

$libraryGatewayClient = new FakeGatewayClient([
  'core_library' => [$ajaxLibraryRow, $componentAjaxLibraryRow],
]);

$downloadedUrls = [];
$fileDownloaderHttp = new ScriptedGatewayHttp(function (string $url) use (&$downloadedUrls): HttpResult {
  $downloadedUrls[] = $url;
  return new HttpResult(200, [], "downloaded body for {$url}");
});
$libraryFileDownloader = new FileDownloader($fileDownloaderHttp);
$libraryRepository = new CatalogRepository($libraryGatewayClient, $libraryFileDownloader);
$coreLibraryCatalog = new CoreLibraryCatalog($libraryRepository, $gatewaySettings);

$libListed = $coreLibraryCatalog->listLibraries(null, 'core', 50, 0);
assertSameValue(1, $libListed['total'], 'kind=core filters out the Component fixture');

$libListedQuery = $coreLibraryCatalog->listLibraries('component-level', null, 50, 0);
assertSameValue('fixture.component.ajax', $libListedQuery['libraries'][0]['libraryId'] ?? null, 'listLibraries() matches against the summary sub-field');

$exactMatch = $coreLibraryCatalog->findLibrary('fixture.core.ajax');
assertSameValue('lib-ajax', $exactMatch['match']['uuid'] ?? null, 'findLibrary() matches an exact libraryId');

$ambiguous = $coreLibraryCatalog->findLibrary('Ajax');
assertSameValue(null, $ambiguous['match'], 'an alias shared by two libraries is ambiguous, not guessed');
assertSameValue(2, count($ambiguous['candidates']), 'both libraries sharing the "Ajax" alias come back as candidates');

$libDetails = $coreLibraryCatalog->libraryDetails($ajaxLibraryRow);
assertSameValue('https://example.test/context/core-lib/fixtureajax', $libDetails['url'], 'libraryDetails() builds an absolute URL');

$libToc = $coreLibraryCatalog->tableOfContents($ajaxLibraryRow);
assertSameValue(['summary', 'architecture', 'topics/dialogs'], array_column($libToc, 'id'), 'tableOfContents() recovers the topics/ prefix from the file URL, not just its basename');
assertSameValue(42, $libToc[1]['size'] ?? null, 'a reference-file entry\'s size is the byte size from file metadata, not a downloaded char count (documented deviation)');
assertSameValue('Topic', $libToc[2]['category'], 'a topics/ doc categorizes as Topic');

$summaryDoc = $coreLibraryCatalog->findDoc($ajaxLibraryRow, 'summary');
assertSameValue(0, count($downloadedUrls), 'reading the summary doc never downloads a file');
assertSameValue(true, str_starts_with((string) $summaryDoc['content'], '# Fixture Ajax'), 'the summary doc\'s content is field_content, inlined');

$architectureDoc = $coreLibraryCatalog->findDoc($ajaxLibraryRow, 'architecture.md');
assertSameValue(1, count($downloadedUrls), 'reading a non-summary doc downloads exactly its one file');
assertSameValue('downloaded body for https://example.test/sites/default/files/dc-core-libraries/fixture.core.ajax/architecture.md', $architectureDoc['content'] ?? null, 'the downloaded body becomes the doc content');

$topicDoc = $coreLibraryCatalog->findDoc($ajaxLibraryRow, 'topics/dialogs');
assertSameValue(true, $topicDoc !== null, 'a topics/ doc is resolvable by its full recovered id');

assertSameValue(null, $coreLibraryCatalog->findDoc($ajaxLibraryRow, 'does-not-exist'), 'findDoc() misses cleanly');

fwrite(STDOUT, "CoreLibraryCatalog tests passed.\n");

// ---------------------------------------------------------------------
// Cache layer: JsonFileCache, CachingGatewayHttp, CatalogStamp
// ---------------------------------------------------------------------

function newTestCacheRoot(): string {
  return sys_get_temp_dir() . '/drop-context-cache-test-' . bin2hex(random_bytes(6));
}

function newTestCache(GatewaySettings $settings): JsonFileCache {
  return new JsonFileCache($settings, newTestCacheRoot());
}

$nullLogger = new NullLogger();

// --- JsonFileCache: hit/miss/purge, and the L2 bypass ---

$plainCache = newTestCache($gatewaySettings);
assertSameValue(null, $plainCache->get('missing'), 'a miss on an empty cache returns null');

$plainCache->set('key-a', ['body' => 'hello']);
assertSameValue('hello', $plainCache->get('key-a')['body'] ?? null, 'a hit returns exactly what was set');

// A second instance over the same root (same GatewaySettings -> same
// namespace hash) proves L2 (not just L1) persisted it — namespaceDir()
// already has the hash appended, so the second instance is built from the
// *root*, exactly like the first, not from namespaceDir() itself.
$sharedRoot = newTestCacheRoot();
$original = new JsonFileCache($gatewaySettings, $sharedRoot);
$original->set('key-a', ['body' => 'hello']);
$reopened = new JsonFileCache($gatewaySettings, $sharedRoot);
assertSameValue('hello', $reopened->get('key-a')['body'] ?? null, 'L2 survives a new JsonFileCache instance over the same root directory');

$reopened->purgeEntries();
assertSameValue(null, $reopened->get('key-a'), 'purgeEntries() drops every cached entry');

putenv('DROP_CONTEXT_CACHE=0');
$bypassRoot = newTestCacheRoot();
$bypassCache = new JsonFileCache($gatewaySettings, $bypassRoot);
$bypassCache->set('key-b', ['body' => 'bypassed']);
assertSameValue('bypassed', $bypassCache->get('key-b')['body'] ?? null, 'L1 still serves within the same instance even with L2 disabled');
$bypassReopened = new JsonFileCache($gatewaySettings, $bypassRoot);
assertSameValue(null, $bypassReopened->get('key-b'), 'DROP_CONTEXT_CACHE=0 never persisted to L2, so a fresh instance misses');
putenv('DROP_CONTEXT_CACHE');

fwrite(STDOUT, "JsonFileCache tests passed.\n");

// --- CachingGatewayHttp: cold fetch, cache hit, conditional revalidation, stale-on-failure ---

$dataUrl = 'https://example.test/data';

// Cold fetch: the first call ever also triggers the (unrateable-limited,
// since no stamp.json exists yet) catalog-changed probe.
$cache1 = newTestCache($gatewaySettings);
$http1 = new ScriptedGatewayHttp(function (string $url) use ($dataUrl): HttpResult {
  if (str_contains($url, '/api/drupal_context/')) {
    return new HttpResult(200, [], json_encode(['total' => 1, 'data' => [['changed' => '2026-01-01T00:00:00+00:00']]]));
  }
  if ($url === $dataUrl) {
    return new HttpResult(200, ['Cache-Control' => ['max-age=100']], 'fresh body');
  }
  throw new RuntimeException("unexpected URL: {$url}");
});
$stamp1 = new CatalogStamp($http1, $gatewaySettings, $cache1, $nullLogger);
$caching1 = new CachingGatewayHttp($http1, $cache1, $stamp1, $nullLogger);

$result1 = $caching1->get($dataUrl);
assertSameValue('fresh body', $result1->body, 'a cold fetch returns the network body');
assertSameValue(3, $http1->callCount(), 'a cold fetch makes 2 stamp probes + 1 real request');

$result2 = $caching1->get($dataUrl);
assertSameValue('fresh body', $result2->body, 'a repeat call within TTL is served from cache');
assertSameValue(3, $http1->callCount(), 'a repeat call within TTL makes no new request at all');

// Conditional revalidation: an expired entry with validators gets a
// conditional GET; a 304 keeps the old body and renews freshness.
$cache2 = newTestCache($gatewaySettings);
$cache2->writeStamp(['value' => 'seed', 'checkedAt' => time()]); // fresh stamp: no probe interferes
$revalidateKey = $dataUrl . '?';
$cache2->set($revalidateKey, [
  'key' => $revalidateKey,
  'url' => $dataUrl,
  'fetchedAt' => time() - 1000,
  'expiresAt' => time() - 1,
  'etag' => '"abc123"',
  'lastModified' => 'Mon, 01 Jan 2024 00:00:00 GMT',
  'body' => 'old body',
]);
$revalidateCalls = 0;
$http2 = new ScriptedGatewayHttp(function (string $url, array $query, array $headers) use (&$revalidateCalls, $dataUrl): HttpResult {
  if ($url !== $dataUrl) {
    throw new RuntimeException("unexpected URL: {$url}");
  }
  $revalidateCalls++;
  assertSameValue('"abc123"', $headers['If-None-Match'] ?? null, 'a conditional GET carries If-None-Match from the cached entry');
  assertSameValue('Mon, 01 Jan 2024 00:00:00 GMT', $headers['If-Modified-Since'] ?? null, 'a conditional GET carries If-Modified-Since from the cached entry');
  return new HttpResult(304, [], '');
});
$stamp2 = new CatalogStamp($http2, $gatewaySettings, $cache2, $nullLogger);
$caching2 = new CachingGatewayHttp($http2, $cache2, $stamp2, $nullLogger);
$result3 = $caching2->get($dataUrl);
assertSameValue('old body', $result3->body, 'a 304 response keeps the previously cached body');
assertSameValue(1, $revalidateCalls, 'exactly one conditional GET is issued for a 304');

// Network failure with an existing entry: serve stale, do not throw.
$cache3 = newTestCache($gatewaySettings);
$cache3->writeStamp(['value' => 'seed', 'checkedAt' => time()]);
$staleKey = $dataUrl . '?';
$cache3->set($staleKey, ['key' => $staleKey, 'url' => $dataUrl, 'fetchedAt' => time() - 1000, 'expiresAt' => time() - 1, 'etag' => null, 'lastModified' => null, 'body' => 'stale body']);
$http3 = new ScriptedGatewayHttp(function (string $url): HttpResult {
  throw new GatewayConnectionException('network is down');
});
$stamp3 = new CatalogStamp($http3, $gatewaySettings, $cache3, $nullLogger);
$caching3 = new CachingGatewayHttp($http3, $cache3, $stamp3, $nullLogger);
$result4 = $caching3->get($dataUrl);
assertSameValue('stale body', $result4->body, 'a network failure with a cached entry serves the stale body instead of throwing');

// Network failure with no entry at all: the exception propagates.
$cache4 = newTestCache($gatewaySettings);
$cache4->writeStamp(['value' => 'seed', 'checkedAt' => time()]);
$http4 = new ScriptedGatewayHttp(function (string $url): HttpResult {
  throw new GatewayConnectionException('network is down');
});
$stamp4 = new CatalogStamp($http4, $gatewaySettings, $cache4, $nullLogger);
$caching4 = new CachingGatewayHttp($http4, $cache4, $stamp4, $nullLogger);
$threw = false;
try {
  $caching4->get($dataUrl);
}
catch (GatewayConnectionException) {
  $threw = true;
}
assertSameValue(true, $threw, 'a network failure with no cached entry propagates the exception');

fwrite(STDOUT, "CachingGatewayHttp tests passed.\n");

// --- CatalogStamp: the invalidation trigger itself ---

// A changed stamp purges previously cached entries.
$cache5 = newTestCache($gatewaySettings);
$cache5->writeStamp(['value' => 'md:1:2020-01-01T00:00:00+00:00|cl:1:2020-01-01T00:00:00+00:00', 'checkedAt' => time() - 999999]);
$purgeKey = $dataUrl . '?';
$cache5->set($purgeKey, ['key' => $purgeKey, 'url' => $dataUrl, 'fetchedAt' => time(), 'expiresAt' => time() + 1000, 'etag' => null, 'lastModified' => null, 'body' => 'should be purged']);
$http5 = new ScriptedGatewayHttp(function (string $url): HttpResult {
  return new HttpResult(200, [], json_encode(['total' => 5, 'data' => [['changed' => '2026-05-05T00:00:00+00:00']]]));
});
(new CatalogStamp($http5, $gatewaySettings, $cache5, $nullLogger))->checkAndMaybePurge();
assertSameValue(null, $cache5->get($purgeKey), 'a changed catalog stamp purges every cached entry');

// An unchanged stamp leaves entries alone.
$cache6 = newTestCache($gatewaySettings);
$sameStamp = 'md:1:2020-01-01T00:00:00+00:00|cl:1:2020-01-01T00:00:00+00:00';
$cache6->writeStamp(['value' => $sameStamp, 'checkedAt' => time() - 999999]);
$keepKey = $dataUrl . '?';
$cache6->set($keepKey, ['key' => $keepKey, 'url' => $dataUrl, 'fetchedAt' => time(), 'expiresAt' => time() + 1000, 'etag' => null, 'lastModified' => null, 'body' => 'should survive']);
$http6 = new ScriptedGatewayHttp(function (string $url): HttpResult {
  return new HttpResult(200, [], json_encode(['total' => 1, 'data' => [['changed' => '2020-01-01T00:00:00+00:00']]]));
});
(new CatalogStamp($http6, $gatewaySettings, $cache6, $nullLogger))->checkAndMaybePurge();
assertSameValue('should survive', $cache6->get($keepKey)['body'] ?? null, 'an unchanged catalog stamp leaves cached entries alone');

// Rate-limiting: a recently-checked stamp makes no probe request at all.
$cache7 = newTestCache($gatewaySettings);
$cache7->writeStamp(['value' => 'whatever', 'checkedAt' => time()]);
$probeCalls7 = 0;
$http7 = new ScriptedGatewayHttp(function () use (&$probeCalls7): HttpResult {
  $probeCalls7++;
  throw new RuntimeException('the probe must not fire within STAMP_TTL');
});
(new CatalogStamp($http7, $gatewaySettings, $cache7, $nullLogger))->checkAndMaybePurge();
assertSameValue(0, $probeCalls7, 'a recent stamp check is rate-limited — no probe request is made');

// A failed probe is swallowed — the existing cache keeps serving.
$cache8 = newTestCache($gatewaySettings);
$cache8->writeStamp(['value' => 'v', 'checkedAt' => time() - 999999]);
$keptKey = $dataUrl . '?';
$cache8->set($keptKey, ['key' => $keptKey, 'url' => $dataUrl, 'fetchedAt' => time(), 'expiresAt' => time() + 1000, 'etag' => null, 'lastModified' => null, 'body' => 'kept']);
$http8 = new ScriptedGatewayHttp(function (): HttpResult {
  throw new GatewayConnectionException('probe network down');
});
(new CatalogStamp($http8, $gatewaySettings, $cache8, $nullLogger))->checkAndMaybePurge();
assertSameValue('kept', $cache8->get($keptKey)['body'] ?? null, 'a failed probe is swallowed and leaves the cache untouched');

// DROP_CONTEXT_CACHE=0 skips the probe entirely — nothing persisted to invalidate.
putenv('DROP_CONTEXT_CACHE=0');
$cache9 = newTestCache($gatewaySettings);
$probeCalls9 = 0;
$http9 = new ScriptedGatewayHttp(function () use (&$probeCalls9): HttpResult {
  $probeCalls9++;
  throw new RuntimeException('should not be called');
});
(new CatalogStamp($http9, $gatewaySettings, $cache9, $nullLogger))->checkAndMaybePurge();
assertSameValue(0, $probeCalls9, 'DROP_CONTEXT_CACHE=0 skips the stamp probe entirely');
putenv('DROP_CONTEXT_CACHE');

fwrite(STDOUT, "CatalogStamp tests passed.\n");
