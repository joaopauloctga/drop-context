<?php

namespace DrupalContext\Service;

use DrupalContext\ValueObject\SkillValueObject;

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