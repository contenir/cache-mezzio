<?php

declare(strict_types=1);

namespace Contenir\Cache\Mezzio\Tests\Integration\Repository;

use Contenir\Cache\CacheControl;
use Contenir\Cache\Mezzio\Repository\LayeredFileRepository;
use Contenir\Cache\Mezzio\Tests\Trait\TemporaryDirectoryTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
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

    public function testAChangeTheAdminSavesIsSeenOnTheNextRead(): void
    {
        $repository = new LayeredFileRepository($this->file, new CacheControl(true));

        $before = $repository->get()->enabled;
        $this->write(['pagecache' => ['options' => ['cache' => false]]]);

        self::assertSame([true, false], [$before, $repository->get()->enabled]);
    }

    public function testAdminOptionsOverrideTheSiteDefaultsOfTheSameName(): void
    {
        $this->write(['pagecache' => ['options' => ['cache' => true, 'cache_with_query' => false, 'ttl' => 60]]]);

        $repository = new LayeredFileRepository($this->file, new CacheControl(
            false,
            ['cache_with_query' => true, 'cache_with_cookie' => true],
        ));

        self::assertSame(
            ['cache_with_query' => false, 'cache_with_cookie' => true, 'ttl' => 60],
            $repository->get()->options,
        );
    }

    public function testAdminRoutesReplaceTheSiteRoutes(): void
    {
        $this->write(['pagecache' => ['routes' => ['/shop.*' => ['cache' => false]]]]);

        $repository = new LayeredFileRepository(
            $this->file,
            new CacheControl(true, [], ['/api.*' => ['cache' => false]]),
        );

        self::assertSame(['/shop.*' => ['cache' => false]], $repository->get()->routes);
    }

    public function testBuildsItsDefaultsFromPagecacheShapedConfig(): void
    {
        $repository = LayeredFileRepository::withDefaults(
            $this->file,
            ['cache' => true, 'cache_with_query' => true, 0 => 'ignored'],
            ['/api.*' => ['cache' => false], '/bad' => 'off'],
        );

        self::assertEquals(
            new CacheControl(true, ['cache_with_query' => true], ['/api.*' => ['cache' => false]]),
            $repository->get(),
        );
    }

    public function testCachingIsOffWhenNeitherTheSiteNorTheAdminTurnedItOn(): void
    {
        self::assertFalse((new LayeredFileRepository($this->file))->get()->enabled);
    }

    public function testKeepsRoutePatternsMadeOfDigitsUnderTheirOwnKey(): void
    {
        $this->write(['pagecache' => ['routes' => ['404' => ['cache' => false], '/api' => ['cache' => false]]]]);

        self::assertSame(
            [404 => ['cache' => false], '/api' => ['cache' => false]],
            (new LayeredFileRepository($this->file))->get()->routes,
        );
    }

    public function testMalformedAdminRoutesAreDropped(): void
    {
        $this->write(['pagecache' => ['routes' => ['/shop.*' => ['cache' => false], '/bad' => 'off']]]);

        self::assertSame(['/shop.*' => ['cache' => false]], (new LayeredFileRepository($this->file))->get()->routes);
    }

    public function testOverridesWithoutAnOptionNameAreDroppedFromARoute(): void
    {
        $this->write(['pagecache' => ['routes' => ['/shop.*' => ['cache' => false, 0 => 'stray']]]]);

        self::assertSame(['/shop.*' => ['cache' => false]], (new LayeredFileRepository($this->file))->get()->routes);
    }

    public function testSavedStateIsReadBack(): void
    {
        $repository = new LayeredFileRepository($this->file);
        $state      = new CacheControl(true, ['cache_with_query' => true], ['/api.*' => ['cache' => false]]);

        $repository->save($state);

        self::assertEquals($state, $repository->get());
    }

    /**
     * @param array<string, mixed> $file
     */
    #[DataProvider('masterSwitchProvider')]
    public function testTheMasterSwitchIsInheritedUnlessTheAdminOverrodeIt(
        bool $siteDefault,
        array $file,
        bool $expected,
    ): void {
        $this->write($file);

        $repository = new LayeredFileRepository($this->file, new CacheControl($siteDefault));

        self::assertSame($expected, $repository->get()->enabled);
    }

    public function testTheSiteDefaultsApplyUntilTheAdminHasWrittenAFile(): void
    {
        $defaults = new CacheControl(true, ['cache_with_query' => true], ['/api.*' => ['cache' => false]]);

        self::assertEquals($defaults, (new LayeredFileRepository($this->file, $defaults))->get());
    }

    public function testUnusableDefaultsMeanCachingIsOff(): void
    {
        self::assertEquals(
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
