<?php

declare(strict_types=1);

namespace Contenir\Cache\Mezzio\Tests\Unit;

use Contenir\Cache\CacheControl;
use Contenir\Cache\CacheControlRepositoryInterface;
use Contenir\Cache\Mezzio\PageCacheMiddlewareFactory;
use Contenir\Cache\Mezzio\Tests\TestAsset\Cache\InMemoryCache;
use Contenir\Cache\Mezzio\Tests\TestAsset\Container\InMemoryContainer;
use Contenir\Cache\Mezzio\Tests\TestAsset\Handler\CountingHandler;
use Contenir\Cache\Mezzio\Tests\TestAsset\Mutator\AppendingMutator;
use Contenir\Cache\Mezzio\Tests\Trait\ServerRequestTrait;
use Contenir\Cache\Repository\InMemoryRepository;
use Laminas\Diactoros\Response\HtmlResponse;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

#[Group('unit')]
#[Group('factory')]
final class PageCacheMiddlewareFactoryTest extends TestCase
{
    use ServerRequestTrait;

    #[Test]
    public function appliesTheConfiguredMutatorsInOrder(): void
    {
        $middleware = (new PageCacheMiddlewareFactory())($this->container(
            ['mutators' => ['app.first', 'app.second']],
            ['app.first' => new AppendingMutator('first'), 'app.second' => new AppendingMutator('second')],
        ));

        $response = $middleware->process($this->request('POST'), new CountingHandler(new HtmlResponse('')));

        static::assertSame('<!-- first:BYPASS --><!-- second:BYPASS -->', (string) $response->getBody());
    }

    #[Test]
    public function buildsAMiddlewareThatCachesPages(): void
    {
        $handler    = new CountingHandler(new HtmlResponse('<p>Work</p>'));
        $middleware = (new PageCacheMiddlewareFactory())($this->container());

        $middleware->process($this->request(), $handler);
        $middleware->process($this->request(), $handler);

        static::assertSame(1, $handler->calls);
    }

    #[Test]
    public function refusesAMutatorEntryThatIsNotAServiceName(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('got int');

        (new PageCacheMiddlewareFactory())($this->container(['mutators' => [42]]));
    }

    #[Test]
    public function refusesAMutatorThatDoesNotImplementTheInterface(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('page cache mutators must implement');

        (new PageCacheMiddlewareFactory())($this->container(['mutators' => ['app.mutator']], [
            'app.mutator' => new stdClass(),
        ]));
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
