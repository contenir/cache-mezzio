# Extending

The extension points: opting one response out, mutating every response after storage, and the other public types.

## Opting a response out

A handler or middleware can stop one response from being stored, the PSR-15
counterpart of the MVC adapter's `pagecache.disable` event:

```php
use Contenir\PageCache\Mezzio\PageCacheMiddleware;

return PageCacheMiddleware::veto($response);           // adds X-Page-Cache: off
return $response->withHeader('Cache-Control', 'no-store');
```

The middleware removes the `X-Page-Cache` header before the response is sent.


## Mutators

A mutator tweaks every response the middleware returns, after storage, so
per-request values never end up in the cache. It runs on hits, misses and
bypassed responses alike, so a placeholder never leaks when caching is off:

```php
use Contenir\PageCache\Mezzio\CacheResult;
use Contenir\PageCache\Mezzio\ResponseMutatorInterface;
use Laminas\Diactoros\StreamFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class PlaceholderMutator implements ResponseMutatorInterface
{
    public function mutate(
        ResponseInterface $response,
        ServerRequestInterface $request,
        CacheResult $result,
    ): ResponseInterface {
        if (! str_contains($response->getHeaderLine('Content-Type'), 'text/html')) {
            return $response;
        }

        $body = strtr((string) $response->getBody(), ['{{HOST}}' => $request->getUri()->getHost()]);

        return $response->withBody((new StreamFactory())->createStream($body));
    }
}
```

```php
'dependencies' => ['invokables' => [PlaceholderMutator::class => PlaceholderMutator::class]],
'pagecache'    => ['mutators' => [PlaceholderMutator::class]],
```

`CacheResult` is `Hit`, `Miss` or `Bypass`.

[Back to the README](../README.md)
