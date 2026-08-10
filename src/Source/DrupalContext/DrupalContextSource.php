<?php

namespace DrupalContext\Source\DrupalContext;

use DrupalContext\Service\AppStoreService;
use DrupalContext\Source\SourceInterface;

class DrupalContextSource implements SourceInterface {

  public function __construct(
    protected Client $client,
    protected AppStoreService $appStoreService,
  ){

  }

  public function downloadSkill($skillName): bool {
    $skillDestination = implode(DIRECTORY_SEPARATOR, [$this->appStoreService->getAppSkillsDir(), $skillName]);
    $tempFile = tempnam(sys_get_temp_dir(), 'drupal-context-skill');
    $this->client->get("/context/skill/$skillName", [
      'sink' => $tempFile,
    ]);
    $zipFile = new \ZipArchive();
    $zipFile->open($tempFile);
    $zipFile->extractTo($skillDestination);
    $zipFile->close();
    return true;
  }

  public function getSkillList(?string $module = null): array {
    return [];
  }
}