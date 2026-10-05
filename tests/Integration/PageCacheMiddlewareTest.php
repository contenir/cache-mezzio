<?php

declare(strict_types=1);

namespace Contenir\Cache\Mezzio\Tests\Integration;

use Contenir\Cache\CacheControl;
use Contenir\Cache\Mezzio\PageCacheMiddleware;
use Contenir\Cache\Mezzio\PageCacheMiddlewareFactory;
use Contenir\Cache\Mezzio\Tests\TestAsset\Container\InMemoryContainer;
use Contenir\Cache\Mezzio\Tests\TestAsset\Handler\CountingHandler;
use Contenir\Cache\Mezzio\Tests\Trait\ServerRequestTrait;
use Contenir\Cache\Mezzio\Tests\Trait\TemporaryDirectoryTrait;
use Contenir\Cache\Repository\FileRepository;
use FilesystemIterator;
use Laminas\Cache\Storage\Adapter\Filesystem;
use Laminas\Cache\Storage\Plugin\Serializer;
use Laminas\Diactoros\Response\HtmlResponse;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function file_put_contents;
use function mkdir;
use function unlink;
use function var_export;

/**
 * The middleware as a site runs it: built by its factory over a laminas-cache
 * Filesystem storage, reading the pagecache.local.php the Contenir admin
 * writes, and purged the ways the admin purges.
 */
#[Group('integration')]
#[Group('middleware')]
final class PageCacheMiddlewareTest extends TestCase
{
    use ServerRequestTrait;
    use TemporaryDirectoryTrait;

    private string $cacheDir;

    private string $adminFile;

    public function testAnAdminFileHoldingOnlyOtherOverridesKeepsTheSiteSwitchOn(): void
    {
        $handler    = new CountingHandler(new HtmlResponse('<p>Work</p>'));
        $middleware = $this->middleware(['options' => ['cache' => true]]);
        $this->writeAdminFile(['pagecache' => ['options' => ['cache_with_cookie' => true]]]);

        $middleware->process($this->request(cookies: ['theme' => 'dark']), $handler);
        $middleware->process($this->request(cookies: ['theme' => 'dark']), $handler);

        self::assertSame(1, $handler->calls);
    }

    public function testAnAdminRouteOverrideTakesAPathOutOfTheCache(): void
    {
        $handler    = new CountingHandler(new HtmlResponse('<p>Work</p>'));
        $middleware = $this->middleware(['options' => ['cache' => true]]);
        $this->writeAdminFile(['pagecache' => ['routes' => ['^/work' => ['cache' => false]]]]);

        $middleware->process($this->request(), $handler);
        $middleware->process($this->request(), $handler);

        self::assertSame(2, $handler->calls);
    }

    public function testEmptyingTheCacheDirectoryAsTheAdminClearCacheButtonDoesPurgesPages(): void
    {
        $handler    = new CountingHandler(new HtmlResponse('<p>Work</p>'));
        $middleware = $this->middleware(['options' => ['cache' => true]]);

        $middleware->process($this->request(), $handler);
        $this->deleteFilesUnder("{$this->temporaryDirectory}/data/cache");
        $middleware->process($this->request(), $handler);

        self::assertSame(2, $handler->calls);
    }

    public function testFlushingTheStorageAsTheAdminPurgeOperationDoesPurgesPages(): void
    {
        $handler    = new CountingHandler(new HtmlResponse('<p>Work</p>'));
        $middleware = $this->middleware(['options' => ['cache' => true]]);

        $middleware->process($this->request(), $handler);
        (new Filesystem(['cache_dir' => $this->cacheDir]))->flush();
        $middleware->process($this->request(), $handler);

        self::assertSame(2, $handler->calls);
    }

    public function testNothingIsCachedUntilTheAdminSwitchesCachingOn(): void
    {
        $handler    = new CountingHandler(new HtmlResponse('<p>Work</p>'));
        $middleware = $this->middleware();

        $middleware->process($this->request(), $handler);
        $middleware->process($this->request(), $handler);

        self::assertSame(2, $handler->calls);
    }

    public function testPagesAreCachedOnceTheAdminSwitchesCachingOn(): void
    {
        $handler    = new CountingHandler(new HtmlResponse('<p>Work</p>'));
        $middleware = $this->middleware();
        (new FileRepository($this->adminFile))->save(CacheControl::enabled());

        $miss = $middleware->process($this->request(), $handler);
        $hit  = $middleware->process($this->request(), $handler);

        self::assertSame(
            [1, 'MISS', 'HIT', '<p>Work</p>'],
            [
                $handler->calls,
                $miss->getHeaderLine('X-PK-Cache'),
                $hit->getHeaderLine('X-PK-Cache'),
                (string) $hit->getBody(),
            ],
        );
    }

    public function testTheAdminSwitchingCachingOffTakesEffectOnTheNextRequest(): void
    {
        $handler    = new CountingHandler(new HtmlResponse('<p>Work</p>'));
        $middleware = $this->middleware();
        (new FileRepository($this->adminFile))->save(CacheControl::enabled());

        $middleware->process($this->request(), $handler);
        (new FileRepository($this->adminFile))->save(CacheControl::disabled());
        $middleware->process($this->request(), $handler);

        self::assertSame(2, $handler->calls);
    }

    protected function setUp(): void
    {
        $this->setUpTemporaryDirectory();
        $this->cacheDir  = "{$this->temporaryDirectory}/data/cache/pages";
        $this->adminFile = "{$this->temporaryDirectory}/config/autoload/pagecache.local.php";
        mkdir($this->cacheDir, permissions: 0o777, recursive: true);
        mkdir("{$this->temporaryDirectory}/config/autoload", permissions: 0o777, recursive: true);
    }

    protected function tearDown(): void
    {
        $this->tearDownTemporaryDirectory();
    }

    /**
     * Deletes every file and keeps the directories, as the admin's ClearCache
     * registrar does.
     */
    private function deleteFilesUnder(string $directory): void
    {
        $entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
            $directory,
            FilesystemIterator::SKIP_DOTS,
        ));

        /** @var SplFileInfo $entry */
        foreach ($entries as $entry) {
            unlink($entry->getPathname());
        }
    }

    /**
     * @param array<string, mixed> $pagecache
     */
    private function middleware(array $pagecache = []): PageCacheMiddleware
    {
        $storage = new Filesystem(['cache_dir' => $this->cacheDir, 'namespace' => 'pages']);
        $storage->addPlugin(new Serializer());

        return (new PageCacheMiddlewareFactory())(new InMemoryContainer([
            'config'      => ['pagecache' => [...$pagecache, 'cache' => 'cache.pages', 'file' => $this->adminFile]],
            'cache.pages' => $storage,
        ]));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function writeAdminFile(array $config): void
    {
        file_put_contents($this->adminFile, "<?php\n\nreturn " . var_export($config, return: true) . ";\n");
    }
}
