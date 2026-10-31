<?php

declare(strict_types=1);

namespace DropContext\DevKit;

use Symfony\Component\Filesystem\Filesystem;

/**
 * Installs drop-context-dev into a project, once per code agent directory.
 *
 * What each code agent gets:
 *
 * @code
 * .claude    .claude/skills/*    .claude/agents/<agent>.md (link)   .mcp.json
 * .cursor    .cursor/skills/*    .cursor/agents/<agent>.md          .cursor/mcp.json
 * .codex     .agents/skills/*    .codex/agents/<agent>.toml         .codex/config.toml
 * .opencode  .opencode/skills/*  .opencode/agents/<agent>.md        opencode.json
 *            + .opencode/commands/<skill>.md
 * .agents    .agents/skills/*
 * @endcode
 *
 * Skills, and the Claude Code agent, are symlinks into the kit, so updating
 * the kit updates them. Agents in other formats are rendered files.
 */
class DevKitInstaller {

  public const AGENT_NAME = 'drop-context-developer';

  public const MCP_SERVER = 'drop-context';

  public function __construct(
    private readonly Filesystem $filesystem,
    private readonly AgentRenderer $renderer,
    private readonly McpConfigWriter $mcpWriter,
  ) {}

  /** @return string[] the code agent directories dev:install supports */
  public static function supportedCodeAgents(): array {
    return ['.claude', '.cursor', '.codex', '.opencode', '.agents'];
  }

  /**
   * @param string[] $codeAgents code agent directories, e.g. ['.claude']
   * @param string $mcpCommand absolute path of drop-context-mcp
   * @param string[] $owned project-relative paths a previous install wrote
   * @param string[] $linkRoots directories whose symlinks count as ours
   */
  public function install(
    DevKit $kit,
    string $projectDir,
    array $codeAgents,
    string $mcpCommand,
    array $owned = [],
    array $linkRoots = [],
    bool $force = FALSE,
  ): DevKitInstallResult {
    $result = new DevKitInstallResult();
    $linkRoots[] = $kit->root;
    $agentFile = self::AGENT_NAME;

    foreach ($codeAgents as $codeAgent) {
      $files = [];
      switch ($codeAgent) {
        case '.claude':
          $files[".claude/agents/{$agentFile}.md"] = ['link', $kit->agentPath()];
          $skillsDir = '.claude/skills';
          break;

        case '.cursor':
          $files[".cursor/agents/{$agentFile}.md"] = ['write', $this->renderer->cursor($kit)];
          $skillsDir = '.cursor/skills';
          break;

        case '.codex':
          $files[".codex/agents/{$agentFile}.toml"] = ['write', $this->renderer->codex($kit)];
          // Codex reads repository skills from .agents/skills, not .codex/skills.
          $skillsDir = '.agents/skills';
          break;

        case '.opencode':
          $files[".opencode/agents/{$agentFile}.md"] = ['write', $this->renderer->opencode($kit)];
          foreach (array_keys($kit->skills()) as $skill) {
            $files[".opencode/commands/{$skill}.md"] = ['write', $this->renderer->opencodeCommand($kit, $skill)];
          }
          $skillsDir = '.opencode/skills';
          break;

        case '.agents':
          $skillsDir = '.agents/skills';
          break;

        default:
          $result->warnings[] = "{$codeAgent} is not supported by dev:install; skipped.";
          continue 2;
      }

      $agentPath = array_key_first($files);
      foreach ($kit->skills() as $skill => $dir) {
        $files["{$skillsDir}/{$skill}"] = ['link', $dir];
      }

      foreach ($files as $path => [$mode, $payload]) {
        if (in_array($path, $result->paths, TRUE) || in_array($path, $result->skipped, TRUE)) {
          continue;
        }
        $this->place($projectDir, $path, $mode, $payload, $owned, $linkRoots, $force, $result);
      }

      $mcpFile = $this->registerMcp($codeAgent, $projectDir, $mcpCommand, $result);
      $result->summary[$codeAgent] = ['skills' => $skillsDir, 'agent' => $agentPath, 'mcp' => $mcpFile];
    }

    return $result;
  }

  /**
   * Removes what an install recorded: its files, and its MCP entries.
   *
   * @param string[] $paths project-relative paths
   * @param array<int, array{file: string, kind: string, container?: string}> $mcp
   * @return string[] warnings about what was left in place
   */
  public function uninstall(string $projectDir, array $paths, array $mcp): array {
    $warnings = [];
    foreach ($paths as $path) {
      $absolute = $projectDir . '/' . $path;
      if (!is_link($absolute) && !file_exists($absolute)) {
        continue;
      }
      if (is_link($absolute) || $this->isRenderedFile($absolute)) {
        $this->filesystem->remove($absolute);
        $this->pruneEmptyParents($projectDir, $path);
      }
      else {
        $warnings[] = "{$path} was changed by hand; left in place.";
      }
    }
    foreach ($mcp as $entry) {
      $file = $projectDir . '/' . $entry['file'];
      try {
        $entry['kind'] === 'toml'
          ? $this->mcpWriter->removeToml($file)
          : $this->mcpWriter->removeJson($file, (string) ($entry['container'] ?? 'mcpServers'), self::MCP_SERVER);
      }
      catch (\RuntimeException $e) {
        $warnings[] = $e->getMessage() . ' Remove the ' . self::MCP_SERVER . ' MCP server from it by hand.';
      }
    }
    return $warnings;
  }

