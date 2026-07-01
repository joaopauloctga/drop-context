<?php

namespace DrupalContext\Source;

interface SourceInterface {

  public function downloadSkill($skillName);

  public function getSkillList();

}