<?php

namespace DropContext\Service;

use DropContext\ValueObject\SkillValueObject;

/**
 * Handle Skill store operations.
 */
class AppStoreSkillService {

  public function __construct(
    protected AppStore $store,
  ) {}

  public function attach(SkillValueObject $skill): void {
    $index = array_search(
      $skill->name,
      array_column($this->store->getApp()->projects[$this->store->getProjectIndex()]->skills, 'name')
    );
    if ($index !== false) {
      $this->store->getApp()->projects[$this->store->getProjectIndex()]->skills[$index] = $skill;
    }
    else {
      $this->store->getApp()->projects[$this->store->getProjectIndex()]->skills[] = $skill;
    }
    $this->store->save();
  }

  /**
   * The current project's skill registry entries.
   *
   * Entries are SkillValueObject when attached in this run and stdClass when
   * read back from app.json — both carry the same public property names.
   *
   * @return array<int, object>
   */
  public function all(): array {
    $index = $this->store->getProjectIndex();
    if ($index === false || $index === null) {
      return [];
    }
    return array_values($this->store->getApp()->projects[$index]->skills ?? []);
  }

  public function isInstalled(string $skillName): bool {
    $skills = $this->store->getApp()->projects[$this->store->getProjectIndex()]->skills;
    return !empty(array_filter($skills, fn($skill) => $skill->name === $skillName));
  }

  public function detach(string $skillName): void {
    $project = $this->store->getApp()->projects[$this->store->getProjectIndex()];
    $project->skills = array_values(
      array_filter($project->skills, fn($skill) => $skill->name !== $skillName)
    );
    $this->store->save();
  }

}