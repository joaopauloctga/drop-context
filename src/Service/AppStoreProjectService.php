<?php

namespace DrupalContext\Service;

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
}