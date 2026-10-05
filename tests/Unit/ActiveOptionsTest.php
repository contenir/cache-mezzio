<?php

declare(strict_types=1);

namespace Contenir\Cache\Mezzio\Tests\Unit;

use Contenir\Cache\CacheControl;
use Contenir\Cache\Mezzio\ActiveOptions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function error_clear_last;
use function error_get_last;

#[Group('unit')]
#[Group('options')]
final class ActiveOptionsTest extends TestCase
{
    /**
     * @return array<string, array{bool, array<array-key, array<string, mixed>>, bool}>
     */
    public static function routeProvider(): array
    {
        return [
            'no routes'                                 => [true, [], true],
            'matching route turns caching off'          => [true, ['/api.*' => ['cache' => false]], false],
            'route that does not match is ignored'      => [true, ['/admin.*' => ['cache' => false]], true],
            'last matching route wins'                  => [
                true,
                ['/api.*' => ['cache' => false], '/api/v1.*' => ['cache' => true]],
                true,
            ],
            'earlier non-matching route is passed over' => [
                true,
                ['/admin.*' => ['cache' => true], '/api.*' => ['cache' => false]],
                false,
            ],
            'later non-matching route does not win'     => [
                true,
                ['/api.*' => ['cache' => false], '/admin.*' => ['cache' => true]],
                false,
            ],
            'master switch off beats a route'           => [false, ['/api.*' => ['cache' => true]], false],
            'route without a cache key keeps it on'     => [true, ['/api.*' => ['ttl' => 60]], true],
            'route with a null cache turns it off'      => [true, ['/api.*' => ['cache' => null]], false],
            'pattern that does not compile is skipped'  => [true, ['/api/(unclosed' => ['cache' => false]], true],
            'backtick in a pattern is escaped'          => [true, ['/api`.*' => ['cache' => false]], true],
            'pattern made of digits'                    => [true, [1 => ['cache' => false]], false],
        ];
    }

    /**
     * @return array<string, array{array<string, mixed>, string, bool, bool}>
     */
    public static function signalProvider(): array
    {
        return [
            'defaults refuse every signal'      => [[], 'query', false, false],
            'cache_with only'                   => [['cache_with_query' => true], 'query', true, false],
            'cache_with and make_id_with'       => [
                ['cache_with_cookie' => true, 'make_id_with_cookie' => true],
                'cookie',
                true,
                true,
            ],
            'string flags are read as booleans' => [
                ['cache_with_post' => '1', 'make_id_with_post' => 'off'],
                'post',
                true,
                false,
            ],
            'other signals are unaffected'      => [['cache_with_query' => true], 'files', false, false],
        ];
    }

    /**
     * @return array<string, array{mixed, null|int}>
     */
    public static function ttlProvider(): array
    {
        return [
            'unset'          => [null, null],
            'integer'        => [600, 600],
            'numeric string' => ['120', 120],
            'not a number'   => ['soon', null],
        ];
    }

    #[Test]
    public function aBacktickInAPatternMatchesALiteralBacktick(): void
    {
        $control = new CacheControl(true, [], ['/a`b' => ['cache' => false]]);

        static::assertNull(ActiveOptions::resolve($control, '/a`b'));
    }

    #[Test]
    public function aPatternThatDoesNotCompileRaisesNoWarning(): void
    {
        error_clear_last();

        ActiveOptions::resolve(new CacheControl(true, [], ['/api/(unclosed' => ['cache' => false]]), '/api/v1');

        static::assertNull(error_get_last());
    }

    #[Test]
    public function aRouteOverrideReplacesTheAdminOptionForItsPath(): void
    {
        $control = new CacheControl(true, ['cache_with_query' => false], ['/search' => ['cache_with_query' => true]]);

        static::assertTrue(ActiveOptions::resolve($control, '/search')?->allows('query'));
    }

    #[Test]
    public function aSignalWithoutOptionsNeitherAllowsCachingNorVariesTheKey(): void
    {
        $active = ActiveOptions::resolve(new CacheControl(true), '/work');

        static::assertSame([false, false], [$active?->allows('unknown'), $active?->variesBy('unknown')]);
    }

    #[Test]
    public function cachingIsOffWhenTheAdminMasterSwitchIsOff(): void
    {
        static::assertNull(ActiveOptions::resolve(new CacheControl(false), '/work'));
    }

    #[Test]
    public function cachingIsOnWhenTheAdminMasterSwitchIsOn(): void
    {
        static::assertInstanceOf(ActiveOptions::class, ActiveOptions::resolve(new CacheControl(true), '/work'));
    }

    /**
     * @param array<string, mixed> $options
     */
    #[Test]
    #[DataProvider('signalProvider')]
    public function optionsSayWhichSignalsAllowCachingAndVaryTheKey(
        array $options,
        string $signal,
        bool $allows,
        bool $variesBy,
    ): void {
        $active = ActiveOptions::resolve(new CacheControl(true, $options), '/work');

        static::assertSame([$allows, $variesBy], [$active?->allows($signal), $active?->variesBy($signal)]);
    }

    /**
     * @param array<string, array<string, mixed>> $routes
     */
    #[Test]
    #[DataProvider('routeProvider')]
    public function routeOverridesDecideWhetherAPathIsCached(bool $enabled, array $routes, bool $expected): void
    {
        $options = ActiveOptions::resolve(new CacheControl($enabled, [], $routes), '/api/v1/items');

        static::assertSame($expected, null !== $options);
    }

    #[Test]
    #[DataProvider('ttlProvider')]
    public function ttlIsReadFromTheOptions(mixed $ttl, ?int $expected): void
    {
        static::assertSame($expected, ActiveOptions::resolve(new CacheControl(true, ['ttl' => $ttl]), '/work')?->ttl());
    }
}
