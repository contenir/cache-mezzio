<?php

declare(strict_types=1);

namespace Contenir\Cache\Mezzio\Tests\Trait;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function bin2hex;
use function is_dir;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sys_get_temp_dir;
use function unlink;

trait TemporaryDirectoryTrait
{
    private string $temporaryDirectory;

    private function setUpTemporaryDirectory(): void
    {
        $this->temporaryDirectory = sys_get_temp_dir() . '/contenir-cache-mezzio-' . bin2hex(random_bytes(length: 8));
        mkdir($this->temporaryDirectory, permissions: 0o777, recursive: true);
    }

    private function tearDownTemporaryDirectory(): void
    {
        if (! is_dir($this->temporaryDirectory)) {
            return;
        }

        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->temporaryDirectory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        /** @var SplFileInfo $entry */
        foreach ($entries as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }

        rmdir($this->temporaryDirectory);
    }
}
