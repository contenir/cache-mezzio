<?php

declare(strict_types=1);

namespace Contenir\Cache\Mezzio\Tests\Integration;

use Contenir\Cache\Mezzio\CacheTicket;
use Contenir\Cache\Mezzio\PageStoreFactory;
use Contenir\Cache\Mezzio\Tests\TestAsset\Container\InMemoryContainer;
use Contenir\Cache\Mezzio\Tests\Trait\TemporaryDirectoryTrait;
use Laminas\Cache\Psr\SimpleCache\SimpleCacheDecorator;
use Laminas\Cache\Storage\Adapter\Filesystem;
use Laminas\Cache\Storage\Plugin\Serializer;
use Laminas\Diactoros\Response\HtmlResponse;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The laminas-cache Filesystem adapter behind most Contenir sites has no
 * per-item TTL. These tests pin down the pitfall that once stopped a Mezzio
 * page cache from ever storing anything, and prove the store avoids it.
 */
#[Group('integration')]
#[Group('factory')]
final class PageStoreFactoryTest extends TestCase
{
    use TemporaryDirectoryTrait;

    private Filesystem $storage;

    #[Test]
    public function aPageIsStoredInAndServedFromAFilesystemStorage(): void
    {
        $store = (new PageStoreFactory())($this->container(['cache' => 'cache.pages', 'per_item_ttl' => true]));

        $stored = $store->save(new CacheTicket('page', ttl: 60), new HtmlResponse('<p>Work</p>'));

        static::assertSame(
            [true, '<p>Work</p>'],
            [$stored, (string) $store->fetch(new CacheTicket('page'))?->getBody()],
        );
    }

    #[Test]
    public function theFilesystemStorageRefusesAWriteThatCarriesAPerItemTtl(): void
    {
        static::assertFalse((new SimpleCacheDecorator($this->storage))->set('page', 'x', 60));
    }

    #[Test]
    public function theTtlIsAppliedToTheStorageItself(): void
    {
        (new PageStoreFactory())($this->container(['cache' => 'cache.pages', 'ttl' => 120]));

        static::assertSame(120, $this->storage->getOptions()->getTtl());
    }

    protected function setUp(): void
    {
        $this->setUpTemporaryDirectory();
        $this->storage = new Filesystem(['cache_dir' => $this->temporaryDirectory, 'namespace' => 'pages']);
        $this->storage->addPlugin(new Serializer());
    }

    protected function tearDown(): void
    {
        $this->tearDownTemporaryDirectory();
    }

    /**
     * @param array<string, mixed> $pagecache
     */
    private function container(array $pagecache): InMemoryContainer
    {
        return new InMemoryContainer([
            'config'      => ['pagecache' => $pagecache],
            'cache.pages' => $this->storage,
        ]);
    }
}
