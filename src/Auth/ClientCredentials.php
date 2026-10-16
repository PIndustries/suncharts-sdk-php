<?php

declare(strict_types=1);

namespace PIndustries\SunCharts\Auth;

use PIndustries\SunCharts\Exception\ApiException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * OAuth2 client credentials grant (RFC 6749 §4.4). The access token is cached in memory (or in a TokenStore you
 * provide) and refreshed shortly before it expires or after the API rejects it.
 */
final class ClientCredentials implements Credentials
{
    private ?string $token = null;

    private int $expiresAt = 0;

    /** @var (callable(): int)|null */
    private $clock;

    public function __construct(
        private readonly string $tokenUrl,
        private readonly string $clientId,
        #[\SensitiveParameter] private readonly string $clientSecret,
        private readonly ClientInterface $http,
        private readonly RequestFactoryInterface $requests,
        private readonly StreamFactoryInterface $streams,
        private readonly string $scope = '',
        private readonly string $header = 'X-Partner-Authorization',
        private readonly ?TokenStore $store = null,
        ?callable $clock = null,
    ) {
        $this->clock = $clock;
    }

    public function authenticate(RequestInterface $request): RequestInterface
    {
        return $request->withHeader($this->header, 'Bearer '.$this->accessToken());
    }

    /** Current access token, fetching a new one when missing or within 30 s of expiry. */
    public function accessToken(): string
    {
        $now = $this->now();
        if ($this->token === null && $this->store !== null) {
            $cached = $this->store->get($this->cacheKey());
            if ($cached !== null) {
                [$this->token, $this->expiresAt] = $cached;
            }
        }
        if ($this->token !== null && $now < $this->expiresAt - 30) {
            return $this->token;
        }
        $body = ['grant_type' => 'client_credentials'];
        if ($this->scope !== '') {
            $body['scope'] = $this->scope;
        }
        $req = $this->requests->createRequest('POST', $this->tokenUrl)
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withHeader('Accept', 'application/json')
            ->withHeader('Authorization', 'Basic '.base64_encode(rawurlencode($this->clientId).':'.rawurlencode($this->clientSecret)))
            ->withBody($this->streams->createStream(http_build_query($body)));
        $resp = $this->http->sendRequest($req);
        $data = json_decode((string) $resp->getBody(), true);
        if ($resp->getStatusCode() >= 400 || ! is_array($data) || ! is_string($data['access_token'] ?? null)) {
            throw ApiException::fromResponse($resp->getStatusCode(), is_array($data) ? $data : [], 'token request failed');
        }
        $this->token = $data['access_token'];
        $this->expiresAt = $now + (int) ($data['expires_in'] ?? 3600);
        $this->store?->put($this->cacheKey(), $this->token, $this->expiresAt);

        return $this->token;
    }

    /** Drop the cached token (e.g. after a 401). */
    public function forget(): void
    {
        $this->token = null;
        $this->expiresAt = 0;
        $this->store?->forget($this->cacheKey());
    }

    private function cacheKey(): string
    {
        return 'suncharts_token_'.sha1($this->tokenUrl.'|'.$this->clientId.'|'.$this->scope);
    }

    private function now(): int
    {
        return $this->clock !== null ? ($this->clock)() : time();
    }

    public function __debugInfo(): array
    {
        return ['tokenUrl' => $this->tokenUrl, 'clientId' => $this->clientId, 'clientSecret' => '***'];
    }
}
