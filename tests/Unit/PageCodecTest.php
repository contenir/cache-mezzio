<?php

declare(strict_types=1);

namespace Contenir\Cache\Mezzio\Tests\Unit;

use Contenir\Cache\Mezzio\PageCodec;
use Contenir\Cache\Mezzio\StoredResponse;
use JsonException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function json_encode;

#[Group('unit')]
#[Group('codec')]
final class PageCodecTest extends TestCase
{
    /**
     * @return array<string, array{mixed}>
     */
    public static function foreignValueProvider(): array
    {
        return [
            'nothing stored'      => [null],
            'not a string'        => [['status' => 200]],
            'not json'            => ['<p>raw html</p>'],
            'json scalar'         => ['42'],
            'no status'           => [json_encode(['body' => 'x', 'storedAt' => 1])],
            'no stored time'      => [json_encode(['status' => 200, 'body' => 'x'])],
            'no body'             => [json_encode(['status' => 200, 'storedAt' => 1])],
            'invalid base64 body' => [json_encode(['status' => 200, 'storedAt' => 1, 'body64' => '***'])],
        ];
    }

    /**
     * @return array<string, array{StoredResponse}>
     */
    public static function pageProvider(): array
    {
        return [
            'html page'       => [new StoredResponse(
                200,
                ['Content-Type' => ['text/html']],
                '<p>Ünïcode</p>',
                1_700_000_000,
            )],
            'redirect'        => [new StoredResponse(301, ['Location' => ['https://www.example.test/']], '', 1)],
            'binary body'     => [new StoredResponse(
                200,
                ['Content-Type' => ['image/png']],
                "\x89PNG\r\n\x1a\n\xff\xfe",
                2,
            )],
            'repeated header' => [new StoredResponse(200, ['Link' => ['</a.css>', '</b.js>']], 'x', 3)],
        ];
    }

    public function testAHeaderThatIsNotUtf8CannotBeEncoded(): void
    {
        $this->expectException(JsonException::class);
        $this->expectExceptionMessage('Malformed UTF-8');

        PageCodec::encode(new StoredResponse(200, ['X-Name' => ["\xff"]], 'x', 1));
    }

    #[DataProvider('pageProvider')]
    public function testAnEncodedPageDecodesToTheSamePage(StoredResponse $page): void
    {
        self::assertEquals($page, PageCodec::decode(PageCodec::encode($page)));
    }

    #[DataProvider('foreignValueProvider')]
    public function testAnEntryItDidNotWriteDecodesToNothing(mixed $value): void
    {
        self::assertNull(PageCodec::decode($value));
    }

    public function testAnEntryWithoutHeadersDecodesToAPageWithoutHeaders(): void
    {
        self::assertSame(
            [],
            PageCodec::decode(json_encode(['status' => 200, 'storedAt' => 1, 'body' => 'x']))?->headers,
        );
    }

    public function testAPlainTextBodyIsStoredReadably(): void
    {
        self::assertStringContainsString(
            '"body":"<p>Work</p>"',
            PageCodec::encode(new StoredResponse(200, [], '<p>Work</p>', 1)),
        );
    }

    public function testMalformedHeadersInAnEntryAreDropped(): void
    {
        $value = json_encode([
            'status'   => 200,
            'storedAt' => 1,
            'body'     => 'x',
            'headers'  => [
                'X-Ok'    => ['yes'],
                'X-Empty' => [],
                'X-Bad'   => 'scalar',
                '0'       => ['numeric name'],
                ''        => ['no name'],
            ],
        ]);

        self::assertSame(['X-Ok' => ['yes']], PageCodec::decode($value)?->headers);
    }
}
