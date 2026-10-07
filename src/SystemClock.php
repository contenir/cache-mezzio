<?php

declare(strict_types=1);

namespace Contenir\PageCache\Mezzio;

use DateTimeImmutable;
use Override;
use Psr\Clock\ClockInterface;

/**
 * The wall clock, used to stamp stored pages and compute the `Age` of a hit.
 *
 * @api
 */
final readonly class SystemClock implements ClockInterface
{
    #[Override]
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}
