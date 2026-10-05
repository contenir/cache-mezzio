<?php

declare(strict_types=1);

namespace Contenir\Cache\Mezzio\Tests\Unit;

use Contenir\Cache\CacheControl;
use Contenir\Cache\CacheControlRepositoryInterface;
use Contenir\Cache\Mezzio\CachePolicyFactory;
use Contenir\Cache\Mezzio\Tests\TestAsset\Container\InMemoryContainer;
use Contenir\Cache\Mezzio\Tests\Trait\ServerRequestTrait;
use Contenir\Cache\Repository\InMemoryRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

#[Group('unit')]
#[Group('factory')]
final class CachePolicyFactoryTest extends TestCase
{
    use ServerRequestTrait;

    /**
     * @return array<string, array{mixed, array<string, mixed>}>
     */
    public static function bypassProvider(): array
    {
        $isPreview = static fn(ServerRequestInterface $request): bool => $request->hasHeader('X-Preview');

        return [
            'callable'                        => [$isPreview, []],
            'service name'                    => ['app.preview_bypass', ['app.preview_bypass' => $isPreview]],
            'truthy non-bool is not a bypass' => [
                static fn(ServerRequestInterface $request): bool|string => $request->hasHeader('X-Preview')
                    ? true
                    : 'yes',
                [],
            ],
        ];
    }

    #[Test]
    public function aRegisteredRepositoryIsAuthoritative(): void
    {
        $policy = (new CachePolicyFactory())(new InMemoryContainer([
            'config'                               => ['pagecache' => ['options' => ['cache' => true]]],
            CacheControlRepositoryInterface::class => new InMemoryRepository(CacheControl::disabled()),
        ]));

        static::assertNull($policy->ticketFor($this->request()));
    }

    #[Test]
    public function refusesABypassThatIsNotCallable(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('config[pagecache][bypass] must be a callable');

        (new CachePolicyFactory())(new InMemoryContainer([
            'config'                               => ['pagecache' => ['bypass' => 'no.such.service']],
            CacheControlRepositoryInterface::class => new InMemoryRepository(),
        ]));
    }

    /**
     * @param array<string, mixed> $services
     */
    #[Test]
    #[DataProvider('bypassProvider')]
    public function theBypassCanBeACallableOrTheServiceNameOfOne(mixed $bypass, array $services): void
    {
        $policy = (new CachePolicyFactory())(new InMemoryContainer([
            ...$services,
            'config'                               => ['pagecache' => ['bypass' => $bypass]],
            CacheControlRepositoryInterface::class => new InMemoryRepository(CacheControl::enabled()),
        ]));

        static::assertSame(
            [true, false],
            [
                null !== $policy->ticketFor($this->request()),
                null !== $policy->ticketFor($this->request()->withHeader('X-Preview', '1')),
            ],
        );
    }

    #[Test]
    public function usesTheConfiguredSessionCookieName(): void
    {
        $policy = (new CachePolicyFactory())(new InMemoryContainer([
            'config'                               => ['pagecache' => ['session_cookie' => 'SID']],
            CacheControlRepositoryInterface::class => new InMemoryRepository(
                new CacheControl(true, ['cache_with_cookie' => true]),
            ),
        ]));

        static::assertNull($policy->ticketFor($this->request(cookies: ['SID' => 'abc'])));
    }
}
