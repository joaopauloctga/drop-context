<?php

declare(strict_types=1);

namespace DropContext\Command\Cache;

use DropContext\Cache\JsonFileCache;
use DropContext\Command\DropContextBaseCommand;
use DropContext\Console\Ui;
use DropContext\Service\AppInstaller;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

/** Manual cache invalidation for the MCP server's gateway response cache — the STAMP_TTL probe is the automatic mechanism; this is the escape hatch. */
#[AsCommand(
  name: 'cache:clear',
  description: 'Clear the entity_gateway response cache used by the MCP server',
)]
final class CacheClearCommand extends DropContextBaseCommand {

  public function __construct(
    AppInstaller $appInstaller,
    Ui $ui,
    private readonly Filesystem $filesystem = new Filesystem(),
  ) {
    parent::__construct($appInstaller, $ui);
  }

  protected function requiresAppSetup(): bool {
    return false;
  }

  public function configure(): void {
    parent::configure();
    $this->addOption(
      name: 'url',
      mode: InputOption::VALUE_REQUIRED,
      description: 'Only clear the cache namespace for this gateway base URL (trailing slash ignored). Defaults to clearing every namespace.',
    );
  }

  public function execute(InputInterface $input, OutputInterface $output): int {
    $url = $input->getOption('url');
    $root = JsonFileCache::defaultCacheRoot();

    if (is_string($url) && $url !== '') {
      $target = JsonFileCache::namespaceDirFor($url, $root);
      $this->filesystem->remove($target);
      $cleared = $url;
    }
    else {
      $target = $root;
      $this->filesystem->remove($root);
      $cleared = 'all';
    }

    if ($this->ui->isJson()) {
      $this->ui->json(['cleared' => $cleared, 'path' => $target]);
      return Command::SUCCESS;
    }

    $this->ui->outro($cleared === 'all'
      ? "Cleared the whole gateway cache ({$target})."
      : "Cleared the cache for {$cleared} ({$target}).");

    return Command::SUCCESS;
  }

}
