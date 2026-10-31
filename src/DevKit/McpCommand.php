<?php

declare(strict_types=1);

namespace DropContext\DevKit;

/**
 * Finds the absolute path of the drop-context-mcp binary.
 *
 * Client configs get an absolute path: apps launched from a desktop
 * launcher or a background service (Cursor, the OpenCode service) often do
 * not have composer's global bin directory on their PATH.
 */
final class McpCommand {

  public const BINARY = 'drop-context-mcp';

  public static function locate(): string {
    // Set by composer's bin proxy when the CLI runs from a composer install.
    $binDir = $GLOBALS['_composer_bin_dir'] ?? null;
    if (is_string($binDir) && is_file($binDir . '/' . self::BINARY)) {
      return $binDir . '/' . self::BINARY;
    }

    // A checkout run as bin/drop-context, with its own vendor directory.
    $packageRoot = dirname(__DIR__, 2);
    if (is_file($packageRoot . '/bin/' . self::BINARY) && is_file($packageRoot . '/vendor/autoload.php')) {
      return $packageRoot . '/bin/' . self::BINARY;
    }

    foreach (explode(PATH_SEPARATOR, (string) getenv('PATH')) as $dir) {
      if ($dir !== '' && is_executable($dir . '/' . self::BINARY)) {
        return $dir . '/' . self::BINARY;
      }
    }

    return self::BINARY;
  }

}
