<?php

namespace DrupalContext\Service;

use DrupalContext\ValueObject\AppValueObject;
use DrupalContext\ValueObject\RunnableProjectValueObject;

/**
 * Facade over the shared AppStore and its sub-stores.
 */
class AppStoreService {

  public function __construct(
    protected AppStore $store,
    public AppStoreSkillService $skills,
    public AppStoreProjectService $projects,
  ) {}

  public function getAppConfigFile(): AppValueObject {
    return $this->store->getApp();
  }

  public function getRunnableProject(): ?RunnableProjectValueObject {
    return $this->store->getRunnableProject();
  }

  public function getAppRootDir(): string {
    return $this->store->getAppRootDir();
  }

  public function getAppSkillsDir(): string {
    return $this->store->getAppSkillsDir();
  }

  public function getAppAgentsDir(): string {
    return $this->store->getAppAgentsDir();
  }

  public function writeAppConfig(AppValueObject $app): void {
    $this->store->save();
  }

  public function isInstalled(): bool {
    return $this->store->isInstalled();
  }

}