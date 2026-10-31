<?php

declare(strict_types=1);

namespace DropContext\DevKit;

use DropContext\Service\AppStoreService;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Downloads a ref of the drop-context-dev repository from GitHub.
 *
 * Each ref is unpacked once into ~/.drop-context/dev-kit/<ref>/, the
 * directory project symlinks point into. Downloading a ref again replaces
 * it in place, which updates every project linked to it.
 */
class DevKitDownloader {

  public const REPOSITORY = 'joaopauloctga/drop-context-dev';

  public function __construct(
    private readonly Filesystem $filesystem,
    private readonly AppStoreService $appStoreService,
  ) {}

  public function download(string $ref): DevKit {
    if (!preg_match('#^[A-Za-z0-9][A-Za-z0-9._/-]*$#', $ref) || str_contains($ref, '..')) {
      throw new \RuntimeException("'{$ref}' is not a valid git ref.");
    }

    $baseDir = $this->appStoreService->getAppRootDir() . '/dev-kit';
    $target = $baseDir . '/' . str_replace('/', '--', $ref);
    $work = $baseDir . '/.download-' . bin2hex(random_bytes(4));
    $this->filesystem->mkdir($work);

    try {
      $zipFile = $work . '/kit.zip';
      $this->fetch(self::archiveUrl($ref), $zipFile, $ref);
      $root = $this->extract($zipFile, $work . '/tree');
      // Validate before replacing a working copy with a broken one.
      DevKit::open($root, '');
      $this->filesystem->remove($target);
      $this->filesystem->rename($root, $target);
    }
    finally {
      $this->filesystem->remove($work);
    }

    return DevKit::open($target, 'github:' . self::REPOSITORY . '@' . $ref);
  }

  public static function archiveUrl(string $ref): string {
    return 'https://codeload.github.com/' . self::REPOSITORY . '/zip/' . $ref;
  }

  protected function fetch(string $url, string $destination, string $ref): void {
    try {
      (new HttpClient(['timeout' => 120]))->request('GET', $url, ['sink' => $destination]);
    }
    catch (BadResponseException $e) {
      $status = $e->getResponse()->getStatusCode();
      throw new \RuntimeException($status === 404
        ? "github.com/" . self::REPOSITORY . " has no ref '{$ref}'."
        : "Downloading drop-context-dev@{$ref} failed with HTTP {$status}.", previous: $e);
    }
    catch (GuzzleException $e) {
      throw new \RuntimeException("Cannot download drop-context-dev@{$ref}: {$e->getMessage()}", previous: $e);
    }
  }

  /** Unpacks a GitHub archive and returns its single top-level directory. */
  private function extract(string $zipFile, string $destination): string {
    $zip = new \ZipArchive();
    if ($zip->open($zipFile) !== TRUE) {
      throw new \RuntimeException('The downloaded drop-context-dev archive is not a valid zip file.');
    }
    try {
      for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = (string) $zip->getNameIndex($i);
        if (str_starts_with($name, '/') || preg_match('#(^|/)\.\.(/|$)#', $name)) {
          throw new \RuntimeException("The drop-context-dev archive holds an unsafe path: {$name}");
        }
      }
      if (!$zip->extractTo($destination)) {
        throw new \RuntimeException('Cannot unpack the drop-context-dev archive.');
      }
    }
    finally {
      $zip->close();
    }

    $roots = glob($destination . '/*', GLOB_ONLYDIR) ?: [];
    if (count($roots) !== 1) {
      throw new \RuntimeException('The drop-context-dev archive does not have a single top-level directory.');
    }
    return $roots[0];
  }

}
