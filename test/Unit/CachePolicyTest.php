<?php

declare(strict_types=1);

namespace Contenir\Cache\Mezzio\Test\Unit;

use Contenir\Cache\CacheControl;
use Contenir\Cache\Mezzio\CachePolicy;
use Contenir\Cache\Mezzio\Test\TestAsset\Session\FakeSession;
use Contenir\Cache\Mezzio\Test\Trait\MakesServerRequest;
use Contenir\Cache\Repository\InMemoryRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

#[Group('unit')]
#[Group('policy')]
final class CachePolicyTest extends TestCase
{
    use MakesServerRequest;

    #[DataProvider('methodProvider')]
    public function testOnlyGetAndHeadRequestsUseTheCache(string $method, bool $expected): void
    {
        $policy = new CachePolicy(new InMemoryRepository(CacheControl::enabled()));

        self::assertSame($expected, null !== $policy->ticketFor($this->request($method)));
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

    public function testTheAdminStateIsReadAfreshForEveryRequest(): void
    {
        $repository = new InMemoryRepository(CacheControl::enabled());
        $policy     = new CachePolicy($repository);

        $before = $policy->ticketFor($this->request());
        $repository->save(CacheControl::disabled());

        self::assertSame([true, false], [null !== $before, null !== $policy->ticketFor($this->request())]);
    }

    public function testARouteOverrideCanTakeAPathOutOfTheCache(): void
    {
        $policy = new CachePolicy(new InMemoryRepository(new CacheControl(true, [], ['^/work' => ['cache' => false]])));

        self::assertNull($policy->ticketFor($this->request()));
    }

    public function testARequestCarryingASignalItsOptionsRefuseBypassesTheCache(): void
    {
        $policy = new CachePolicy(new InMemoryRepository(CacheControl::enabled()));

        self::assertNull($policy->ticketFor($this->request(query: ['page' => '2'])));
    }

    /**
     * @param array<string, mixed> $attributes
     */
    #[DataProvider('authenticatedProvider')]
    public function testAuthenticatedRequestsBypassTheCache(array $attributes): void
    {
        $control = new CacheControl(true, ['cache_with_session' => true]);

        self::assertNull((new CachePolicy(new InMemoryRepository($control)))->ticketFor($this->request(
            attributes: $attributes,
        )));
    }

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

    public function testTheBypassCallableCanKeepARequestOutOfTheCache(): void
    {
        $policy = new CachePolicy(
            new InMemoryRepository(CacheControl::enabled()),
            static fn(ServerRequestInterface $request): bool => '' !== $request->getHeaderLine('X-Preview'),
        );

        self::assertSame(
            [true, false],
            [
                null !== $policy->ticketFor($this->request()),
                null !== $policy->ticketFor($this->request()->withHeader('X-Preview', '1')),
            ],
        );
    }

    public function testTheTicketCarriesTheTtlOptionForThePath(): void
    {
        $policy = new CachePolicy(new InMemoryRepository(new CacheControl(true, ['ttl' => 900])));

        self::assertSame(900, $policy->ticketFor($this->request())?->ttl);
    }

    public function testTheSessionCookieNameIsConfigurable(): void
    {
        $control = new CacheControl(true, ['cache_with_cookie' => true, 'cache_with_session' => false]);
        $policy  = new CachePolicy(new InMemoryRepository($control), sessionCookie: 'SID');

        self::assertSame(
            [true, false],
            [
                null !== $policy->ticketFor($this->request(cookies: ['PHPSESSID' => 'abc'])),
                null !== $policy->ticketFor($this->request(cookies: ['SID' => 'abc'])),
            ],
        );
    }
}
