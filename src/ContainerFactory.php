<?php

namespace DrupalContext;

use DrupalContext\Command\CodeAgents\AddCodeAgent;
use DrupalContext\Command\CodeAgents\ListCodeAgents;
use DrupalContext\Command\CodeAgents\RemoveCodeAgent;
use DrupalContext\Command\CodeAgents\SetPreferableCodeAgents;
use DrupalContext\Command\Skill\SkillAddCommand;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use DrupalContext\Service\AppInstaller;
use DrupalContext\Service\CodeAgentsService;
use DrupalContext\Service\SkillService;
use DrupalContext\Source\DrupalContext\Client;
use DrupalContext\Source\DrupalContext\DrupalContextSource;
use DrupalContext\Source\SkilsSH\SkillsSHSource;
use DrupalContext\Source\SourceManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;

class ContainerFactory {

  public static function create(): ContainerBuilder {
    $container = new ContainerBuilder();

    $container->registerForAutoconfiguration(Command::class)
      ->addTag('console.command');

    static::register($container);

    $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../config'));
    $loader->load('services.yaml');

    $container->compile();
    return $container;
  }

  private static function register(ContainerBuilder $container): void {

    $container->autowire(Filesystem::class)->setPublic(true);
    $container->autowire(AppInstaller::class);
    $container->autowire(CodeAgentsService::class);
    $container->autowire(SourceManager::class);
    $container->autowire(SkillService::class)->setPublic(true);
    $container->autowire(DrupalContextSource::class)->setPublic(true);
    $container->autowire(SkillsSHSource::class)->setPublic(true);
    $container->autowire(Client::class)->setPublic(true);

    $command = SkillAddCommand::class;
    $container->autowire($command)->setPublic(true)->setAutoconfigured(true);
    $container->autowire(SetPreferableCodeAgents::class)->setPublic(true)->setAutoconfigured(true);
    $container->autowire(AddCodeAgent::class)->setPublic(true)->setAutoconfigured(true);
    $container->autowire(RemoveCodeAgent::class)->setPublic(true)->setAutoconfigured(true);
    $container->autowire(ListCodeAgents::class)->setPublic(true)->setAutoconfigured(true);



  }


}
