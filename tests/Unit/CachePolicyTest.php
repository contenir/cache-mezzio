<?php

declare(strict_types=1);

namespace Contenir\Cache\Mezzio\Tests\Unit;

use Contenir\Cache\CacheControl;
use Contenir\Cache\Mezzio\CachePolicy;
use Contenir\Cache\Mezzio\Tests\TestAsset\Session\FakeSession;
use Contenir\Cache\Mezzio\Tests\Trait\ServerRequestTrait;
use Contenir\Cache\Repository\InMemoryRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

#[Group('unit')]
#[Group('policy')]
final class CachePolicyTest extends TestCase
{
    use ServerRequestTrait;

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function authenticatedProvider(): array
    {
        return [
            'user attribute'      => [['user' => 'ada']],
            'session with a user' => [['session' => new FakeSession(['user_id' => 1])]],
        ];
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function methodProvider(): array
    {
        return [
            'GET'            => ['GET', true],
            'HEAD'           => ['HEAD', true],
            'lower-case get' => ['get', true],
            'POST'           => ['POST', false],
            'PUT'            => ['PUT', false],
            'DELETE'         => ['DELETE', false],
            'OPTIONS'        => ['OPTIONS', false],
        ];
    }

    #[Test]
    public function aRequestCarryingASignalItsOptionsRefuseBypassesTheCache(): void
    {
        $policy = new CachePolicy(new InMemoryRepository(CacheControl::enabled()));

        static::assertNull($policy->ticketFor($this->request(query: ['page' => '2'])));
    }

    #[Test]
    public function aRouteOverrideCanTakeAPathOutOfTheCache(): void
    {
        $policy = new CachePolicy(new InMemoryRepository(new CacheControl(true, [], ['^/work' => ['cache' => false]])));

        static::assertNull($policy->ticketFor($this->request()));
    }

    /**
     * @param array<string, mixed> $attributes
     */
    #[Test]
    #[DataProvider('authenticatedProvider')]
    public function authenticatedRequestsBypassTheCache(array $attributes): void
    {
        $control = new CacheControl(true, ['cache_with_session' => true]);

        static::assertNull((new CachePolicy(new InMemoryRepository($control)))->ticketFor($this->request(
            attributes: $attributes,
        )));
    }

    #[Test]
    #[DataProvider('methodProvider')]
    public function onlyGetAndHeadRequestsUseTheCache(string $method, bool $expected): void
    {
        $policy = new CachePolicy(new InMemoryRepository(CacheControl::enabled()));

        static::assertSame($expected, null !== $policy->ticketFor($this->request($method)));
    }

    #[Test]
    public function theAdminStateIsReadAfreshForEveryRequest(): void
    {
        $repository = new InMemoryRepository(CacheControl::enabled());
        $policy     = new CachePolicy($repository);

        $before = $policy->ticketFor($this->request());
        $repository->save(CacheControl::disabled());

        static::assertSame([true, false], [null !== $before, null !== $policy->ticketFor($this->request())]);
    }

    #[Test]
    public function theBypassCallableCanKeepARequestOutOfTheCache(): void
    {
        $policy = new CachePolicy(
            new InMemoryRepository(CacheControl::enabled()),
            static fn(ServerRequestInterface $request): bool => '' !== $request->getHeaderLine('X-Preview'),
        );

        static::assertSame(
            [true, false],
            [
                null !== $policy->ticketFor($this->request()),
                null !== $policy->ticketFor($this->request()->withHeader('X-Preview', '1')),
            ],
        );
    }

    #[Test]
    public function theSessionCookieNameIsConfigurable(): void
    {
        $control = new CacheControl(true, ['cache_with_cookie' => true, 'cache_with_session' => false]);
        $policy  = new CachePolicy(new InMemoryRepository($control), sessionCookie: 'SID');

        static::assertSame(
            [true, false],
            [
                null !== $policy->ticketFor($this->request(cookies: ['PHPSESSID' => 'abc'])),
                null !== $policy->ticketFor($this->request(cookies: ['SID' => 'abc'])),
            ],
        );
    }

    #[Test]
    public function theTicketCarriesTheTtlOptionForThePath(): void
    {
        $policy = new CachePolicy(new InMemoryRepository(new CacheControl(true, ['ttl' => 900])));

        static::assertSame(900, $policy->ticketFor($this->request())?->ttl);
    }
}
