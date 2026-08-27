<?php

declare(strict_types=1);

namespace DropContext\Service;

use DropContext\Gateway\Dto\SkillRow;
use DropContext\Gateway\Repository\SkillRepository;
use DropContext\SkillUpdateState;
use DropContext\ValueObject\InstalledSkillStatus;
use DropContext\ValueObject\SkillValueObject;

class SkillStatusService {

  public function __construct(
    private readonly SkillRepository $skillRepository,
    private readonly SkillService $skillService,
    private readonly AppStoreService $appStoreService,
  ) {}

  /** @return InstalledSkillStatus[] */
  public function inspect(): array {
    $installed = $this->skillService->listInstalledSkills();
    if ($installed === []) {
      return [];
    }

    $uuids = array_values(array_filter(array_column($installed, 'uuid')));
    $names = array_column($installed, 'name');

    // UUID is the durable identity for new installs. Exact title remains the
    // compatibility path for entries written before UUIDs were persisted.
    $byUuid = $this->skillRepository->findByUuids($uuids);
    $byTitle = $this->skillRepository->findByTitles($names);

    return array_map(function (array $local) use ($byUuid, $byTitle): InstalledSkillStatus {
      $matches = $byTitle[$local['name']] ?? [];
      $remote = $local['uuid'] !== null ? ($byUuid[$local['uuid']] ?? null) : null;
      $remote ??= count($matches) === 1 ? $matches[0] : null;

      if ($remote === null) {
        $message = count($matches) > 1
          ? 'More than one remote skill has this title and the local entry has no usable UUID.'
          : 'The skill could not be found in the Drop Context gateway.';
        return $this->status($local, null, SkillUpdateState::UNAVAILABLE, $message);
      }

      if ($local['paths'] === []) {
        return $this->status(
          $local,
          $remote,
          SkillUpdateState::MISSING,
          'The registry entry exists, but no installed skill path was found in this project.',
        );
      }

      $latestVersion = self::version($remote->version);
      if ($latestVersion === null) {
        return $this->status(
          $local,
          $remote,
          SkillUpdateState::VERSION_UNKNOWN,
          'The gateway did not return a version for this skill.',
        );
      }

      $installedVersion = trim($local['version']);
      $state = match (true) {
        $installedVersion === $latestVersion => SkillUpdateState::CURRENT,
        // Before field_skill_version existed, the CLI persisted this fixed
        // placeholder. Its one-time transition to the server's 0.1 baseline
        // is a migration, not a downgrade.
        $installedVersion === '', $installedVersion === '1.0.0' => SkillUpdateState::UPDATE_AVAILABLE,
        version_compare($latestVersion, $installedVersion, '>') => SkillUpdateState::UPDATE_AVAILABLE,
        default => SkillUpdateState::LOCAL_NEWER,
      };

      return $this->status($local, $remote, $state);
    }, $installed);
  }

  public function update(InstalledSkillStatus $status): SkillValueObject {
    if (!$status->hasUpdate()) {
      throw new \RuntimeException("Skill '{$status->name}' does not have an available update.");
    }

    $agents = array_keys($status->paths);
    if ($agents === []) {
      throw new \RuntimeException("Skill '{$status->name}' has no installed agent path to update.");
    }

    $updated = $this->skillService->installSkill($status->remoteSkill, $agents);

    // A title change changes the canonical directory name. Install the new
    // target first, then remove the old links and replace the registry entry.
    if ($updated->name !== $status->name) {
      $this->skillService->removeSkill($status->name, $agents);
    }
    $this->appStoreService->skills->replace($status->name, $updated);

    return $updated;
  }

  /**
   * @param array{name: string, uuid: string|null, version: string, changed: int|null, paths: array<string, string>} $local
   */
  private function status(
    array $local,
    ?SkillRow $remote,
    SkillUpdateState $state,
    ?string $message = null,
  ): InstalledSkillStatus {
    $remoteUuid = $remote !== null && $remote->uuid !== '' ? $remote->uuid : null;
    return new InstalledSkillStatus(
      name: $local['name'],
      uuid: $local['uuid'] ?? $remoteUuid,
      installedVersion: trim($local['version']),
      latestVersion: self::version($remote?->version),
      state: $state,
      paths: $local['paths'],
      remoteSkill: $remote,
      message: $message,
    );
  }

  private static function version(?string $version): ?string {
    $version = $version !== null ? trim($version) : '';
    return $version !== '' ? $version : null;
  }

}
