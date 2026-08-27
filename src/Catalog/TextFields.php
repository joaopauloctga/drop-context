<?php

declare(strict_types=1);

namespace DropContext\Catalog;

/**
 * Reads the shapes the entity_gateway's field output plugins actually emit,
 * tolerating the collapsed forms they use when only one piece was requested
 * (mirrors `SkillRow::textValue()`/`refUuid()` in `Gateway/Dto/SkillRow.php`):
 *
 * - a text field (`output: raw` + `include_summary: true`) is `{value,
 *   summary}` when the field type carries a summary (`text_with_summary`),
 *   or a bare string when it doesn't (`text_long`, e.g. `release_note`'s
 *   `content`) — TextFieldOutput::buildEntry() collapses a single-key entry.
 * - an entity_reference (`mode: uuid_label`) is `{uuid, label}`, or `null`
 *   when the field has no value; `mode: uuid` (the default, unconfigured) is
 *   a bare uuid string.
 */
final class TextFields {

  /** The plain string value of a text field, whichever shape it arrived in. */
  public static function value(mixed $raw): string {
    if (is_array($raw)) {
      return isset($raw['value']) ? (string) $raw['value'] : (string) ($raw['processed'] ?? '');
    }
    return $raw !== null ? (string) $raw : '';
  }

  /** The `.summary` sub-value of a text_with_summary field, or '' when absent/collapsed to a bare string. */
  public static function summary(mixed $raw): string {
    return is_array($raw) ? (string) ($raw['summary'] ?? '') : '';
  }

  /** The referenced entity's UUID, from either a bare-uuid or a uuid_label stub. */
  public static function refUuid(mixed $raw): ?string {
    if (is_array($raw)) {
      return isset($raw['uuid']) ? (string) $raw['uuid'] : null;
    }
    return $raw !== null ? (string) $raw : null;
  }

  /** The referenced entity's label, only present in uuid_label mode and only when the target resource exposes its own label field. */
  public static function refLabel(mixed $raw): string {
    return is_array($raw) ? (string) ($raw['label'] ?? '') : '';
  }

}
