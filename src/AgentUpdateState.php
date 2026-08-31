<?php

declare(strict_types=1);

namespace DropContext;

enum AgentUpdateState: string {

  case CURRENT = 'current';
  case UPDATE_AVAILABLE = 'update_available';
  case MISSING = 'missing';
  case UNAVAILABLE = 'unavailable';
  case VERSION_UNKNOWN = 'version_unknown';
  case LOCAL_NEWER = 'local_newer';

  public function label(): string {
    return match ($this) {
      self::CURRENT => 'Up to date',
      self::UPDATE_AVAILABLE => 'Update available',
      self::MISSING => 'Files missing',
      self::UNAVAILABLE => 'Unavailable',
      self::VERSION_UNKNOWN => 'Version unavailable',
      self::LOCAL_NEWER => 'Local version is newer',
    };
  }

}
