<?php

declare(strict_types=1);

namespace Contenir\PageCache\Mezzio\Tests\TestAsset\Mutator;

use Contenir\PageCache\Mezzio\CacheResult;
use Contenir\PageCache\Mezzio\ResponseMutatorInterface;
use Laminas\Diactoros\StreamFactory;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Appends a marker naming the cache result to the body, the way a build-stamp
 * mutator would.
 */
final readonly class AppendingMutator implements ResponseMutatorInterface
{
    public function __construct(
        private string $label = 'stamp',
    ) {}

    #[Override]
    public function mutate(
        ResponseInterface $response,
        ServerRequestInterface $request,
        CacheResult $result,
    ): ResponseInterface {
        $body = (string) $response->getBody() . "<!-- {$this->label}:{$result->value} -->";

        return $response->withBody((new StreamFactory())->createStream($body));
    }
}
