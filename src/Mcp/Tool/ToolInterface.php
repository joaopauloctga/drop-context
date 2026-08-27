<?php

declare(strict_types=1);

namespace DropContext\Mcp\Tool;

use Mcp\Schema\Tool;
use Mcp\Server\Handler\ToolHandlerInterface;

/**
 * Contract every MCP tool implements: its own `Mcp\Schema\Tool` definition
 * plus (via ToolHandlerInterface::execute()) the handler the SDK dispatches
 * `tools/call` to. `_instanceof` in config/services.yaml tags every
 * implementation `drop_context.mcp_tool`, which McpServerFactory collects as
 * a `!tagged_iterator` and registers one by one with `Server::builder()->add()`.
 */
interface ToolInterface extends ToolHandlerInterface {

  public function definition(): Tool;

}
