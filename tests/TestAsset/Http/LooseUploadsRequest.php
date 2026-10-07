<?php

declare(strict_types=1);

namespace Contenir\PageCache\Mezzio\Tests\TestAsset\Http;

use Laminas\Diactoros\ServerRequest;
use Override;

/**
 * A request from a PSR-7 implementation that does not validate its uploaded
 * files tree, so it can hold entries that are neither files nor arrays.
 */
final class LooseUploadsRequest extends ServerRequest
{
    /**
     * @param array<array-key, mixed> $looseUploads
     */
    public function __construct(
        private readonly array $looseUploads,
    ) {
        parent::__construct(
            uri: 'https://www.example.test/work',
            method: 'GET',
        );
    }

    /**
     * @return array<array-key, mixed>
     */
    #[Override]
    public function getUploadedFiles(): array
    {
        return $this->looseUploads;
    }
}
