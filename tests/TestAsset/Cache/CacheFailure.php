<?php

declare(strict_types=1);

namespace Contenir\Cache\Mezzio\Tests\TestAsset\Cache;

use Psr\SimpleCache\CacheException;
use RuntimeException;

final class CacheFailure extends RuntimeException implements CacheException {}
