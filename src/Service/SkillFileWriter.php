<?php

declare(strict_types=1);

namespace DropContext\Service;

use DropContext\Gateway\Dto\SkillRow;
use DropContext\Gateway\FileDownloader;
use Symfony\Component\Filesystem\Filesystem;

/** Writes a resolved SkillRow to disk as SKILL.md + references/*. */
class SkillFileWriter {

  public function __construct(
    private readonly Filesystem $filesystem,
    private readonly FileDownloader $fileDownloader,
  ) {}

  /** @return string the directory the skill was written to */
  public function write(SkillRow $skill, string $destDir): string {
    if ($skill->content === null || trim($skill->content) === '') {
      throw new \RuntimeException("Skill '{$skill->title}' has no content — nothing to write.");
    }

    $dir = rtrim($destDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . self::sanitizeTitle($skill->title);
    $this->filesystem->mkdir($dir);
    $this->filesystem->dumpFile($dir . DIRECTORY_SEPARATOR . 'SKILL.md', $skill->content);

    // Reference entries with no download URL used to leave the skill silently
    // short of its references/ dir. The cause is server-side, so the message
    // stays at what the user can act on — never the source's own internals.
    if (!$skill->referencesResolved) {
      throw new \RuntimeException("Skill '{$skill->title}' has reference files that could not be downloaded.");
    }

    $refsDir = $dir . DIRECTORY_SEPARATOR . 'references';
    // Stale files from a previous install would otherwise survive a reinstall
    // that renamed or dropped a reference.
    $this->filesystem->remove($refsDir);

    if ($skill->references === []) {
      return $dir;
    }

    $this->filesystem->mkdir($refsDir);
    foreach ($skill->references as $reference) {
      $name = $reference->targetName();
      if ($name === null || $name === '.' || $name === '..') {
        throw new \RuntimeException("Skill '{$skill->title}' has a reference file with no usable filename.");
      }
      $this->filesystem->dumpFile(
        $refsDir . DIRECTORY_SEPARATOR . $name,
        $this->fileDownloader->download((string) $reference->url),
      );
    }

    return $dir;
  }

  /**
   * The canonical directory name for a skill title — server data must never
   * be trusted as a path component ('/', '\' and '..' would escape the dir).
   */
  public static function sanitizeTitle(string $title): string {
    $name = basename(str_replace('\\', '/', trim($title)));
    if ($name === '' || $name === '.' || $name === '..') {
      throw new \RuntimeException("Skill title '{$title}' cannot be used as a directory name.");
    }
    return $name;
  }

}
