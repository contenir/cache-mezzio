<?php

declare(strict_types=1);

namespace Contenir\Cache\Mezzio\Test\Trait;

use Laminas\Diactoros\ServerRequest;
use Psr\Http\Message\ServerRequestInterface;

trait MakesServerRequest
{
    /**
     * @param array<string, mixed> $query
     * @param array<string, string> $cookies
     * @param array<string, mixed> $attributes
     */
    private function request(
        string $method = 'GET',
        array $query = [],
        array $cookies = [],
        array $attributes = [],
    ): ServerRequestInterface {
        $request = new ServerRequest(
            uri: 'https://www.example.test/work',
            method: $method,
            cookieParams: $cookies,
            queryParams: $query,
        );

        foreach ($attributes as $name => $value) {
            $request = $request->withAttribute($name, $value);
        }

        return $request;
    }
}
