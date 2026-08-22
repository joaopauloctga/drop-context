<?php

declare(strict_types=1);

namespace DropContext\Command\Skill;

use DropContext\Console\Exception\NonInteractivePromptException;
use DropContext\Gateway\Dto\SkillLookupResult;
use DropContext\Gateway\Dto\SkillRow;

/** Shared not-found/ambiguous resolution UX for skill:add and skill:show. Requires $this->ui (DropContextBaseCommand). */
trait ResolvesSkillTrait {

  /** Resolves a lookup result to exactly one SkillRow, prompting on ambiguity. null means an error was already emitted. */
  protected function resolveSkill(string $nameOrUuid, SkillLookupResult $result): ?SkillRow {
    if ($result->isFound()) {
      return $result->skill;
    }

    if ($result->isAmbiguous()) {
      return $this->promptCandidate($nameOrUuid, $result->candidates);
    }

    return $this->promptSuggestion($nameOrUuid, $result->suggestions);
  }

  /** @param SkillRow[] $candidates */
  private function promptCandidate(string $nameOrUuid, array $candidates): ?SkillRow {
    if ($this->ui->isJson()) {
      $this->ui->error("Multiple skills match '{$nameOrUuid}'.");
      $this->ui->json([
        'error' => "Multiple skills match '{$nameOrUuid}'.",
        'candidates' => self::identify($candidates),
      ]);
      return null;
    }

    if (!$this->ui->isInteractive()) {
      $this->ui->error("Multiple skills match '{$nameOrUuid}'.");
      $this->ui->note(implode("\n", array_map(
        static fn (SkillRow $skill) => "{$skill->title} ({$skill->uuid})",
        $candidates,
      )));
      return null;
    }

    $options = [];
    foreach ($candidates as $candidate) {
      $options[$candidate->uuid] = "{$candidate->title} ({$candidate->uuid})";
    }

    try {
      $uuid = $this->ui->select('Multiple skills match, choose one:', $options);
    }
    catch (NonInteractivePromptException) {
      $this->ui->error("Multiple skills match '{$nameOrUuid}'.");
      return null;
    }

    foreach ($candidates as $candidate) {
      if ($candidate->uuid === $uuid) {
        return $candidate;
      }
    }
    return null;
  }

  /** @param SkillRow[] $suggestions */
  private function promptSuggestion(string $nameOrUuid, array $suggestions): ?SkillRow {
    $this->ui->error("Skill '{$nameOrUuid}' not found.");

    if ($this->ui->isJson()) {
      $this->ui->json([
        'error' => "Skill '{$nameOrUuid}' not found.",
        'suggestions' => self::identify($suggestions),
      ]);
      return null;
    }

    if ($suggestions === []) {
      return null;
    }

    $titles = array_map(static fn (SkillRow $skill) => $skill->title, $suggestions);

    if ($this->ui->isInteractive()) {
      $options = [];
      foreach ($suggestions as $suggestion) {
        $options[$suggestion->uuid] = $suggestion->title;
      }
      try {
        $uuid = $this->ui->select('Did you mean:', $options);
        foreach ($suggestions as $suggestion) {
          if ($suggestion->uuid === $uuid) {
            return $suggestion;
          }
        }
        return null;
      }
      catch (NonInteractivePromptException) {
        // Fall through to the plain suggestion list below.
      }
    }

    $this->ui->note('Did you mean: ' . implode(', ', $titles));
    return null;
  }

  /**
   * @param SkillRow[] $skills
   * @return array<int, array{uuid: string, title: string}>
   */
  private static function identify(array $skills): array {
    return array_map(
      static fn (SkillRow $skill) => ['uuid' => $skill->uuid, 'title' => $skill->title],
      $skills,
    );
  }

}
