<?php

declare(strict_types=1);

namespace DropContext\Command\Agent;

use DropContext\Console\Exception\NonInteractivePromptException;
use DropContext\Gateway\Dto\AgentLookupResult;
use DropContext\Gateway\Dto\AgentRow;

/** Shared not-found/ambiguous resolution UX for agent:add and agent:show. */
trait ResolvesAgentTrait {

  protected function resolveAgent(string $nameOrUuid, AgentLookupResult $result): ?AgentRow {
    if ($result->isFound()) {
      return $result->agent;
    }

    if ($result->isAmbiguous()) {
      return $this->promptCandidate($nameOrUuid, $result->candidates);
    }

    return $this->promptSuggestion($nameOrUuid, $result->suggestions);
  }

  /** @param AgentRow[] $candidates */
  private function promptCandidate(string $nameOrUuid, array $candidates): ?AgentRow {
    if ($this->ui->isJson()) {
      $this->ui->error("Multiple agents match '{$nameOrUuid}'.");
      $this->ui->json([
        'error' => "Multiple agents match '{$nameOrUuid}'.",
        'candidates' => self::identify($candidates),
      ]);
      return null;
    }

    if (!$this->ui->isInteractive()) {
      $this->ui->error("Multiple agents match '{$nameOrUuid}'.");
      $this->ui->note(implode("\n", array_map(
        static fn (AgentRow $agent) => ($agent->machineName ?: $agent->title) . " ({$agent->uuid})",
        $candidates,
      )));
      return null;
    }

    $options = [];
    foreach ($candidates as $candidate) {
      $options[$candidate->uuid] = ($candidate->machineName ?: $candidate->title) . " — {$candidate->title} ({$candidate->uuid})";
    }

    try {
      $uuid = $this->ui->select('Multiple agents match, choose one:', $options);
    }
    catch (NonInteractivePromptException) {
      $this->ui->error("Multiple agents match '{$nameOrUuid}'.");
      return null;
    }

    foreach ($candidates as $candidate) {
      if ($candidate->uuid === $uuid) {
        return $candidate;
      }
    }
    return null;
  }

  /** @param AgentRow[] $suggestions */
  private function promptSuggestion(string $nameOrUuid, array $suggestions): ?AgentRow {
    $this->ui->error("Agent '{$nameOrUuid}' not found.");

    if ($this->ui->isJson()) {
      $this->ui->json([
        'error' => "Agent '{$nameOrUuid}' not found.",
        'suggestions' => self::identify($suggestions),
      ]);
      return null;
    }

    if ($suggestions === []) {
      return null;
    }

    $titles = array_map(static fn (AgentRow $agent) => $agent->title, $suggestions);

    if ($this->ui->isInteractive()) {
      $options = [];
      foreach ($suggestions as $suggestion) {
        $options[$suggestion->uuid] = ($suggestion->machineName ?: $suggestion->title) . ' — ' . $suggestion->title;
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
        // Fall through.
      }
    }

    $this->ui->note('Did you mean: ' . implode(', ', $titles));
    return null;
  }

  /**
   * @param AgentRow[] $agents
   * @return array<int, array{uuid: string, title: string, machineName: ?string}>
   */
  private static function identify(array $agents): array {
    return array_map(
      static fn (AgentRow $agent) => [
        'uuid' => $agent->uuid,
        'title' => $agent->title,
        'machineName' => $agent->machineName,
      ],
      $agents,
    );
  }

}
