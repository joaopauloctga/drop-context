<?php

declare(strict_types=1);

namespace DropContext\Catalog;

/**
 * Pure-PHP helpers for comparing and classifying Drupal module release tags.
 *
 * Ported 1:1 from `drupal-site/web/modules/custom/dc_entities/src/ReleaseVersion.php`
 * so the CLI's release resolution can never disagree with the site's.
 *
 * CAVEAT: {@see self::normalize()} only strips the legacy `8.x-3.2` core-
 * compatibility prefix, so comparing a legacy-prefixed tag against a plain
 * semver tag (`3.0.0`) for a module that mixes both schemes can produce the
 * wrong ranking.
 */
final class ReleaseVersion {

  /**
   * Determines whether a release tag is a stable (non-pre-release) tag.
   *
   * Unstable only when the tag ends with `-alpha`/`-beta`/`-rc`/`-dev`,
   * optionally followed by digits (case-insensitive, anchored to the end).
   */
  public static function isStable(string $tag): bool {
    return preg_match('/-(alpha|beta|rc|dev)\d*$/i', $tag) !== 1;
  }

  /**
   * Strips a leading Drupal core-compatibility prefix from a release tag.
   *
   * E.g. `8.x-3.2` becomes `3.2`; a plain semver tag is returned unchanged.
   */
  public static function normalize(string $tag): string {
    return preg_replace('/^\d+\.x-/', '', $tag) ?? $tag;
  }

  /**
   * Compares two release tags after normalizing away any core-compat prefix.
   *
   * Ascending order, `version_compare()`-style: negative when $a is older.
   * See the class docblock for the mixed-version-scheme caveat.
   */
  public static function compare(string $a, string $b): int {
    return version_compare(self::normalize($a), self::normalize($b));
  }

}
