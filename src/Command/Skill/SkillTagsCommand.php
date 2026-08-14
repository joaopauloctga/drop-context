<?php

declare(strict_types=1);

namespace DrupalContext\Command\Skill;

use DrupalContext\Command\DrupalContextBaseCommand;
use DrupalContext\Console\Ui;
use DrupalContext\Gateway\Dto\TagRow;
use DrupalContext\Gateway\Exception\GatewayException;
use DrupalContext\Gateway\Repository\TagRepository;
use DrupalContext\Service\AppInstaller;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'skill:tags', description: 'List skill tags available for filtering', aliases: ['s:tags'])]
class SkillTagsCommand extends DrupalContextBaseCommand {

  public function __construct(
    AppInstaller $appInstaller,
    Ui $ui,
    protected readonly TagRepository $tagRepository,
  ) {
    parent::__construct($appInstaller, $ui);
  }

  protected function requiresAppSetup(): bool {
    return false;
  }

  public function execute(InputInterface $input, OutputInterface $output): int {
    $this->ui->intro('Listing skill tags');

    try {
      $tags = $this->ui->spin(fn () => $this->tagRepository->all(), 'Fetching tags…');
    }
    catch (GatewayException $e) {
      $this->ui->error($this->describeGatewayError($e));
      return Command::FAILURE;
    }

    if ($this->ui->isJson()) {
      $this->ui->json(['count' => count($tags), 'tags' => $tags]);
      return Command::SUCCESS;
    }

    if ($tags === []) {
      $this->ui->note('No tags found.');
      return Command::SUCCESS;
    }

    $rows = array_map(static fn (TagRow $tag) => [$tag->name, $tag->uuid], $tags);
    $this->ui->table(['Tag', 'UUID'], $rows);
    $this->ui->outro(count($tags) . ' tag(s)');

    return Command::SUCCESS;
  }

}
