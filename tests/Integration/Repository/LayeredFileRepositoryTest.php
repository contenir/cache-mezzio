<?php

declare(strict_types=1);

namespace Contenir\PageCache\Mezzio\Tests\Integration\Repository;

use Contenir\PageCache\CacheControl;
use Contenir\PageCache\Mezzio\Repository\LayeredFileRepository;
use Contenir\PageCache\Mezzio\Tests\Trait\TemporaryDirectoryTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function var_export;

#[Group('integration')]
#[Group('repository')]
final class LayeredFileRepositoryTest extends TestCase
{
    use TemporaryDirectoryTrait;

    private string $file;

    /**
     * @return array<string, array{bool, array<string, mixed>, bool}>
     */
    public static function masterSwitchProvider(): array
    {
        return [
            'admin turned it off'                => [true, ['pagecache' => ['options' => ['cache' => false]]], false],
            'admin turned it on'                 => [false, ['pagecache' => ['options' => ['cache' => true]]], true],
            'admin overrode another option only' => [
                true,
                ['pagecache' => ['options' => ['cache_with_cookie' => true]]],
                true,
            ],
            'admin overrode routes only'         => [true, ['pagecache' => ['routes' => []]], true],
            'file without a pagecache section'   => [true, ['maintenance' => ['enabled' => false]], true],
            'pagecache section is not an array'  => [true, ['pagecache' => 'off'], true],
        ];
    }

    #[Test]
    public function aChangeTheAdminSavesIsSeenOnTheNextRead(): void
    {
        $repository = new LayeredFileRepository($this->file, new CacheControl(true));

        $before = $repository->get()->enabled;
        $this->write(['pagecache' => ['options' => ['cache' => false]]]);

        static::assertSame([true, false], [$before, $repository->get()->enabled]);
    }

    #[Test]
    public function adminOptionsOverrideTheSiteDefaultsOfTheSameName(): void
    {
        $this->write(['pagecache' => ['options' => ['cache' => true, 'cache_with_query' => false, 'ttl' => 60]]]);

        $repository = new LayeredFileRepository($this->file, new CacheControl(
            false,
            ['cache_with_query' => true, 'cache_with_cookie' => true],
        ));

        static::assertSame(
            ['cache_with_query' => false, 'cache_with_cookie' => true, 'ttl' => 60],
            $repository->get()->options,
        );
    }

    #[Test]
    public function adminRoutesReplaceTheSiteRoutes(): void
    {
        $this->write(['pagecache' => ['routes' => ['/shop.*' => ['cache' => false]]]]);

        $repository = new LayeredFileRepository(
            $this->file,
            new CacheControl(true, [], ['/api.*' => ['cache' => false]]),
        );

        static::assertSame(['/shop.*' => ['cache' => false]], $repository->get()->routes);
    }

    #[Test]
    public function buildsItsDefaultsFromPagecacheShapedConfig(): void
    {
        $repository = LayeredFileRepository::withDefaults(
            $this->file,
            ['cache' => true, 'cache_with_query' => true, 0 => 'ignored'],
            ['/api.*' => ['cache' => false], '/bad' => 'off'],
        );

        static::assertEquals(
            new CacheControl(true, ['cache_with_query' => true], ['/api.*' => ['cache' => false]]),
            $repository->get(),
        );
    }

    #[Test]
    public function cachingIsOffWhenNeitherTheSiteNorTheAdminTurnedItOn(): void
    {
        static::assertFalse((new LayeredFileRepository($this->file))->get()->enabled);
    }

    #[Test]
    public function keepsRoutePatternsMadeOfDigitsUnderTheirOwnKey(): void
    {
        $this->write(['pagecache' => ['routes' => ['404' => ['cache' => false], '/api' => ['cache' => false]]]]);

        static::assertSame(
            [404 => ['cache' => false], '/api' => ['cache' => false]],
            (new LayeredFileRepository($this->file))->get()->routes,
        );
    }

    #[Test]
    public function malformedAdminRoutesAreDropped(): void
    {
        $this->write(['pagecache' => ['routes' => ['/shop.*' => ['cache' => false], '/bad' => 'off']]]);

        static::assertSame(['/shop.*' => ['cache' => false]], (new LayeredFileRepository($this->file))->get()->routes);
    }

    #[Test]
    public function overridesWithoutAnOptionNameAreDroppedFromARoute(): void
    {
        $this->write(['pagecache' => ['routes' => ['/shop.*' => ['cache' => false, 0 => 'stray']]]]);

        static::assertSame(['/shop.*' => ['cache' => false]], (new LayeredFileRepository($this->file))->get()->routes);
    }

    #[Test]
    public function savedStateIsReadBack(): void
    {
        $repository = new LayeredFileRepository($this->file);
        $state      = new CacheControl(true, ['cache_with_query' => true], ['/api.*' => ['cache' => false]]);

        $repository->save($state);

        static::assertEquals($state, $repository->get());
    }

    /**
     * @param array<string, mixed> $file
     */
    #[Test]
    #[DataProvider('masterSwitchProvider')]
    public function theMasterSwitchIsInheritedUnlessTheAdminOverrodeIt(
        bool $siteDefault,
        array $file,
        bool $expected,
    ): void {
        $this->write($file);

        $repository = new LayeredFileRepository($this->file, new CacheControl($siteDefault));

        static::assertSame($expected, $repository->get()->enabled);
    }

    #[Test]
    public function theSiteDefaultsApplyUntilTheAdminHasWrittenAFile(): void
    {
        $defaults = new CacheControl(true, ['cache_with_query' => true], ['/api.*' => ['cache' => false]]);

        static::assertEquals($defaults, (new LayeredFileRepository($this->file, $defaults))->get());
    }

    #[Test]
    public function unusableDefaultsMeanCachingIsOff(): void
    {
        static::assertEquals(
            new CacheControl(false),
            LayeredFileRepository::withDefaults($this->file, 'nonsense', null)->get(),
        );
    }

    protected function setUp(): void
    {
        $this->setUpTemporaryDirectory();
        $this->file = "{$this->temporaryDirectory}/pagecache.local.php";
    }

    protected function tearDown(): void
    {
        $this->tearDownTemporaryDirectory();
    }

    /**
     * @param array<string, mixed> $config
     */
    private function write(array $config): void
    {
        file_put_contents($this->file, "<?php\n\nreturn " . var_export($config, return: true) . ";\n");
    }
}
