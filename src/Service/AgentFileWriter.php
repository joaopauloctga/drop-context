<?php

declare(strict_types=1);

namespace DropContext\Service;

use DropContext\Gateway\Dto\AgentRow;
use DropContext\Gateway\FileDownloader;
use Symfony\Component\Filesystem\Filesystem;

/** Writes a resolved AgentRow to disk as AGENTS.md + references/*. */
class AgentFileWriter {

  public function __construct(
    private readonly Filesystem $filesystem,
    private readonly FileDownloader $fileDownloader,
  ) {}

  /** @return string the directory the agent was written to */
  public function write(AgentRow $agent, string $destDir): string {
    if ($agent->content === null || trim($agent->content) === '') {
      throw new \RuntimeException("Agent '{$agent->title}' has no content — nothing to write.");
    }

    $dir = rtrim($destDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . self::cacheDirName($agent->installKey());
    $this->filesystem->mkdir($dir);
    $this->filesystem->dumpFile($dir . DIRECTORY_SEPARATOR . 'AGENTS.md', $agent->content);

    if (!$agent->referencesResolved) {
      throw new \RuntimeException("Agent '{$agent->title}' has reference files that could not be downloaded.");
    }

    $refsDir = $dir . DIRECTORY_SEPARATOR . 'references';
    $this->filesystem->remove($refsDir);

    if ($agent->references === []) {
      return $dir;
    }

    $this->filesystem->mkdir($refsDir);
    foreach ($agent->references as $reference) {
      $name = $reference->targetName();
      if ($name === null || $name === '.' || $name === '..') {
        throw new \RuntimeException("Agent '{$agent->title}' has a reference file with no usable filename.");
      }
      $this->filesystem->dumpFile(
        $refsDir . DIRECTORY_SEPARATOR . $name,
        $this->fileDownloader->download((string) $reference->url),
      );
    }

    return $dir;
  }

  /**
   * Filesystem-safe cache directory for an install key.
   *
   * User agents use `username:slug`; `:` is not portable in directory names.
   */
  public static function cacheDirName(string $installKey): string {
    $name = basename(str_replace('\\', '/', trim($installKey)));
    $name = str_replace(':', '--', $name);
    if ($name === '' || $name === '.' || $name === '..') {
      throw new \RuntimeException("Agent key '{$installKey}' cannot be used as a directory name.");
    }
    return $name;
  }

  /** @deprecated use cacheDirName() with installKey() */
  public static function sanitizeTitle(string $title): string {
    return self::cacheDirName($title);
  }

}
