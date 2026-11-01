<?php

declare(strict_types=1);

namespace DropContext\DevKit;

use Symfony\Component\Filesystem\Filesystem;

/**
 * Adds and removes one MCP server entry in a client's config file.
 *
 * JSON configs (Claude Code, Cursor, OpenCode) get a key under a container
 * object; every other key in the file is preserved. Codex's TOML config gets
 * a block between BEGIN/END markers, so it can be removed without parsing
 * the rest of the file.
 */
class McpConfigWriter {

  public const ADDED = 'added';
  public const UNCHANGED = 'unchanged';
  public const KEPT = 'kept';

  private const TOML_BEGIN = '# BEGIN drop-context dev:install';
  private const TOML_END = '# END drop-context dev:install';

  public function __construct(
    private readonly Filesystem $filesystem,
  ) {}

  /**
   * Sets $container.$name to $server, unless another value is already there.
   *
   * @param array<string, mixed> $server
   * @param array<string, mixed> $defaults top-level keys for a new file
   * @return string ADDED, UNCHANGED (already equal) or KEPT (left as is)
   */
  public function addJson(string $file, string $container, string $name, array $server, array $defaults = []): string {
    $data = $this->readJson($file) ?? (object) $defaults;
    if (!isset($data->{$container}) || !is_object($data->{$container})) {
      $data->{$container} = new \stdClass();
    }
    $wanted = json_decode(json_encode($server, JSON_THROW_ON_ERROR));
    if (isset($data->{$container}->{$name})) {
      return $data->{$container}->{$name} == $wanted ? self::UNCHANGED : self::KEPT;
    }
    $data->{$container}->{$name} = $wanted;
    $this->writeJson($file, $data);
    return self::ADDED;
  }

  /** Removes $container.$name. Deletes the file when nothing else is left. */
  public function removeJson(string $file, string $container, string $name): bool {
    $data = $this->readJson($file);
    if ($data === null || !isset($data->{$container}->{$name})) {
      return FALSE;
    }
    unset($data->{$container}->{$name});
    if ((array) $data->{$container} === []) {
      unset($data->{$container});
    }
    $rest = array_diff(array_keys((array) $data), ['$schema']);
    if ($rest === []) {
      $this->filesystem->remove($file);
    }
    else {
      $this->writeJson($file, $data);
    }
    return TRUE;
  }

  /** Writes [mcp_servers.$name] into a Codex config.toml. */
  public function addToml(string $file, string $name, string $command): string {
    $content = is_file($file) ? (string) file_get_contents($file) : '';
    $block = self::TOML_BEGIN . "\n"
      . "[mcp_servers.{$name}]\n"
      . 'command = ' . json_encode($command, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
      . self::TOML_END . "\n";

    $existing = $this->tomlBlock($content);
    if ($existing !== null) {
      if ($existing === $block) {
        return self::UNCHANGED;
      }
      $this->filesystem->dumpFile($file, str_replace($existing, $block, $content));
      return self::ADDED;
    }

    $table = '/^\s*\[mcp_servers\.(?:"' . preg_quote($name, '/') . '"|' . preg_quote($name, '/') . ')\]\s*$/m';
    if (preg_match($table, $content)) {
      return self::KEPT;
    }

    $separator = $content === '' || str_ends_with($content, "\n\n") ? '' : (str_ends_with($content, "\n") ? "\n" : "\n\n");
    $this->filesystem->dumpFile($file, $content . $separator . $block);
    return self::ADDED;
  }

  /** Removes the marked block. Deletes the file when nothing else is left. */
  public function removeToml(string $file): bool {
    $content = is_file($file) ? (string) file_get_contents($file) : '';
    $block = $this->tomlBlock($content);
    if ($block === null) {
      return FALSE;
    }
    $rest = str_replace($block, '', $content);
    if (trim($rest) === '') {
      $this->filesystem->remove($file);
    }
    else {
      $this->filesystem->dumpFile($file, rtrim($rest, "\n") . "\n");
    }
    return TRUE;
  }

  private function tomlBlock(string $content): ?string {
    $pattern = '/' . preg_quote(self::TOML_BEGIN, '/') . '\n.*?' . preg_quote(self::TOML_END, '/') . '\n?/s';
    return preg_match($pattern, $content, $matches) ? $matches[0] : null;
  }

  /** @return object|null null when the file does not exist */
  private function readJson(string $file): ?object {
    if (!is_file($file)) {
      return null;
    }
    $text = (string) file_get_contents($file);
    if (trim($text) === '') {
      return new \stdClass();
    }
    try {
      $data = json_decode($text, false, 512, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException $e) {
      throw new \RuntimeException("Cannot parse {$file} as JSON ({$e->getMessage()}).", previous: $e);
    }
    if (!is_object($data)) {
      throw new \RuntimeException("{$file} does not hold a JSON object.");
    }
    return $data;
  }

  private function writeJson(string $file, object $data): void {
    $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    // PHP indents with four spaces; client configs use two.
    $json = preg_replace_callback('/^( {4})+/m', static fn (array $m): string => str_repeat('  ', strlen($m[0]) / 4), $json);
    $this->filesystem->dumpFile($file, $json . "\n");
  }

}
