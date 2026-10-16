<?php
/**
 * AuthApi
 * PHP version 8.1
 *
 * @category Class
 * @package  PIndustries\SunCharts\Generated
 * @author   OpenAPI Generator team
 * @link     https://openapi-generator.tech
 */

/**
 * SunCharts API
 *
 * Build your own backend on SunCharts. Everything you need is here: this reference, a free sandbox and public SDKs. The API is plain HTTPS + JSON, so any language works; the SDKs are optional conveniences. Machine-readable spec: [`/openapi.json`](/openapi.json) (also `/api/v1/openapi.yaml`).  # Quickstart  1. **Get sandbox credentials** — open the partner console at    [`https://sandbox-api.suncharts.app/partners/`](https://sandbox-api.suncharts.app/partners/), create a sandbox    account (email link, no password), then **Create app**. Copy the `client_id` and `client_secret` (shown once). 2. **Get an access token** (OAuth2 client credentials):  ```bash curl -s https://sandbox-api.suncharts.app/oauth/token \\   -u \"$CLIENT_ID:$CLIENT_SECRET\" \\   -d grant_type=client_credentials -d scope=\"partner.read tenants.read\" # {\"access_token\":\"pt_…\",\"token_type\":\"Bearer\",\"expires_in\":3600,\"scope\":\"partner.read tenants.read\"} ```  3. **Call the API**:  ```bash curl -s https://sandbox-api.suncharts.app/api/v1/partner/me -H \"Authorization: Bearer $ACCESS_TOKEN\" ```  4. **Or use an SDK** (see *SDKs*; Rust shown — PHP, Python and TypeScript work the same way):  ```toml [dependencies] suncharts-sdk = { git = \"https://github.com/PIndustries/suncharts-sdk-rust\", tag = \"v0.4.0\" } ```  ```rust let client = suncharts_sdk::Client::builder(suncharts_sdk::SANDBOX_BASE_URL)     .client_credentials(std::env::var(\"CLIENT_ID\")?, std::env::var(\"CLIENT_SECRET\")?)     .build()?; let me = client.partner_me().await?; // token fetched, cached and refreshed for you ```  # Environments  | Environment | Base URL | Data | |---|---|---| | Sandbox | `https://sandbox-api.suncharts.app` | Simulated EHR data; reset nightly 03:15 America/Chicago (partner accounts, apps and keys are kept) | | Production | `https://api.suncharts.app` | Real clinics |  Sandbox and production are separate systems: credentials from one never work on the other. Sandbox keys start with `sk_test_`, production keys with `sk_live_`. Sandbox apps cannot hold `clinical.write`.  **Sandbox test data.** Every sandbox partner gets a tenant `<your-partner-slug>-test` (\"Sandbox Test Clinic\") with passwordless users `test.owner@example.test` (owner) and `test.clinician@example.test` (clinician); it shows up in `GET /api/v1/partner/tenants` and is re-created after the nightly reset. Sandbox apps start with `email_delivery: partner`, so relayed `auth/magic/_*`, signup and invite calls return the `delivery` secret instead of sending mail, and passkey ceremonies use your app's `rp_id`/`auth_origin` exactly as in production. `sandbox-api.p.industries` and `htpi.p.industries` are legacy aliases; use the hosts above.  # Authentication  Two ways to authenticate your **server** (never ship either to a browser or mobile app):  ## OAuth2 client credentials (recommended)  `POST /oauth/token` per [RFC 6749 §4.4](https://www.rfc-editor.org/rfc/rfc6749#section-4.4). Send client authentication as HTTP Basic (`client_id:client_secret`) **or** as `client_id` / `client_secret` body fields; the body may be `application/x-www-form-urlencoded` or JSON. Optional `scope` narrows the token to a subset of your app's scopes. Tokens (`pt_…`) last **1 hour**; request a new one when `expires_in` runs out (the SDK does this). Errors follow RFC 6749 §5.2: `invalid_request`, `invalid_client` (401), `unsupported_grant_type`, `invalid_scope`. `POST /api/v1/partner/token` remains as a legacy JSON alias.  ## API keys  Long-lived keys created in the partner console, optionally with an expiry and a subset of the app's scopes. Send as `Authorization: Bearer sk_…` or `X-API-Key: sk_…`.  **Where to send partner credentials.** Every partner endpoint also accepts them as `X-Partner-Authorization: Bearer <token or key>`; it is checked first, so one request can carry your credentials there and an end user's token in `Authorization`. A `401` caused by partner credentials carries `WWW-Authenticate: Bearer realm=\"partner\"` — fetch a new access token and retry once. Prefer OAuth2 for production; use keys where a token exchange is impractical.  ## Scopes  | Scope | Grants | |---|---| | `partner.read` | Your partner profile, branding and app identity; read auth settings | | `tenants.read` / `tenants.write` | List / create your clinics (tenants) | | `users.passthrough` | Relay end-user sign-in for your users (see *White-label auth relay*) | | `clinical.read` / `clinical.write` | Clinical endpoints (with the end user's token) | | `apps.manage` | Change your app's auth settings via the API |  ## Rotation and revocation  Everything is self-serve in the partner console (`/partners/` on each environment): - **Rotate client secret** — a new secret is issued; the previous one keeps working for a grace window   (default 24 h, up to 7 days) so you can roll deploys without downtime. - **Rotate API key** — issues a replacement with the same scopes; the old key expires after the grace window. - **Revoke** an app or key — takes effect immediately; outstanding access tokens stop working. - Every issue, rotation, revocation and denied token request is in the console's audit log.  ## End-user tokens  Clinical calls run as a clinic user. Your backend signs the user in through the auth endpoints (below) and uses the returned user access token (`userBearer`). Refresh with `POST /api/v1/auth/refresh`.  # White-label auth relay  Your backend can call the end-user auth endpoints (`/api/v1/auth/_*`, `/api/v1/signup*`, `/api/v1/invites/_*`, `/api/v1/tenant/invites*`) on behalf of your users so they only ever see your brand. Identify your app on those calls with:  ``` X-Partner-Authorization: Bearer <OAuth access token or API key>   (scope users.passthrough) X-End-User-IP: 203.0.113.7                                         (optional; per-user rate limits) ```  Without the header, the endpoints behave as for first-party apps. On relayed calls `device_nonce` is optional (secrets are bound to your app) and no CAPTCHA is required. With it, your app's **auth settings** apply:  | Setting | Effect | |---|---| | `auth_origin` | Your web origin (e.g. `https://app.example.com`): passkey origin and base for links | | `rp_id` | Passkey relying-party ID (your domain; must be the origin host or a parent of it) | | `android_cert_sha256[]` | Your Android signing-cert fingerprints; accepted as passkey origins (`android:apk-key-hash:…`) | | `apple_app_ids[]` | `TEAMID.bundle.id` of your iOS apps (for your associated-domains file) | | `email_link_base` | Link target for sign-in emails; `{token}` placeholder, else `#t=<token>` is appended | | `email_delivery` | `partner` or `platform` (below) |  Set them in the console or with `PATCH /api/v1/partner/auth-settings`. Passkey registration and sign-in then use your RP ID, origins and brand name.  **Email delivery.** With `partner` delivery we send nothing: `auth/magic/start`, `signup/start`, `signup` and `signup/resend` return a `delivery` object (`email`, `token`, `code`, `purpose`, `expires_in_mins`) on your partner-authenticated call only, and you deliver it however you like (`delivery` is `null` when there is nothing to send, e.g. an unknown email on sign-in). With `platform` delivery we send the email under your brand and from-address with links on your origin — available once your custom domain is verified; until then those calls return `409 partner_delivery_required`. Clinic invites for your tenants link to your `auth_origin`.  # Implementing a client (any language)  The contract is the HTTP described here; nothing depends on an SDK. A conforming client:  - **Transport** — HTTPS only, TLS 1.2+. Request and response bodies are UTF-8 JSON   (`Content-Type: application/json`) except `POST /oauth/token`, which also takes   `application/x-www-form-urlencoded`. Send a descriptive `User-Agent` (e.g. `acme-backend/1.4 php/8.3`). - **Data types** — timestamps are RFC 3339 in UTC (`2026-10-16T16:00:00Z`); dates are `YYYY-MM-DD`;   IDs are opaque strings (don't parse them, don't assume UUIDs or length). Absent and `null` mean the same.   **Ignore unknown fields and enum values** you don't recognise: v1 grows additively. - **Token handling** — cache the `access_token` per (client_id, scope) and reuse it until about 60 s before   `expires_in` elapses; then request a new one (there are no refresh tokens for client credentials). On a   `401` from an API call, fetch a fresh token **once** and retry; if it fails again, surface the error.   Never log `client_secret`, access tokens or API keys. - **Errors** — branch on HTTP status and the `error` code (see *Errors*), never on `message`. On `409`,   sub-codes are the text before the first `:` in `message` (e.g. `partner_delivery_required`). - **Retries** — retry `429` after `Retry-After` seconds, and `502/503/504` and network errors on idempotent   requests (`GET`, `PUT`, `DELETE`) with exponential backoff and jitter (e.g. 0.5 s, 1 s, 2 s; max 3 tries).   Don't blindly retry `POST` auth calls (they may send an email). - **Relay** — on end-user auth calls made for your users, add `X-Partner-Authorization: Bearer <token or key>`   and `X-End-User-IP`. Pass the end user's own `Authorization` header (if any) through unchanged. - **Timeouts** — 10 s connect, 30 s total is a good default; FHIR calls proxy to the clinic's EHR and can be   slower.  ### Minimal flow with plain HTTP  ```bash TOKEN=$(curl -s https://sandbox-api.suncharts.app/oauth/token -u \"$CLIENT_ID:$CLIENT_SECRET\" \\   -d grant_type=client_credentials | jq -r .access_token) curl -s https://sandbox-api.suncharts.app/api/v1/partner/tenants -H \"Authorization: Bearer $TOKEN\" # relayed sign-in for one of your users, with partner delivery: curl -s https://sandbox-api.suncharts.app/api/v1/auth/magic/start \\   -H \"X-Partner-Authorization: Bearer $TOKEN\" -H \"X-End-User-IP: 203.0.113.7\" \\   -H 'Content-Type: application/json' -d '{\"email\":\"dr.lee@example.com\"}' # {\"status\":\"sent\",\"message\":\"…\",\"resend_after\":60,\"expires_in\":600,\"code_length\":6, #  \"delivery\":{\"email\":\"dr.lee@example.com\",\"token\":\"…\",\"code\":\"123456\",\"purpose\":\"signin\",\"expires_in_mins\":10}} ```  # Errors  Errors are JSON with a stable, brand-neutral code:  ```json {\"error\": \"bad_request\", \"message\": \"human-readable detail\"} ```  | HTTP | `error` | Meaning | |---|---|---| | 400 | `bad_request` | Invalid input | | 401 | `unauthorized` | Missing, expired or revoked credentials (`invalid_client` on `/oauth/token`) | | 403 | `forbidden` | Credential lacks the scope / access | | 404 | `not_found` | No such resource (or not yours) | | 409 | `conflict` | State conflict (`message` starts with a sub-code such as `partner_delivery_required`) | | 428 | `openemr_link_required` / `openemr_relink_required` | The user must (re)link their EHR account | | 429 | `rate_limited` | Slow down; honour `Retry-After` | | 424 | `upstream_error` | The clinic's EHR failed or timed out; retry idempotent calls with backoff | | 5xx | `internal` | Our side; retry with backoff |  Codes are only ever added within v1.  # Rate limits  - Per app: `rate_limit_per_min` (default **600/min**) shared by its tokens and keys. - Token endpoint: 30 requests/min per source IP. - Auth endpoints: per end user (send `X-End-User-IP` when relaying) and per email. - Exceeding a limit returns `429` with `Retry-After` (seconds). Back off and retry.  # Versioning  - The major version is in the path (`/api/v1`). Within v1 changes are **additive only**: new endpoints,   new optional request fields, new response fields, new error codes. Ignore unknown fields. - Breaking changes ship as `/api/v2` with at least **6 months** of overlap. Deprecated operations are marked   `deprecated` here and answer with `Deprecation` and `Sunset` headers before removal. - The SDKs follow SemVer independently of the API version; minor versions add features, patch versions fix bugs.  # SDKs  Official SDKs are generated from this spec ([`/openapi.json`](/openapi.json)) on every API change, plus a thin hand-written layer for OAuth2 token caching/refresh, API keys, the relay header, retries and pagination.  | Language | Repository | Install | License | |---|---|---|---| | PHP 8.2+ | [`PIndustries/suncharts-sdk-php`](https://github.com/PIndustries/suncharts-sdk-php) | `composer require pindustries/suncharts-sdk-php` (VCS repo until published) | MIT | | Rust | [`PIndustries/suncharts-sdk-rust`](https://github.com/PIndustries/suncharts-sdk-rust) | `suncharts-sdk = { git = \"https://github.com/PIndustries/suncharts-sdk-rust\", tag = \"v0.4.0\" }` | MIT OR Apache-2.0 | | Python 3.9+ | [`PIndustries/suncharts-sdk-python`](https://github.com/PIndustries/suncharts-sdk-python) | `pip install \"git+https://github.com/PIndustries/suncharts-sdk-python@v0.1.0\"` | MIT | | TypeScript / Node 18+ | [`PIndustries/suncharts-sdk-typescript`](https://github.com/PIndustries/suncharts-sdk-typescript) | `npm install github:PIndustries/suncharts-sdk-typescript#v0.1.0` | MIT |  Any other language — generate a client from [`/openapi.json`](/openapi.json).
 *
 * The version of the OpenAPI document: 1.1.0
 * Generated by: https://openapi-generator.tech
 * Generator version: 7.26.0
 */

/**
 * NOTE: This class is auto generated by OpenAPI Generator (https://openapi-generator.tech).
 * https://openapi-generator.tech
 * Do not edit the class manually.
 */

namespace PIndustries\SunCharts\Generated\Api;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\MultipartStream;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use PIndustries\SunCharts\Generated\ApiException;
use PIndustries\SunCharts\Generated\Configuration;
use PIndustries\SunCharts\Generated\FormDataProcessor;
use PIndustries\SunCharts\Generated\HeaderSelector;
use PIndustries\SunCharts\Generated\ObjectSerializer;

/**
 * AuthApi Class Doc Comment
 *
 * @category Class
 * @package  PIndustries\SunCharts\Generated
 * @author   OpenAPI Generator team
 * @link     https://openapi-generator.tech
 */
class AuthApi
{
    /**
     * @var ClientInterface
     */
    protected $client;

    /**
     * @var Configuration
     */
    protected $config;

    /**
     * @var HeaderSelector
     */
    protected $headerSelector;

    /**
     * @var int Host index
     */
    protected $hostIndex;

    /** @var string[] $contentTypes **/
    public const contentTypes = [
        'login' => [
            'application/json',
        ],
        'loginSelectClinic' => [
            'application/json',
        ],
        'logout' => [
            'application/json',
        ],
        'magicLinkStart' => [
            'application/json',
        ],
        'magicLinkVerify' => [
            'application/json',
        ],
        'me' => [
            'application/json',
        ],
        'passkeyLoginFinish' => [
            'application/json',
        ],
        'passkeyLoginStart' => [
            'application/json',
        ],
        'passkeyRegisterFinish' => [
            'application/json',
        ],
        'passkeyRegisterStart' => [
            'application/json',
        ],
        'refresh' => [
            'application/json',
        ],
        'signupStart' => [
            'application/json',
        ],
    ];

    /**
     * @param ClientInterface $client
     * @param Configuration   $config
     * @param HeaderSelector  $selector
     * @param int             $hostIndex (Optional) host index to select the list of hosts if defined in the OpenAPI spec
     */
    public function __construct(
        ?ClientInterface $client = null,
        ?Configuration $config = null,
        ?HeaderSelector $selector = null,
        int $hostIndex = 0
    ) {
        $this->client = $client ?: new Client();
        $this->config = $config ?: Configuration::getDefaultConfiguration();
        $this->headerSelector = $selector ?: new HeaderSelector();
        $this->hostIndex = $hostIndex;
    }

    /**
     * Set the host index
     *
     * @param int $hostIndex Host index (required)
     */
    public function setHostIndex($hostIndex): void
    {
        $this->hostIndex = $hostIndex;
    }

    /**
     * Get the host index
     *
     * @return int Host index
     */
    public function getHostIndex()
    {
        return $this->hostIndex;
    }

    /**
     * @return Configuration
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Operation login
     *
     * End-user sign-in (passthrough from the partner backend)
     *
     * @param  \PIndustries\SunCharts\Generated\Model\LoginRequest $login_request login_request (required)
     * @param  string|null $x_partner_authorization White-label relay: &#x60;Bearer &lt;pt_… token | sk_… key&gt;&#x60; of the partner app (scope users.passthrough). Applies the app&#39;s auth settings. (optional)
     * @param  string|null $x_end_user_ip Relay only: the end user&#39;s IP for rate limiting. (optional)
     * @param  string $contentType The value for the Content-Type header. Check self::contentTypes['login'] to see the possible values for this operation
     *
     * @throws \PIndustries\SunCharts\Generated\ApiException on non-2xx response or if the response body is not in the expected format
     * @throws \InvalidArgumentException
     * @return \PIndustries\SunCharts\Generated\Model\Login200Response|\PIndustries\SunCharts\Generated\Model\Error|\PIndustries\SunCharts\Generated\Model\Error|\PIndustries\SunCharts\Generated\Model\Error
     */
    public function login($login_request, $x_partner_authorization = null, $x_end_user_ip = null, string $contentType = self::contentTypes['login'][0])
    {
        list($response) = $this->loginWithHttpInfo($login_request, $x_partner_authorization, $x_end_user_ip, $contentType);
        return $response;
    }

