# suncharts-sdk-php

PHP SDK for the [SunCharts](https://suncharts.app) public API: OpenEMR scheduling, charts and FHIR behind one
partner-friendly HTTP API. PSR-18 HTTP (bring Guzzle, Symfony HttpClient, ...), partner API keys or OAuth2 client
credentials, MIT licensed.

```bash
composer require pindustries/suncharts-sdk-php guzzlehttp/guzzle
```

```php
use PIndustries\SunCharts\Client;
use PIndustries\SunCharts\Auth\ApiKey;

// Partner API key (sent as `X-Partner-Authorization: Bearer <key>`; the header name is configurable)
$api = Client::create(Client::SANDBOX, new ApiKey(getenv('PARTNER_API_KEY')));

// ...or OAuth2 client credentials (token cached and refreshed automatically)
$api = Client::clientCredentials(Client::SANDBOX, getenv('PARTNER_CLIENT_ID'), getenv('PARTNER_CLIENT_SECRET'));

$api->magicStart('doc@clinic.example');                 // email sign-in code / link
$tokens = $api->magicVerify(['email' => 'doc@clinic.example', 'code' => '123456']);
$today = $api->withUserToken($tokens['access_token'])->today(['tz' => 'America/Chicago']);

$api->listTenants();                                    // partner endpoints
$api->inviteUser($tenantId, 'new@clinic.example');

// Raw pass-through for a backend-for-frontend (header allowlist in, safe headers out)
$resp = $api->forward('GET', '/api/v1/inbox?limit=20', $incomingHeaders, $body);
```

Errors throw `PIndustries\SunCharts\Exception\ApiException` with `status()`, `errorCode()` (the API's `error`
field) and `body()`. Secrets are marked `#[\SensitiveParameter]` and hidden from `var_dump`.

Hosts: production `https://api.suncharts.app`, sandbox `https://sandbox-api.suncharts.app`.

## Development

```bash
composer install && composer test && composer stan
```

MIT © P.Industries
