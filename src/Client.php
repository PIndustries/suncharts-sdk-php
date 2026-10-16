<?php

declare(strict_types=1);

namespace PIndustries\SunCharts;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use PIndustries\SunCharts\Auth\ClientCredentials;
use PIndustries\SunCharts\Auth\Credentials;
use PIndustries\SunCharts\Auth\NoCredentials;
use PIndustries\SunCharts\Exception\ApiException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * SunCharts API client.
 *
 *   $c = Client::create('https://sandbox-api.suncharts.app', new ApiKey($key));
 *   $tokens = $c->magicStart('doc@clinic.example');
 *   $today = $c->withUserToken($accessToken)->today();
 *
 * Every operation in the OpenAPI spec is also available, typed, through the generated API classes:
 *
 *   $tenants = $c->api(PartnerApi::class)->partnerListTenants();   // PIndustries\SunCharts\Generated\Api\PartnerApi
 */
final class Client
{
    public const PRODUCTION = 'https://api.suncharts.app';

    public const SANDBOX = 'https://sandbox-api.suncharts.app';

    /** Request headers forwarded by `forward()`; anything else (cookies, hop-by-hop, client IPs) is dropped. */
    public const FORWARD_HEADERS = ['authorization', 'content-type', 'accept', 'accept-language', 'if-none-match', 'if-modified-since', 'idempotency-key'];

    private string $baseUrl;

