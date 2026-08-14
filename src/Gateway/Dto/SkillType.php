<?php

declare(strict_types=1);

namespace DrupalContext\Gateway\Dto;

/** Valid values for the skill's field_skill_type. Validates the --type CLI option. */
enum SkillType: string {

  case CoreModule = 'core_module';
  case ContribModule = 'contrib_module';
  case CoreComponent = 'core_component';
  case AgentSkill = 'agent_skill';
  case UserSkill = 'user_skill';
  case Other = 'other';

  /** @return string[] */
  public static function values(): array {
    return array_map(static fn (self $case): string => $case->value, self::cases());
  }

}
