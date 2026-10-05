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
use PHPUnit\Framework\TestCase;

use function array_values;

#[Group('unit')]
#[Group('middleware')]
final class PageCacheMiddlewareTest extends TestCase
{
    use ServerRequestTrait;

    private InMemoryCache $cache;

    private InMemoryRepository $repository;

    public function testAnswersAHeadRequestFromTheStoredPageWithoutItsBody(): void
    {
        $handler    = new CountingHandler(new HtmlResponse('<p>Work</p>'));
        $middleware = $this->middleware();

        $middleware->process($this->request(), $handler);
        $response = $middleware->process($this->request('HEAD'), $handler);

        self::assertSame([1, '', 'HIT'], [
            $handler->calls,
            (string) $response->getBody(),
            $response->getHeaderLine('X-PK-Cache'),
        ]);
    }

    public function testAVetoedResponseIsNotStoredAndLosesTheVetoHeader(): void
    {
        $vetoed   = PageCacheMiddleware::veto(new HtmlResponse('<p>Form</p>'));
        $response = $this->middleware()->process($this->request(), new CountingHandler($vetoed));

        self::assertSame([[], false], [$this->cache->items, $response->hasHeader('X-Page-Cache')]);
    }

    public function testBypassesTheCacheEntirelyWhenTheAdminSwitchesItOff(): void
    {
        $this->repository->save(CacheControl::disabled());
        $handler    = new CountingHandler(new HtmlResponse('x'));
        $middleware = $this->middleware();

        $middleware->process($this->request(), $handler);
        $response = $middleware->process($this->request(), $handler);

        self::assertSame([2, [], ''], [$handler->calls, $this->cache->items, $response->getHeaderLine('X-PK-Cache')]);
    }

    public function testDoesNotCacheAnErrorResponse(): void
    {
        $this->middleware()->process($this->request(), new CountingHandler(new HtmlResponse('', 404)));

        self::assertSame([], $this->cache->items);
    }

    public function testDoesNotCacheAPost(): void
    {
        $this->middleware()->process($this->request('POST'), new CountingHandler(new HtmlResponse('ok')));

        self::assertSame([], $this->cache->items);
    }

    public function testDoesNotStoreTheEmptyBodyOfAHeadMiss(): void
    {
        $this->middleware()->process($this->request('HEAD'), new CountingHandler(new HtmlResponse('')));

        self::assertSame([], $this->cache->items);
    }

    public function testMarksAStoredResponseAsAMiss(): void
    {
        $response = $this->middleware()->process($this->request(), new CountingHandler(new HtmlResponse('x')));

        self::assertSame('MISS', $response->getHeaderLine('X-PK-Cache'));
    }

    public function testMutationsAreAppliedAfterStorageSoTheyAreNeverCached(): void
    {
        $this->middleware([new AppendingMutator()])->process(
            $this->request(),
            new CountingHandler(new HtmlResponse('<p>Work</p>')),
        );

        self::assertStringNotContainsString('stamp', (string) array_values($this->cache->items)[0]);
    }

    public function testMutatorsRunInTheOrderGiven(): void
    {
        $response = $this->middleware([new AppendingMutator('first'), new AppendingMutator('second')])->process(
            $this->request('POST'),
            new CountingHandler(new HtmlResponse('')),
        );

        self::assertSame('<!-- first:BYPASS --><!-- second:BYPASS -->', (string) $response->getBody());
    }

    public function testMutatorsSeeHowTheCacheDealtWithEachRequest(): void
    {
        $handler    = new CountingHandler(new HtmlResponse('<p>Work</p>'));
        $middleware = $this->middleware([new AppendingMutator()]);

        $miss = $middleware->process($this->request(), $handler);
        $hit  = $middleware->process($this->request(), $handler);
        $post = $middleware->process($this->request('POST'), $handler);

        self::assertSame(
            [
                '<p>Work</p><!-- stamp:MISS -->',
                '<p>Work</p><!-- stamp:HIT -->',
                '<p>Work</p><!-- stamp:BYPASS -->',
            ],
            [(string) $miss->getBody(), (string) $hit->getBody(), (string) $post->getBody()],
        );
    }

    public function testServesARepeatRequestFromTheCache(): void
    {
        $handler    = new CountingHandler(new HtmlResponse('<p>Work</p>'));
        $middleware = $this->middleware();

        $middleware->process($this->request(), $handler);
        $response = $middleware->process($this->request(), $handler);

        self::assertSame([1, '<p>Work</p>', 'HIT'], [
            $handler->calls,
            (string) $response->getBody(),
            $response->getHeaderLine('X-PK-Cache'),
        ]);
    }

    public function testStopsServingStoredPagesAsSoonAsTheAdminSwitchesCachingOff(): void
    {
        $handler    = new CountingHandler(new HtmlResponse('x'));
        $middleware = $this->middleware();

        $middleware->process($this->request(), $handler);
        $this->repository->save(CacheControl::disabled());
        $middleware->process($this->request(), $handler);

        self::assertSame(2, $handler->calls);
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
