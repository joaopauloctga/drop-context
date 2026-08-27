<?php

declare(strict_types=1);

namespace DropContext\Mcp;

use DropContext\Application;
use DropContext\Command\Mcp\McpServeCommand;
use DropContext\ContainerFactory;
use Symfony\Component\Console\Application as BaseApplication;

/**
 * The `drop-context-mcp` binary's Console application.
 *
 * Deliberately minimal: registers only `mcp:serve` and makes it the single
 * default command, so `drop-context-mcp` with no arguments already serves.
 * No UpdateNotifier — unlike the main `Application`, it would write to
 * stderr/stdout at the wrong time relative to the MCP transport.
 */
final class McpServerApplication extends BaseApplication {

  public const string NAME = 'Drop Context MCP';

  public function __construct() {
    parent::__construct(self::NAME, Application::currentVersion());

    $container = ContainerFactory::create();
    $this->add($container->get(McpServeCommand::class));
    $this->setDefaultCommand('mcp:serve', true);
  }

}
