<?php

declare(strict_types=1);

namespace Contenir\Cache\Mezzio\Tests\TestAsset\Session;

use function array_key_exists;

/**
 * A session object shaped like mezzio-session's SessionInterface: toArray()
 * and has().
 */
final readonly class FakeSession
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        private array $data = [],
    ) {}

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->data);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
