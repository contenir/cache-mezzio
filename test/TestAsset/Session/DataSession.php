<?php

declare(strict_types=1);

namespace Contenir\Cache\Mezzio\Test\TestAsset\Session;

/**
 * A session object exposing only toArray().
 */
final readonly class DataSession
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        private array $data = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
