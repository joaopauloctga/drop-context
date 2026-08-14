<?php

namespace DrupalContext;

use DrupalContext\Command\CodeAgents\AddCodeAgent;
use DrupalContext\Command\CodeAgents\ListCodeAgents;
use DrupalContext\Command\CodeAgents\RemoveCodeAgent;
use DrupalContext\Command\CodeAgents\SetPreferableCodeAgents;
use DrupalContext\Command\Module\ModuleListCommand;
use DrupalContext\Command\Skill\SkillAddCommand;
use DrupalContext\Command\Skill\SkillListCommand;
use DrupalContext\Command\Skill\SkillRemoveCommand;
use DrupalContext\Command\Skill\SkillShowCommand;
use DrupalContext\Command\Skill\SkillTagsCommand;
use DrupalContext\Console\Ui;
use DrupalContext\Gateway\GatewayClient;
use DrupalContext\Gateway\GatewayClientInterface;
use DrupalContext\Gateway\GatewaySettings;
use DrupalContext\Gateway\Repository\ModuleRepository;
use DrupalContext\Gateway\Repository\ReleaseRepository;
use DrupalContext\Gateway\Repository\SkillRepository;
use DrupalContext\Gateway\Repository\TagRepository;
use DrupalContext\Gateway\SkillQueryService;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use DrupalContext\Service\AppInstaller;
use DrupalContext\Service\CodeAgentsService;
use DrupalContext\Service\SkillFileWriter;
use DrupalContext\Service\SkillService;
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

    // Console output.
    $container->autowire(Ui::class)->setPublic(true);

    // Gateway client stack.
    $container->autowire(GatewaySettings::class)->setPublic(true);
    $container->autowire(GatewayClient::class)->setPublic(true);
    $container->setAlias(GatewayClientInterface::class, GatewayClient::class)->setPublic(true);

    // Gateway repositories + join/enrichment service.
    $container->autowire(SkillRepository::class)->setPublic(true);
    $container->autowire(ModuleRepository::class)->setPublic(true);
    $container->autowire(ReleaseRepository::class)->setPublic(true);
    $container->autowire(TagRepository::class)->setPublic(true);
    $container->autowire(SkillQueryService::class)->setPublic(true);

    // Skill file writer.
    $container->autowire(SkillFileWriter::class)->setPublic(true);

    $command = SkillAddCommand::class;
    $container->autowire($command)->setPublic(true)->setAutoconfigured(true);
    $container->autowire(SetPreferableCodeAgents::class)->setPublic(true)->setAutoconfigured(true);
    $container->autowire(AddCodeAgent::class)->setPublic(true)->setAutoconfigured(true);
    $container->autowire(RemoveCodeAgent::class)->setPublic(true)->setAutoconfigured(true);
    $container->autowire(ListCodeAgents::class)->setPublic(true)->setAutoconfigured(true);
    $container->autowire(SkillListCommand::class)->setPublic(true)->setAutoconfigured(true);
    $container->autowire(SkillRemoveCommand::class)->setPublic(true)->setAutoconfigured(true);
    $container->autowire(SkillShowCommand::class)->setPublic(true)->setAutoconfigured(true);
    $container->autowire(SkillTagsCommand::class)->setPublic(true)->setAutoconfigured(true);
    $container->autowire(ModuleListCommand::class)->setPublic(true)->setAutoconfigured(true);



  }


}