    /**
     * Operation loginWithHttpInfo
     *
     * End-user sign-in (passthrough from the partner backend)
     *
     * @param  \PIndustries\SunCharts\Generated\Model\LoginRequest $login_request (required)
     * @param  string|null $x_partner_authorization White-label relay: &#x60;Bearer &lt;pt_… token | sk_… key&gt;&#x60; of the partner app (scope users.passthrough). Applies the app&#39;s auth settings. (optional)
     * @param  string|null $x_end_user_ip Relay only: the end user&#39;s IP for rate limiting. (optional)
     * @param  string $contentType The value for the Content-Type header. Check self::contentTypes['login'] to see the possible values for this operation
     *
     * @throws \PIndustries\SunCharts\Generated\ApiException on non-2xx response or if the response body is not in the expected format
     * @throws \InvalidArgumentException
     * @return array of \PIndustries\SunCharts\Generated\Model\Login200Response|\PIndustries\SunCharts\Generated\Model\Error|\PIndustries\SunCharts\Generated\Model\Error|\PIndustries\SunCharts\Generated\Model\Error, HTTP status code, HTTP response headers (array of strings)
     */
    public function loginWithHttpInfo($login_request, $x_partner_authorization = null, $x_end_user_ip = null, string $contentType = self::contentTypes['login'][0])
    {
        $request = $this->loginRequest($login_request, $x_partner_authorization, $x_end_user_ip, $contentType);

        try {
            $options = $this->createHttpClientOption();
            try {
                $response = $this->client->send($request, $options);
            } catch (RequestException $e) {
                throw new ApiException(
                    "[{$e->getCode()}] {$e->getMessage()}",
                    (int) $e->getCode(),
                    $e->getResponse() ? $e->getResponse()->getHeaders() : null,
                    $e->getResponse() ? (string) $e->getResponse()->getBody() : null
                );
            } catch (ConnectException $e) {
                throw new ApiException(
                    "[{$e->getCode()}] {$e->getMessage()}",
                    (int) $e->getCode(),
                    null,
                    null
                );
            }

            $statusCode = $response->getStatusCode();


            switch($statusCode) {
                case 200:
                    return $this->handleResponseWithDataType(
                        '\PIndustries\SunCharts\Generated\Model\Login200Response',
                        $request,
                        $response,
                    );
                case 401:
                    return $this->handleResponseWithDataType(
                        '\PIndustries\SunCharts\Generated\Model\Error',
                        $request,
                        $response,
                    );
                case 429:
                    return $this->handleResponseWithDataType(
                        '\PIndustries\SunCharts\Generated\Model\Error',
                        $request,
                        $response,
                    );
                default:
                    return $this->handleResponseWithDataType(
                        '\PIndustries\SunCharts\Generated\Model\Error',
                        $request,
                        $response,
                    );
            }

            

            if ($statusCode < 200 || $statusCode > 299) {
                throw new ApiException(
                    sprintf(
                        '[%d] Error connecting to the API (%s)',
                        $statusCode,
                        (string) $request->getUri()
                    ),
                    $statusCode,
                    $response->getHeaders(),
                    (string) $response->getBody()
                );
            }

            return $this->handleResponseWithDataType(
                '\PIndustries\SunCharts\Generated\Model\Login200Response',
                $request,
                $response,
            );
        } catch (ApiException $e) {
            switch ($e->getCode()) {
                case 200:
                    $data = ObjectSerializer::deserialize(
                        $e->getResponseBody(),
                        '\PIndustries\SunCharts\Generated\Model\Login200Response',
                        $e->getResponseHeaders()
                    );
                    $e->setResponseObject($data);
                    throw $e;
                case 401:
                    $data = ObjectSerializer::deserialize(
                        $e->getResponseBody(),
                        '\PIndustries\SunCharts\Generated\Model\Error',
                        $e->getResponseHeaders()
                    );
                    $e->setResponseObject($data);
                    throw $e;
                case 429:
                    $data = ObjectSerializer::deserialize(
                        $e->getResponseBody(),
                        '\PIndustries\SunCharts\Generated\Model\Error',
                        $e->getResponseHeaders()
                    );
                    $e->setResponseObject($data);
                    throw $e;
                default:
                    $data = ObjectSerializer::deserialize(
                        $e->getResponseBody(),
                        '\PIndustries\SunCharts\Generated\Model\Error',
                        $e->getResponseHeaders()
                    );
                    $e->setResponseObject($data);
                    throw $e;
            }
        

            throw $e;
        }
    }

