<?php

declare(strict_types=1);

namespace DropContext\Command\Mcp;

use DropContext\Mcp\McpServerFactory;
use Mcp\Server\Transport\StdioTransport;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Starts the MCP server over stdio.
 *
 * Extends Command directly — never DropContextBaseCommand, whose first-run
 * wizard, Ui (laravel/prompts) and --json/--source options have no place on
 * a stdio transport: nothing in this process may write to stdout except
 * through the transport itself.
 *
 * Registered on both binaries: `drop-context-mcp` (via McpServerApplication,
 * as the sole default command) and the main `drop-context mcp:serve` (picked
 * up automatically — it is still a Command, tagged `console.command` like
 * every other one), which is what `claude mcp add ... -- drop-context
 * mcp:serve` would point at.
 */
#[AsCommand(
  name: 'mcp:serve',
  description: 'Start the MCP server over stdio (list_modules, get_module, get_doc, list_core_libraries, get_core_library, get_core_library_doc)',
)]
final class McpServeCommand extends Command {

  public function __construct(
    private readonly McpServerFactory $serverFactory,
  ) {
    parent::__construct();
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    // Duplicate file descriptors: Server::run() closes the transport's
    // streams in its `finally` block. Handing it the process-global
    // STDIN/STDOUT constants directly would leave them closed — beyond
    // recovery — for the rest of the process; the php:// wrappers reference
    // duplicate descriptors, so closing them leaves the constants usable.
    $stdin = fopen('php://stdin', 'r') ?: STDIN;
    $stdout = fopen('php://stdout', 'w') ?: STDOUT;

    $server = $this->serverFactory->create();
    $server->run(new StdioTransport($stdin, $stdout));

    return Command::SUCCESS;
  }

}
