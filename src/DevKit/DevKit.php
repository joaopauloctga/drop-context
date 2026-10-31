<?php

declare(strict_types=1);

namespace DropContext\DevKit;

use Symfony\Component\Yaml\Yaml;

/**
 * A drop-context-dev tree on disk: its skills, its agent and its version.
 *
 * The tree is either a download of the GitHub repository or a local
 * checkout passed with `dev:install --from`.
 */
final class DevKit {

  public const AGENT_FILE = 'agents/drop-context-developer.md';

  private function __construct(
    public readonly string $root,
    public readonly string $source,
  ) {}

  /** Opens and validates a drop-context-dev tree. */
  public static function open(string $root, string $source): self {
    $real = realpath($root);
    if ($real === false || !is_dir($real)) {
      throw new \RuntimeException("No drop-context-dev tree at {$root}.");
    }
    $kit = new self($real, $source);
    if (!is_file($kit->agentPath())) {
      throw new \RuntimeException("{$real} is not a drop-context-dev tree: " . self::AGENT_FILE . ' is missing.');
    }
    if ($kit->skills() === []) {
      throw new \RuntimeException("{$real} is not a drop-context-dev tree: it has no skills/*/SKILL.md.");
    }
    return $kit;
  }

  /** The plugin version from .claude-plugin/plugin.json, or "unknown". */
  public function version(): string {
    $manifest = $this->root . '/.claude-plugin/plugin.json';
    if (!is_file($manifest)) {
      return 'unknown';
    }
    $data = json_decode((string) file_get_contents($manifest), true);
    return is_array($data) && is_string($data['version'] ?? null) ? $data['version'] : 'unknown';
  }

  /** @return array<string, string> skill name => absolute skill directory */
  public function skills(): array {
    $skills = [];
    foreach (glob($this->root . '/skills/*/SKILL.md') ?: [] as $file) {
      $dir = dirname($file);
      $skills[basename($dir)] = $dir;
    }
    ksort($skills);
    return $skills;
  }

  /** @return array<string, mixed> the skill's SKILL.md frontmatter */
  public function skillFrontmatter(string $skill): array {
    [$meta] = self::parseFrontmatter((string) file_get_contents($this->root . "/skills/{$skill}/SKILL.md"));
    return $meta;
  }

  public function agentPath(): string {
    return $this->root . '/' . self::AGENT_FILE;
  }

  /** @return array{name: string, description: string, body: string} */
  public function agent(): array {
    [$meta, $body] = self::parseFrontmatter((string) file_get_contents($this->agentPath()));
    foreach (['name', 'description'] as $field) {
      if (!is_string($meta[$field] ?? null) || trim($meta[$field]) === '') {
        throw new \RuntimeException(self::AGENT_FILE . " has no {$field} in its frontmatter.");
      }
    }
    return [
      'name' => $meta['name'],
      'description' => trim($meta['description']),
      'body' => $body,
    ];
  }

  /**
   * Splits a Markdown file into its YAML frontmatter and its body.
   *
   * @return array{0: array<string, mixed>, 1: string}
   */
  public static function parseFrontmatter(string $text): array {
    if (!preg_match('/\A---\r?\n(.*?)\r?\n---\r?\n(.*)\z/s', $text, $matches)) {
      return [[], $text];
    }
    $meta = Yaml::parse($matches[1]);
    return [is_array($meta) ? $meta : [], ltrim($matches[2], "\r\n")];
  }

}
