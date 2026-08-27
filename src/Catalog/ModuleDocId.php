<?php

declare(strict_types=1);

namespace DropContext\Catalog;

/**
 * Pure-PHP helpers for deriving and normalizing module doc identifiers.
 *
 * Ported 1:1 from `drupal-site/web/modules/custom/dc_entities/src/ModuleDocId.php`.
 *
 * A doc's public id is its `sourceFile` (the gateway's camelCase output key
 * for `field_source_file`) with the trailing `.md` extension stripped
 * (`hooks.md` -> `hooks`, `submodules/eca_base.md` -> `submodules/eca_base`).
 * normalize() turns any caller-supplied reference — an id, a bare file name,
 * a category label, or a submodule machine name — into the same lowercase,
 * dash-separated shape a doc's own id already has, so a single case-
 * insensitive comparison can match it.
 */
final class ModuleDocId {

  /**
   * Derives a doc's public id from its stored source file path.
   *
   * @param string $sourceFile
   *   A module_doc's `sourceFile` value, e.g. `hooks.md` or
   *   `submodules/eca_base.md`.
   *
   * @return string
   *   The doc id: the source file with a trailing `.md` extension removed.
   */
  public static function fromSourceFile(string $sourceFile): string {
    return str_ends_with($sourceFile, '.md') ? substr($sourceFile, 0, -3) : $sourceFile;
  }

  /**
   * Normalizes a caller-supplied doc reference for comparison against an id.
   *
   * Trims whitespace, lowercases, strips a leading `./` or `/`, strips one
   * trailing `.md` extension, and collapses runs of whitespace into a single
   * `-` — so `"AI Integration"` and `"Extension Points"` normalize to the
   * same shape their file-derived ids already have (`ai-integration`,
   * `extension-points`), while an underscore in a submodule machine name
   * (`eca_base`) is left untouched.
   *
   * @param string $ref
   *   The caller-supplied reference: a doc id, a file name, a category
   *   label, or a submodule machine name.
   *
   * @return string
   *   The normalized reference, ready for a case-sensitive `===` comparison
   *   against an already-lowercase id.
   */
  public static function normalize(string $ref): string {
    $value = strtolower(trim($ref));
    $value = ltrim($value, './');
    if (str_ends_with($value, '.md')) {
      $value = substr($value, 0, -3);
    }
    $value = trim($value);
    return (string) preg_replace('/\s+/', '-', $value);
  }

}
