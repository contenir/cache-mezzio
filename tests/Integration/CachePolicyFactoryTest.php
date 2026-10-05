<?php

declare(strict_types=1);

namespace Contenir\Cache\Mezzio\Tests\Integration;

use Contenir\Cache\Mezzio\CachePolicy;
use Contenir\Cache\Mezzio\CachePolicyFactory;
use Contenir\Cache\Mezzio\Tests\TestAsset\Container\InMemoryContainer;
use Contenir\Cache\Mezzio\Tests\Trait\ServerRequestTrait;
use Contenir\Cache\Mezzio\Tests\Trait\TemporaryDirectoryTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function chdir;
use function file_put_contents;
use function getcwd;
use function mkdir;
use function rmdir;

/**
 * Without a registered repository the factory reads the admin's file, by
 * default under the working directory; these cases use a real one.
 */
#[Group('integration')]
#[Group('factory')]
final class CachePolicyFactoryTest extends TestCase
{
    use ServerRequestTrait;
    use TemporaryDirectoryTrait;

    private string $originalWorkingDirectory;

    public function testFallsBackToTheSiteDefaultsWhenTheWorkingDirectoryIsGone(): void
    {
        $vanished = "{$this->temporaryDirectory}/vanished";
        mkdir($vanished);
        chdir($vanished);
        rmdir($vanished);

        self::assertNotNull($this->sitePolicyWithCachingOn()->ticketFor($this->request()));
    }

    public function testReadsTheAdminFileUnderTheWorkingDirectoryByDefault(): void
    {
        mkdir("{$this->temporaryDirectory}/config/autoload", recursive: true);
        file_put_contents(
            "{$this->temporaryDirectory}/" . CachePolicyFactory::DEFAULT_FILE,
            data: "<?php return ['pagecache' => ['options' => ['cache' => false]]];",
        );
        chdir($this->temporaryDirectory);

        self::assertNull($this->sitePolicyWithCachingOn()->ticketFor($this->request()));
    }

    protected function setUp(): void
    {
        $this->originalWorkingDirectory = (string) getcwd();
        $this->setUpTemporaryDirectory();
    }

    protected function tearDown(): void
    {
        chdir($this->originalWorkingDirectory);
        $this->tearDownTemporaryDirectory();
    }

    private function sitePolicyWithCachingOn(): CachePolicy
    {
        return (new CachePolicyFactory())(new InMemoryContainer([
            'config' => ['pagecache' => ['options' => ['cache' => true]]],
        ]));
    }
}
