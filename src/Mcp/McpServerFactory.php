<?php

declare(strict_types=1);

namespace DropContext\Mcp;

use DropContext\Application;
use DropContext\Mcp\Tool\ToolInterface;
use Mcp\Capability\Tool\NameValidator;
use Mcp\Server;

/** Builds the configured MCP server: identity, instructions, pagination, logger, and every tagged tool. */
final class McpServerFactory {

  /**
   * @param iterable<\DropContext\Mcp\Tool\ToolInterface> $tools tagged `drop_context.mcp_tool` — see config/services.yaml
   */
  public function __construct(
    private readonly iterable $tools,
    private readonly StderrLogger $logger,
  ) {}

  public function create(): Server {
    $builder = Server::builder()
      ->setServerInfo('drop-context', Application::currentVersion())
      ->setInstructions(McpInstructions::TEXT)
      ->setPaginationLimit(50)
      ->setLogger($this->logger);

    $nameValidator = new NameValidator();
    foreach ($this->tools as $tool) {
      if (!$tool instanceof ToolInterface) {
        continue;
      }
      $definition = $tool->definition();
      if (!$nameValidator->isValid($definition->name)) {
        // Defensive: every tool name here is a literal we control, so this
        // should never trip — but a build must not abort over one bad tool.
        $this->logger->warning('Skipping MCP tool "{name}": name is not valid per MCP NameValidator.', ['name' => $definition->name]);
        continue;
      }
      $builder->add($definition, $tool);
    }

    return $builder->build();
  }

}
