# suncharts-sdk-php

PHP SDK for the [SunCharts](https://suncharts.app) public API: OpenEMR scheduling, charts and FHIR behind one
partner-friendly HTTP API. PSR-18 HTTP (bring Guzzle, Symfony HttpClient, ...), partner API keys or OAuth2 client
credentials, MIT licensed. Every operation and model is **generated from the public OpenAPI spec**
(`openapi.json`), so the SDK never drifts from the API.

```bash
composer require pindustries/suncharts-sdk-php:^26.10
# until it is on Packagist, add the VCS repository first:
# composer config repositories.suncharts vcs https://github.com/PIndustries/suncharts-sdk-php
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

## Generated operations

`PIndustries\SunCharts\Generated\Api\*Api` covers every operation in the spec with typed models
(`PIndustries\SunCharts\Generated\Model\*`). `Client::api()` returns one wired to the client's base URL,
credentials and user token, with OAuth refresh on a partner 401 and retries on 429/502/503/504:

```php
use PIndustries\SunCharts\Generated\Api\PartnerApi;
use PIndustries\SunCharts\Generated\Api\AppointmentsApi;

$me = $api->api(PartnerApi::class)->partnerMe();
echo $me->getPartner()->getName();
$today = $api->withUserToken($accessToken)->api(AppointmentsApi::class)->today(null, '2026-10-16');
```

Generated calls throw `PIndustries\SunCharts\Generated\ApiException` (`getCode()` = HTTP status,
`getResponseBody()`). The generated layer uses Guzzle (now a dependency); the hand-written methods keep using
any PSR-18 client.

`examples/sandbox_smoke.php` runs a token + read calls against the sandbox.

Errors throw `PIndustries\SunCharts\Exception\ApiException` with `status()`, `errorCode()` (the API's `error`
field) and `body()`. Secrets are marked `#[\SensitiveParameter]` and hidden from `var_dump`.

Hosts: production `https://api.suncharts.app`, sandbox `https://sandbox-api.suncharts.app`.

## Development

```bash
composer install && composer test && composer stan
scripts/generate.sh      # regenerate src/Generated from openapi.json (Java 11+); never edit it by hand
```

`openapi.json` is synced from the API automatically (a pull request is opened when the spec changes); CI fails
if `src/Generated` is not exactly what the generator produces.

MIT © P.Industries

## Versioning

Releases use the P.Industries format `YY.MM.DD.<build-of-day>` (UTC date), e.g. `26.10.16.1`; the git tag is exactly
that (no `v`). Composer uses the 4-part version as is; constrain with `^26.10` (any later release). Bump with `scripts/version.sh set $(scripts/version.sh next)`; merging a `VERSION` change to
`main` tags the release automatically (`.github/workflows/release.yml`).
