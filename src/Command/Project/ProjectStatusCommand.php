<?php

declare(strict_types=1);

namespace DropContext\Command\Project;

use DropContext\Catalog\ReleaseVersion;
use DropContext\Command\DropContextBaseCommand;
use DropContext\Console\Ui;
use DropContext\Gateway\Dto\ModuleRow;
use DropContext\Gateway\Dto\ReleaseRow;
use DropContext\Gateway\Dto\SkillRow;
use DropContext\Gateway\Exception\GatewayException;
use DropContext\Gateway\Repository\ModuleRepository;
use DropContext\Gateway\Repository\ReleaseRepository;
use DropContext\Gateway\Repository\SkillRepository;
use DropContext\Service\AppInstaller;
use DropContext\Service\AppStoreService;
use DropContext\Service\CodeAgentsService;
use DropContext\Service\SkillService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Maps the current Drupal project's installed modules (from composer.lock)
 * against the Drop Context catalog: which are documented, at what version,
 * and whether a generated skill exists for them / is installed here.
 */
#[AsCommand(
  name: 'project:status',
  description: "Map the current project's installed modules against the Drop Context catalog",
  aliases: ['p:status'],
)]
class ProjectStatusCommand extends DropContextBaseCommand {

  public function __construct(
    AppInstaller $appInstaller,
    Ui $ui,
    protected readonly ModuleRepository $moduleRepository,
    protected readonly ReleaseRepository $releaseRepository,
    protected readonly SkillRepository $skillRepository,
    protected readonly SkillService $skillService,
    protected readonly AppStoreService $appStoreService,
  ) {
    parent::__construct($appInstaller, $ui);
  }

  /** Read-only and must work before the first-run wizard ever ran. */
  protected function requiresAppSetup(): bool {
    return false;
  }

  public function execute(InputInterface $input, OutputInterface $output): int {
    $this->ui->intro('Checking project status');

    $projectRoot = $this->appStoreService->getRunnableProject()?->path ?? getcwd();

    $lock = $this->readComposerLock($projectRoot);
    if ($lock === null) {
      $message = "No composer.json/composer.lock found in {$projectRoot}. " .
        'Run this command from the root of a Drupal project.';
      $this->ui->error($message);
      if ($this->ui->isJson()) {
        $this->ui->json(['error' => $message]);
      }
      return Command::FAILURE;
    }

    [$core, $installedModules] = $this->parseComposerLock($lock);

    if ($installedModules === []) {
      if ($this->ui->isJson()) {
        $this->ui->json([
          'core' => $core,
          'modules' => [],
          'summary' => ['total' => 0, 'documented' => 0, 'with_skill' => 0, 'skills_installed' => 0],
        ]);
        return Command::SUCCESS;
      }
      $this->ui->note('No drupal/* modules found in composer.lock.');
      return Command::SUCCESS;
    }

    try {
      [$moduleRows, $releasesByModule, $skillsByTitle] = $this->ui->spin(
        fn () => $this->fetchCatalogData($installedModules),
        'Checking the Drop Context catalog…',
      );
    }
    catch (GatewayException $e) {
      $this->ui->error($this->describeGatewayError($e));
      return Command::FAILURE;
    }

    $rows = $this->buildRows($installedModules, $moduleRows, $releasesByModule, $skillsByTitle);
    ksort($rows);

    $summary = [
      'total' => count($rows),
      'documented' => count(array_filter($rows, static fn (array $row) => $row['documented'] !== [])),
      'with_skill' => count(array_filter($rows, static fn (array $row) => $row['skill'] !== null && $row['skill']['available'])),
      'skills_installed' => count(array_filter($rows, static fn (array $row) => $row['skill'] !== null && $row['skill']['installed'])),
    ];

    if ($this->ui->isJson()) {
      $this->ui->json([
        'core' => $core,
        'modules' => array_values($rows),
        'summary' => $summary,
      ]);
      return Command::SUCCESS;
    }

    $this->ui->info('Core: ' . ($core ?? 'unknown'));

    $tableRows = array_map(static function (array $row) {
      $skill = $row['skill'];
      $skillLabel = match (true) {
        $skill === null => '—',
        $skill['installed'] => "{$skill['name']} (installed)",
        $skill['available'] => "{$skill['name']} (available)",
        default => $skill['name'],
      };

      return [
        $row['machine'],
        $row['installed'],
        $row['documented'] === [] ? '—' : implode(', ', $row['documented']),
        $row['match'],
        $skillLabel,
      ];
    }, $rows);

    $this->ui->table(['Machine name', 'Installed', 'Documented', 'Match', 'Skill'], $tableRows);
    $this->ui->outro(
      "{$summary['total']} module(s), {$summary['documented']} documented, " .
      "{$summary['with_skill']} with a skill, {$summary['skills_installed']} skill(s) installed"
    );

    return Command::SUCCESS;
  }

  /** @return array{0: array<string, ModuleRow>, 1: array<string, ReleaseRow[]>, 2: array<string, SkillRow[]>} */
  private function fetchCatalogData(array $installedModules): array {
    $machineNames = array_keys($installedModules);
    $moduleRows = $this->moduleRepository->findByMachineNames($machineNames);

    $moduleUuids = array_map(static fn (ModuleRow $module) => $module->uuid, $moduleRows);
    $releasesByModule = $moduleUuids === [] ? [] : $this->releaseRepository->findByModuleUuids($moduleUuids);

    $titles = array_map(
      static fn (string $machineName) => 'dc-' . str_replace('_', '-', $machineName),
      $machineNames,
    );
    $skillsByTitle = $this->skillRepository->findByTitles($titles);

    return [$moduleRows, $releasesByModule, $skillsByTitle];
  }