    public function __construct(
        string $baseUrl,
        private readonly Credentials $credentials,
        private readonly ClientInterface $http,
        private readonly RequestFactoryInterface $requests,
        private readonly StreamFactoryInterface $streams,
        private readonly string $userAgent = 'suncharts-sdk-php/0.2',
        private readonly ?string $userToken = null,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /** Build with PSR-18/17 discovery (Guzzle, Symfony HttpClient, ...). */
    public static function create(string $baseUrl, ?Credentials $credentials = null, ?ClientInterface $http = null, string $userAgent = 'suncharts-sdk-php/0.2'): self
    {
        return new self(
            $baseUrl,
            $credentials ?? new NoCredentials(),
            $http ?? Psr18ClientDiscovery::find(),
            Psr17FactoryDiscovery::findRequestFactory(),
            Psr17FactoryDiscovery::findStreamFactory(),
            $userAgent,
        );
    }

    /** OAuth2 client-credentials helper sharing this client's HTTP stack. */
    public static function clientCredentials(string $baseUrl, string $clientId, #[\SensitiveParameter] string $clientSecret, ?ClientInterface $http = null, string $tokenPath = '/oauth/token', string $scope = ''): self
    {
        $http ??= Psr18ClientDiscovery::find();
        $rf = Psr17FactoryDiscovery::findRequestFactory();
        $sf = Psr17FactoryDiscovery::findStreamFactory();
        $cc = new ClientCredentials(rtrim($baseUrl, '/').$tokenPath, $clientId, $clientSecret, $http, $rf, $sf, $scope);

        return new self($baseUrl, $cc, $http, $rf, $sf);
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    /** Same client acting for a signed-in user (Authorization: Bearer <access token>). */
    public function withUserToken(string $accessToken): self
    {
        return new self($this->baseUrl, $this->credentials, $this->http, $this->requests, $this->streams, $this->userAgent, $accessToken);
    }

    // ---- auth -------------------------------------------------------------------------------------------------

    /** @return array<string, mixed> tokens, or `{clinic_selection_required, selection_token, clinics}` */
    public function login(string $email, #[\SensitiveParameter] string $password, ?string $totp = null, ?string $deviceName = null): array
    {
        return $this->json('POST', '/api/v1/auth/login', array_filter(['email' => $email, 'password' => $password, 'totp' => $totp, 'device_name' => $deviceName], fn ($v) => $v !== null));
    }

    /** @return array<string, mixed> */
    public function selectClinic(string $selectionToken, string $tenantId): array
    {
        return $this->json('POST', '/api/v1/auth/login/select', ['selection_token' => $selectionToken, 'tenant_id' => $tenantId]);
    }

    /** @return array<string, mixed> */
    public function magicStart(string $email): array
    {
        return $this->json('POST', '/api/v1/auth/magic/start', ['email' => $email]);
    }

    /**
     * @param array<string, string> $proof `['code' => ..., 'email' => ...]` or `['token' => ...]`
     * @return array<string, mixed>
     */
    public function magicVerify(array $proof): array
    {
        return $this->json('POST', '/api/v1/auth/magic/verify', $proof);
    }

    /** @return array<string, mixed> */
    public function refresh(#[\SensitiveParameter] string $refreshToken): array
    {
        return $this->json('POST', '/api/v1/auth/refresh', ['refresh_token' => $refreshToken]);
    }

    public function logout(#[\SensitiveParameter] string $refreshToken): void
    {
        $this->json('POST', '/api/v1/auth/logout', ['refresh_token' => $refreshToken]);
    }

    // ---- user (requires withUserToken) --------------------------------------------------------------------------

    /** @return array<string, mixed> */
    public function me(): array
    {
        return $this->json('GET', '/api/v1/me');
    }

    /**
     * @param array<string, string> $query e.g. ['date' => '2026-10-16', 'tz' => 'America/Chicago']
     * @return array<string, mixed>
     */
    public function today(array $query = []): array
    {
        return $this->json('GET', '/api/v1/today', null, $query);
    }

    /** @return array<string, mixed> */
    public function connections(): array
    {
        return $this->json('GET', '/api/v1/connections');
    }

    /**
     * @param array<string, string> $query
     * @return array<string, mixed> FHIR Bundle
     */
    public function fhir(string $connectionId, string $path, array $query = []): array
    {
        return $this->json('GET', '/api/v1/connections/'.rawurlencode($connectionId).'/fhir/'.ltrim($path, '/'), null, $query);
    }

    // ---- partner (partner credentials) ----------------------------------------------------------------------------

    /** @return list<array<string, mixed>> */
    public function listTenants(): array
    {
        $r = $this->json('GET', '/api/v1/partner/tenants');

        return array_values($r['tenants'] ?? $r['items'] ?? (array_is_list($r) ? $r : []));
    }

    /**
     * @param array<string, mixed> $tenant
     * @return array<string, mixed>
     */
    public function createTenant(array $tenant): array
    {
        return $this->json('POST', '/api/v1/partner/tenants', $tenant);
    }

    /** @return list<array<string, mixed>> */
    public function tenantUsers(string $tenantId): array
    {
        $r = $this->json('GET', '/api/v1/partner/tenants/'.rawurlencode($tenantId).'/users');

        return array_values($r['users'] ?? $r['items'] ?? (array_is_list($r) ? $r : []));
    }

    /** @return array<string, mixed> may include a `delivery` block when the partner sends the email itself */
    public function inviteUser(string $tenantId, string $email, string $role = 'clinician'): array
    {
        return $this->json('POST', '/api/v1/partner/tenants/'.rawurlencode($tenantId).'/invites', ['email' => $email, 'role' => $role]);
    }

    /** @return array<string, mixed> */
    public function deactivateUser(string $tenantId, string $userId): array
    {
        return $this->json('POST', '/api/v1/partner/tenants/'.rawurlencode($tenantId).'/users/'.rawurlencode($userId).'/deactivate');
    }

    /** @return array<string, mixed> */
    public function health(): array
    {
        return $this->json('GET', '/health');
    }

    // ---- generated operations ------------------------------------------------------------------------------------

    /**
     * A generated API class (`PIndustries\SunCharts\Generated\Api\*Api`) wired to this client's base URL, partner
     * credentials and user token, with a fresh OAuth token after a partner 401 and retries on 429 (honouring
     * `Retry-After`) and on 502/503/504 for idempotent requests.
     *
     * @template T of object
     * @param class-string<T> $class
     * @param (callable(\Psr\Http\Message\RequestInterface, array<string, mixed>): \GuzzleHttp\Promise\PromiseInterface)|null $handler Guzzle handler (tests: `MockHandler`)
     * @return T
     */
    public function api(string $class, int $maxRetries = 3, ?callable $handler = null): object
    {
        $stack = \GuzzleHttp\HandlerStack::create($handler);
        $stack->push(\GuzzleHttp\Middleware::mapRequest(fn (\Psr\Http\Message\RequestInterface $r) => $this->prepare($r)));
        $stack->push(\GuzzleHttp\Middleware::retry(
            function (int $n, \Psr\Http\Message\RequestInterface $req, ?\Psr\Http\Message\ResponseInterface $resp, mixed $e = null) use ($maxRetries): bool {
                if ($resp === null && $e instanceof \GuzzleHttp\Exception\RequestException) {
                    $resp = $e->getResponse();
                }
                if ($resp === null || $n >= $maxRetries) {
                    return false;
                }
                $s = $resp->getStatusCode();
                if ($s === 401 && $n === 0 && $this->credentials instanceof ClientCredentials
                    && str_contains(strtolower($resp->getHeaderLine('www-authenticate')), 'partner')) {
                    $this->credentials->forget();

                    return true;
                }

                return $s === 429 || (in_array($s, [502, 503, 504], true) && in_array($req->getMethod(), ['GET', 'HEAD', 'PUT', 'DELETE', 'OPTIONS'], true));
            },
            function (int $n, ?\Psr\Http\Message\ResponseInterface $resp): int {
                $ra = $resp?->getHeaderLine('retry-after') ?? '';
                if ($resp?->getStatusCode() === 401) {
                    return 0;
                }

                return is_numeric($ra) ? (int) min(60000, (float) $ra * 1000) : (int) (500 * 2 ** ($n - 1) * (0.5 + mt_rand() / mt_getrandmax() / 2));
            },
        ));
        $config = (new \PIndustries\SunCharts\Generated\Configuration())->setHost($this->baseUrl)->setUserAgent($this->userAgent);

        /** @var T */
        return new $class(new \GuzzleHttp\Client(['handler' => $stack, 'http_errors' => true]), $config);
    }

    /** Partner credentials (re-evaluated on every attempt, so a refreshed token is picked up) + user token. */
    private function prepare(\Psr\Http\Message\RequestInterface $req): \Psr\Http\Message\RequestInterface
    {
        if ($this->userToken !== null) {
            $req = $req->withHeader('Authorization', 'Bearer '.$this->userToken);
        }

        return $this->credentials->authenticate($req);
    }

    // ---- low level --------------------------------------------------------------------------------------------

    /**
     * JSON request; throws ApiException on non-2xx.
     *
     * @param array<string, mixed>|null $body
     * @param array<string, string|int> $query
     * @return array<string, mixed>
     */
    public function json(string $method, string $path, ?array $body = null, array $query = []): array
    {
        $pq = $path.($query ? '?'.http_build_query($query) : '');
        $headers = ['accept' => 'application/json'];
        if ($body !== null) {
            $headers['content-type'] = 'application/json';
        }
        $r = $this->send($method, $pq, $headers, $body === null ? '' : (string) json_encode($body, JSON_UNESCAPED_SLASHES));
        $data = $r->json() ?? [];
        if ($r->status >= 400) {
            throw ApiException::fromResponse($r->status, $data);
        }

        return $data;
    }

    /**
     * Raw pass-through: forwards method, path+query, an allowlist of headers and the body, adds partner credentials,
     * and returns the response as-is (no exception on 4xx/5xx).
     *
     * @param array<string, string|list<string>> $headers
     */
    public function forward(string $method, string $pathAndQuery, array $headers, string $body): Response
    {
        $keep = [];
        foreach ($headers as $k => $v) {
            $k = strtolower((string) $k);
            if (in_array($k, self::FORWARD_HEADERS, true)) {
                $keep[$k] = is_array($v) ? implode(', ', $v) : $v;
            }
        }

        return $this->send($method, $pathAndQuery, $keep, $body);
    }

    /** @param array<string, string> $headers */
    private function send(string $method, string $pathAndQuery, array $headers, string $body, bool $retried = false): Response
    {
        $req = $this->requests->createRequest(strtoupper($method), $this->baseUrl.'/'.ltrim($pathAndQuery, '/'))
            ->withHeader('User-Agent', $this->userAgent);
        foreach ($headers as $k => $v) {
            $req = $req->withHeader($k, $v);
        }
        if ($this->userToken !== null && ! isset($headers['authorization'])) {
            $req = $req->withHeader('Authorization', 'Bearer '.$this->userToken);
        }
        if ($body !== '') {
            $req = $req->withBody($this->streams->createStream($body));
        }
        $req = $this->credentials->authenticate($req);
        $resp = $this->http->sendRequest($req);
        if ($resp->getStatusCode() === 401 && ! $retried && $this->credentials instanceof ClientCredentials
            && str_contains(strtolower($resp->getHeaderLine('www-authenticate')), 'partner')) {
            $this->credentials->forget();

            return $this->send($method, $pathAndQuery, $headers, $body, true);
        }
        $h = [];
        foreach ($resp->getHeaders() as $k => $v) {
            $h[strtolower((string) $k)] = implode(', ', $v);
        }

        return new Response($resp->getStatusCode(), $h, (string) $resp->getBody());
    }
}
