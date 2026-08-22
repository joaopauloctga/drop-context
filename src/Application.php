<?php

namespace DropContext;

use Composer\InstalledVersions;
use DropContext\Service\UpdateNotifier;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Application extends BaseApplication {

  public const PACKAGE_NAME = 'joaopauloctga/drop-context';

  public const NAME = 'Drop Context CLI';

  private const FALLBACK_VERSION = 'dev-main';

  private readonly UpdateNotifier $updateNotifier;

  public function __construct() {
    parent::__construct(self::NAME, self::currentVersion());
    $container = ContainerFactory::create();
    $this->updateNotifier = $container->get(UpdateNotifier::class);

    foreach ($container->findTaggedServiceIds('console.command') as $id => $_) {
      $this->add($container->get($id));
    }
  }

  public static function currentVersion(): string {
    try {
      $rootPackage = InstalledVersions::getRootPackage();
      if (($rootPackage['name'] ?? null) === self::PACKAGE_NAME) {
        return $rootPackage['pretty_version'] ?? self::FALLBACK_VERSION;
      }

      return InstalledVersions::getPrettyVersion(self::PACKAGE_NAME) ?? self::FALLBACK_VERSION;
    }
    catch (\OutOfBoundsException) {
      return self::FALLBACK_VERSION;
    }
  }

  public function doRun(InputInterface $input, OutputInterface $output): int {
    $exitCode = parent::doRun($input, $output);

    if ($exitCode === Command::SUCCESS) {
      $this->updateNotifier->notify($input, $output);
    }

    return $exitCode;
  }

  protected function getDefaultInputDefinition(): InputDefinition {
    $definition = parent::getDefaultInputDefinition();
    $definition->addOption(new InputOption(
      name: 'no-update-check',
      mode: InputOption::VALUE_NONE,
      description: 'Do not check whether a newer CLI version is available.',
    ));

    return $definition;
  }

}
