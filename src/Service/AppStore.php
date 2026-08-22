<?php

namespace DrupalContext\Service;

use DrupalContext\Application;
use DrupalContext\ValueObject\AppValueObject;
use DrupalContext\ValueObject\ProjectValueObject;
use DrupalContext\ValueObject\RunnableProjectValueObject;

/**
 * Single, shared holder for the loaded app config state.
 *
 * Symfony shares one instance of this service, so every service that injects
 * it sees the same $app. State must live here (composition), never in a base
 * class that others extend (inheritance shares code, not instances).
 */
class AppStore {

  const APP_ROOT_DIR = '.drupal-context';

  const APP_SKILLS_DIR = 'skills';

  const APP_AGENTS_DIR = 'agents';

  const APP_CONFIG_FILE = 'app.json';

  protected AppValueObject $app;

  protected ?array $composerData = null;

  protected int|false|null $projectIndex = null;

  protected ?RunnableProjectValueObject $runnableProject = null;

  public function __construct() {
    if ($this->getProjectComposer()) {
      $this->runnableProject = new RunnableProjectValueObject(
        name: $this->getProjectComposer()['name'],
        path: getcwd(),
      );
    }

    $this->load();

    $this->projectIndex = $this->runnableProject !== null
      ? array_search($this->runnableProject->name, array_column($this->app->projects, 'name'))
      : null;
  }

  public function getApp(): AppValueObject {
    return $this->app;
  }

  public function getProjects(): array {
    return $this->app->projects;
  }

  public function getProjectIndex(): int|false|null {
    return $this->projectIndex;
  }

  public function getRunnableProject(): ?RunnableProjectValueObject {
    return $this->runnableProject;
  }

  public function isInstalled(): bool {
    return file_exists($this->getAppConfigFilePath());
  }

  public function load(): AppValueObject {
    $config_file_path = $this->getAppConfigFilePath();
    $now = time();

    if (!file_exists($config_file_path)) {
      $this->app = new AppValueObject(
        version: Application::currentVersion(),
        created: $now,
        changed: $now,
        projects: [],
        skills: [],
        agents: [],
        codeAgents: [],
      );
    }
    else {
      $config = json_decode(file_get_contents($config_file_path), false, 512, JSON_THROW_ON_ERROR);
      $this->app = new AppValueObject(
        version: Application::currentVersion(),
        created: $config->created,
        changed: $config->changed,
        projects: $config->projects,
        skills: $config->skills,
        agents: $config->agents,
        codeAgents: $config->codeAgents,
        gateway: $config->gateway ?? null,
      );
    }

    return $this->app;
  }

  public function save(): void {
    $this->installApp();
    file_put_contents(
      $this->getAppConfigFilePath(),
      json_encode($this->app, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)
    );
  }

  public function setupCurrentProject(): void {
    if ($this->runnableProject === null) {
      return;
    }
    $exist = array_filter($this->app->projects, fn($project) => $project->name === $this->runnableProject->name);
    if (empty($exist)) {
      $this->app->projects[] = new ProjectValueObject(
        name: $this->runnableProject->name,
        path: $this->runnableProject->path,
        version: Application::currentVersion(),
        skills: [],
        agents: [],
      );
      $this->projectIndex = array_key_last($this->app->projects);
    }
  }

  public function getProjectComposer(): ?array {
    if ($this->composerData !== null) {
      return $this->composerData;
    }

    $currentProject = getcwd();
    if (!file_exists($currentProject . DIRECTORY_SEPARATOR . 'composer.json')) {
      return null;
    }
    $composer = json_decode(file_get_contents($currentProject . DIRECTORY_SEPARATOR . 'composer.json'), true);
    $this->composerData = $composer;
    return $this->composerData;
  }

  public function getAppRootDir(): string {
    return getenv('HOME') . DIRECTORY_SEPARATOR . self::APP_ROOT_DIR;
  }

  public function getAppSkillsDir(): string {
    return $this->getAppRootDir() . DIRECTORY_SEPARATOR . self::APP_SKILLS_DIR;
  }

  public function getAppAgentsDir(): string {
    return $this->getAppRootDir() . DIRECTORY_SEPARATOR . self::APP_AGENTS_DIR;
  }

  public function getAppConfigFilePath(): string {
    return $this->getAppRootDir() . DIRECTORY_SEPARATOR . self::APP_CONFIG_FILE;
  }

  protected function installApp(): void {
    if (is_dir($this->getAppRootDir())) {
      return;
    }
    mkdir($this->getAppRootDir(), 0755, true);
  }

}