    /**
     * Operation loginAsync
     *
     * End-user sign-in (passthrough from the partner backend)
     *
     * @param  \PIndustries\SunCharts\Generated\Model\LoginRequest $login_request (required)
     * @param  string|null $x_partner_authorization White-label relay: &#x60;Bearer &lt;pt_… token | sk_… key&gt;&#x60; of the partner app (scope users.passthrough). Applies the app&#39;s auth settings. (optional)
     * @param  string|null $x_end_user_ip Relay only: the end user&#39;s IP for rate limiting. (optional)
     * @param  string $contentType The value for the Content-Type header. Check self::contentTypes['login'] to see the possible values for this operation
     *
     * @throws \InvalidArgumentException
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function loginAsync($login_request, $x_partner_authorization = null, $x_end_user_ip = null, string $contentType = self::contentTypes['login'][0])
    {
        return $this->loginAsyncWithHttpInfo($login_request, $x_partner_authorization, $x_end_user_ip, $contentType)
            ->then(
                function ($response) {
                    return $response[0];
                }
            );
    }

    /**
     * Operation loginAsyncWithHttpInfo
     *
     * End-user sign-in (passthrough from the partner backend)
     *
     * @param  \PIndustries\SunCharts\Generated\Model\LoginRequest $login_request (required)
     * @param  string|null $x_partner_authorization White-label relay: &#x60;Bearer &lt;pt_… token | sk_… key&gt;&#x60; of the partner app (scope users.passthrough). Applies the app&#39;s auth settings. (optional)
     * @param  string|null $x_end_user_ip Relay only: the end user&#39;s IP for rate limiting. (optional)
     * @param  string $contentType The value for the Content-Type header. Check self::contentTypes['login'] to see the possible values for this operation
     *
     * @throws \InvalidArgumentException
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function loginAsyncWithHttpInfo($login_request, $x_partner_authorization = null, $x_end_user_ip = null, string $contentType = self::contentTypes['login'][0])
    {
        $returnType = '\PIndustries\SunCharts\Generated\Model\Login200Response';
        $request = $this->loginRequest($login_request, $x_partner_authorization, $x_end_user_ip, $contentType);

        return $this->client
            ->sendAsync($request, $this->createHttpClientOption())
            ->then(
                function ($response) use ($returnType) {
                    if ($returnType === '\SplFileObject') {
                        $content = $response->getBody(); //stream goes to serializer
                    } else {
                        $content = (string) $response->getBody();
                        if ($returnType !== 'string') {
                            $content = json_decode($content);
                        }
                    }

                    return [
                        ObjectSerializer::deserialize($content, $returnType, []),
                        $response->getStatusCode(),
                        $response->getHeaders()
                    ];
                },
                function ($exception) {
                    $response = $exception->getResponse();
                    $statusCode = $response->getStatusCode();
                    throw new ApiException(
                        sprintf(
                            '[%d] Error connecting to the API (%s)',
                            $statusCode,
                            $exception->getRequest()->getUri()
                        ),
                        $statusCode,
                        $response->getHeaders(),
                        (string) $response->getBody()
                    );
                }
            );
    }

    /**
     * Create request for operation 'login'
     *
     * @param  \PIndustries\SunCharts\Generated\Model\LoginRequest $login_request (required)
     * @param  string|null $x_partner_authorization White-label relay: &#x60;Bearer &lt;pt_… token | sk_… key&gt;&#x60; of the partner app (scope users.passthrough). Applies the app&#39;s auth settings. (optional)
     * @param  string|null $x_end_user_ip Relay only: the end user&#39;s IP for rate limiting. (optional)
     * @param  string $contentType The value for the Content-Type header. Check self::contentTypes['login'] to see the possible values for this operation
     *
     * @throws \InvalidArgumentException
     * @return \GuzzleHttp\Psr7\Request
     */
    public function loginRequest($login_request, $x_partner_authorization = null, $x_end_user_ip = null, string $contentType = self::contentTypes['login'][0])
    {

        // verify the required parameter 'login_request' is set
        if ($login_request === null || (is_array($login_request) && count($login_request) === 0)) {
            throw new \InvalidArgumentException(
                'Missing the required parameter $login_request when calling login'
            );
        }




        $resourcePath = '/api/v1/auth/login';
        $formParams = [];
        $queryParams = [];
        $headerParams = [];
        $httpBody = '';
        $multipart = false;


        // header params
        if ($x_partner_authorization !== null) {
            $headerParams['X-Partner-Authorization'] = ObjectSerializer::toHeaderValue($x_partner_authorization);
        }
        // header params
        if ($x_end_user_ip !== null) {
            $headerParams['X-End-User-IP'] = ObjectSerializer::toHeaderValue($x_end_user_ip);
        }



        $headers = $this->headerSelector->selectHeaders(
            ['application/json', ],
            $contentType,
            $multipart
        );

        // for model (json/xml)
        if (isset($login_request)) {
            if (stripos($headers['Content-Type'], 'application/json') !== false) {
                # if Content-Type contains "application/json", json_encode the body
                try {
                    $httpBody = json_encode(ObjectSerializer::sanitizeForSerialization($login_request), JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {
                    throw new \InvalidArgumentException('json_encode error: ' . $e->getMessage(), 0, $e);
                }
            } else {
                $httpBody = $login_request;
            }
        } elseif (count($formParams) > 0) {
            if ($multipart) {
                $multipartContents = [];
                foreach ($formParams as $formParamName => $formParamValue) {
                    $formParamValueItems = is_array($formParamValue) ? $formParamValue : [$formParamValue];
                    foreach ($formParamValueItems as $formParamValueItem) {
                        $multipartContents[] = [
                            'name' => $formParamName,
                            'contents' => $formParamValueItem
                        ];
                    }
                }
                // for HTTP post (form)
                $httpBody = new MultipartStream($multipartContents);

            } elseif (stripos($headers['Content-Type'], 'application/json') !== false) {
                # if Content-Type contains "application/json", json_encode the form parameters
                try {
                    $httpBody = json_encode($formParams, JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {
                    throw new \InvalidArgumentException('json_encode error: ' . $e->getMessage(), 0, $e);
                }
            } else {
                // for HTTP post (form)
                $httpBody = ObjectSerializer::buildQuery($formParams);
            }
        }


        $defaultHeaders = [];
        if ($this->config->getUserAgent()) {
            $defaultHeaders['User-Agent'] = $this->config->getUserAgent();
        }

        $headers = array_merge(
            $defaultHeaders,
            $headerParams,
            $headers
        );

        $operationHost = $this->config->getHost();
        $query = ObjectSerializer::buildQuery($queryParams);
        return new Request(
            'POST',
            $operationHost . $resourcePath . ($query ? "?{$query}" : ''),
            $headers,
            $httpBody
        );
    }

    /**
     * Operation loginSelectClinic
     *
     * Finish a multi-clinic sign-in
     *
     * @param  \PIndustries\SunCharts\Generated\Model\SelectClinicRequest $select_clinic_request select_clinic_request (required)
     * @param  string $contentType The value for the Content-Type header. Check self::contentTypes['loginSelectClinic'] to see the possible values for this operation
     *
     * @throws \PIndustries\SunCharts\Generated\ApiException on non-2xx response or if the response body is not in the expected format
     * @throws \InvalidArgumentException
     * @return \PIndustries\SunCharts\Generated\Model\Login200Response|\PIndustries\SunCharts\Generated\Model\Error|\PIndustries\SunCharts\Generated\Model\Error|\PIndustries\SunCharts\Generated\Model\Error
     */
    public function loginSelectClinic($select_clinic_request, string $contentType = self::contentTypes['loginSelectClinic'][0])
    {
        list($response) = $this->loginSelectClinicWithHttpInfo($select_clinic_request, $contentType);
        return $response;
    }

    /**
     * Operation loginSelectClinicWithHttpInfo
     *
     * Finish a multi-clinic sign-in
     *
     * @param  \PIndustries\SunCharts\Generated\Model\SelectClinicRequest $select_clinic_request (required)
     * @param  string $contentType The value for the Content-Type header. Check self::contentTypes['loginSelectClinic'] to see the possible values for this operation
     *
     * @throws \PIndustries\SunCharts\Generated\ApiException on non-2xx response or if the response body is not in the expected format
     * @throws \InvalidArgumentException
     * @return array of \PIndustries\SunCharts\Generated\Model\Login200Response|\PIndustries\SunCharts\Generated\Model\Error|\PIndustries\SunCharts\Generated\Model\Error|\PIndustries\SunCharts\Generated\Model\Error, HTTP status code, HTTP response headers (array of strings)
     */
    public function loginSelectClinicWithHttpInfo($select_clinic_request, string $contentType = self::contentTypes['loginSelectClinic'][0])
    {
        $request = $this->loginSelectClinicRequest($select_clinic_request, $contentType);

        try {
            $options = $this->createHttpClientOption();
            try {
                $response = $this->client->send($request, $options);
            } catch (RequestException $e) {
                throw new ApiException(
                    "[{$e->getCode()}] {$e->getMessage()}",
                    (int) $e->getCode(),
                    $e->getResponse() ? $e->getResponse()->getHeaders() : null,
                    $e->getResponse() ? (string) $e->getResponse()->getBody() : null
                );
            } catch (ConnectException $e) {
                throw new ApiException(
                    "[{$e->getCode()}] {$e->getMessage()}",
                    (int) $e->getCode(),
                    null,
                    null
                );
            }

            $statusCode = $response->getStatusCode();


            switch($statusCode) {
                case 200:
                    return $this->handleResponseWithDataType(
                        '\PIndustries\SunCharts\Generated\Model\Login200Response',
                        $request,
                        $response,
                    );
                case 401:
                    return $this->handleResponseWithDataType(
                        '\PIndustries\SunCharts\Generated\Model\Error',
                        $request,
                        $response,
                    );
                case 429:
                    return $this->handleResponseWithDataType(
                        '\PIndustries\SunCharts\Generated\Model\Error',
                        $request,
                        $response,
                    );
                default:
                    return $this->handleResponseWithDataType(
                        '\PIndustries\SunCharts\Generated\Model\Error',
                        $request,
                        $response,
                    );
            }

            

            if ($statusCode < 200 || $statusCode > 299) {
                throw new ApiException(
                    sprintf(
                        '[%d] Error connecting to the API (%s)',
                        $statusCode,
                        (string) $request->getUri()
                    ),
                    $statusCode,
                    $response->getHeaders(),
                    (string) $response->getBody()
                );
            }

            return $this->handleResponseWithDataType(
                '\PIndustries\SunCharts\Generated\Model\Login200Response',
                $request,
                $response,
            );
        } catch (ApiException $e) {
            switch ($e->getCode()) {
                case 200:
                    $data = ObjectSerializer::deserialize(
                        $e->getResponseBody(),
                        '\PIndustries\SunCharts\Generated\Model\Login200Response',
                        $e->getResponseHeaders()
                    );
                    $e->setResponseObject($data);
                    throw $e;
                case 401:
                    $data = ObjectSerializer::deserialize(
                        $e->getResponseBody(),
                        '\PIndustries\SunCharts\Generated\Model\Error',
                        $e->getResponseHeaders()
                    );
                    $e->setResponseObject($data);
                    throw $e;
                case 429:
                    $data = ObjectSerializer::deserialize(
                        $e->getResponseBody(),
                        '\PIndustries\SunCharts\Generated\Model\Error',
                        $e->getResponseHeaders()
                    );
                    $e->setResponseObject($data);
                    throw $e;
                default:
                    $data = ObjectSerializer::deserialize(
                        $e->getResponseBody(),
                        '\PIndustries\SunCharts\Generated\Model\Error',
                        $e->getResponseHeaders()
                    );
                    $e->setResponseObject($data);
                    throw $e;
            }
        

            throw $e;
        }
    }

    /**
     * Operation loginSelectClinicAsync
     *
     * Finish a multi-clinic sign-in
     *
     * @param  \PIndustries\SunCharts\Generated\Model\SelectClinicRequest $select_clinic_request (required)
     * @param  string $contentType The value for the Content-Type header. Check self::contentTypes['loginSelectClinic'] to see the possible values for this operation
     *
     * @throws \InvalidArgumentException
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function loginSelectClinicAsync($select_clinic_request, string $contentType = self::contentTypes['loginSelectClinic'][0])
    {
        return $this->loginSelectClinicAsyncWithHttpInfo($select_clinic_request, $contentType)
            ->then(
                function ($response) {
                    return $response[0];
                }
            );
    }

    /**
     * Operation loginSelectClinicAsyncWithHttpInfo
     *
     * Finish a multi-clinic sign-in
     *
     * @param  \PIndustries\SunCharts\Generated\Model\SelectClinicRequest $select_clinic_request (required)
     * @param  string $contentType The value for the Content-Type header. Check self::contentTypes['loginSelectClinic'] to see the possible values for this operation
     *
     * @throws \InvalidArgumentException
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function loginSelectClinicAsyncWithHttpInfo($select_clinic_request, string $contentType = self::contentTypes['loginSelectClinic'][0])
    {
        $returnType = '\PIndustries\SunCharts\Generated\Model\Login200Response';
        $request = $this->loginSelectClinicRequest($select_clinic_request, $contentType);

        return $this->client
            ->sendAsync($request, $this->createHttpClientOption())
            ->then(
                function ($response) use ($returnType) {
                    if ($returnType === '\SplFileObject') {
                        $content = $response->getBody(); //stream goes to serializer
                    } else {
                        $content = (string) $response->getBody();
                        if ($returnType !== 'string') {
                            $content = json_decode($content);
                        }
                    }

                    return [
                        ObjectSerializer::deserialize($content, $returnType, []),
                        $response->getStatusCode(),
                        $response->getHeaders()
                    ];
                },
                function ($exception) {
                    $response = $exception->getResponse();
                    $statusCode = $response->getStatusCode();
                    throw new ApiException(
                        sprintf(
                            '[%d] Error connecting to the API (%s)',
                            $statusCode,
                            $exception->getRequest()->getUri()
                        ),
                        $statusCode,
                        $response->getHeaders(),
                        (string) $response->getBody()
                    );
                }
            );
    }

    /**
     * Create request for operation 'loginSelectClinic'
     *
     * @param  \PIndustries\SunCharts\Generated\Model\SelectClinicRequest $select_clinic_request (required)
     * @param  string $contentType The value for the Content-Type header. Check self::contentTypes['loginSelectClinic'] to see the possible values for this operation
     *
     * @throws \InvalidArgumentException
     * @return \GuzzleHttp\Psr7\Request
     */
    public function loginSelectClinicRequest($select_clinic_request, string $contentType = self::contentTypes['loginSelectClinic'][0])
    {

        // verify the required parameter 'select_clinic_request' is set
        if ($select_clinic_request === null || (is_array($select_clinic_request) && count($select_clinic_request) === 0)) {
            throw new \InvalidArgumentException(
                'Missing the required parameter $select_clinic_request when calling loginSelectClinic'
            );
        }


        $resourcePath = '/api/v1/auth/login/select';
        $formParams = [];
        $queryParams = [];
        $headerParams = [];
        $httpBody = '';
        $multipart = false;





        $headers = $this->headerSelector->selectHeaders(
            ['application/json', ],
            $contentType,
            $multipart
        );

        // for model (json/xml)
        if (isset($select_clinic_request)) {
            if (stripos($headers['Content-Type'], 'application/json') !== false) {
                # if Content-Type contains "application/json", json_encode the body
                try {
                    $httpBody = json_encode(ObjectSerializer::sanitizeForSerialization($select_clinic_request), JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {
                    throw new \InvalidArgumentException('json_encode error: ' . $e->getMessage(), 0, $e);
                }
            } else {
                $httpBody = $select_clinic_request;
            }
        } elseif (count($formParams) > 0) {
            if ($multipart) {
                $multipartContents = [];
                foreach ($formParams as $formParamName => $formParamValue) {
                    $formParamValueItems = is_array($formParamValue) ? $formParamValue : [$formParamValue];
                    foreach ($formParamValueItems as $formParamValueItem) {
                        $multipartContents[] = [
                            'name' => $formParamName,
                            'contents' => $formParamValueItem
                        ];
                    }
                }
                // for HTTP post (form)
                $httpBody = new MultipartStream($multipartContents);

            } elseif (stripos($headers['Content-Type'], 'application/json') !== false) {
                # if Content-Type contains "application/json", json_encode the form parameters
                try {
                    $httpBody = json_encode($formParams, JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {
                    throw new \InvalidArgumentException('json_encode error: ' . $e->getMessage(), 0, $e);
                }
            } else {
                // for HTTP post (form)
                $httpBody = ObjectSerializer::buildQuery($formParams);
            }
        }


        $defaultHeaders = [];
        if ($this->config->getUserAgent()) {
            $defaultHeaders['User-Agent'] = $this->config->getUserAgent();
        }

        $headers = array_merge(
            $defaultHeaders,
            $headerParams,
            $headers
        );

        $operationHost = $this->config->getHost();
        $query = ObjectSerializer::buildQuery($queryParams);
        return new Request(
            'POST',
            $operationHost . $resourcePath . ($query ? "?{$query}" : ''),
            $headers,
            $httpBody
        );
    }

    /**
     * Operation logout
     *
     * Revoke the current session
     *
     * @param  string $contentType The value for the Content-Type header. Check self::contentTypes['logout'] to see the possible values for this operation
     *
     * @throws \PIndustries\SunCharts\Generated\ApiException on non-2xx response or if the response body is not in the expected format
     * @throws \InvalidArgumentException
     * @return void
     */
    public function logout(string $contentType = self::contentTypes['logout'][0])
    {
        $this->logoutWithHttpInfo($contentType);
    }

    /**
     * Operation logoutWithHttpInfo
     *
     * Revoke the current session
     *
     * @param  string $contentType The value for the Content-Type header. Check self::contentTypes['logout'] to see the possible values for this operation
     *
     * @throws \PIndustries\SunCharts\Generated\ApiException on non-2xx response or if the response body is not in the expected format
     * @throws \InvalidArgumentException
     * @return array of null, HTTP status code, HTTP response headers (array of strings)
     */
    public function logoutWithHttpInfo(string $contentType = self::contentTypes['logout'][0])
    {
        $request = $this->logoutRequest($contentType);

        try {
            $options = $this->createHttpClientOption();
            try {
                $response = $this->client->send($request, $options);
            } catch (RequestException $e) {
                throw new ApiException(
                    "[{$e->getCode()}] {$e->getMessage()}",
                    (int) $e->getCode(),
                    $e->getResponse() ? $e->getResponse()->getHeaders() : null,
                    $e->getResponse() ? (string) $e->getResponse()->getBody() : null
                );
            } catch (ConnectException $e) {
                throw new ApiException(
                    "[{$e->getCode()}] {$e->getMessage()}",
                    (int) $e->getCode(),
                    null,
                    null
                );
            }

            $statusCode = $response->getStatusCode();


            return [null, $statusCode, $response->getHeaders()];
        } catch (ApiException $e) {
            switch ($e->getCode()) {
                default:
                    $data = ObjectSerializer::deserialize(
                        $e->getResponseBody(),
                        '\PIndustries\SunCharts\Generated\Model\Error',
                        $e->getResponseHeaders()
                    );
                    $e->setResponseObject($data);
                    throw $e;
            }
        

            throw $e;
        }
    }

    /**
     * Operation logoutAsync
     *
     * Revoke the current session
     *
     * @param  string $contentType The value for the Content-Type header. Check self::contentTypes['logout'] to see the possible values for this operation
     *
     * @throws \InvalidArgumentException
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function logoutAsync(string $contentType = self::contentTypes['logout'][0])
    {
        return $this->logoutAsyncWithHttpInfo($contentType)
            ->then(
                function ($response) {
                    return $response[0];
                }
            );
    }

    /**
     * Operation logoutAsyncWithHttpInfo
     *
     * Revoke the current session
     *
     * @param  string $contentType The value for the Content-Type header. Check self::contentTypes['logout'] to see the possible values for this operation
     *
     * @throws \InvalidArgumentException
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function logoutAsyncWithHttpInfo(string $contentType = self::contentTypes['logout'][0])
    {
        $returnType = '';
        $request = $this->logoutRequest($contentType);

        return $this->client
            ->sendAsync($request, $this->createHttpClientOption())
            ->then(
                function ($response) use ($returnType) {
                    return [null, $response->getStatusCode(), $response->getHeaders()];
                },
                function ($exception) {
                    $response = $exception->getResponse();
                    $statusCode = $response->getStatusCode();
                    throw new ApiException(
                        sprintf(
                            '[%d] Error connecting to the API (%s)',
                            $statusCode,
                            $exception->getRequest()->getUri()
                        ),
                        $statusCode,
                        $response->getHeaders(),
                        (string) $response->getBody()
                    );
                }
            );
    }

    /**
     * Create request for operation 'logout'
     *
     * @param  string $contentType The value for the Content-Type header. Check self::contentTypes['logout'] to see the possible values for this operation
     *
     * @throws \InvalidArgumentException
     * @return \GuzzleHttp\Psr7\Request
     */
    public function logoutRequest(string $contentType = self::contentTypes['logout'][0])
    {


        $resourcePath = '/api/v1/auth/logout';
        $formParams = [];
        $queryParams = [];
        $headerParams = [];
        $httpBody = '';
        $multipart = false;





        $headers = $this->headerSelector->selectHeaders(
            ['application/json', ],
            $contentType,
            $multipart
        );

        // for model (json/xml)
        if (count($formParams) > 0) {
            if ($multipart) {
                $multipartContents = [];
                foreach ($formParams as $formParamName => $formParamValue) {
                    $formParamValueItems = is_array($formParamValue) ? $formParamValue : [$formParamValue];
                    foreach ($formParamValueItems as $formParamValueItem) {
                        $multipartContents[] = [
                            'name' => $formParamName,
                            'contents' => $formParamValueItem
                        ];
                    }
                }
                // for HTTP post (form)
                $httpBody = new MultipartStream($multipartContents);

            } elseif (stripos($headers['Content-Type'], 'application/json') !== false) {
                # if Content-Type contains "application/json", json_encode the form parameters
                try {
                    $httpBody = json_encode($formParams, JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {
                    throw new \InvalidArgumentException('json_encode error: ' . $e->getMessage(), 0, $e);
                }
            } else {
                // for HTTP post (form)
                $httpBody = ObjectSerializer::buildQuery($formParams);
            }
        }

        // this endpoint requires Bearer (JWT) authentication (access token)
        if (!empty($this->config->getAccessToken())) {
            $headers['Authorization'] = 'Bearer ' . $this->config->getAccessToken();
        }

        $defaultHeaders = [];
        if ($this->config->getUserAgent()) {
            $defaultHeaders['User-Agent'] = $this->config->getUserAgent();
        }

        $headers = array_merge(
            $defaultHeaders,
            $headerParams,
            $headers
        );

        $operationHost = $this->config->getHost();
        $query = ObjectSerializer::buildQuery($queryParams);
        return new Request(
            'POST',
            $operationHost . $resourcePath . ($query ? "?{$query}" : ''),
            $headers,
            $httpBody
        );
    }

    /**
     * Operation magicLinkStart
     *
     * Email a single-use sign-in link + 6-digit code
     *
     * @param  \PIndustries\SunCharts\Generated\Model\MagicStartRequest $magic_start_request magic_start_request (required)
     * @param  string|null $x_partner_authorization White-label relay: &#x60;Bearer &lt;pt_… token | sk_… key&gt;&#x60; of the partner app (scope users.passthrough). Applies the app&#39;s auth settings. (optional)
     * @param  string|null $x_end_user_ip Relay only: the end user&#39;s IP for rate limiting. (optional)
     * @param  string $contentType The value for the Content-Type header. Check self::contentTypes['magicLinkStart'] to see the possible values for this operation
     *
     * @throws \PIndustries\SunCharts\Generated\ApiException on non-2xx response or if the response body is not in the expected format
     * @throws \InvalidArgumentException
     * @return \PIndustries\SunCharts\Generated\Model\MagicSent|\PIndustries\SunCharts\Generated\Model\Error|\PIndustries\SunCharts\Generated\Model\Error
     */
    public function magicLinkStart($magic_start_request, $x_partner_authorization = null, $x_end_user_ip = null, string $contentType = self::contentTypes['magicLinkStart'][0])
    {
        list($response) = $this->magicLinkStartWithHttpInfo($magic_start_request, $x_partner_authorization, $x_end_user_ip, $contentType);
        return $response;
    }

    /**
     * Operation magicLinkStartWithHttpInfo
     *
     * Email a single-use sign-in link + 6-digit code
     *
     * @param  \PIndustries\SunCharts\Generated\Model\MagicStartRequest $magic_start_request (required)
     * @param  string|null $x_partner_authorization White-label relay: &#x60;Bearer &lt;pt_… token | sk_… key&gt;&#x60; of the partner app (scope users.passthrough). Applies the app&#39;s auth settings. (optional)
     * @param  string|null $x_end_user_ip Relay only: the end user&#39;s IP for rate limiting. (optional)
     * @param  string $contentType The value for the Content-Type header. Check self::contentTypes['magicLinkStart'] to see the possible values for this operation
     *
     * @throws \PIndustries\SunCharts\Generated\ApiException on non-2xx response or if the response body is not in the expected format
     * @throws \InvalidArgumentException
     * @return array of \PIndustries\SunCharts\Generated\Model\MagicSent|\PIndustries\SunCharts\Generated\Model\Error|\PIndustries\SunCharts\Generated\Model\Error, HTTP status code, HTTP response headers (array of strings)
     */
    public function magicLinkStartWithHttpInfo($magic_start_request, $x_partner_authorization = null, $x_end_user_ip = null, string $contentType = self::contentTypes['magicLinkStart'][0])
    {
        $request = $this->magicLinkStartRequest($magic_start_request, $x_partner_authorization, $x_end_user_ip, $contentType);

        try {
            $options = $this->createHttpClientOption();
            try {
                $response = $this->client->send($request, $options);
            } catch (RequestException $e) {
                throw new ApiException(
                    "[{$e->getCode()}] {$e->getMessage()}",
                    (int) $e->getCode(),
                    $e->getResponse() ? $e->getResponse()->getHeaders() : null,
                    $e->getResponse() ? (string) $e->getResponse()->getBody() : null
                );
            } catch (ConnectException $e) {
                throw new ApiException(
                    "[{$e->getCode()}] {$e->getMessage()}",
                    (int) $e->getCode(),
                    null,
                    null
                );
            }

            $statusCode = $response->getStatusCode();


            switch($statusCode) {
                case 202:
                    return $this->handleResponseWithDataType(
                        '\PIndustries\SunCharts\Generated\Model\MagicSent',
                        $request,
                        $response,
                    );
                case 429:
                    return $this->handleResponseWithDataType(
                        '\PIndustries\SunCharts\Generated\Model\Error',
                        $request,
                        $response,
                    );
                case 409:
                    return $this->handleResponseWithDataType(
                        '\PIndustries\SunCharts\Generated\Model\Error',
                        $request,
                        $response,
                    );
            }

            

            if ($statusCode < 200 || $statusCode > 299) {
                throw new ApiException(
                    sprintf(
                        '[%d] Error connecting to the API (%s)',
                        $statusCode,
                        (string) $request->getUri()
                    ),
                    $statusCode,
                    $response->getHeaders(),
                    (string) $response->getBody()
                );
            }

            return $this->handleResponseWithDataType(
                '\PIndustries\SunCharts\Generated\Model\MagicSent',
                $request,
                $response,
            );
        } catch (ApiException $e) {
            switch ($e->getCode()) {
                case 202:
                    $data = ObjectSerializer::deserialize(
                        $e->getResponseBody(),
                        '\PIndustries\SunCharts\Generated\Model\MagicSent',
                        $e->getResponseHeaders()
                    );
                    $e->setResponseObject($data);
                    throw $e;
                case 429:
                    $data = ObjectSerializer::deserialize(
                        $e->getResponseBody(),
                        '\PIndustries\SunCharts\Generated\Model\Error',
                        $e->getResponseHeaders()
                    );
                    $e->setResponseObject($data);
                    throw $e;
                case 409:
                    $data = ObjectSerializer::deserialize(
                        $e->getResponseBody(),
                        '\PIndustries\SunCharts\Generated\Model\Error',
                        $e->getResponseHeaders()
                    );
                    $e->setResponseObject($data);
                    throw $e;
            }
        

            throw $e;
        }
    }

    /**
     * Operation magicLinkStartAsync
     *
     * Email a single-use sign-in link + 6-digit code
     *
     * @param  \PIndustries\SunCharts\Generated\Model\MagicStartRequest $magic_start_request (required)
     * @param  string|null $x_partner_authorization White-label relay: &#x60;Bearer &lt;pt_… token | sk_… key&gt;&#x60; of the partner app (scope users.passthrough). Applies the app&#39;s auth settings. (optional)
     * @param  string|null $x_end_user_ip Relay only: the end user&#39;s IP for rate limiting. (optional)
     * @param  string $contentType The value for the Content-Type header. Check self::contentTypes['magicLinkStart'] to see the possible values for this operation
     *
     * @throws \InvalidArgumentException
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function magicLinkStartAsync($magic_start_request, $x_partner_authorization = null, $x_end_user_ip = null, string $contentType = self::contentTypes['magicLinkStart'][0])
    {
        return $this->magicLinkStartAsyncWithHttpInfo($magic_start_request, $x_partner_authorization, $x_end_user_ip, $contentType)
            ->then(
                function ($response) {
                    return $response[0];
                }
            );
    }

    /**
     * Operation magicLinkStartAsyncWithHttpInfo
     *
     * Email a single-use sign-in link + 6-digit code
     *
     * @param  \PIndustries\SunCharts\Generated\Model\MagicStartRequest $magic_start_request (required)
     * @param  string|null $x_partner_authorization White-label relay: &#x60;Bearer &lt;pt_… token | sk_… key&gt;&#x60; of the partner app (scope users.passthrough). Applies the app&#39;s auth settings. (optional)
     * @param  string|null $x_end_user_ip Relay only: the end user&#39;s IP for rate limiting. (optional)
     * @param  string $contentType The value for the Content-Type header. Check self::contentTypes['magicLinkStart'] to see the possible values for this operation
     *
     * @throws \InvalidArgumentException
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function magicLinkStartAsyncWithHttpInfo($magic_start_request, $x_partner_authorization = null, $x_end_user_ip = null, string $contentType = self::contentTypes['magicLinkStart'][0])
    {
        $returnType = '\PIndustries\SunCharts\Generated\Model\MagicSent';
        $request = $this->magicLinkStartRequest($magic_start_request, $x_partner_authorization, $x_end_user_ip, $contentType);

        return $this->client
            ->sendAsync($request, $this->createHttpClientOption())
            ->then(
                function ($response) use ($returnType) {
                    if ($returnType === '\SplFileObject') {
                        $content = $response->getBody(); //stream goes to serializer
                    } else {
                        $content = (string) $response->getBody();
                        if ($returnType !== 'string') {
                            $content = json_decode($content);
                        }
                    }

                    return [
                        ObjectSerializer::deserialize($content, $returnType, []),
                        $response->getStatusCode(),
                        $response->getHeaders()
                    ];
                },
                function ($exception) {
                    $response = $exception->getResponse();
                    $statusCode = $response->getStatusCode();
                    throw new ApiException(
                        sprintf(
                            '[%d] Error connecting to the API (%s)',
                            $statusCode,
                            $exception->getRequest()->getUri()
                        ),
                        $statusCode,
                        $response->getHeaders(),
                        (string) $response->getBody()
                    );
                }
            );
    }

    /**
     * Create request for operation 'magicLinkStart'
     *
     * @param  \PIndustries\SunCharts\Generated\Model\MagicStartRequest $magic_start_request (required)
     * @param  string|null $x_partner_authorization White-label relay: &#x60;Bearer &lt;pt_… token | sk_… key&gt;&#x60; of the partner app (scope users.passthrough). Applies the app&#39;s auth settings. (optional)
     * @param  string|null $x_end_user_ip Relay only: the end user&#39;s IP for rate limiting. (optional)
     * @param  string $contentType The value for the Content-Type header. Check self::contentTypes['magicLinkStart'] to see the possible values for this operation
     *
     * @throws \InvalidArgumentException
     * @return \GuzzleHttp\Psr7\Request
     */
    public function magicLinkStartRequest($magic_start_request, $x_partner_authorization = null, $x_end_user_ip = null, string $contentType = self::contentTypes['magicLinkStart'][0])
    {

        // verify the required parameter 'magic_start_request' is set
        if ($magic_start_request === null || (is_array($magic_start_request) && count($magic_start_request) === 0)) {
            throw new \InvalidArgumentException(
                'Missing the required parameter $magic_start_request when calling magicLinkStart'
            );
        }




        $resourcePath = '/api/v1/auth/magic/start';
        $formParams = [];
        $queryParams = [];
        $headerParams = [];
        $httpBody = '';
        $multipart = false;


        // header params
        if ($x_partner_authorization !== null) {
            $headerParams['X-Partner-Authorization'] = ObjectSerializer::toHeaderValue($x_partner_authorization);
        }
        // header params
        if ($x_end_user_ip !== null) {
            $headerParams['X-End-User-IP'] = ObjectSerializer::toHeaderValue($x_end_user_ip);
        }



        $headers = $this->headerSelector->selectHeaders(
            ['application/json', ],
            $contentType,
            $multipart
        );

        // for model (json/xml)
        if (isset($magic_start_request)) {
            if (stripos($headers['Content-Type'], 'application/json') !== false) {
                # if Content-Type contains "application/json", json_encode the body
                try {
                    $httpBody = json_encode(ObjectSerializer::sanitizeForSerialization($magic_start_request), JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {
                    throw new \InvalidArgumentException('json_encode error: ' . $e->getMessage(), 0, $e);
                }
            } else {
                $httpBody = $magic_start_request;
            }
        } elseif (count($formParams) > 0) {
            if ($multipart) {
                $multipartContents = [];
                foreach ($formParams as $formParamName => $formParamValue) {
                    $formParamValueItems = is_array($formParamValue) ? $formParamValue : [$formParamValue];
                    foreach ($formParamValueItems as $formParamValueItem) {
                        $multipartContents[] = [
                            'name' => $formParamName,
                            'contents' => $formParamValueItem
                        ];
                    }
                }
                // for HTTP post (form)
                $httpBody = new MultipartStream($multipartContents);

            } elseif (stripos($headers['Content-Type'], 'application/json') !== false) {
                # if Content-Type contains "application/json", json_encode the form parameters
                try {
                    $httpBody = json_encode($formParams, JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {
                    throw new \InvalidArgumentException('json_encode error: ' . $e->getMessage(), 0, $e);
                }
            } else {
                // for HTTP post (form)
                $httpBody = ObjectSerializer::buildQuery($formParams);
            }
        }


        $defaultHeaders = [];
        if ($this->config->getUserAgent()) {
            $defaultHeaders['User-Agent'] = $this->config->getUserAgent();
        }

        $headers = array_merge(
            $defaultHeaders,
            $headerParams,
            $headers
        );

        $operationHost = $this->config->getHost();
        $query = ObjectSerializer::buildQuery($queryParams);
        return new Request(
            'POST',
            $operationHost . $resourcePath . ($query ? "?{$query}" : ''),
            $headers,
            $httpBody
        );
    }

    /**
     * Operation magicLinkVerify
     *
     * Redeem a magic link or code (same device only)
     *
     * @param  \PIndustries\SunCharts\Generated\Model\MagicVerifyRequest $magic_verify_request magic_verify_request (required)
     * @param  string|null $x_partner_authorization White-label relay: &#x60;Bearer &lt;pt_… token | sk_… key&gt;&#x60; of the partner app (scope users.passthrough). Applies the app&#39;s auth settings. (optional)
     * @param  string|null $x_end_user_ip Relay only: the end user&#39;s IP for rate limiting. (optional)
     * @param  string $contentType The value for the Content-Type header. Check self::contentTypes['magicLinkVerify'] to see the possible values for this operation
     *
     * @throws \PIndustries\SunCharts\Generated\ApiException on non-2xx response or if the response body is not in the expected format
     * @throws \InvalidArgumentException
     * @return \PIndustries\SunCharts\Generated\Model\Login200Response|\PIndustries\SunCharts\Generated\Model\Error|\PIndustries\SunCharts\Generated\Model\Error|\PIndustries\SunCharts\Generated\Model\Error
     */
    public function magicLinkVerify($magic_verify_request, $x_partner_authorization = null, $x_end_user_ip = null, string $contentType = self::contentTypes['magicLinkVerify'][0])
    {
        list($response) = $this->magicLinkVerifyWithHttpInfo($magic_verify_request, $x_partner_authorization, $x_end_user_ip, $contentType);
        return $response;
    }

    /**
     * Operation magicLinkVerifyWithHttpInfo
     *
     * Redeem a magic link or code (same device only)
     *
     * @param  \PIndustries\SunCharts\Generated\Model\MagicVerifyRequest $magic_verify_request (required)
     * @param  string|null $x_partner_authorization White-label relay: &#x60;Bearer &lt;pt_… token | sk_… key&gt;&#x60; of the partner app (scope users.passthrough). Applies the app&#39;s auth settings. (optional)
     * @param  string|null $x_end_user_ip Relay only: the end user&#39;s IP for rate limiting. (optional)
     * @param  string $contentType The value for the Content-Type header. Check self::contentTypes['magicLinkVerify'] to see the possible values for this operation
     *
     * @throws \PIndustries\SunCharts\Generated\ApiException on non-2xx response or if the response body is not in the expected format
     * @throws \InvalidArgumentException
     * @return array of \PIndustries\SunCharts\Generated\Model\Login200Response|\PIndustries\SunCharts\Generated\Model\Error|\PIndustries\SunCharts\Generated\Model\Error|\PIndustries\SunCharts\Generated\Model\Error, HTTP status code, HTTP response headers (array of strings)
     */
    public function magicLinkVerifyWithHttpInfo($magic_verify_request, $x_partner_authorization = null, $x_end_user_ip = null, string $contentType = self::contentTypes['magicLinkVerify'][0])
    {
        $request = $this->magicLinkVerifyRequest($magic_verify_request, $x_partner_authorization, $x_end_user_ip, $contentType);

        try {
            $options = $this->createHttpClientOption();
            try {
                $response = $this->client->send($request, $options);
            } catch (RequestException $e) {
                throw new ApiException(
                    "[{$e->getCode()}] {$e->getMessage()}",
                    (int) $e->getCode(),
                    $e->getResponse() ? $e->getResponse()->getHeaders() : null,
                    $e->getResponse() ? (string) $e->getResponse()->getBody() : null
                );
            } catch (ConnectException $e) {
                throw new ApiException(
                    "[{$e->getCode()}] {$e->getMessage()}",
                    (int) $e->getCode(),
                    null,
                    null
                );
            }

            $statusCode = $response->getStatusCode();


            switch($statusCode) {
                case 200:
                    return $this->handleResponseWithDataType(
                        '\PIndustries\SunCharts\Generated\Model\Login200Response',
                        $request,
                        $response,
                    );
                case 401:
                    return $this->handleResponseWithDataType(
                        '\PIndustries\SunCharts\Generated\Model\Error',
                        $request,
                        $response,
                    );
                case 429:
                    return $this->handleResponseWithDataType(
                        '\PIndustries\SunCharts\Generated\Model\Error',
                        $request,
                        $response,
                    );
                default:
                    return $this->handleResponseWithDataType(
                        '\PIndustries\SunCharts\Generated\Model\Error',
                        $request,
                        $response,
                    );
            }

            

            if ($statusCode < 200 || $statusCode > 299) {
                throw new ApiException(
                    sprintf(
                        '[%d] Error connecting to the API (%s)',
                        $statusCode,
                        (string) $request->getUri()
                    ),
                    $statusCode,
                    $response->getHeaders(),
                    (string) $response->getBody()
                );
            }

            return $this->handleResponseWithDataType(
                '\PIndustries\SunCharts\Generated\Model\Login200Response',
                $request,
                $response,
            );
        } catch (ApiException $e) {
            switch ($e->getCode()) {
                case 200:
                    $data = ObjectSerializer::deserialize(
                        $e->getResponseBody(),
                        '\PIndustries\SunCharts\Generated\Model\Login200Response',
                        $e->getResponseHeaders()
                    );
                    $e->setResponseObject($data);
                    throw $e;
                case 401:
                    $data = ObjectSerializer::deserialize(
                        $e->getResponseBody(),
                        '\PIndustries\SunCharts\Generated\Model\Error',
                        $e->getResponseHeaders()
                    );
                    $e->setResponseObject($data);
                    throw $e;
                case 429:
                    $data = ObjectSerializer::deserialize(
                        $e->getResponseBody(),
                        '\PIndustries\SunCharts\Generated\Model\Error',
                        $e->getResponseHeaders()
                    );
                    $e->setResponseObject($data);
                    throw $e;
                default:
                    $data = ObjectSerializer::deserialize(
                        $e->getResponseBody(),
                        '\PIndustries\SunCharts\Generated\Model\Error',
                        $e->getResponseHeaders()
                    );
                    $e->setResponseObject($data);
                    throw $e;
            }
        

            throw $e;
        }
    }

    /**
     * Operation magicLinkVerifyAsync
     *
     * Redeem a magic link or code (same device only)
     *
     * @param  \PIndustries\SunCharts\Generated\Model\MagicVerifyRequest $magic_verify_request (required)
     * @param  string|null $x_partner_authorization White-label relay: &#x60;Bearer &lt;pt_… token | sk_… key&gt;&#x60; of the partner app (scope users.passthrough). Applies the app&#39;s auth settings. (optional)
     * @param  string|null $x_end_user_ip Relay only: the end user&#39;s IP for rate limiting. (optional)
     * @param  string $contentType The value for the Content-Type header. Check self::contentTypes['magicLinkVerify'] to see the possible values for this operation
     *
     * @throws \InvalidArgumentException
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function magicLinkVerifyAsync($magic_verify_request, $x_partner_authorization = null, $x_end_user_ip = null, string $contentType = self::contentTypes['magicLinkVerify'][0])
    {
        return $this->magicLinkVerifyAsyncWithHttpInfo($magic_verify_request, $x_partner_authorization, $x_end_user_ip, $contentType)
            ->then(
                function ($response) {
                    return $response[0];
                }
            );
    }

    /**
     * Operation magicLinkVerifyAsyncWithHttpInfo
     *
     * Redeem a magic link or code (same device only)
     *
     * @param  \PIndustries\SunCharts\Generated\Model\MagicVerifyRequest $magic_verify_request (required)
     * @param  string|null $x_partner_authorization White-label relay: &#x60;Bearer &lt;pt_… token | sk_… key&gt;&#x60; of the partner app (scope users.passthrough). Applies the app&#39;s auth settings. (optional)
     * @param  string|null $x_end_user_ip Relay only: the end user&#39;s IP for rate limiting. (optional)
     * @param  string $contentType The value for the Content-Type header. Check self::contentTypes['magicLinkVerify'] to see the possible values for this operation
     *
     * @throws \InvalidArgumentException
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function magicLinkVerifyAsyncWithHttpInfo($magic_verify_request, $x_partner_authorization = null, $x_end_user_ip = null, string $contentType = self::contentTypes['magicLinkVerify'][0])
    {
        $returnType = '\PIndustries\SunCharts\Generated\Model\Login200Response';
        $request = $this->magicLinkVerifyRequest($magic_verify_request, $x_partner_authorization, $x_end_user_ip, $contentType);

        return $this->client
            ->sendAsync($request, $this->createHttpClientOption())
            ->then(
                function ($response) use ($returnType) {
                    if ($returnType === '\SplFileObject') {
                        $content = $response->getBody(); //stream goes to serializer
                    } else {
                        $content = (string) $response->getBody();
                        if ($returnType !== 'string') {
                            $content = json_decode($content);
                        }
                    }

                    return [
                        ObjectSerializer::deserialize($content, $returnType, []),
                        $response->getStatusCode(),
                        $response->getHeaders()
                    ];
                },
                function ($exception) {
                    $response = $exception->getResponse();
                    $statusCode = $response->getStatusCode();
                    throw new ApiException(
                        sprintf(
                            '[%d] Error connecting to the API (%s)',
                            $statusCode,
                            $exception->getRequest()->getUri()
                        ),
                        $statusCode,
                        $response->getHeaders(),
                        (string) $response->getBody()
                    );
                }
            );
    }

    /**
     * Create request for operation 'magicLinkVerify'
     *
     * @param  \PIndustries\SunCharts\Generated\Model\MagicVerifyRequest $magic_verify_request (required)
     * @param  string|null $x_partner_authorization White-label relay: &#x60;Bearer &lt;pt_… token | sk_… key&gt;&#x60; of the partner app (scope users.passthrough). Applies the app&#39;s auth settings. (optional)
     * @param  string|null $x_end_user_ip Relay only: the end user&#39;s IP for rate limiting. (optional)
     * @param  string $contentType The value for the Content-Type header. Check self::contentTypes['magicLinkVerify'] to see the possible values for this operation
     *
     * @throws \InvalidArgumentException
     * @return \GuzzleHttp\Psr7\Request
     */
    public function magicLinkVerifyRequest($magic_verify_request, $x_partner_authorization = null, $x_end_user_ip = null, string $contentType = self::contentTypes['magicLinkVerify'][0])
    {

        // verify the required parameter 'magic_verify_request' is set
        if ($magic_verify_request === null || (is_array($magic_verify_request) && count($magic_verify_request) === 0)) {
            throw new \InvalidArgumentException(
                'Missing the required parameter $magic_verify_request when calling magicLinkVerify'
            );
        }




        $resourcePath = '/api/v1/auth/magic/verify';
        $formParams = [];
        $queryParams = [];
        $headerParams = [];
        $httpBody = '';
        $multipart = false;


        // header params
        if ($x_partner_authorization !== null) {
            $headerParams['X-Partner-Authorization'] = ObjectSerializer::toHeaderValue($x_partner_authorization);
        }
        // header params
        if ($x_end_user_ip !== null) {
            $headerParams['X-End-User-IP'] = ObjectSerializer::toHeaderValue($x_end_user_ip);
        }



        $headers = $this->headerSelector->selectHeaders(
            ['application/json', ],
            $contentType,
            $multipart
        );

        // for model (json/xml)
        if (isset($magic_verify_request)) {
            if (stripos($headers['Content-Type'], 'application/json') !== false) {
                # if Content-Type contains "application/json", json_encode the body
                try {
                    $httpBody = json_encode(ObjectSerializer::sanitizeForSerialization($magic_verify_request), JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {
                    throw new \InvalidArgumentException('json_encode error: ' . $e->getMessage(), 0, $e);
                }
            } else {
                $httpBody = $magic_verify_request;
            }
        } elseif (count($formParams) > 0) {
            if ($multipart) {
                $multipartContents = [];
                foreach ($formParams as $formParamName => $formParamValue) {
                    $formParamValueItems = is_array($formParamValue) ? $formParamValue : [$formParamValue];
                    foreach ($formParamValueItems as $formParamValueItem) {
                        $multipartContents[] = [
                            'name' => $formParamName,
                            'contents' => $formParamValueItem
                        ];
                    }
                }
                // for HTTP post (form)
                $httpBody = new MultipartStream($multipartContents);

            } elseif (stripos($headers['Content-Type'], 'application/json') !== false) {
                # if Content-Type contains "application/json", json_encode the form parameters
                try {
                    $httpBody = json_encode($formParams, JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {
                    throw new \InvalidArgumentException('json_encode error: ' . $e->getMessage(), 0, $e);
                }
            } else {
                // for HTTP post (form)
                $httpBody = ObjectSerializer::buildQuery($formParams);
            }
        }


        $defaultHeaders = [];
        if ($this->config->getUserAgent()) {
            $defaultHeaders['User-Agent'] = $this->config->getUserAgent();
        }

        $headers = array_merge(
            $defaultHeaders,
            $headerParams,
            $headers
        );

        $operationHost = $this->config->getHost();
        $query = ObjectSerializer::buildQuery($queryParams);
        return new Request(
            'POST',
            $operationHost . $resourcePath . ($query ? "?{$query}" : ''),
            $headers,
            $httpBody
        );
    }

    /**
     * Operation me
     *
     * Current user, tenant, role
     *
     * @param  string $contentType The value for the Content-Type header. Check self::contentTypes['me'] to see the possible values for this operation
     *
     * @throws \PIndustries\SunCharts\Generated\ApiException on non-2xx response or if the response body is not in the expected format
     * @throws \InvalidArgumentException
     * @return array<string,mixed>|\PIndustries\SunCharts\Generated\Model\Error
     */
    public function me(string $contentType = self::contentTypes['me'][0])
    {
        list($response) = $this->meWithHttpInfo($contentType);
        return $response;
    }

    /**
     * Operation meWithHttpInfo
     *
     * Current user, tenant, role
     *
     * @param  string $contentType The value for the Content-Type header. Check self::contentTypes['me'] to see the possible values for this operation
     *
     * @throws \PIndustries\SunCharts\Generated\ApiException on non-2xx response or if the response body is not in the expected format
     * @throws \InvalidArgumentException
     * @return array of array<string,mixed>|\PIndustries\SunCharts\Generated\Model\Error, HTTP status code, HTTP response headers (array of strings)
     */
    public function meWithHttpInfo(string $contentType = self::contentTypes['me'][0])
    {
        $request = $this->meRequest($contentType);

        try {
            $options = $this->createHttpClientOption();
            try {
                $response = $this->client->send($request, $options);
            } catch (RequestException $e) {
                throw new ApiException(
                    "[{$e->getCode()}] {$e->getMessage()}",
                    (int) $e->getCode(),
                    $e->getResponse() ? $e->getResponse()->getHeaders() : null,
                    $e->getResponse() ? (string) $e->getResponse()->getBody() : null
                );
            } catch (ConnectException $e) {
                throw new ApiException(
                    "[{$e->getCode()}] {$e->getMessage()}",
                    (int) $e->getCode(),
                    null,
                    null
                );
            }

            $statusCode = $response->getStatusCode();


            switch($statusCode) {
                case 200:
                    return $this->handleResponseWithDataType(
                        'array<string,mixed>',
                        $request,
                        $response,
                    );
                default:
                    return $this->handleResponseWithDataType(
                        '\PIndustries\SunCharts\Generated\Model\Error',
                        $request,
                        $response,
                    );
            }

            

            if ($statusCode < 200 || $statusCode > 299) {
                throw new ApiException(
                    sprintf(
                        '[%d] Error connecting to the API (%s)',
                        $statusCode,
                        (string) $request->getUri()
                    ),
                    $statusCode,
                    $response->getHeaders(),
                    (string) $response->getBody()
                );
            }

            return $this->handleResponseWithDataType(
                'array<string,mixed>',
                $request,
                $response,
            );
        } catch (ApiException $e) {
            switch ($e->getCode()) {
                case 200:
                    $data = ObjectSerializer::deserialize(
                        $e->getResponseBody(),
                        'array<string,mixed>',
                        $e->getResponseHeaders()
                    );
                    $e->setResponseObject($data);
                    throw $e;
                default:
                    $data = ObjectSerializer::deserialize(
                        $e->getResponseBody(),
                        '\PIndustries\SunCharts\Generated\Model\Error',
                        $e->getResponseHeaders()
                    );
                    $e->setResponseObject($data);
                    throw $e;
            }
        

            throw $e;
        }
    }

    /**
     * Operation meAsync
     *
     * Current user, tenant, role
     *
     * @param  string $contentType The value for the Content-Type header. Check self::contentTypes['me'] to see the possible values for this operation
     *
     * @throws \InvalidArgumentException
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function meAsync(string $contentType = self::contentTypes['me'][0])
    {
        return $this->meAsyncWithHttpInfo($contentType)
            ->then(
                function ($response) {
                    return $response[0];
                }
            );
    }

    /**
     * Operation meAsyncWithHttpInfo
     *
     * Current user, tenant, role
     *
     * @param  string $contentType The value for the Content-Type header. Check self::contentTypes['me'] to see the possible values for this operation
     *
     * @throws \InvalidArgumentException
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function meAsyncWithHttpInfo(string $contentType = self::contentTypes['me'][0])
    {
        $returnType = 'array<string,mixed>';
        $request = $this->meRequest($contentType);

        return $this->client
            ->sendAsync($request, $this->createHttpClientOption())
            ->then(
                function ($response) use ($returnType) {
                    if ($returnType === '\SplFileObject') {
                        $content = $response->getBody(); //stream goes to serializer
                    } else {
                        $content = (string) $response->getBody();
                        if ($returnType !== 'string') {
                            $content = json_decode($content);
                        }
                    }

                    return [
                        ObjectSerializer::deserialize($content, $returnType, []),
                        $response->getStatusCode(),
                        $response->getHeaders()
                    ];
                },
                function ($exception) {
                    $response = $exception->getResponse();
                    $statusCode = $response->getStatusCode();
                    throw new ApiException(
                        sprintf(
                            '[%d] Error connecting to the API (%s)',
                            $statusCode,
                            $exception->getRequest()->getUri()
                        ),
                        $statusCode,
                        $response->getHeaders(),
                        (string) $response->getBody()
                    );
                }
            );
    }

    /**
     * Create request for operation 'me'
     *
     * @param  string $contentType The value for the Content-Type header. Check self::contentTypes['me'] to see the possible values for this operation
     *
     * @throws \InvalidArgumentException
     * @return \GuzzleHttp\Psr7\Request
     */
    public function meRequest(string $contentType = self::contentTypes['me'][0])
    {


        $resourcePath = '/api/v1/me';
        $formParams = [];
        $queryParams = [];
        $headerParams = [];
        $httpBody = '';
        $multipart = false;





        $headers = $this->headerSelector->selectHeaders(
            ['application/json', ],
            $contentType,
            $multipart
        );

        // for model (json/xml)
        if (count($formParams) > 0) {
            if ($multipart) {
                $multipartContents = [];
                foreach ($formParams as $formParamName => $formParamValue) {
                    $formParamValueItems = is_array($formParamValue) ? $formParamValue : [$formParamValue];
                    foreach ($formParamValueItems as $formParamValueItem) {
                        $multipartContents[] = [
                            'name' => $formParamName,
                            'contents' => $formParamValueItem
                        ];
                    }
                }
                // for HTTP post (form)
                $httpBody = new MultipartStream($multipartContents);

            } elseif (stripos($headers['Content-Type'], 'application/json') !== false) {
                # if Content-Type contains "application/json", json_encode the form parameters
                try {
                    $httpBody = json_encode($formParams, JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {
                    throw new \InvalidArgumentException('json_encode error: ' . $e->getMessage(), 0, $e);
                }
            } else {
                // for HTTP post (form)
                $httpBody = ObjectSerializer::buildQuery($formParams);
            }
        }

        // this endpoint requires Bearer (JWT) authentication (access token)
        if (!empty($this->config->getAccessToken())) {
            $headers['Authorization'] = 'Bearer ' . $this->config->getAccessToken();
        }

        $defaultHeaders = [];
        if ($this->config->getUserAgent()) {
            $defaultHeaders['User-Agent'] = $this->config->getUserAgent();
        }

        $headers = array_merge(
            $defaultHeaders,
            $headerParams,
            $headers
        );

        $operationHost = $this->config->getHost();
        $query = ObjectSerializer::buildQuery($queryParams);
        return new Request(
            'GET',
            $operationHost . $resourcePath . ($query ? "?{$query}" : ''),
            $headers,
            $httpBody
        );
    }

    /**
     * Operation passkeyLoginFinish
     *
     * Finish passkey sign-in → user tokens
     *
     * @param  object $body body (required)
     * @param  string|null $x_partner_authorization White-label relay: &#x60;Bearer &lt;pt_… token | sk_… key&gt;&#x60; of the partner app (scope users.passthrough). Applies the app&#39;s auth settings. (optional)
     * @param  string|null $x_end_user_ip Relay only: the end user&#39;s IP for rate limiting. (optional)
     * @param  string $contentType The value for the Content-Type header. Check self::contentTypes['passkeyLoginFinish'] to see the possible values for this operation
     *
     * @throws \PIndustries\SunCharts\Generated\ApiException on non-2xx response or if the response body is not in the expected format
     * @throws \InvalidArgumentException
     * @return \PIndustries\SunCharts\Generated\Model\UserTokens|\PIndustries\SunCharts\Generated\Model\Error
     */
    public function passkeyLoginFinish($body, $x_partner_authorization = null, $x_end_user_ip = null, string $contentType = self::contentTypes['passkeyLoginFinish'][0])
    {
        list($response) = $this->passkeyLoginFinishWithHttpInfo($body, $x_partner_authorization, $x_end_user_ip, $contentType);
        return $response;
    }

    /**
     * Operation passkeyLoginFinishWithHttpInfo
     *
     * Finish passkey sign-in → user tokens
     *
     * @param  object $body (required)
     * @param  string|null $x_partner_authorization White-label relay: &#x60;Bearer &lt;pt_… token | sk_… key&gt;&#x60; of the partner app (scope users.passthrough). Applies the app&#39;s auth settings. (optional)
     * @param  string|null $x_end_user_ip Relay only: the end user&#39;s IP for rate limiting. (optional)
     * @param  string $contentType The value for the Content-Type header. Check self::contentTypes['passkeyLoginFinish'] to see the possible values for this operation
     *
     * @throws \PIndustries\SunCharts\Generated\ApiException on non-2xx response or if the response body is not in the expected format
     * @throws \InvalidArgumentException
     * @return array of \PIndustries\SunCharts\Generated\Model\UserTokens|\PIndustries\SunCharts\Generated\Model\Error, HTTP status code, HTTP response headers (array of strings)
     */
    public function passkeyLoginFinishWithHttpInfo($body, $x_partner_authorization = null, $x_end_user_ip = null, string $contentType = self::contentTypes['passkeyLoginFinish'][0])
    {
        $request = $this->passkeyLoginFinishRequest($body, $x_partner_authorization, $x_end_user_ip, $contentType);

        try {
            $options = $this->createHttpClientOption();
            try {
                $response = $this->client->send($request, $options);
            } catch (RequestException $e) {
                throw new ApiException(
                    "[{$e->getCode()}] {$e->getMessage()}",
                    (int) $e->getCode(),
                    $e->getResponse() ? $e->getResponse()->getHeaders() : null,
                    $e->getResponse() ? (string) $e->getResponse()->getBody() : null
                );
            } catch (ConnectException $e) {
                throw new ApiException(
                    "[{$e->getCode()}] {$e->getMessage()}",
                    (int) $e->getCode(),
                    null,
                    null
                );
            }

            $statusCode = $response->getStatusCode();


            switch($statusCode) {
                case 200:
                    return $this->handleResponseWithDataType(
                        '\PIndustries\SunCharts\Generated\Model\UserTokens',
                        $request,
                        $response,
                    );
                default:
                    return $this->handleResponseWithDataType(
                        '\PIndustries\SunCharts\Generated\Model\Error',
                        $request,
                        $response,
                    );
            }

            

            if ($statusCode < 200 || $statusCode > 299) {
                throw new ApiException(
                    sprintf(
                        '[%d] Error connecting to the API (%s)',
                        $statusCode,
                        (string) $request->getUri()
                    ),
                    $statusCode,
                    $response->getHeaders(),
                    (string) $response->getBody()
                );
            }

            return $this->handleResponseWithDataType(
                '\PIndustries\SunCharts\Generated\Model\UserTokens',
                $request,
                $response,
            );
        } catch (ApiException $e) {
            switch ($e->getCode()) {
                case 200:
                    $data = ObjectSerializer::deserialize(
                        $e->getResponseBody(),
                        '\PIndustries\SunCharts\Generated\Model\UserTokens',
                        $e->getResponseHeaders()
                    );
                    $e->setResponseObject($data);
                    throw $e;
                default:
                    $data = ObjectSerializer::deserialize(
                        $e->getResponseBody(),
                        '\PIndustries\SunCharts\Generated\Model\Error',
                        $e->getResponseHeaders()
                    );
                    $e->setResponseObject($data);
                    throw $e;
            }
        

            throw $e;
        }
    }

    /**
     * Operation passkeyLoginFinishAsync
     *
     * Finish passkey sign-in → user tokens
     *
     * @param  object $body (required)
     * @param  string|null $x_partner_authorization White-label relay: &#x60;Bearer &lt;pt_… token | sk_… key&gt;&#x60; of the partner app (scope users.passthrough). Applies the app&#39;s auth settings. (optional)
     * @param  string|null $x_end_user_ip Relay only: the end user&#39;s IP for rate limiting. (optional)
     * @param  string $contentType The value for the Content-Type header. Check self::contentTypes['passkeyLoginFinish'] to see the possible values for this operation
     *
     * @throws \InvalidArgumentException
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function passkeyLoginFinishAsync($body, $x_partner_authorization = null, $x_end_user_ip = null, string $contentType = self::contentTypes['passkeyLoginFinish'][0])
    {
        return $this->passkeyLoginFinishAsyncWithHttpInfo($body, $x_partner_authorization, $x_end_user_ip, $contentType)
            ->then(
                function ($response) {
                    return $response[0];
                }
            );
    }

    /**
     * Operation passkeyLoginFinishAsyncWithHttpInfo
     *
     * Finish passkey sign-in → user tokens
     *
     * @param  object $body (required)
     * @param  string|null $x_partner_authorization White-label relay: &#x60;Bearer &lt;pt_… token | sk_… key&gt;&#x60; of the partner app (scope users.passthrough). Applies the app&#39;s auth settings. (optional)
     * @param  string|null $x_end_user_ip Relay only: the end user&#39;s IP for rate limiting. (optional)
     * @param  string $contentType The value for the Content-Type header. Check self::contentTypes['passkeyLoginFinish'] to see the possible values for this operation
     *
     * @throws \InvalidArgumentException
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function passkeyLoginFinishAsyncWithHttpInfo($body, $x_partner_authorization = null, $x_end_user_ip = null, string $contentType = self::contentTypes['passkeyLoginFinish'][0])
    {
        $returnType = '\PIndustries\SunCharts\Generated\Model\UserTokens';
        $request = $this->passkeyLoginFinishRequest($body, $x_partner_authorization, $x_end_user_ip, $contentType);

        return $this->client
            ->sendAsync($request, $this->createHttpClientOption())
            ->then(
                function ($response) use ($returnType) {
                    if ($returnType === '\SplFileObject') {
                        $content = $response->getBody(); //stream goes to serializer
                    } else {
                        $content = (string) $response->getBody();
                        if ($returnType !== 'string') {
                            $content = json_decode($content);
                        }
                    }

                    return [
                        ObjectSerializer::deserialize($content, $returnType, []),
                        $response->getStatusCode(),
                        $response->getHeaders()
                    ];
                },
                function ($exception) {
                    $response = $exception->getResponse();
                    $statusCode = $response->getStatusCode();
                    throw new ApiException(
                        sprintf(
                            '[%d] Error connecting to the API (%s)',
                            $statusCode,
                            $exception->getRequest()->getUri()
                        ),
                        $statusCode,
                        $response->getHeaders(),
                        (string) $response->getBody()
                    );
                }
            );
    }

    /**
     * Create request for operation 'passkeyLoginFinish'
     *
     * @param  object $body (required)
     * @param  string|null $x_partner_authorization White-label relay: &#x60;Bearer &lt;pt_… token | sk_… key&gt;&#x60; of the partner app (scope users.passthrough). Applies the app&#39;s auth settings. (optional)
     * @param  string|null $x_end_user_ip Relay only: the end user&#39;s IP for rate limiting. (optional)
     * @param  string $contentType The value for the Content-Type header. Check self::contentTypes['passkeyLoginFinish'] to see the possible values for this operation
     *
     * @throws \InvalidArgumentException
     * @return \GuzzleHttp\Psr7\Request
     */
    public function passkeyLoginFinishRequest($body, $x_partner_authorization = null, $x_end_user_ip = null, string $contentType = self::contentTypes['passkeyLoginFinish'][0])
    {

        // verify the required parameter 'body' is set
        if ($body === null || (is_array($body) && count($body) === 0)) {
            throw new \InvalidArgumentException(
                'Missing the required parameter $body when calling passkeyLoginFinish'
            );
        }




        $resourcePath = '/api/v1/auth/passkey/login/finish';
        $formParams = [];
        $queryParams = [];
        $headerParams = [];
        $httpBody = '';
        $multipart = false;


        // header params
        if ($x_partner_authorization !== null) {
            $headerParams['X-Partner-Authorization'] = ObjectSerializer::toHeaderValue($x_partner_authorization);
        }
        // header params
        if ($x_end_user_ip !== null) {
            $headerParams['X-End-User-IP'] = ObjectSerializer::toHeaderValue($x_end_user_ip);
        }



        $headers = $this->headerSelector->selectHeaders(
            ['application/json', ],
            $contentType,
            $multipart
        );

        // for model (json/xml)
        if (isset($body)) {
            if (stripos($headers['Content-Type'], 'application/json') !== false) {
                # if Content-Type contains "application/json", json_encode the body
                try {
                    $httpBody = json_encode(ObjectSerializer::sanitizeForSerialization($body), JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {
                    throw new \InvalidArgumentException('json_encode error: ' . $e->getMessage(), 0, $e);
                }
            } else {
                $httpBody = $body;
            }
        } elseif (count($formParams) > 0) {
            if ($multipart) {
                $multipartContents = [];
                foreach ($formParams as $formParamName => $formParamValue) {
                    $formParamValueItems = is_array($formParamValue) ? $formParamValue : [$formParamValue];
                    foreach ($formParamValueItems as $formParamValueItem) {
                        $multipartContents[] = [
                            'name' => $formParamName,
                            'contents' => $formParamValueItem
                        ];
                    }
                }
                // for HTTP post (form)
                $httpBody = new MultipartStream($multipartContents);

            } elseif (stripos($headers['Content-Type'], 'application/json') !== false) {
                # if Content-Type contains "application/json", json_encode the form parameters
                try {
                    $httpBody = json_encode($formParams, JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {
                    throw new \InvalidArgumentException('json_encode error: ' . $e->getMessage(), 0, $e);
                }
            } else {
                // for HTTP post (form)
                $httpBody = ObjectSerializer::buildQuery($formParams);
            }
        }


        $defaultHeaders = [];
        if ($this->config->getUserAgent()) {
            $defaultHeaders['User-Agent'] = $this->config->getUserAgent();
        }

        $headers = array_merge(
            $defaultHeaders,
            $headerParams,
            $headers
        );

        $operationHost = $this->config->getHost();
        $query = ObjectSerializer::buildQuery($queryParams);
        return new Request(
            'POST',
            $operationHost . $resourcePath . ($query ? "?{$query}" : ''),
            $headers,
            $httpBody
        );
    }

    /**
     * Operation passkeyLoginStart
     *
     * Begin passkey sign-in (WebAuthn options)
     *
     * @param  object $body body (required)
     * @param  string|null $x_partner_authorization White-label relay: &#x60;Bearer &lt;pt_… token | sk_… key&gt;&#x60; of the partner app (scope users.passthrough). Applies the app&#39;s auth settings. (optional)
     * @param  string|null $x_end_user_ip Relay only: the end user&#39;s IP for rate limiting. (optional)
     * @param  string $contentType The value for the Content-Type header. Check self::contentTypes['passkeyLoginStart'] to see the possible values for this operation
     *
     * @throws \PIndustries\SunCharts\Generated\ApiException on non-2xx response or if the response body is not in the expected format
     * @throws \InvalidArgumentException
     * @return object|\PIndustries\SunCharts\Generated\Model\Error
     */
    public function passkeyLoginStart($body, $x_partner_authorization = null, $x_end_user_ip = null, string $contentType = self::contentTypes['passkeyLoginStart'][0])
    {
        list($response) = $this->passkeyLoginStartWithHttpInfo($body, $x_partner_authorization, $x_end_user_ip, $contentType);
        return $response;
    }

    /**
     * Operation passkeyLoginStartWithHttpInfo
     *
     * Begin passkey sign-in (WebAuthn options)
     *
     * @param  object $body (required)
     * @param  string|null $x_partner_authorization White-label relay: &#x60;Bearer &lt;pt_… token | sk_… key&gt;&#x60; of the partner app (scope users.passthrough). Applies the app&#39;s auth settings. (optional)
     * @param  string|null $x_end_user_ip Relay only: the end user&#39;s IP for rate limiting. (optional)
     * @param  string $contentType The value for the Content-Type header. Check self::contentTypes['passkeyLoginStart'] to see the possible values for this operation
     *
     * @throws \PIndustries\SunCharts\Generated\ApiException on non-2xx response or if the response body is not in the expected format
     * @throws \InvalidArgumentException
     * @return array of object|\PIndustries\SunCharts\Generated\Model\Error, HTTP status code, HTTP response headers (array of strings)
     */
    public function passkeyLoginStartWithHttpInfo($body, $x_partner_authorization = null, $x_end_user_ip = null, string $contentType = self::contentTypes['passkeyLoginStart'][0])
    {
        $request = $this->passkeyLoginStartRequest($body, $x_partner_authorization, $x_end_user_ip, $contentType);

        try {
            $options = $this->createHttpClientOption();
            try {
                $response = $this->client->send($request, $options);
            } catch (RequestException $e) {
                throw new ApiException(
                    "[{$e->getCode()}] {$e->getMessage()}",
                    (int) $e->getCode(),
                    $e->getResponse() ? $e->getResponse()->getHeaders() : null,
                    $e->getResponse() ? (string) $e->getResponse()->getBody() : null
                );
            } catch (ConnectException $e) {
                throw new ApiException(
                    "[{$e->getCode()}] {$e->getMessage()}",
                    (int) $e->getCode(),
                    null,
                    null
                );
            }

            $statusCode = $response->getStatusCode();


            switch($statusCode) {
                case 200:
                    return $this->handleResponseWithDataType(
                        'object',
                        $request,
                        $response,
                    );
                default:
                    return $this->handleResponseWithDataType(
                        '\PIndustries\SunCharts\Generated\Model\Error',
                        $request,
                        $response,
                    );
            }

            

            if ($statusCode < 200 || $statusCode > 299) {
                throw new ApiException(
                    sprintf(
                        '[%d] Error connecting to the API (%s)',
                        $statusCode,
                        (string) $request->getUri()
                    ),
                    $statusCode,
                    $response->getHeaders(),
                    (string) $response->getBody()
                );
            }

            return $this->handleResponseWithDataType(
                'object',
                $request,
                $response,
            );
        } catch (ApiException $e) {
            switch ($e->getCode()) {
                case 200:
                    $data = ObjectSerializer::deserialize(
                        $e->getResponseBody(),
                        'object',
                        $e->getResponseHeaders()
                    );
                    $e->setResponseObject($data);
                    throw $e;
                default:
                    $data = ObjectSerializer::deserialize(
                        $e->getResponseBody(),
                        '\PIndustries\SunCharts\Generated\Model\Error',
                        $e->getResponseHeaders()
                    );
                    $e->setResponseObject($data);
                    throw $e;
            }
        

            throw $e;
        }
    }

    /**
     * Operation passkeyLoginStartAsync
     *
     * Begin passkey sign-in (WebAuthn options)
     *
     * @param  object $body (required)
     * @param  string|null $x_partner_authorization White-label relay: &#x60;Bearer &lt;pt_… token | sk_… key&gt;&#x60; of the partner app (scope users.passthrough). Applies the app&#39;s auth settings. (optional)
     * @param  string|null $x_end_user_ip Relay only: the end user&#39;s IP for rate limiting. (optional)
     * @param  string $contentType The value for the Content-Type header. Check self::contentTypes['passkeyLoginStart'] to see the possible values for this operation
     *
     * @throws \InvalidArgumentException
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function passkeyLoginStartAsync($body, $x_partner_authorization = null, $x_end_user_ip = null, string $contentType = self::contentTypes['passkeyLoginStart'][0])
    {
        return $this->passkeyLoginStartAsyncWithHttpInfo($body, $x_partner_authorization, $x_end_user_ip, $contentType)
            ->then(
                function ($response) {
                    return $response[0];
                }
            );
    }

    /**
     * Operation passkeyLoginStartAsyncWithHttpInfo
     *
     * Begin passkey sign-in (WebAuthn options)
     *
     * @param  object $body (required)
     * @param  string|null $x_partner_authorization White-label relay: &#x60;Bearer &lt;pt_… token | sk_… key&gt;&#x60; of the partner app (scope users.passthrough). Applies the app&#39;s auth settings. (optional)
     * @param  string|null $x_end_user_ip Relay only: the end user&#39;s IP for rate limiting. (optional)
     * @param  string $contentType The value for the Content-Type header. Check self::contentTypes['passkeyLoginStart'] to see the possible values for this operation
     *
     * @throws \InvalidArgumentException
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function passkeyLoginStartAsyncWithHttpInfo($body, $x_partner_authorization = null, $x_end_user_ip = null, string $contentType = self::contentTypes['passkeyLoginStart'][0])
    {
        $returnType = 'object';
        $request = $this->passkeyLoginStartRequest($body, $x_partner_authorization, $x_end_user_ip, $contentType);

        return $this->client
            ->sendAsync($request, $this->createHttpClientOption())
            ->then(
                function ($response) use ($returnType) {
                    if ($returnType === '\SplFileObject') {
                        $content = $response->getBody(); //stream goes to serializer
                    } else {
                        $content = (string) $response->getBody();
                        if ($returnType !== 'string') {
                            $content = json_decode($content);
                        }
                    }

                    return [
                        ObjectSerializer::deserialize($content, $returnType, []),
                        $response->getStatusCode(),
                        $response->getHeaders()
                    ];
                },
                function ($exception) {
                    $response = $exception->getResponse();
                    $statusCode = $response->getStatusCode();
                    throw new ApiException(
                        sprintf(
                            '[%d] Error connecting to the API (%s)',
                            $statusCode,
                            $exception->getRequest()->getUri()
                        ),
                        $statusCode,
                        $response->getHeaders(),
                        (string) $response->getBody()
                    );
                }
            );
    }

    /**
     * Create request for operation 'passkeyLoginStart'
     *
     * @param  object $body (required)
     * @param  string|null $x_partner_authorization White-label relay: &#x60;Bearer &lt;pt_… token | sk_… key&gt;&#x60; of the partner app (scope users.passthrough). Applies the app&#39;s auth settings. (optional)
     * @param  string|null $x_end_user_ip Relay only: the end user&#39;s IP for rate limiting. (optional)
     * @param  string $contentType The value for the Content-Type header. Check self::contentTypes['passkeyLoginStart'] to see the possible values for this operation
     *
     * @throws \InvalidArgumentException
     * @return \GuzzleHttp\Psr7\Request
     */
    public function passkeyLoginStartRequest($body, $x_partner_authorization = null, $x_end_user_ip = null, string $contentType = self::contentTypes['passkeyLoginStart'][0])
    {

        // verify the required parameter 'body' is set
        if ($body === null || (is_array($body) && count($body) === 0)) {
            throw new \InvalidArgumentException(
                'Missing the required parameter $body when calling passkeyLoginStart'
            );
        }




        $resourcePath = '/api/v1/auth/passkey/login/start';
        $formParams = [];
        $queryParams = [];
        $headerParams = [];
        $httpBody = '';
        $multipart = false;


        // header params
        if ($x_partner_authorization !== null) {
            $headerParams['X-Partner-Authorization'] = ObjectSerializer::toHeaderValue($x_partner_authorization);
        }
        // header params
        if ($x_end_user_ip !== null) {
            $headerParams['X-End-User-IP'] = ObjectSerializer::toHeaderValue($x_end_user_ip);
        }



        $headers = $this->headerSelector->selectHeaders(
            ['application/json', ],
            $contentType,
            $multipart
        );

        // for model (json/xml)
        if (isset($body)) {
            if (stripos($headers['Content-Type'], 'application/json') !== false) {
                # if Content-Type contains "application/json", json_encode the body
                try {
                    $httpBody = json_encode(ObjectSerializer::sanitizeForSerialization($body), JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {
                    throw new \InvalidArgumentException('json_encode error: ' . $e->getMessage(), 0, $e);
                }
            } else {
                $httpBody = $body;
            }
        } elseif (count($formParams) > 0) {
            if ($multipart) {
                $multipartContents = [];
                foreach ($formParams as $formParamName => $formParamValue) {
                    $formParamValueItems = is_array($formParamValue) ? $formParamValue : [$formParamValue];
                    foreach ($formParamValueItems as $formParamValueItem) {
                        $multipartContents[] = [
                            'name' => $formParamName,
                            'contents' => $formParamValueItem
                        ];
                    }
                }
                // for HTTP post (form)
                $httpBody = new MultipartStream($multipartContents);

            } elseif (stripos($headers['Content-Type'], 'application/json') !== false) {
                # if Content-Type contains "application/json", json_encode the form parameters
                try {
                    $httpBody = json_encode($formParams, JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {
                    throw new \InvalidArgumentException('json_encode error: ' . $e->getMessage(), 0, $e);
                }
            } else {
                // for HTTP post (form)
                $httpBody = ObjectSerializer::buildQuery($formParams);
            }
        }


        $defaultHeaders = [];
        if ($this->config->getUserAgent()) {
            $defaultHeaders['User-Agent'] = $this->config->getUserAgent();
        }

        $headers = array_merge(
            $defaultHeaders,
            $headerParams,
            $headers
        );

        $operationHost = $this->config->getHost();
        $query = ObjectSerializer::buildQuery($queryParams);
        return new Request(
            'POST',
            $operationHost . $resourcePath . ($query ? "?{$query}" : ''),
            $headers,
            $httpBody
        );
    }

    /**
     * Operation passkeyRegisterFinish
     *
     * Finish passkey registration
     *
     * @param  object $body body (required)
     * @param  string|null $x_partner_authorization White-label relay: &#x60;Bearer &lt;pt_… token | sk_… key&gt;&#x60; of the partner app (scope users.passthrough). Applies the app&#39;s auth settings. (optional)
     * @param  string|null $x_end_user_ip Relay only: the end user&#39;s IP for rate limiting. (optional)
     * @param  string $contentType The value for the Content-Type header. Check self::contentTypes['passkeyRegisterFinish'] to see the possible values for this operation
     *
     * @throws \PIndustries\SunCharts\Generated\ApiException on non-2xx response or if the response body is not in the expected format
     * @throws \InvalidArgumentException
     * @return object|\PIndustries\SunCharts\Generated\Model\Error
     */
    public function passkeyRegisterFinish($body, $x_partner_authorization = null, $x_end_user_ip = null, string $contentType = self::contentTypes['passkeyRegisterFinish'][0])
    {
        list($response) = $this->passkeyRegisterFinishWithHttpInfo($body, $x_partner_authorization, $x_end_user_ip, $contentType);
        return $response;
    }

    /**
     * Operation passkeyRegisterFinishWithHttpInfo
     *
     * Finish passkey registration
     *
     * @param  object $body (required)
     * @param  string|null $x_partner_authorization White-label relay: &#x60;Bearer &lt;pt_… token | sk_… key&gt;&#x60; of the partner app (scope users.passthrough). Applies the app&#39;s auth settings. (optional)
     * @param  string|null $x_end_user_ip Relay only: the end user&#39;s IP for rate limiting. (optional)
     * @param  string $contentType The value for the Content-Type header. Check self::contentTypes['passkeyRegisterFinish'] to see the possible values for this operation
     *
     * @throws \PIndustries\SunCharts\Generated\ApiException on non-2xx response or if the response body is not in the expected format
     * @throws \InvalidArgumentException
     * @return array of object|\PIndustries\SunCharts\Generated\Model\Error, HTTP status code, HTTP response headers (array of strings)
     */
    public function passkeyRegisterFinishWithHttpInfo($body, $x_partner_authorization = null, $x_end_user_ip = null, string $contentType = self::contentTypes['passkeyRegisterFinish'][0])
    {
        $request = $this->passkeyRegisterFinishRequest($body, $x_partner_authorization, $x_end_user_ip, $contentType);

        try {
            $options = $this->createHttpClientOption();
            try {
                $response = $this->client->send($request, $options);
            } catch (RequestException $e) {
                throw new ApiException(
                    "[{$e->getCode()}] {$e->getMessage()}",
                    (int) $e->getCode(),
                    $e->getResponse() ? $e->getResponse()->getHeaders() : null,
                    $e->getResponse() ? (string) $e->getResponse()->getBody() : null
                );
            } catch (ConnectException $e) {
                throw new ApiException(
                    "[{$e->getCode()}] {$e->getMessage()}",
                    (int) $e->getCode(),
                    null,
                    null
                );
            }

            $statusCode = $response->getStatusCode();


            switch($statusCode) {
                case 200:
                    return $this->handleResponseWithDataType(
                        'object',
                        $request,
                        $response,
                    );
                default:
                    return $this->handleResponseWithDataType(
                        '\PIndustries\SunCharts\Generated\Model\Error',
                        $request,
                        $response,
                    );
            }

            

            if ($statusCode < 200 || $statusCode > 299) {
                throw new ApiException(
                    sprintf(
                        '[%d] Error connecting to the API (%s)',
                        $statusCode,
                        (string) $request->getUri()
                    ),
                    $statusCode,
                    $response->getHeaders(),
                    (string) $response->getBody()
                );
            }

            return $this->handleResponseWithDataType(
                'object',
                $request,
                $response,
            );
        } catch (ApiException $e) {
            switch ($e->getCode()) {
                case 200:
                    $data = ObjectSerializer::deserialize(
                        $e->getResponseBody(),
                        'object',
                        $e->getResponseHeaders()
                    );
                    $e->setResponseObject($data);
                    throw $e;
                default:
                    $data = ObjectSerializer::deserialize(
                        $e->getResponseBody(),
                        '\PIndustries\SunCharts\Generated\Model\Error',
                        $e->getResponseHeaders()
                    );
                    $e->setResponseObject($data);
                    throw $e;
            }
        

            throw $e;
        }
    }

    /**
     * Operation passkeyRegisterFinishAsync
     *
     * Finish passkey registration
     *
     * @param  object $body (required)
     * @param  string|null $x_partner_authorization White-label relay: &#x60;Bearer &lt;pt_… token | sk_… key&gt;&#x60; of the partner app (scope users.passthrough). Applies the app&#39;s auth settings. (optional)
     * @param  string|null $x_end_user_ip Relay only: the end user&#39;s IP for rate limiting. (optional)
     * @param  string $contentType The value for the Content-Type header. Check self::contentTypes['passkeyRegisterFinish'] to see the possible values for this operation
     *
     * @throws \InvalidArgumentException
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function passkeyRegisterFinishAsync($body, $x_partner_authorization = null, $x_end_user_ip = null, string $contentType = self::contentTypes['passkeyRegisterFinish'][0])
    {
        return $this->passkeyRegisterFinishAsyncWithHttpInfo($body, $x_partner_authorization, $x_end_user_ip, $contentType)
            ->then(
                function ($response) {
                    return $response[0];
                }
            );
    }

    /**
     * Operation passkeyRegisterFinishAsyncWithHttpInfo
     *
     * Finish passkey registration
     *
     * @param  object $body (required)
     * @param  string|null $x_partner_authorization White-label relay: &#x60;Bearer &lt;pt_… token | sk_… key&gt;&#x60; of the partner app (scope users.passthrough). Applies the app&#39;s auth settings. (optional)
     * @param  string|null $x_end_user_ip Relay only: the end user&#39;s IP for rate limiting. (optional)
     * @param  string $contentType The value for the Content-Type header. Check self::contentTypes['passkeyRegisterFinish'] to see the possible values for this operation
     *
     * @throws \InvalidArgumentException
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function passkeyRegisterFinishAsyncWithHttpInfo($body, $x_partner_authorization = null, $x_end_user_ip = null, string $contentType = self::contentTypes['passkeyRegisterFinish'][0])
    {
        $returnType = 'object';
        $request = $this->passkeyRegisterFinishRequest($body, $x_partner_authorization, $x_end_user_ip, $contentType);

        return $this->client
            ->sendAsync($request, $this->createHttpClientOption())
            ->then(
                function ($response) use ($returnType) {
                    if ($returnType === '\SplFileObject') {
                        $content = $response->getBody(); //stream goes to serializer
                    } else {
                        $content = (string) $response->getBody();
                        if ($returnType !== 'string') {
                            $content = json_decode($content);
                        }
                    }

                    return [
                        ObjectSerializer::deserialize($content, $returnType, []),
                        $response->getStatusCode(),
                        $response->getHeaders()
                    ];
                },
                function ($exception) {
                    $response = $exception->getResponse();
                    $statusCode = $response->getStatusCode();
                    throw new ApiException(
                        sprintf(
                            '[%d] Error connecting to the API (%s)',
                            $statusCode,
                            $exception->getRequest()->getUri()
                        ),
                        $statusCode,
                        $response->getHeaders(),
                        (string) $response->getBody()
                    );
                }
            );
    }

    /**
     * Create request for operation 'passkeyRegisterFinish'
     *
     * @param  object $body (required)
     * @param  string|null $x_partner_authorization White-label relay: &#x60;Bearer &lt;pt_… token | sk_… key&gt;&#x60; of the partner app (scope users.passthrough). Applies the app&#39;s auth settings. (optional)
     * @param  string|null $x_end_user_ip Relay only: the end user&#39;s IP for rate limiting. (optional)
     * @param  string $contentType The value for the Content-Type header. Check self::contentTypes['passkeyRegisterFinish'] to see the possible values for this operation
     *
     * @throws \InvalidArgumentException
     * @return \GuzzleHttp\Psr7\Request
     */
    public function passkeyRegisterFinishRequest($body, $x_partner_authorization = null, $x_end_user_ip = null, string $contentType = self::contentTypes['passkeyRegisterFinish'][0])
    {

        // verify the required parameter 'body' is set
        if ($body === null || (is_array($body) && count($body) === 0)) {
            throw new \InvalidArgumentException(
                'Missing the required parameter $body when calling passkeyRegisterFinish'
            );
        }




        $resourcePath = '/api/v1/auth/passkey/register/finish';
        $formParams = [];
        $queryParams = [];
        $headerParams = [];
        $httpBody = '';
        $multipart = false;


        // header params
        if ($x_partner_authorization !== null) {
            $headerParams['X-Partner-Authorization'] = ObjectSerializer::toHeaderValue($x_partner_authorization);
        }
        // header params
        if ($x_end_user_ip !== null) {
            $headerParams['X-End-User-IP'] = ObjectSerializer::toHeaderValue($x_end_user_ip);
        }



        $headers = $this->headerSelector->selectHeaders(
            ['application/json', ],
            $contentType,
            $multipart
        );

        // for model (json/xml)
        if (isset($body)) {
            if (stripos($headers['Content-Type'], 'application/json') !== false) {
                # if Content-Type contains "application/json", json_encode the body
                try {
                    $httpBody = json_encode(ObjectSerializer::sanitizeForSerialization($body), JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {
                    throw new \InvalidArgumentException('json_encode error: ' . $e->getMessage(), 0, $e);
                }
            } else {
                $httpBody = $body;
            }
        } elseif (count($formParams) > 0) {
            if ($multipart) {
                $multipartContents = [];
                foreach ($formParams as $formParamName => $formParamValue) {
                    $formParamValueItems = is_array($formParamValue) ? $formParamValue : [$formParamValue];
                    foreach ($formParamValueItems as $formParamValueItem) {
                        $multipartContents[] = [
                            'name' => $formParamName,
                            'contents' => $formParamValueItem
                        ];
                    }
                }
                // for HTTP post (form)
                $httpBody = new MultipartStream($multipartContents);

            } elseif (stripos($headers['Content-Type'], 'application/json') !== false) {
                # if Content-Type contains "application/json", json_encode the form parameters
                try {
                    $httpBody = json_encode($formParams, JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {
                    throw new \InvalidArgumentException('json_encode error: ' . $e->getMessage(), 0, $e);
                }
            } else {
                // for HTTP post (form)
                $httpBody = ObjectSerializer::buildQuery($formParams);
            }
        }

        // this endpoint requires Bearer (JWT) authentication (access token)
        if (!empty($this->config->getAccessToken())) {
            $headers['Authorization'] = 'Bearer ' . $this->config->getAccessToken();
        }

        $defaultHeaders = [];
        if ($this->config->getUserAgent()) {
            $defaultHeaders['User-Agent'] = $this->config->getUserAgent();
        }

        $headers = array_merge(
            $defaultHeaders,
            $headerParams,
            $headers
        );

        $operationHost = $this->config->getHost();
        $query = ObjectSerializer::buildQuery($queryParams);
        return new Request(
            'POST',
            $operationHost . $resourcePath . ($query ? "?{$query}" : ''),
            $headers,
            $httpBody
        );
    }

    /**
     * Operation passkeyRegisterStart
     *
     * Begin passkey registration (user token required)
     *
     * @param  object $body body (required)
     * @param  string|null $x_partner_authorization White-label relay: &#x60;Bearer &lt;pt_… token | sk_… key&gt;&#x60; of the partner app (scope users.passthrough). Applies the app&#39;s auth settings. (optional)
     * @param  string|null $x_end_user_ip Relay only: the end user&#39;s IP for rate limiting. (optional)
     * @param  string $contentType The value for the Content-Type header. Check self::contentTypes['passkeyRegisterStart'] to see the possible values for this operation
     *
     * @throws \PIndustries\SunCharts\Generated\ApiException on non-2xx response or if the response body is not in the expected format
     * @throws \InvalidArgumentException
     * @return object|\PIndustries\SunCharts\Generated\Model\Error
     */
    public function passkeyRegisterStart($body, $x_partner_authorization = null, $x_end_user_ip = null, string $contentType = self::contentTypes['passkeyRegisterStart'][0])
    {
        list($response) = $this->passkeyRegisterStartWithHttpInfo($body, $x_partner_authorization, $x_end_user_ip, $contentType);
        return $response;
    }

    /**
     * Operation passkeyRegisterStartWithHttpInfo
     *
     * Begin passkey registration (user token required)
     *
     * @param  object $body (required)
     * @param  string|null $x_partner_authorization White-label relay: &#x60;Bearer &lt;pt_… token | sk_… key&gt;&#x60; of the partner app (scope users.passthrough). Applies the app&#39;s auth settings. (optional)
     * @param  string|null $x_end_user_ip Relay only: the end user&#39;s IP for rate limiting. (optional)
     * @param  string $contentType The value for the Content-Type header. Check self::contentTypes['passkeyRegisterStart'] to see the possible values for this operation
     *
     * @throws \PIndustries\SunCharts\Generated\ApiException on non-2xx response or if the response body is not in the expected format
     * @throws \InvalidArgumentException
     * @return array of object|\PIndustries\SunCharts\Generated\Model\Error, HTTP status code, HTTP response headers (array of strings)
     */
    public function passkeyRegisterStartWithHttpInfo($body, $x_partner_authorization = null, $x_end_user_ip = null, string $contentType = self::contentTypes['passkeyRegisterStart'][0])
    {
        $request = $this->passkeyRegisterStartRequest($body, $x_partner_authorization, $x_end_user_ip, $contentType);

        try {
            $options = $this->createHttpClientOption();
            try {
                $response = $this->client->send($request, $options);
            } catch (RequestException $e) {
                throw new ApiException(
                    "[{$e->getCode()}] {$e->getMessage()}",
                    (int) $e->getCode(),
                    $e->getResponse() ? $e->getResponse()->getHeaders() : null,
                    $e->getResponse() ? (string) $e->getResponse()->getBody() : null
                );
            } catch (ConnectException $e) {
                throw new ApiException(
                    "[{$e->getCode()}] {$e->getMessage()}",
                    (int) $e->getCode(),
                    null,
                    null
                );
            }

            $statusCode = $response->getStatusCode();


            switch($statusCode) {
                case 200:
                    return $this->handleResponseWithDataType(
                        'object',
                        $request,
                        $response,
                    );
                default:
                    return $this->handleResponseWithDataType(
                        '\PIndustries\SunCharts\Generated\Model\Error',
                        $request,
                        $response,
                    );
            }

            

            if ($statusCode < 200 || $statusCode > 299) {
                throw new ApiException(
                    sprintf(
                        '[%d] Error connecting to the API (%s)',
                        $statusCode,
                        (string) $request->getUri()
                    ),
                    $statusCode,
                    $response->getHeaders(),
                    (string) $response->getBody()
                );
            }

            return $this->handleResponseWithDataType(
                'object',
                $request,
                $response,
            );
        } catch (ApiException $e) {
            switch ($e->getCode()) {
                case 200:
                    $data = ObjectSerializer::deserialize(
                        $e->getResponseBody(),
                        'object',
                        $e->getResponseHeaders()
                    );
                    $e->setResponseObject($data);
                    throw $e;
                default:
                    $data = ObjectSerializer::deserialize(
                        $e->getResponseBody(),
                        '\PIndustries\SunCharts\Generated\Model\Error',
                        $e->getResponseHeaders()
                    );
                    $e->setResponseObject($data);
                    throw $e;
            }
        

            throw $e;
        }
    }

    /**
     * Operation passkeyRegisterStartAsync
     *
     * Begin passkey registration (user token required)
     *
     * @param  object $body (required)
     * @param  string|null $x_partner_authorization White-label relay: &#x60;Bearer &lt;pt_… token | sk_… key&gt;&#x60; of the partner app (scope users.passthrough). Applies the app&#39;s auth settings. (optional)
     * @param  string|null $x_end_user_ip Relay only: the end user&#39;s IP for rate limiting. (optional)
     * @param  string $contentType The value for the Content-Type header. Check self::contentTypes['passkeyRegisterStart'] to see the possible values for this operation
     *
     * @throws \InvalidArgumentException
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function passkeyRegisterStartAsync($body, $x_partner_authorization = null, $x_end_user_ip = null, string $contentType = self::contentTypes['passkeyRegisterStart'][0])
    {
        return $this->passkeyRegisterStartAsyncWithHttpInfo($body, $x_partner_authorization, $x_end_user_ip, $contentType)
            ->then(
                function ($response) {
                    return $response[0];
                }
            );
    }

    /**
     * Operation passkeyRegisterStartAsyncWithHttpInfo
     *
     * Begin passkey registration (user token required)
     *
     * @param  object $body (required)
     * @param  string|null $x_partner_authorization White-label relay: &#x60;Bearer &lt;pt_… token | sk_… key&gt;&#x60; of the partner app (scope users.passthrough). Applies the app&#39;s auth settings. (optional)
     * @param  string|null $x_end_user_ip Relay only: the end user&#39;s IP for rate limiting. (optional)
     * @param  string $contentType The value for the Content-Type header. Check self::contentTypes['passkeyRegisterStart'] to see the possible values for this operation
     *
     * @throws \InvalidArgumentException
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function passkeyRegisterStartAsyncWithHttpInfo($body, $x_partner_authorization = null, $x_end_user_ip = null, string $contentType = self::contentTypes['passkeyRegisterStart'][0])
    {
        $returnType = 'object';
        $request = $this->passkeyRegisterStartRequest($body, $x_partner_authorization, $x_end_user_ip, $contentType);

        return $this->client
            ->sendAsync($request, $this->createHttpClientOption())
            ->then(
                function ($response) use ($returnType) {
                    if ($returnType === '\SplFileObject') {
                        $content = $response->getBody(); //stream goes to serializer
                    } else {
                        $content = (string) $response->getBody();
                        if ($returnType !== 'string') {
                            $content = json_decode($content);
                        }
                    }

                    return [
                        ObjectSerializer::deserialize($content, $returnType, []),
                        $response->getStatusCode(),
                        $response->getHeaders()
                    ];
                },
                function ($exception) {
                    $response = $exception->getResponse();
                    $statusCode = $response->getStatusCode();
                    throw new ApiException(
                        sprintf(
                            '[%d] Error connecting to the API (%s)',
                            $statusCode,
                            $exception->getRequest()->getUri()
                        ),
                        $statusCode,
                        $response->getHeaders(),
                        (string) $response->getBody()
                    );
                }
            );
    }

    /**
     * Create request for operation 'passkeyRegisterStart'
     *
     * @param  object $body (required)
     * @param  string|null $x_partner_authorization White-label relay: &#x60;Bearer &lt;pt_… token | sk_… key&gt;&#x60; of the partner app (scope users.passthrough). Applies the app&#39;s auth settings. (optional)
     * @param  string|null $x_end_user_ip Relay only: the end user&#39;s IP for rate limiting. (optional)
     * @param  string $contentType The value for the Content-Type header. Check self::contentTypes['passkeyRegisterStart'] to see the possible values for this operation
     *
     * @throws \InvalidArgumentException
     * @return \GuzzleHttp\Psr7\Request
     */
    public function passkeyRegisterStartRequest($body, $x_partner_authorization = null, $x_end_user_ip = null, string $contentType = self::contentTypes['passkeyRegisterStart'][0])
    {

        // verify the required parameter 'body' is set
        if ($body === null || (is_array($body) && count($body) === 0)) {
            throw new \InvalidArgumentException(
                'Missing the required parameter $body when calling passkeyRegisterStart'
            );
        }




        $resourcePath = '/api/v1/auth/passkey/register/start';
        $formParams = [];
        $queryParams = [];
        $headerParams = [];
        $httpBody = '';
        $multipart = false;


        // header params
        if ($x_partner_authorization !== null) {
            $headerParams['X-Partner-Authorization'] = ObjectSerializer::toHeaderValue($x_partner_authorization);
        }
        // header params
        if ($x_end_user_ip !== null) {
            $headerParams['X-End-User-IP'] = ObjectSerializer::toHeaderValue($x_end_user_ip);
        }



        $headers = $this->headerSelector->selectHeaders(
            ['application/json', ],
            $contentType,
            $multipart
        );

        // for model (json/xml)
        if (isset($body)) {
            if (stripos($headers['Content-Type'], 'application/json') !== false) {
                # if Content-Type contains "application/json", json_encode the body
                try {
                    $httpBody = json_encode(ObjectSerializer::sanitizeForSerialization($body), JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {
                    throw new \InvalidArgumentException('json_encode error: ' . $e->getMessage(), 0, $e);
                }
            } else {
                $httpBody = $body;
            }
        } elseif (count($formParams) > 0) {
            if ($multipart) {
                $multipartContents = [];
                foreach ($formParams as $formParamName => $formParamValue) {
                    $formParamValueItems = is_array($formParamValue) ? $formParamValue : [$formParamValue];
                    foreach ($formParamValueItems as $formParamValueItem) {
                        $multipartContents[] = [
                            'name' => $formParamName,
                            'contents' => $formParamValueItem
                        ];
                    }
                }
                // for HTTP post (form)
                $httpBody = new MultipartStream($multipartContents);

            } elseif (stripos($headers['Content-Type'], 'application/json') !== false) {
                # if Content-Type contains "application/json", json_encode the form parameters
                try {
                    $httpBody = json_encode($formParams, JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {
                    throw new \InvalidArgumentException('json_encode error: ' . $e->getMessage(), 0, $e);
                }
            } else {
                // for HTTP post (form)
                $httpBody = ObjectSerializer::buildQuery($formParams);
            }
        }

        // this endpoint requires Bearer (JWT) authentication (access token)
        if (!empty($this->config->getAccessToken())) {
            $headers['Authorization'] = 'Bearer ' . $this->config->getAccessToken();
        }

        $defaultHeaders = [];
        if ($this->config->getUserAgent()) {
            $defaultHeaders['User-Agent'] = $this->config->getUserAgent();
        }

        $headers = array_merge(
            $defaultHeaders,
            $headerParams,
            $headers
        );

        $operationHost = $this->config->getHost();
        $query = ObjectSerializer::buildQuery($queryParams);
        return new Request(
            'POST',
            $operationHost . $resourcePath . ($query ? "?{$query}" : ''),
            $headers,
            $httpBody
        );
    }

    /**
     * Operation refresh
     *
     * Rotate the refresh token
     *
     * @param  \PIndustries\SunCharts\Generated\Model\RefreshRequest $refresh_request refresh_request (required)
     * @param  string $contentType The value for the Content-Type header. Check self::contentTypes['refresh'] to see the possible values for this operation
     *
     * @throws \PIndustries\SunCharts\Generated\ApiException on non-2xx response or if the response body is not in the expected format
     * @throws \InvalidArgumentException
     * @return \PIndustries\SunCharts\Generated\Model\UserTokens|\PIndustries\SunCharts\Generated\Model\Error
     */
    public function refresh($refresh_request, string $contentType = self::contentTypes['refresh'][0])
    {
        list($response) = $this->refreshWithHttpInfo($refresh_request, $contentType);
        return $response;
    }

    /**
     * Operation refreshWithHttpInfo
     *
     * Rotate the refresh token
     *
     * @param  \PIndustries\SunCharts\Generated\Model\RefreshRequest $refresh_request (required)
     * @param  string $contentType The value for the Content-Type header. Check self::contentTypes['refresh'] to see the possible values for this operation
     *
     * @throws \PIndustries\SunCharts\Generated\ApiException on non-2xx response or if the response body is not in the expected format
     * @throws \InvalidArgumentException
     * @return array of \PIndustries\SunCharts\Generated\Model\UserTokens|\PIndustries\SunCharts\Generated\Model\Error, HTTP status code, HTTP response headers (array of strings)
     */
    public function refreshWithHttpInfo($refresh_request, string $contentType = self::contentTypes['refresh'][0])
    {
        $request = $this->refreshRequest($refresh_request, $contentType);

        try {
            $options = $this->createHttpClientOption();
            try {
                $response = $this->client->send($request, $options);
            } catch (RequestException $e) {
                throw new ApiException(
                    "[{$e->getCode()}] {$e->getMessage()}",
                    (int) $e->getCode(),
                    $e->getResponse() ? $e->getResponse()->getHeaders() : null,
                    $e->getResponse() ? (string) $e->getResponse()->getBody() : null
                );
            } catch (ConnectException $e) {
                throw new ApiException(
                    "[{$e->getCode()}] {$e->getMessage()}",
                    (int) $e->getCode(),
                    null,
                    null
                );
            }

            $statusCode = $response->getStatusCode();


            switch($statusCode) {
                case 200:
                    return $this->handleResponseWithDataType(
                        '\PIndustries\SunCharts\Generated\Model\UserTokens',
                        $request,
                        $response,
                    );
                default:
                    return $this->handleResponseWithDataType(
                        '\PIndustries\SunCharts\Generated\Model\Error',
                        $request,
                        $response,
                    );
            }

            

            if ($statusCode < 200 || $statusCode > 299) {
                throw new ApiException(
                    sprintf(
                        '[%d] Error connecting to the API (%s)',
                        $statusCode,
                        (string) $request->getUri()
                    ),
                    $statusCode,
                    $response->getHeaders(),
                    (string) $response->getBody()
                );
            }

            return $this->handleResponseWithDataType(
                '\PIndustries\SunCharts\Generated\Model\UserTokens',
                $request,
                $response,
            );
        } catch (ApiException $e) {
            switch ($e->getCode()) {
                case 200:
                    $data = ObjectSerializer::deserialize(
                        $e->getResponseBody(),
                        '\PIndustries\SunCharts\Generated\Model\UserTokens',
                        $e->getResponseHeaders()
                    );
                    $e->setResponseObject($data);
                    throw $e;
                default:
                    $data = ObjectSerializer::deserialize(
                        $e->getResponseBody(),
                        '\PIndustries\SunCharts\Generated\Model\Error',
                        $e->getResponseHeaders()
                    );
                    $e->setResponseObject($data);
                    throw $e;
            }
        

            throw $e;
        }
    }

    /**
     * Operation refreshAsync
     *
     * Rotate the refresh token
     *
     * @param  \PIndustries\SunCharts\Generated\Model\RefreshRequest $refresh_request (required)
     * @param  string $contentType The value for the Content-Type header. Check self::contentTypes['refresh'] to see the possible values for this operation
     *
     * @throws \InvalidArgumentException
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function refreshAsync($refresh_request, string $contentType = self::contentTypes['refresh'][0])
    {
        return $this->refreshAsyncWithHttpInfo($refresh_request, $contentType)
            ->then(
                function ($response) {
                    return $response[0];
                }
            );
    }

    /**
     * Operation refreshAsyncWithHttpInfo
     *
     * Rotate the refresh token
     *
     * @param  \PIndustries\SunCharts\Generated\Model\RefreshRequest $refresh_request (required)
     * @param  string $contentType The value for the Content-Type header. Check self::contentTypes['refresh'] to see the possible values for this operation
     *
     * @throws \InvalidArgumentException
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function refreshAsyncWithHttpInfo($refresh_request, string $contentType = self::contentTypes['refresh'][0])
    {
        $returnType = '\PIndustries\SunCharts\Generated\Model\UserTokens';
        $request = $this->refreshRequest($refresh_request, $contentType);

        return $this->client
            ->sendAsync($request, $this->createHttpClientOption())
            ->then(
                function ($response) use ($returnType) {
                    if ($returnType === '\SplFileObject') {
                        $content = $response->getBody(); //stream goes to serializer
                    } else {
                        $content = (string) $response->getBody();
                        if ($returnType !== 'string') {
                            $content = json_decode($content);
                        }
                    }

                    return [
                        ObjectSerializer::deserialize($content, $returnType, []),
                        $response->getStatusCode(),
                        $response->getHeaders()
                    ];
                },
                function ($exception) {
                    $response = $exception->getResponse();
                    $statusCode = $response->getStatusCode();
                    throw new ApiException(
                        sprintf(
                            '[%d] Error connecting to the API (%s)',
                            $statusCode,
                            $exception->getRequest()->getUri()
                        ),
                        $statusCode,
                        $response->getHeaders(),
                        (string) $response->getBody()
                    );
                }
            );
    }

    /**
     * Create request for operation 'refresh'
     *
     * @param  \PIndustries\SunCharts\Generated\Model\RefreshRequest $refresh_request (required)
     * @param  string $contentType The value for the Content-Type header. Check self::contentTypes['refresh'] to see the possible values for this operation
     *
     * @throws \InvalidArgumentException
     * @return \GuzzleHttp\Psr7\Request
     */
    public function refreshRequest($refresh_request, string $contentType = self::contentTypes['refresh'][0])
    {

        // verify the required parameter 'refresh_request' is set
        if ($refresh_request === null || (is_array($refresh_request) && count($refresh_request) === 0)) {
            throw new \InvalidArgumentException(
                'Missing the required parameter $refresh_request when calling refresh'
            );
        }


        $resourcePath = '/api/v1/auth/refresh';
        $formParams = [];
        $queryParams = [];
        $headerParams = [];
        $httpBody = '';
        $multipart = false;





        $headers = $this->headerSelector->selectHeaders(
            ['application/json', ],
            $contentType,
            $multipart
        );

        // for model (json/xml)
        if (isset($refresh_request)) {
            if (stripos($headers['Content-Type'], 'application/json') !== false) {
                # if Content-Type contains "application/json", json_encode the body
                try {
                    $httpBody = json_encode(ObjectSerializer::sanitizeForSerialization($refresh_request), JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {
                    throw new \InvalidArgumentException('json_encode error: ' . $e->getMessage(), 0, $e);
                }
            } else {
                $httpBody = $refresh_request;
            }
        } elseif (count($formParams) > 0) {
            if ($multipart) {
                $multipartContents = [];
                foreach ($formParams as $formParamName => $formParamValue) {
                    $formParamValueItems = is_array($formParamValue) ? $formParamValue : [$formParamValue];
                    foreach ($formParamValueItems as $formParamValueItem) {
                        $multipartContents[] = [
                            'name' => $formParamName,
                            'contents' => $formParamValueItem
                        ];
                    }
                }
                // for HTTP post (form)
                $httpBody = new MultipartStream($multipartContents);

            } elseif (stripos($headers['Content-Type'], 'application/json') !== false) {
                # if Content-Type contains "application/json", json_encode the form parameters
                try {
                    $httpBody = json_encode($formParams, JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {
                    throw new \InvalidArgumentException('json_encode error: ' . $e->getMessage(), 0, $e);
                }
            } else {
                // for HTTP post (form)
                $httpBody = ObjectSerializer::buildQuery($formParams);
            }
        }


        $defaultHeaders = [];
        if ($this->config->getUserAgent()) {
            $defaultHeaders['User-Agent'] = $this->config->getUserAgent();
        }

        $headers = array_merge(
            $defaultHeaders,
            $headerParams,
            $headers
        );

        $operationHost = $this->config->getHost();
        $query = ObjectSerializer::buildQuery($queryParams);
        return new Request(
            'POST',
            $operationHost . $resourcePath . ($query ? "?{$query}" : ''),
            $headers,
            $httpBody
        );
    }

    /**
     * Operation signupStart
     *
     * Passwordless sign-up: email + clinic name → link/code
     *
     * @param  object $body body (required)
     * @param  string|null $x_partner_authorization White-label relay: &#x60;Bearer &lt;pt_… token | sk_… key&gt;&#x60; of the partner app (scope users.passthrough). Applies the app&#39;s auth settings. (optional)
     * @param  string|null $x_end_user_ip Relay only: the end user&#39;s IP for rate limiting. (optional)
     * @param  string $contentType The value for the Content-Type header. Check self::contentTypes['signupStart'] to see the possible values for this operation
     *
     * @throws \PIndustries\SunCharts\Generated\ApiException on non-2xx response or if the response body is not in the expected format
     * @throws \InvalidArgumentException
     * @return \PIndustries\SunCharts\Generated\Model\MagicSent|\PIndustries\SunCharts\Generated\Model\Error
     */
    public function signupStart($body, $x_partner_authorization = null, $x_end_user_ip = null, string $contentType = self::contentTypes['signupStart'][0])
    {
        list($response) = $this->signupStartWithHttpInfo($body, $x_partner_authorization, $x_end_user_ip, $contentType);
        return $response;
    }

    /**
     * Operation signupStartWithHttpInfo
     *
     * Passwordless sign-up: email + clinic name → link/code
     *
     * @param  object $body (required)
     * @param  string|null $x_partner_authorization White-label relay: &#x60;Bearer &lt;pt_… token | sk_… key&gt;&#x60; of the partner app (scope users.passthrough). Applies the app&#39;s auth settings. (optional)
     * @param  string|null $x_end_user_ip Relay only: the end user&#39;s IP for rate limiting. (optional)
     * @param  string $contentType The value for the Content-Type header. Check self::contentTypes['signupStart'] to see the possible values for this operation
     *
     * @throws \PIndustries\SunCharts\Generated\ApiException on non-2xx response or if the response body is not in the expected format
     * @throws \InvalidArgumentException
     * @return array of \PIndustries\SunCharts\Generated\Model\MagicSent|\PIndustries\SunCharts\Generated\Model\Error, HTTP status code, HTTP response headers (array of strings)
     */
    public function signupStartWithHttpInfo($body, $x_partner_authorization = null, $x_end_user_ip = null, string $contentType = self::contentTypes['signupStart'][0])
    {
        $request = $this->signupStartRequest($body, $x_partner_authorization, $x_end_user_ip, $contentType);

        try {
            $options = $this->createHttpClientOption();
            try {
                $response = $this->client->send($request, $options);
            } catch (RequestException $e) {
                throw new ApiException(
                    "[{$e->getCode()}] {$e->getMessage()}",
                    (int) $e->getCode(),
                    $e->getResponse() ? $e->getResponse()->getHeaders() : null,
                    $e->getResponse() ? (string) $e->getResponse()->getBody() : null
                );
            } catch (ConnectException $e) {
                throw new ApiException(
                    "[{$e->getCode()}] {$e->getMessage()}",
                    (int) $e->getCode(),
                    null,
                    null
                );
            }

            $statusCode = $response->getStatusCode();


            switch($statusCode) {
                case 202:
                    return $this->handleResponseWithDataType(
                        '\PIndustries\SunCharts\Generated\Model\MagicSent',
                        $request,
                        $response,
                    );
                default:
                    return $this->handleResponseWithDataType(
                        '\PIndustries\SunCharts\Generated\Model\Error',
                        $request,
                        $response,
                    );
            }

            

            if ($statusCode < 200 || $statusCode > 299) {
                throw new ApiException(
                    sprintf(
                        '[%d] Error connecting to the API (%s)',
                        $statusCode,
                        (string) $request->getUri()
                    ),
                    $statusCode,
                    $response->getHeaders(),
                    (string) $response->getBody()
                );
            }

            return $this->handleResponseWithDataType(
                '\PIndustries\SunCharts\Generated\Model\MagicSent',
                $request,
                $response,
            );
        } catch (ApiException $e) {
            switch ($e->getCode()) {
                case 202:
                    $data = ObjectSerializer::deserialize(
                        $e->getResponseBody(),
                        '\PIndustries\SunCharts\Generated\Model\MagicSent',
                        $e->getResponseHeaders()
                    );
                    $e->setResponseObject($data);
                    throw $e;
                default:
                    $data = ObjectSerializer::deserialize(
                        $e->getResponseBody(),
                        '\PIndustries\SunCharts\Generated\Model\Error',
                        $e->getResponseHeaders()
                    );
                    $e->setResponseObject($data);
                    throw $e;
            }
        

            throw $e;
        }
    }

    /**
     * Operation signupStartAsync
     *
     * Passwordless sign-up: email + clinic name → link/code
     *
     * @param  object $body (required)
     * @param  string|null $x_partner_authorization White-label relay: &#x60;Bearer &lt;pt_… token | sk_… key&gt;&#x60; of the partner app (scope users.passthrough). Applies the app&#39;s auth settings. (optional)
     * @param  string|null $x_end_user_ip Relay only: the end user&#39;s IP for rate limiting. (optional)
     * @param  string $contentType The value for the Content-Type header. Check self::contentTypes['signupStart'] to see the possible values for this operation
     *
     * @throws \InvalidArgumentException
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function signupStartAsync($body, $x_partner_authorization = null, $x_end_user_ip = null, string $contentType = self::contentTypes['signupStart'][0])
    {
        return $this->signupStartAsyncWithHttpInfo($body, $x_partner_authorization, $x_end_user_ip, $contentType)
            ->then(
                function ($response) {
                    return $response[0];
                }
            );
    }

    /**
     * Operation signupStartAsyncWithHttpInfo
     *
     * Passwordless sign-up: email + clinic name → link/code
     *
     * @param  object $body (required)
     * @param  string|null $x_partner_authorization White-label relay: &#x60;Bearer &lt;pt_… token | sk_… key&gt;&#x60; of the partner app (scope users.passthrough). Applies the app&#39;s auth settings. (optional)
     * @param  string|null $x_end_user_ip Relay only: the end user&#39;s IP for rate limiting. (optional)
     * @param  string $contentType The value for the Content-Type header. Check self::contentTypes['signupStart'] to see the possible values for this operation
     *
     * @throws \InvalidArgumentException
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function signupStartAsyncWithHttpInfo($body, $x_partner_authorization = null, $x_end_user_ip = null, string $contentType = self::contentTypes['signupStart'][0])
    {
        $returnType = '\PIndustries\SunCharts\Generated\Model\MagicSent';
        $request = $this->signupStartRequest($body, $x_partner_authorization, $x_end_user_ip, $contentType);

        return $this->client
            ->sendAsync($request, $this->createHttpClientOption())
            ->then(
                function ($response) use ($returnType) {
                    if ($returnType === '\SplFileObject') {
                        $content = $response->getBody(); //stream goes to serializer
                    } else {
                        $content = (string) $response->getBody();
                        if ($returnType !== 'string') {
                            $content = json_decode($content);
                        }
                    }

                    return [
                        ObjectSerializer::deserialize($content, $returnType, []),
                        $response->getStatusCode(),
                        $response->getHeaders()
                    ];
                },
                function ($exception) {
                    $response = $exception->getResponse();
                    $statusCode = $response->getStatusCode();
                    throw new ApiException(
                        sprintf(
                            '[%d] Error connecting to the API (%s)',
                            $statusCode,
                            $exception->getRequest()->getUri()
                        ),
                        $statusCode,
                        $response->getHeaders(),
                        (string) $response->getBody()
                    );
                }
            );
    }

    /**
     * Create request for operation 'signupStart'
     *
     * @param  object $body (required)
     * @param  string|null $x_partner_authorization White-label relay: &#x60;Bearer &lt;pt_… token | sk_… key&gt;&#x60; of the partner app (scope users.passthrough). Applies the app&#39;s auth settings. (optional)
     * @param  string|null $x_end_user_ip Relay only: the end user&#39;s IP for rate limiting. (optional)
     * @param  string $contentType The value for the Content-Type header. Check self::contentTypes['signupStart'] to see the possible values for this operation
     *
     * @throws \InvalidArgumentException
     * @return \GuzzleHttp\Psr7\Request
     */
    public function signupStartRequest($body, $x_partner_authorization = null, $x_end_user_ip = null, string $contentType = self::contentTypes['signupStart'][0])
    {

        // verify the required parameter 'body' is set
        if ($body === null || (is_array($body) && count($body) === 0)) {
            throw new \InvalidArgumentException(
                'Missing the required parameter $body when calling signupStart'
            );
        }




        $resourcePath = '/api/v1/signup/start';
        $formParams = [];
        $queryParams = [];
        $headerParams = [];
        $httpBody = '';
        $multipart = false;


        // header params
        if ($x_partner_authorization !== null) {
            $headerParams['X-Partner-Authorization'] = ObjectSerializer::toHeaderValue($x_partner_authorization);
        }
        // header params
        if ($x_end_user_ip !== null) {
            $headerParams['X-End-User-IP'] = ObjectSerializer::toHeaderValue($x_end_user_ip);
        }



        $headers = $this->headerSelector->selectHeaders(
            ['application/json', ],
            $contentType,
            $multipart
        );

        // for model (json/xml)
        if (isset($body)) {
            if (stripos($headers['Content-Type'], 'application/json') !== false) {
                # if Content-Type contains "application/json", json_encode the body
                try {
                    $httpBody = json_encode(ObjectSerializer::sanitizeForSerialization($body), JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {
                    throw new \InvalidArgumentException('json_encode error: ' . $e->getMessage(), 0, $e);
                }
            } else {
                $httpBody = $body;
            }
        } elseif (count($formParams) > 0) {
            if ($multipart) {
                $multipartContents = [];
                foreach ($formParams as $formParamName => $formParamValue) {
                    $formParamValueItems = is_array($formParamValue) ? $formParamValue : [$formParamValue];
                    foreach ($formParamValueItems as $formParamValueItem) {
                        $multipartContents[] = [
                            'name' => $formParamName,
                            'contents' => $formParamValueItem
                        ];
                    }
                }
                // for HTTP post (form)
                $httpBody = new MultipartStream($multipartContents);

            } elseif (stripos($headers['Content-Type'], 'application/json') !== false) {
                # if Content-Type contains "application/json", json_encode the form parameters
                try {
                    $httpBody = json_encode($formParams, JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {
                    throw new \InvalidArgumentException('json_encode error: ' . $e->getMessage(), 0, $e);
                }
            } else {
                // for HTTP post (form)
                $httpBody = ObjectSerializer::buildQuery($formParams);
            }
        }


        $defaultHeaders = [];
        if ($this->config->getUserAgent()) {
            $defaultHeaders['User-Agent'] = $this->config->getUserAgent();
        }

        $headers = array_merge(
            $defaultHeaders,
            $headerParams,
            $headers
        );

        $operationHost = $this->config->getHost();
        $query = ObjectSerializer::buildQuery($queryParams);
        return new Request(
            'POST',
            $operationHost . $resourcePath . ($query ? "?{$query}" : ''),
            $headers,
            $httpBody
        );
    }

    /**
     * Create http client option
     *
     * @throws \RuntimeException on file opening failure
     * @return array of http client options
     */
    protected function createHttpClientOption()
    {
        $options = [];
        if ($this->config->getDebug()) {
            $options[RequestOptions::DEBUG] = fopen($this->config->getDebugFile(), 'a');
            if (!$options[RequestOptions::DEBUG]) {
                throw new \RuntimeException('Failed to open the debug file: ' . $this->config->getDebugFile());
            }
        }

        if ($this->config->getCertFile()) {
            $options[RequestOptions::CERT] = $this->config->getCertFile();
        }

        if ($this->config->getKeyFile()) {
            $options[RequestOptions::SSL_KEY] = $this->config->getKeyFile();
        }

        return $options;
    }

    private function handleResponseWithDataType(
        string $dataType,
        RequestInterface $request,
        ResponseInterface $response
    ): array {
        if ($dataType === '\SplFileObject') {
            $content = $response->getBody(); //stream goes to serializer
        } else {
            $content = (string) $response->getBody();
            if ($dataType !== 'string') {
                try {
                    $content = json_decode($content, false, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException $exception) {
                    throw new ApiException(
                        sprintf(
                            'Error JSON decoding server response (%s)',
                            $request->getUri()
                        ),
                        $response->getStatusCode(),
                        $response->getHeaders(),
                        $content
                    );
                }
            }
        }

        return [
            ObjectSerializer::deserialize($content, $dataType, []),
            $response->getStatusCode(),
            $response->getHeaders()
        ];
    }

    private function responseWithinRangeCode(
        string $rangeCode,
        int $statusCode
    ): bool {
        $left = (int) ($rangeCode[0].'00');
        $right = (int) ($rangeCode[0].'99');

        return $statusCode >= $left && $statusCode <= $right;
    }
}
