<?php

namespace DropContext\Service;

/**
 * Handle Project store operations
 */
class AppStoreProjectService {

  public function __construct(
    protected AppStore $store,
  ) {}

  public function getProjects(): array {
    return $this->store->getProjects();
  }

  /** The current project's dev:install record, or null when there is none. */
  public function getDevKit(): ?object {
    $index = $this->store->getProjectIndex();
    if ($index === false || $index === null) {
      return null;
    }
    $record = $this->store->getApp()->projects[$index]->devKit ?? null;
    return is_object($record) ? $record : null;
  }

  /** Stores (or with null, clears) the current project's dev:install record. */
  public function setDevKit(?object $record): void {
    $index = $this->store->getProjectIndex();
    if ($index === false || $index === null) {
      throw new \RuntimeException('Run this command inside a registered project.');
    }
    $this->store->getApp()->projects[$index]->devKit = $record;
    $this->store->save();
  }

}