  /**
   * @param array<string, string> $installedModules machineName => installed version
   * @param array<string, ModuleRow> $moduleRows
   * @param array<string, ReleaseRow[]> $releasesByModule keyed by module uuid
   * @param array<string, SkillRow[]> $skillsByTitle
   * @return array<string, array{machine: string, installed: string, documented: string[], match: string, skill: array{name: string, available: bool, installed: bool}|null}>
   */
  private function buildRows(array $installedModules, array $moduleRows, array $releasesByModule, array $skillsByTitle): array {
    $rows = [];

    foreach ($installedModules as $machineName => $installedVersion) {
      $module = $moduleRows[$machineName] ?? null;
      $releases = $module !== null ? ($releasesByModule[$module->uuid] ?? []) : [];

      $documented = array_values(array_unique(array_filter(
        array_map(static fn (ReleaseRow $release) => $release->releaseTag, $releases),
        static fn (?string $tag) => $tag !== null && $tag !== '',
      )));

      $match = $this->matchVersion($installedVersion, $releases, $documented);

      $skillTitle = 'dc-' . str_replace('_', '-', $machineName);
      $skillCandidates = $skillsByTitle[$skillTitle] ?? [];
      $skill = $skillCandidates === [] ? null : [
        'name' => $skillTitle,
        'available' => true,
        'installed' => $this->isSkillInstalled($skillTitle),
      ];

      $rows[$machineName] = [
        'machine' => $machineName,
        'installed' => $installedVersion,
        'documented' => $documented,
        'match' => $match,
        'skill' => $skill,
      ];
    }

    return $rows;
  }

  /** True when a skill of that title is installed in the current project — app.json record or an on-disk symlink. */
  private function isSkillInstalled(string $skillTitle): bool {
    if ($this->skillService->findInstalledPaths($skillTitle, CodeAgentsService::KNOWN_CODE_AGENTS) !== []) {
      return true;
    }
    foreach ($this->appStoreService->skills->all() as $entry) {
      if (($entry->name ?? null) === $skillTitle) {
        return true;
      }
    }
    return false;
  }

  /** @param ReleaseRow[] $releases @param string[] $documented */
  private function matchVersion(string $installed, array $releases, array $documented): string {
    if ($documented === []) {
      return 'none';
    }

    foreach ($documented as $tag) {
      if (self::versionsEqual($tag, $installed)) {
        return 'exact';
      }
    }

    $installedMajor = self::majorComponent($installed);

    foreach ($releases as $release) {
      if ($release->releaseLine !== null && self::majorComponent($release->releaseLine) === $installedMajor) {
        return 'line';
      }
    }

    foreach ($documented as $tag) {
      if (self::majorComponent($tag) === $installedMajor) {
        return 'line';
      }
    }

    return 'none';
  }

  /** Tolerant tag/installed-version equality: strips `v`, legacy `N.x-` prefixes, and a trailing `.0` patch. */
  private static function versionsEqual(string $a, string $b): bool {
    $normalizedA = self::normalizeForCompare($a);
    $normalizedB = self::normalizeForCompare($b);
    if ($normalizedA === $normalizedB) {
      return true;
    }
    return @version_compare($normalizedA, $normalizedB) === 0;
  }

  private static function normalizeForCompare(string $version): string {
    $version = ltrim(trim($version), 'v');
    $version = ReleaseVersion::normalize($version);
    $parts = explode('.', $version);
    while (count($parts) > 1 && end($parts) === '0') {
      array_pop($parts);
    }
    return implode('.', $parts);
  }

  private static function majorComponent(string $version): ?string {
    $normalized = self::normalizeForCompare($version);
    if (preg_match('/^(\d+)/', $normalized, $matches) === 1) {
      return $matches[1];
    }
    return null;
  }

  /** @return array{0: string|null, 1: array<string, string>} [core version, machineName => installed version] */
  private function parseComposerLock(array $lock): array {
    $packages = [...($lock['packages'] ?? []), ...($lock['packages-dev'] ?? [])];

    $coreVersion = null;
    $coreRecommendedVersion = null;
    $modules = [];

    foreach ($packages as $package) {
      $name = (string) ($package['name'] ?? '');
      $version = isset($package['version']) ? ltrim((string) $package['version'], 'v') : null;

      if ($version === null || $name === '') {
        continue;
      }

      if ($name === 'drupal/core') {
        $coreVersion = $version;
      }
      elseif ($name === 'drupal/core-recommended') {
        $coreRecommendedVersion = $version;
      }
      elseif (str_starts_with($name, 'drupal/') && ($package['type'] ?? null) === 'drupal-module') {
        $machineName = substr($name, strlen('drupal/'));
        $modules[$machineName] = $version;
      }
    }

    ksort($modules);

    return [$coreVersion ?? $coreRecommendedVersion, $modules];
  }

  /** @return array<string, mixed>|null */
  private function readComposerLock(string $projectRoot): ?array {
    $composerJson = $projectRoot . DIRECTORY_SEPARATOR . 'composer.json';
    $composerLock = $projectRoot . DIRECTORY_SEPARATOR . 'composer.lock';

    if (!file_exists($composerJson) || !file_exists($composerLock)) {
      return null;
    }

    $decoded = json_decode(file_get_contents($composerLock), true);
    return is_array($decoded) ? $decoded : null;
  }

}
