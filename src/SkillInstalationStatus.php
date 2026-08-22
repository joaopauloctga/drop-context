<?php

namespace DropContext;

enum SkillInstalationStatus: string {
  case EXTERNAL = "installed_from_another_source";

  case INTERNAL = "installed_from_drop_context";

  case NOT_INSTALLED = "not_installed";
}
