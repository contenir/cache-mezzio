<?php

declare(strict_types=1);

namespace Contenir\Cache\Mezzio\Tests\Unit;

use Contenir\Cache\CacheControl;
use Contenir\Cache\Mezzio\ActiveOptions;
use Contenir\Cache\Mezzio\CacheKeyGenerator;
use Contenir\Cache\Mezzio\SessionInspector;
use Contenir\Cache\Mezzio\Tests\TestAsset\Http\LooseUploadsRequest;
use Contenir\Cache\Mezzio\Tests\Trait\ServerRequestTrait;
use Laminas\Diactoros\UploadedFile;
use Laminas\Diactoros\Uri;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use stdClass;

use const UPLOAD_ERR_OK;

#[Group('unit')]
#[Group('key')]
final class CacheKeyGeneratorTest extends TestCase
{
    use ServerRequestTrait;

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function refusalProvider(): array
    {
        return [
            'query'   => [['query' => ['page' => '2']], 'cache_with_query'],
            'post'    => [['parsedBody' => ['name' => 'Ada']], 'cache_with_post'],
            'files'   => [['uploadedFiles' => ['cv' => 'file']], 'cache_with_files'],
            'session' => [['attributes' => ['session' => ['cart' => 1]]], 'cache_with_session'],
            'cookie'  => [['cookies' => ['theme' => 'dark']], 'cache_with_cookie'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function uriProvider(): array
    {
        return [
            'another path'   => ['https://www.example.test/about'],
            'another host'   => ['https://shop.example.test/work'],
            'another scheme' => ['http://www.example.test/work'],
            'another port'   => ['https://www.example.test:8443/work'],
        ];
    }

    /**
     * @return array<string, array{array<string, mixed>, array<string, mixed>, string}>
     */
    public static function variationProvider(): array
    {
        return [
            'query'   => [['query' => ['page' => '1']], ['query' => ['page' => '2']], 'query'],
            'post'    => [['parsedBody' => ['q' => 'a']], ['parsedBody' => ['q' => 'b']], 'post'],
            'files'   => [['uploadedFiles' => ['cv' => 'one.pdf']], ['uploadedFiles' => ['cv' => 'two.pdf']], 'files'],
            'session' => [
                ['attributes' => ['session' => ['cart' => 1]]],
                ['attributes' => ['session' => ['cart' => 2]]],
                'session',
            ],
            'cookie'  => [['cookies' => ['theme' => 'dark']], ['cookies' => ['theme' => 'light']], 'cookie'],
        ];
    }

    #[Test]
    public function aParsedBodyObjectVariesTheKeyByItsProperties(): void
    {
        $options   = ['cache_with_post' => true, 'make_id_with_post' => true];
        $object    = new stdClass();
        $object->q = 'a';

        static::assertSame(
            $this->key($this->request()->withParsedBody(['q' => 'a']), $options),
            $this->key($this->request()->withParsedBody($object), $options),
        );
    }

    #[Test]
    public function aPlainRequestGetsAPrefixedKey(): void
    {
        static::assertStringStartsWith(CacheKeyGenerator::PREFIX, (string) $this->key($this->request(), []));
    }

    /**
     * @param array<string, mixed> $signals
     */
    #[Test]
    #[DataProvider('refusalProvider')]
    public function aRequestCarryingASignalIsRefusedUnlessItsCacheWithOptionIsOn(
        array $signals,
        string $option,
    ): void {
        $request = $this->signalled($signals);

        static::assertSame(
            [true, false],
            [null === $this->key($request, []), null === $this->key($request, [$option => true])],
        );
    }

    /**
     * @param array<string, mixed> $first
     * @param array<string, mixed> $second
     */
    #[Test]
    #[DataProvider('variationProvider')]
    public function makeIdWithDecidesWhetherASignalVariesTheKey(
        array $first,
        array $second,
        string $signal,
    ): void {
        $shared = ["cache_with_{$signal}" => true];
        $varied = [...$shared, "make_id_with_{$signal}" => true];

        static::assertSame(
            [true, false],
            [
                $this->key($this->signalled($first), $shared) === $this->key($this->signalled($second), $shared),
                $this->key($this->signalled($first), $varied) === $this->key($this->signalled($second), $varied),
            ],
        );
    }

    #[Test]
    public function nestedUploadedFilesVaryTheKey(): void
    {
        $options = ['cache_with_files' => true, 'make_id_with_files' => true];

        static::assertNotSame(
            $this->key($this->request()->withUploadedFiles(['docs' => [$this->upload('a.pdf')]]), $options),
            $this->key($this->request()->withUploadedFiles(['docs' => [$this->upload('b.pdf')]]), $options),
        );
    }

    #[Test]
    #[DataProvider('uriProvider')]
    public function theHostAndPathAreAlwaysPartOfTheKey(string $other): void
    {
        static::assertNotSame(
            $this->key($this->request(), []),
            $this->key($this->request()->withUri(new Uri($other)), []),
        );
    }

    #[Test]
    public function theOrderOfQueryParametersDoesNotChangeTheKey(): void
    {
        $options = ['cache_with_query' => true, 'make_id_with_query' => true];
        $first   = $this->request(query: ['b' => '2', 'a' => ['y' => '1', 'x' => '0']]);
        $second  = $this->request(query: ['a' => ['x' => '0', 'y' => '1'], 'b' => '2']);

        static::assertSame($this->key($first, $options), $this->key($second, $options));
    }

    #[Test]
    public function uploadEntriesThatAreNeitherFilesNorArraysAreIgnored(): void
    {
        $options = ['cache_with_files' => true, 'make_id_with_files' => true];

        static::assertSame(
            $this->key(new LooseUploadsRequest(['cv' => 'not a file']), $options),
            $this->key(new LooseUploadsRequest(['cv' => 42]), $options),
        );
    }

    /**
     * @param array<string, mixed> $options
     */
    private function key(ServerRequestInterface $request, array $options): ?string
    {
        $active = ActiveOptions::resolve(new CacheControl(true, $options), $request->getUri()->getPath());
        static::assertNotNull($active);

        return (new CacheKeyGenerator(new SessionInspector()))->generate($request, $active);
    }

    /**
     * @param array<string, mixed> $signals
     */
    private function signalled(array $signals): ServerRequestInterface
    {
        $files = [];
        foreach ((array) ($signals['uploadedFiles'] ?? []) as $name => $filename) {
            $files[(string) $name] = $this->upload((string) $filename);
        }

        /** @var array<string, mixed> $query */
        $query = $signals['query'] ?? [];
        /** @var array<string, string> $cookies */
        $cookies = $signals['cookies'] ?? [];
        /** @var null|array<string, mixed> $body */
        $body = $signals['parsedBody'] ?? null;
        /** @var array<string, mixed> $attributes */
        $attributes = $signals['attributes'] ?? [];

        return $this->request(
            query: $query,
            cookies: $cookies,
            attributes: $attributes,
        )
            ->withUploadedFiles($files)
            ->withParsedBody($body);
    }

    private function upload(string $filename): UploadedFile
    {
        return new UploadedFile('php://memory', 0, UPLOAD_ERR_OK, $filename, 'application/pdf');
    }
}
