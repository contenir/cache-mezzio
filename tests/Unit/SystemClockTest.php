<?php

declare(strict_types=1);

namespace Contenir\PageCache\Mezzio\Tests\Unit;

use Contenir\PageCache\Mezzio\SystemClock;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class SystemClockTest extends TestCase
{
    #[Test]
    public function tellsTheCurrentTime(): void
    {
        $before = new DateTimeImmutable();
        $now    = (new SystemClock())->now();
        $after  = new DateTimeImmutable();

        static::assertTrue($before <= $now && $now <= $after);
    }
}
