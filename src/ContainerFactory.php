<?php

namespace DropContext;

use DropContext\Command\CodeAgents\AddCodeAgent;
use DropContext\Command\CodeAgents\ListCodeAgents;
use DropContext\Command\CodeAgents\RemoveCodeAgent;
use DropContext\Command\CodeAgents\SetPreferableCodeAgents;
use DropContext\Command\Module\ModuleListCommand;
use DropContext\Command\Skill\SkillAddCommand;
use DropContext\Command\Skill\SkillListCommand;
use DropContext\Command\Skill\SkillRemoveCommand;
use DropContext\Command\Skill\SkillShowCommand;
use DropContext\Command\Skill\SkillTagsCommand;
use DropContext\Console\Ui;
use DropContext\Gateway\GatewayClient;
use DropContext\Gateway\GatewayClientInterface;
use DropContext\Gateway\GatewaySettings;
use DropContext\Gateway\Repository\ModuleRepository;
use DropContext\Gateway\Repository\ReleaseRepository;
use DropContext\Gateway\Repository\SkillRepository;
use DropContext\Gateway\Repository\TagRepository;
use DropContext\Gateway\SkillQueryService;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use DropContext\Service\AppInstaller;
use DropContext\Service\CodeAgentsService;
use DropContext\Service\SkillFileWriter;
use DropContext\Service\SkillService;
use DropContext\Service\UpdateChecker;
use DropContext\Service\UpdateNotifier;
use DropContext\Source\DropContext\DropContextSource;
use DropContext\Source\SkilsSH\SkillsSHSource;
use DropContext\Source\SourceManager;
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
    $container->autowire(UpdateChecker::class);
    $container->autowire(UpdateNotifier::class)->setPublic(true);
    $container->autowire(DropContextSource::class)->setPublic(true);
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
