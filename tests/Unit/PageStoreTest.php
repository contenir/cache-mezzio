<?php

declare(strict_types=1);

namespace Contenir\Cache\Mezzio\Tests\Unit;

use Contenir\Cache\Mezzio\CacheTicket;
use Contenir\Cache\Mezzio\PageCodec;
use Contenir\Cache\Mezzio\PageStore;
use Contenir\Cache\Mezzio\StoredResponse;
use Contenir\Cache\Mezzio\Tests\TestAsset\Cache\FailingCache;
use Contenir\Cache\Mezzio\Tests\TestAsset\Cache\InMemoryCache;
use Contenir\Cache\Mezzio\Tests\TestAsset\Clock\FrozenClock;
use Laminas\Diactoros\CallbackStream;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\Response\HtmlResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

use function array_key_exists;

#[Group('unit')]
#[Group('store')]
final class PageStoreTest extends TestCase
{
    private InMemoryCache $cache;

    private FrozenClock $clock;

    /**
     * @return array<string, array{ResponseInterface, bool}>
     */
    public static function cacheableProvider(): array
    {
        return [
            '200 OK'                       => [new HtmlResponse('x', 200), true],
            '203 non-authoritative'        => [new HtmlResponse('x', 203), true],
            '301 moved permanently'        => [new Response\RedirectResponse('/new', 301), true],
            '308 permanent redirect'       => [new Response\RedirectResponse('/new', 308), true],
            '302 found'                    => [new Response\RedirectResponse('/new', 302), false],
            '204 no content'               => [new Response\EmptyResponse(204), false],
            '404 not found'                => [new HtmlResponse('x', 404), false],
            '500 server error'             => [new HtmlResponse('x', 500), false],
            'vetoed'                       => [new HtmlResponse('x', 200, ['X-Page-Cache' => 'off']), false],
            'sets a cookie'                => [new HtmlResponse('x', 200, ['Set-Cookie' => 'PHPSESSID=abc']), false],
            'no-store'                     => [new HtmlResponse('x', 200, ['Cache-Control' => 'no-store']), false],
            'private'                      => [new HtmlResponse('x', 200, [
                    'Cache-Control' => 'max-age=0, private',
                ]), false],
            'no-store in mixed case'       => [new HtmlResponse('x', 200, ['Cache-Control' => 'No-Store']), false],
            'public max-age'               => [new HtmlResponse('x', 200, [
                    'Cache-Control' => 'public, max-age=60',
                ]), true],
            'no-cache is still storable'   => [new HtmlResponse('x', 200, ['Cache-Control' => 'no-cache']), true],
            'body that cannot be replayed' => [new Response(new CallbackStream(static fn(): string => 'x')), false],
        ];
    }

    /**
     * @return array<string, array{null|int, int}>
     */
    public static function perItemTtlProvider(): array
    {
        return [
            'the ttl option for the path' => [60, 60],
            'the configured ttl'          => [null, 600],
        ];
    }

    public function testAFailingCacheBackendIsAMiss(): void
    {
        self::assertNull((new PageStore(new FailingCache(), $this->clock))->fetch(new CacheTicket('page')));
    }

    public function testAFailingCacheBackendStoresNothing(): void
    {
        self::assertFalse((new PageStore(new FailingCache(), $this->clock))->save(
            new CacheTicket('page'),
            new HtmlResponse('x'),
        ));
    }

    public function testAHitCarriesItsAgeInSeconds(): void
    {
        $store = $this->store();
        $store->save(new CacheTicket('page'), new HtmlResponse('x'));
        $this->clock->advance(90);

        self::assertSame('90', $store->fetch(new CacheTicket('page'))?->getHeaderLine('Age'));
    }

    public function testAHitFetchedAsItIsStoredHasAnAgeOfZero(): void
    {
        $store = $this->store();
        $store->save(new CacheTicket('page'), new HtmlResponse('x'));

        self::assertSame('0', $store->fetch(new CacheTicket('page'))?->getHeaderLine('Age'));
    }

    public function testAHitIsMarkedAsAHit(): void
    {
        $store = $this->store();
        $store->save(new CacheTicket('page'), new HtmlResponse('x'));

        self::assertSame('HIT', $store->fetch(new CacheTicket('page'))?->getHeaderLine('X-PK-Cache'));
    }

    public function testAHitKeepsAnAgeHeaderThePageAlreadyHad(): void
    {
        $store = $this->store();
        $store->save(new CacheTicket('page'), new HtmlResponse('x', 200, ['Age' => '5']));
        $this->clock->advance(90);

        self::assertSame('5', $store->fetch(new CacheTicket('page'))?->getHeaderLine('Age'));
    }

