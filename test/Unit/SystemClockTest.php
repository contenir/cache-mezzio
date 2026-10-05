<?php

declare(strict_types=1);

namespace Contenir\Cache\Mezzio\Test\Unit;

use Contenir\Cache\Mezzio\SystemClock;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class SystemClockTest extends TestCase
{
    public function testTellsTheCurrentTime(): void
    {
        $before = new DateTimeImmutable();
        $now    = (new SystemClock())->now();
        $after  = new DateTimeImmutable();

        self::assertTrue($before <= $now && $now <= $after);
    }
}
