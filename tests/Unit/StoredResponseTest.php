<?php

declare(strict_types=1);

namespace Contenir\Cache\Mezzio\Tests\Unit;

use Contenir\Cache\Mezzio\StoredResponse;
use Laminas\Diactoros\Response\HtmlResponse;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
#[Group('codec')]
final class StoredResponseTest extends TestCase
{
    #[Test]
    public function capturesStatusHeadersAndBodyOfAResponse(): void
    {
        $page = StoredResponse::fromResponse(new HtmlResponse('<p>Work</p>', 203, ['X-Thing' => 'a']), 42);

        static::assertEquals(
            new StoredResponse(
                203,
                ['X-Thing' => ['a'], 'content-type' => ['text/html; charset=utf-8']],
                '<p>Work</p>',
                42,
            ),
            $page,
        );
    }

    #[Test]
    public function leavesExcludedHeadersOutWhateverTheirCase(): void
    {
        $response = new HtmlResponse('x', 200, ['X-Page-Cache' => 'off', 'X-Kept' => 'yes']);

        static::assertArrayNotHasKey(
            'X-Page-Cache',
            StoredResponse::fromResponse($response, 1, ['x-page-cache'])->headers,
        );
    }

    #[Test]
    public function rebuildsAnEquivalentResponse(): void
    {
        $response = (new StoredResponse(301, ['Location' => ['/new']], 'moved', 1))->toResponse();

        static::assertSame(
            [301, '/new', 'moved'],
            [$response->getStatusCode(), $response->getHeaderLine('Location'), (string) $response->getBody()],
        );
    }
}