  /**
   * Links or writes one file, unless something that is not ours is there.
   *
   * @param string[] $owned
   * @param string[] $linkRoots
   */
  private function place(
    string $projectDir,
    string $path,
    string $mode,
    string $payload,
    array $owned,
    array $linkRoots,
    bool $force,
    DevKitInstallResult $result,
  ): void {
    $absolute = $projectDir . '/' . $path;
    $exists = is_link($absolute) || file_exists($absolute);
    if ($exists && !$force && !$this->isOurs($absolute, $path, $owned, $linkRoots)) {
      $result->skipped[] = $path;
      return;
    }
    if ($exists) {
      $this->filesystem->remove($absolute);
    }
    if ($mode === 'link') {
      $this->filesystem->mkdir(dirname($absolute));
      $this->filesystem->symlink($payload, $absolute);
    }
    else {
      $this->filesystem->dumpFile($absolute, $payload);
    }
    $result->paths[] = $path;
  }

  /** @return string|null the config file the server is registered in */
  private function registerMcp(string $codeAgent, string $projectDir, string $command, DevKitInstallResult $result): ?string {
    $stdio = ['type' => 'stdio', 'command' => $command, 'args' => []];
    $targets = [
      '.claude' => ['.mcp.json', 'json', 'mcpServers', $stdio, []],
      '.cursor' => ['.cursor/mcp.json', 'json', 'mcpServers', $stdio, []],
      '.codex' => ['.codex/config.toml', 'toml', null, null, []],
      '.opencode' => ['opencode.json', 'json', 'mcp',
        ['type' => 'local', 'command' => [$command], 'enabled' => TRUE],
        ['$schema' => 'https://opencode.ai/config.json'],
      ],
    ];
    if (!isset($targets[$codeAgent])) {
      return null;
    }
    [$file, $kind, $container, $server, $defaults] = $targets[$codeAgent];
    foreach ($result->mcp as $entry) {
      if ($entry['file'] === $file) {
        return $file;
      }
    }
    if ($codeAgent === '.opencode' && is_file($projectDir . '/opencode.jsonc')) {
      $result->warnings[] = 'opencode.jsonc found: add the ' . self::MCP_SERVER . " MCP server to it by hand:\n"
        . json_encode(['mcp' => [self::MCP_SERVER => $server]], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
      return null;
    }

    try {
      $status = $kind === 'toml'
        ? $this->mcpWriter->addToml($projectDir . '/' . $file, self::MCP_SERVER, $command)
        : $this->mcpWriter->addJson($projectDir . '/' . $file, $container, self::MCP_SERVER, $server, $defaults);
    }
    catch (\RuntimeException $e) {
      $result->warnings[] = $e->getMessage() . ' Add the ' . self::MCP_SERVER . ' MCP server to it by hand.';
      return null;
    }

    $entry = ['file' => $file, 'kind' => $kind, 'status' => $status];
    if ($container !== null) {
      $entry['container'] = $container;
    }
    $result->mcp[] = $entry;
    return $file;
  }

  /**
   * @param string[] $owned
   * @param string[] $linkRoots
   */
  private function isOurs(string $absolute, string $path, array $owned, array $linkRoots): bool {
    if (in_array($path, $owned, TRUE)) {
      return TRUE;
    }
    if (is_link($absolute)) {
      $target = (string) readlink($absolute);
      foreach ($linkRoots as $root) {
        if (str_starts_with($target, rtrim($root, '/') . '/')) {
          return TRUE;
        }
      }
      return FALSE;
    }
    return $this->isRenderedFile($absolute);
  }

  /**
   * Removes the directories a path leaves empty, up to its code agent dir.
   *
   * The code agent dir itself (.claude, .cursor, …) stays: its presence is
   * how the CLI detects which code agents a project uses.
   */
  private function pruneEmptyParents(string $projectDir, string $path): void {
    $dir = dirname($path);
    while (str_contains($dir, '/')) {
      $absolute = $projectDir . '/' . $dir;
      if (!is_dir($absolute) || (scandir($absolute) ?: []) !== ['.', '..']) {
        return;
      }
      rmdir($absolute);
      $dir = dirname($dir);
    }
  }

  private function isRenderedFile(string $absolute): bool {
    if (!is_file($absolute)) {
      return FALSE;
    }
    $head = (string) file_get_contents($absolute, FALSE, null, 0, 512);
    return str_contains($head, AgentRenderer::MARKER);
  }

}
