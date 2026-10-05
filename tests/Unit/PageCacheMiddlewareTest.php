<?php

declare(strict_types=1);

namespace Contenir\Cache\Mezzio\Tests\Unit;

use Contenir\Cache\CacheControl;
use Contenir\Cache\Mezzio\CachePolicy;
use Contenir\Cache\Mezzio\PageCacheMiddleware;
use Contenir\Cache\Mezzio\PageStore;
use Contenir\Cache\Mezzio\Tests\TestAsset\Cache\InMemoryCache;
use Contenir\Cache\Mezzio\Tests\TestAsset\Clock\FrozenClock;
use Contenir\Cache\Mezzio\Tests\TestAsset\Handler\CountingHandler;
use Contenir\Cache\Mezzio\Tests\TestAsset\Mutator\AppendingMutator;
use Contenir\Cache\Mezzio\Tests\Trait\ServerRequestTrait;
use Contenir\Cache\Repository\InMemoryRepository;
use Laminas\Diactoros\Response\HtmlResponse;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_values;

#[Group('unit')]
#[Group('middleware')]
final class PageCacheMiddlewareTest extends TestCase
{
    use ServerRequestTrait;

    private InMemoryCache $cache;

    private InMemoryRepository $repository;

    #[Test]
    public function answersAHeadRequestFromTheStoredPageWithoutItsBody(): void
    {
        $handler    = new CountingHandler(new HtmlResponse('<p>Work</p>'));
        $middleware = $this->middleware();

        $middleware->process($this->request(), $handler);
        $response = $middleware->process($this->request('HEAD'), $handler);

        static::assertSame([1, '', 'HIT'], [
            $handler->calls,
            (string) $response->getBody(),
            $response->getHeaderLine('X-PK-Cache'),
        ]);
    }

    #[Test]
    public function aResponseThatIsNotStoredIsABypassRatherThanAMiss(): void
    {
        $response = $this->middleware([new AppendingMutator()])->process(
            $this->request(),
            new CountingHandler(new HtmlResponse('', 404)),
        );

        static::assertSame(['', '<!-- stamp:BYPASS -->'], [
            $response->getHeaderLine('X-PK-Cache'),
            (string) $response->getBody(),
        ]);
    }

    #[Test]
    public function aVetoedResponseIsNotStoredAndLosesTheVetoHeader(): void
    {
        $vetoed   = PageCacheMiddleware::veto(new HtmlResponse('<p>Form</p>'));
        $response = $this->middleware()->process($this->request(), new CountingHandler($vetoed));

        static::assertSame([[], false], [$this->cache->items, $response->hasHeader('X-Page-Cache')]);
    }

    #[Test]
    public function bypassesTheCacheEntirelyWhenTheAdminSwitchesItOff(): void
    {
        $this->repository->save(CacheControl::disabled());
        $handler    = new CountingHandler(new HtmlResponse('x'));
        $middleware = $this->middleware();

        $middleware->process($this->request(), $handler);
        $response = $middleware->process($this->request(), $handler);

        static::assertSame([2, [], ''], [$handler->calls, $this->cache->items, $response->getHeaderLine('X-PK-Cache')]);
    }

    #[Test]
    public function doesNotCacheAnErrorResponse(): void
    {
        $this->middleware()->process($this->request(), new CountingHandler(new HtmlResponse('', 404)));

        static::assertSame([], $this->cache->items);
    }

    #[Test]
    public function doesNotCacheAPost(): void
    {
        $this->middleware()->process($this->request('POST'), new CountingHandler(new HtmlResponse('ok')));

        static::assertSame([], $this->cache->items);
    }

    #[Test]
    public function doesNotStoreTheEmptyBodyOfAHeadMiss(): void
    {
        $this->middleware()->process($this->request('HEAD'), new CountingHandler(new HtmlResponse('')));

        static::assertSame([], $this->cache->items);
    }

    #[Test]
    public function marksAStoredResponseAsAMiss(): void
    {
        $response = $this->middleware()->process($this->request(), new CountingHandler(new HtmlResponse('x')));

        static::assertSame('MISS', $response->getHeaderLine('X-PK-Cache'));
    }

    #[Test]
    public function mutationsAreAppliedAfterStorageSoTheyAreNeverCached(): void
    {
        $this->middleware([new AppendingMutator()])->process(
            $this->request(),
            new CountingHandler(new HtmlResponse('<p>Work</p>')),
        );

        static::assertStringNotContainsString('stamp', (string) array_values($this->cache->items)[0]);
    }

    #[Test]
    public function mutatorsRunInTheOrderGiven(): void
    {
        $response = $this->middleware([new AppendingMutator('first'), new AppendingMutator('second')])->process(
            $this->request('POST'),
            new CountingHandler(new HtmlResponse('')),
        );

        static::assertSame('<!-- first:BYPASS --><!-- second:BYPASS -->', (string) $response->getBody());
    }

    #[Test]
    public function mutatorsSeeHowTheCacheDealtWithEachRequest(): void
    {
        $handler    = new CountingHandler(new HtmlResponse('<p>Work</p>'));
        $middleware = $this->middleware([new AppendingMutator()]);

        $miss = $middleware->process($this->request(), $handler);
        $hit  = $middleware->process($this->request(), $handler);
        $post = $middleware->process($this->request('POST'), $handler);

        static::assertSame(
            [
                '<p>Work</p><!-- stamp:MISS -->',
                '<p>Work</p><!-- stamp:HIT -->',
                '<p>Work</p><!-- stamp:BYPASS -->',
            ],
            [(string) $miss->getBody(), (string) $hit->getBody(), (string) $post->getBody()],
        );
    }

    #[Test]
    public function readsTheRequestMethodCaseInsensitively(): void
    {
        $handler    = new CountingHandler(new HtmlResponse('<p>Work</p>'));
        $middleware = $this->middleware();

        $middleware->process($this->request('get'), $handler);
        $response = $middleware->process($this->request('get'), $handler);

        static::assertSame([1, '<p>Work</p>'], [$handler->calls, (string) $response->getBody()]);
    }

    #[Test]
    public function servesARepeatRequestFromTheCache(): void
    {
        $handler    = new CountingHandler(new HtmlResponse('<p>Work</p>'));
        $middleware = $this->middleware();

        $middleware->process($this->request(), $handler);
        $response = $middleware->process($this->request(), $handler);

        static::assertSame([1, '<p>Work</p>', 'HIT'], [
            $handler->calls,
            (string) $response->getBody(),
            $response->getHeaderLine('X-PK-Cache'),
        ]);
    }

    #[Test]
    public function stopsServingStoredPagesAsSoonAsTheAdminSwitchesCachingOff(): void
    {
        $handler    = new CountingHandler(new HtmlResponse('x'));
        $middleware = $this->middleware();

        $middleware->process($this->request(), $handler);
        $this->repository->save(CacheControl::disabled());
        $middleware->process($this->request(), $handler);

        static::assertSame(2, $handler->calls);
    }

    protected function setUp(): void
    {
        $this->cache      = new InMemoryCache();
        $this->repository = new InMemoryRepository(CacheControl::enabled());
    }

    /**
     * @param list<\Contenir\Cache\Mezzio\ResponseMutatorInterface> $mutators
     */
    private function middleware(array $mutators = []): PageCacheMiddleware
    {
        return new PageCacheMiddleware(
            new CachePolicy($this->repository),
            new PageStore($this->cache, new FrozenClock()),
            $mutators,
        );
    }
}
