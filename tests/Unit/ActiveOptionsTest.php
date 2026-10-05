<?php

declare(strict_types=1);

namespace Contenir\Cache\Mezzio\Tests\Unit;

use Contenir\Cache\CacheControl;
use Contenir\Cache\Mezzio\ActiveOptions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
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

    public function testABacktickInAPatternMatchesALiteralBacktick(): void
    {
        $control = new CacheControl(true, [], ['/a`b' => ['cache' => false]]);

        self::assertNull(ActiveOptions::resolve($control, '/a`b'));
    }

    public function testAPatternThatDoesNotCompileRaisesNoWarning(): void
    {
        error_clear_last();

        ActiveOptions::resolve(new CacheControl(true, [], ['/api/(unclosed' => ['cache' => false]]), '/api/v1');

        self::assertNull(error_get_last());
    }

    public function testARouteOverrideReplacesTheAdminOptionForItsPath(): void
    {
        $control = new CacheControl(true, ['cache_with_query' => false], ['/search' => ['cache_with_query' => true]]);

        self::assertTrue(ActiveOptions::resolve($control, '/search')?->allows('query'));
    }

    public function testASignalWithoutOptionsNeitherAllowsCachingNorVariesTheKey(): void
    {
        $active = ActiveOptions::resolve(new CacheControl(true), '/work');

        self::assertSame([false, false], [$active?->allows('unknown'), $active?->variesBy('unknown')]);
    }

    public function testCachingIsOffWhenTheAdminMasterSwitchIsOff(): void
    {
        self::assertNull(ActiveOptions::resolve(new CacheControl(false), '/work'));
    }

    public function testCachingIsOnWhenTheAdminMasterSwitchIsOn(): void
    {
        self::assertInstanceOf(ActiveOptions::class, ActiveOptions::resolve(new CacheControl(true), '/work'));
    }

    /**
     * @param array<string, mixed> $options
     */
    #[DataProvider('signalProvider')]
    public function testOptionsSayWhichSignalsAllowCachingAndVaryTheKey(
        array $options,
        string $signal,
        bool $allows,
        bool $variesBy,
    ): void {
        $active = ActiveOptions::resolve(new CacheControl(true, $options), '/work');

        self::assertSame([$allows, $variesBy], [$active?->allows($signal), $active?->variesBy($signal)]);
    }

    /**
     * @param array<string, array<string, mixed>> $routes
     */
    #[DataProvider('routeProvider')]
    public function testRouteOverridesDecideWhetherAPathIsCached(bool $enabled, array $routes, bool $expected): void
    {
        $options = ActiveOptions::resolve(new CacheControl($enabled, [], $routes), '/api/v1/items');

        self::assertSame($expected, null !== $options);
    }

    #[DataProvider('ttlProvider')]
    public function testTtlIsReadFromTheOptions(mixed $ttl, ?int $expected): void
    {
        self::assertSame($expected, ActiveOptions::resolve(new CacheControl(true, ['ttl' => $ttl]), '/work')?->ttl());
    }
}
