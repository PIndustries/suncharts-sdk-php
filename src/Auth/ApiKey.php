<?php

declare(strict_types=1);

namespace PIndustries\SunCharts\Auth;

use Psr\Http\Message\RequestInterface;

/**
 * Static partner API key. Sent in a dedicated header (default `X-Partner-Authorization: Bearer <key>`) so the
 * standard `Authorization` header stays free for an end-user session token.
 */
final class ApiKey implements Credentials
{
    public function __construct(
        #[\SensitiveParameter] private readonly string $key,
        private readonly string $header = 'X-Partner-Authorization',
        private readonly string $scheme = 'Bearer',
    ) {
    }

    public function authenticate(RequestInterface $request): RequestInterface
    {
        if ($this->key === '') {
            return $request;
        }

        return $request->withHeader($this->header, trim($this->scheme.' '.$this->key));
    }

    public function __debugInfo(): array
    {
        return ['header' => $this->header, 'key' => '***'];
    }
}
