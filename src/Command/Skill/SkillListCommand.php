<?php

declare(strict_types=1);

namespace DropContext\Command\Skill;

use DropContext\Command\DropContextBaseCommand;
use DropContext\Console\Ui;
use DropContext\Gateway\Dto\SkillRow;
use DropContext\Gateway\Dto\SkillType;
use DropContext\Gateway\Exception\GatewayException;
use DropContext\Gateway\Exception\ModuleNotFoundException;
use DropContext\Gateway\Exception\TagNotFoundException;
use DropContext\Gateway\SkillQueryService;
use DropContext\Service\AppInstaller;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
  name: 'skill:list',
  description: 'List skills from the Drop Context gateway',
  aliases: ['s:list', 'skill:source-list', 's:s-list'],
)]
class SkillListCommand extends DropContextBaseCommand {

  public function __construct(
    AppInstaller $appInstaller,
    Ui $ui,
    protected readonly SkillQueryService $skillQueryService,
  ) {
    parent::__construct($appInstaller, $ui);
  }

  protected function requiresAppSetup(): bool {
    return false;
  }

  public function configure(): void {
    parent::configure();
    $this->addOption('module', 'm', InputOption::VALUE_OPTIONAL, 'Module machine name to filter skills by');
    $this->addOption('tag', 't', InputOption::VALUE_OPTIONAL, 'Tag name to filter skills by');
    $this->addOption('type', null, InputOption::VALUE_OPTIONAL, 'Skill type to filter by (' . implode(', ', SkillType::values()) . ')');
    $this->addOption('search', null, InputOption::VALUE_OPTIONAL, 'Free-text search on skill title');
  }

  public function execute(InputInterface $input, OutputInterface $output): int {
    $module = $input->getOption('module');
    $tag = $input->getOption('tag');
    $type = $input->getOption('type');
    $search = $input->getOption('search');

    if ($type !== null && SkillType::tryFrom($type) === null) {
      $this->ui->error("Unknown skill type '{$type}'. Valid types: " . implode(', ', SkillType::values()));
      return Command::FAILURE;
    }

    $this->ui->intro('Listing skills');

    try {
      $skills = $this->ui->spin(
        fn () => $this->skillQueryService->search(text: $search, module: $module, tag: $tag, type: $type),
        'Fetching skills…',
      );
    }
    catch (ModuleNotFoundException|TagNotFoundException $e) {
      $this->ui->error($e->getMessage());
      if ($e->suggestions !== []) {
        $this->ui->note('Did you mean: ' . implode(', ', $e->suggestions));
      }
      return Command::FAILURE;
    }
    catch (GatewayException $e) {
      $this->ui->error($this->describeGatewayError($e));
      return Command::FAILURE;
    }

    if ($this->ui->isJson()) {
      $this->ui->json(['count' => count($skills), 'skills' => $skills]);
      return Command::SUCCESS;
    }

    if ($skills === []) {
      $this->ui->note('No skills found.');
      return Command::SUCCESS;
    }

    // Only the human-readable table needs the module/release columns —
    // resolving them costs extra gateway requests.
    try {
      $columns = $this->ui->spin(
        fn () => $this->skillQueryService->resolveModuleColumns($skills),
        'Resolving modules…',
      );
    }
    catch (GatewayException $e) {
      $this->ui->error($this->describeGatewayError($e));
      return Command::FAILURE;
    }

    $rows = array_map(function (SkillRow $skill) use ($columns) {
      $col = $columns[$skill->uuid] ?? ['module' => '', 'release' => ''];
      return [
        $skill->title,
        $skill->type ?? '',
        $col['module'],
        $col['release'],
        implode(', ', array_map(static fn ($tag) => $tag->name, $skill->tags)),
        self::truncate($skill->description ?? '', 60),
      ];
    }, $skills);

    $this->ui->table(['Title', 'Type', 'Module', 'Release', 'Tags', 'Description'], $rows);
    $this->ui->outro(count($skills) . ' skill(s)');

    return Command::SUCCESS;
  }

  private static function truncate(string $text, int $length): string {
    $text = trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    return mb_strlen($text) > $length ? mb_substr($text, 0, $length - 1) . '…' : $text;
  }

}
