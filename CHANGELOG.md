# Changelog

## 0.2.0 — 2026-10-16
- New `PIndustries\SunCharts\Generated` namespace: every operation (`Api\*Api`) and model (`Model\*`) of the
  SunCharts OpenAPI spec (`openapi.json`, API 1.1.0), generated with openapi-generator 7.26.0
  (`scripts/generate.sh`; CI fails if stale). Spec changes arrive as automated pull requests.
- `Client::api(PartnerApi::class)`: generated API wired to the client's credentials and user token, with OAuth
  refresh on partner 401 and retries (429 with `Retry-After`; 502/503/504 on idempotent requests).
- Dependencies: `guzzlehttp/guzzle ^7.3` and `guzzlehttp/psr7 ^2` are now required (used by the generated
  layer); `ext-json`, `ext-mbstring`.
- Default User-Agent `suncharts-sdk-php/0.2`.
- No changes to existing hand-written methods.

## 0.1.0
- PSR-18 client, API key + OAuth2 client credentials, pass-through, partner endpoints.
