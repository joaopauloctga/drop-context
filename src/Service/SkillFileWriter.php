<?php

declare(strict_types=1);

namespace DropContext\Service;

use DropContext\Gateway\Dto\SkillRow;
use Symfony\Component\Filesystem\Filesystem;

/** Writes a resolved SkillRow to disk as SKILL.md + references/*. */
class SkillFileWriter {

  public function __construct(
    private readonly Filesystem $filesystem,
  ) {}

  /** @return string the directory the skill was written to */
  public function write(SkillRow $skill, string $destDir): string {
    if ($skill->content === null || trim($skill->content) === '') {
      throw new \RuntimeException("Skill '{$skill->title}' has no content — nothing to write.");
    }

    $dir = rtrim($destDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . self::sanitizeTitle($skill->title);
    $this->filesystem->mkdir($dir);
    $this->filesystem->dumpFile($dir . DIRECTORY_SEPARATOR . 'SKILL.md', $skill->content);

    if ($skill->references !== []) {
      if (!$skill->referencesInlined) {
        throw new \RuntimeException(
          "Skill '{$skill->title}' has references that are still bare UUIDs — the site is missing the Phase 0 " .
          'entity_gateway config (paragraph_skill_reference resource + inline field display on field_references).',
        );
      }

      $refsDir = $dir . DIRECTORY_SEPARATOR . 'references';
      $this->filesystem->mkdir($refsDir);
      foreach ($skill->references as $reference) {
        if ($reference->filename === null || $reference->filename === '') {
          throw new \RuntimeException("Skill '{$skill->title}' has a reference with no filename.");
        }
        $this->filesystem->dumpFile(
          $refsDir . DIRECTORY_SEPARATOR . basename($reference->filename),
          $reference->content,
        );
      }
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
