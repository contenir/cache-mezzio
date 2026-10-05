<?php

declare(strict_types=1);

namespace Contenir\Cache\Mezzio\Test\Unit;

use Contenir\Cache\CacheControl;
use Contenir\Cache\CacheControlRepositoryInterface;
use Contenir\Cache\Mezzio\PageCacheMiddlewareFactory;
use Contenir\Cache\Mezzio\Test\TestAsset\Cache\InMemoryCache;
use Contenir\Cache\Mezzio\Test\TestAsset\Container\InMemoryContainer;
use Contenir\Cache\Mezzio\Test\TestAsset\Handler\CountingHandler;
use Contenir\Cache\Mezzio\Test\TestAsset\Mutator\AppendingMutator;
use Contenir\Cache\Mezzio\Test\Trait\MakesServerRequest;
use Contenir\Cache\Repository\InMemoryRepository;
use Laminas\Diactoros\Response\HtmlResponse;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

#[Group('unit')]
#[Group('factory')]
final class PageCacheMiddlewareFactoryTest extends TestCase
{
    use MakesServerRequest;

    public function testBuildsAMiddlewareThatCachesPages(): void
    {
        $handler    = new CountingHandler(new HtmlResponse('<p>Work</p>'));
        $middleware = (new PageCacheMiddlewareFactory())($this->container());

        $middleware->process($this->request(), $handler);
        $middleware->process($this->request(), $handler);

        self::assertSame(1, $handler->calls);
    }

    public function testAppliesTheConfiguredMutatorsInOrder(): void
    {
        $middleware = (new PageCacheMiddlewareFactory())($this->container(
            ['mutators' => ['app.first', 'app.second']],
            ['app.first' => new AppendingMutator('first'), 'app.second' => new AppendingMutator('second')],
        ));

        $response = $middleware->process($this->request('POST'), new CountingHandler(new HtmlResponse('')));

        self::assertSame('<!-- first:BYPASS --><!-- second:BYPASS -->', (string) $response->getBody());
    }

    public function testRefusesAMutatorThatDoesNotImplementTheInterface(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('page cache mutators must implement');

        (new PageCacheMiddlewareFactory())($this->container(['mutators' => ['app.mutator']], [
            'app.mutator' => new stdClass(),
        ]));
    }

    public function testRefusesAMutatorEntryThatIsNotAServiceName(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('got int');

        (new PageCacheMiddlewareFactory())($this->container(['mutators' => [42]]));
    }

    /**
     * @param array<string, mixed> $pagecache
     * @param array<string, mixed> $services
     */
    private function container(array $pagecache = [], array $services = []): InMemoryContainer
    {
        return new InMemoryContainer([
            ...$services,
            'config'                               => ['pagecache' => [...$pagecache, 'cache' => 'cache.pages']],
            'cache.pages'                          => new InMemoryCache(),
            CacheControlRepositoryInterface::class => new InMemoryRepository(CacheControl::enabled()),
        ]);
    }
}
