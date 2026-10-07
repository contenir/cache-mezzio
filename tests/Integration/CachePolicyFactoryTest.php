<?php

declare(strict_types=1);

namespace Contenir\PageCache\Mezzio\Tests\Integration;

use Contenir\PageCache\Mezzio\CachePolicy;
use Contenir\PageCache\Mezzio\CachePolicyFactory;
use Contenir\PageCache\Mezzio\Tests\TestAsset\Container\InMemoryContainer;
use Contenir\PageCache\Mezzio\Tests\Trait\ServerRequestTrait;
use Contenir\PageCache\Mezzio\Tests\Trait\TemporaryDirectoryTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
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

    #[Test]
    public function anchorsTheDefaultAdminFileToTheWorkingDirectoryAtBuildTime(): void
    {
        mkdir("{$this->temporaryDirectory}/config/autoload", recursive: true);
        mkdir("{$this->temporaryDirectory}/elsewhere");
        file_put_contents(
            "{$this->temporaryDirectory}/" . CachePolicyFactory::DEFAULT_FILE,
            data: "<?php return ['pagecache' => ['options' => ['cache' => false]]];",
        );
        chdir($this->temporaryDirectory);
        $policy = $this->sitePolicyWithCachingOn();
        chdir("{$this->temporaryDirectory}/elsewhere");

        static::assertNull($policy->ticketFor($this->request()));
    }

    #[Test]
    public function fallsBackToTheSiteDefaultsWhenTheWorkingDirectoryIsGone(): void
    {
        $vanished = "{$this->temporaryDirectory}/vanished";
        mkdir($vanished);
        chdir($vanished);
        rmdir($vanished);

        static::assertNotNull($this->sitePolicyWithCachingOn()->ticketFor($this->request()));
    }

    #[Test]
    public function readsTheAdminFileUnderTheWorkingDirectoryByDefault(): void
    {
        mkdir("{$this->temporaryDirectory}/config/autoload", recursive: true);
        file_put_contents(
            "{$this->temporaryDirectory}/" . CachePolicyFactory::DEFAULT_FILE,
            data: "<?php return ['pagecache' => ['options' => ['cache' => false]]];",
        );
        chdir($this->temporaryDirectory);

        static::assertNull($this->sitePolicyWithCachingOn()->ticketFor($this->request()));
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