    public function testAHitKeepsTheCacheControlThePageWasStoredWith(): void
    {
        $store = $this->store();
        $store->save(new CacheTicket('page'), new HtmlResponse('x', 200, ['Cache-Control' => 'public, max-age=30']));

        self::assertSame('public, max-age=30', $store->fetch(new CacheTicket('page'))?->getHeaderLine('Cache-Control'));
    }

    public function testAHitStoredByAClockAheadOfOursHasAnAgeOfZero(): void
    {
        $this->store()->save(new CacheTicket('page'), new HtmlResponse('x'));
        $store = new PageStore($this->cache, new FrozenClock($this->clock->now()->modify('-30 seconds')));

        self::assertSame('0', $store->fetch(new CacheTicket('page'))?->getHeaderLine('Age'));
    }

    public function testAHitWithoutCacheControlGetsTheConfiguredOne(): void
    {
        $store = new PageStore($this->cache, $this->clock, cacheControl: 'public, max-age=60');
        $store->save(new CacheTicket('page'), new HtmlResponse('x'));

        self::assertSame('public, max-age=60', $store->fetch(new CacheTicket('page'))?->getHeaderLine('Cache-Control'));
    }

    public function testAnEntryThePageCacheDidNotWriteIsAMiss(): void
    {
        $this->cache->items['page'] = ['status' => 200, 'headers' => [], 'body' => 'legacy array entry'];

        self::assertNull($this->store()->fetch(new CacheTicket('page')));
    }

    public function testAPageWhoseHeadersCannotBeEncodedIsNotStored(): void
    {
        self::assertFalse($this->store()->save(new CacheTicket('page'), new HtmlResponse('x', 200, [
            'X-Name' => "caf\xe9",
        ])));
    }

    public function testAStoredEntryRoundTripsThroughTheCodec(): void
    {
        $this->store()->save(new CacheTicket('page'), new HtmlResponse('x'));

        self::assertInstanceOf(StoredResponse::class, PageCodec::decode($this->cache->items['page']));
    }

    public function testAStoredPageIsFetchedWithItsStatusAndBody(): void
    {
        $store = $this->store();
        $store->save(new CacheTicket('page'), new HtmlResponse('<p>Work</p>', 203));

        $hit = $store->fetch(new CacheTicket('page'));

        self::assertSame([203, '<p>Work</p>'], [$hit?->getStatusCode(), (string) $hit?->getBody()]);
    }

    public function testFetchingAPageThatWasNeverStoredIsAMiss(): void
    {
        self::assertNull($this->store()->fetch(new CacheTicket('page')));
    }

    public function testNoTtlIsPassedSoACacheThatRefusesPerItemTtlsStillStores(): void
    {
        $stored = (new PageStore($this->cache, $this->clock, ttl: 600))->save(
            new CacheTicket('page', ttl: 60),
            new HtmlResponse('x'),
        );

        self::assertSame([true, true, null], [
            $stored,
            array_key_exists('page', $this->cache->ttls),
            $this->cache->ttls['page'],
        ]);
    }

    #[DataProvider('cacheableProvider')]
    public function testOnlyCacheableResponsesAreStored(ResponseInterface $response, bool $expected): void
    {
        self::assertSame($expected, $this->store()->save(new CacheTicket('page'), $response));
    }

    #[DataProvider('perItemTtlProvider')]
    public function testPerItemTtlsArePassedOnlyWhenSwitchedOn(?int $optionTtl, int $expected): void
    {
        $cache = new InMemoryCache(honoursTtl: true);

        (new PageStore($cache, $this->clock, perItemTtl: true, ttl: 600))->save(
            new CacheTicket('page', ttl: $optionTtl),
            new HtmlResponse('x'),
        );

        self::assertSame($expected, $cache->ttls['page']);
    }

    public function testPerItemTtlsDefaultToFiveMinutes(): void
    {
        $cache = new InMemoryCache(honoursTtl: true);

        (new PageStore($cache, $this->clock, perItemTtl: true))->save(new CacheTicket('page'), new HtmlResponse('x'));

        self::assertSame(300, $cache->ttls['page']);
    }

    public function testThePageIsStampedWithTheTimeItWasStored(): void
    {
        $this->store()->save(new CacheTicket('page'), new HtmlResponse('x'));

        self::assertSame(
            $this->clock->now()->getTimestamp(),
            PageCodec::decode($this->cache->items['page'])?->storedAt,
        );
    }

    public function testTheVetoAndStatusHeadersAreNotStored(): void
    {
        $this->store()->save(new CacheTicket('page'), new HtmlResponse('x', 200, ['X-PK-Cache' => 'MISS']));

        self::assertArrayNotHasKey('X-PK-Cache', PageCodec::decode($this->cache->items['page'])->headers ?? []);
    }

    protected function setUp(): void
    {
        $this->cache = new InMemoryCache();
        $this->clock = new FrozenClock();
    }

    private function store(): PageStore
    {
        return new PageStore($this->cache, $this->clock);
    }
}
