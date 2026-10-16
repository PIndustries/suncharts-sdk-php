<?php
/**
 * ClinicSelection
 *
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

namespace PIndustries\SunCharts\Generated\Model;

use \ArrayAccess;
use \PIndustries\SunCharts\Generated\ObjectSerializer;

/**
 * ClinicSelection Class Doc Comment
 *
 * @category Class
 * @description Returned instead of tokens when the email belongs to several clinics. Call /api/v1/auth/login/select with one tenant_id.
 * @package  PIndustries\SunCharts\Generated
 * @author   OpenAPI Generator team
 * @link     https://openapi-generator.tech
 * @implements \ArrayAccess<string, mixed>
 */
class ClinicSelection implements ModelInterface, ArrayAccess, \JsonSerializable
{
    public const DISCRIMINATOR = null;

    /**
     * The original name of the model.
     *
     * @var string
     */
    protected static $openAPIModelName = 'ClinicSelection';

    /**
     * Array of property to type mappings. Used for (de)serialization
     *
     * @var string[]
     */
    protected static $openAPITypes = [
        'clinic_selection_required' => 'bool',
        'selection_token' => 'string',
        'expires_in' => 'int',
        'clinics' => '\PIndustries\SunCharts\Generated\Model\ClinicMembership[]'
    ];

    /**
     * Array of property to format mappings. Used for (de)serialization
     *
     * @var string[]
     * @phpstan-var array<string, string|null>
     * @psalm-var array<string, string|null>
     */
    protected static $openAPIFormats = [
        'clinic_selection_required' => null,
        'selection_token' => null,
        'expires_in' => null,
        'clinics' => null
    ];

    /**
     * Array of nullable properties. Used for (de)serialization
     *
     * @var boolean[]
     */
    protected static array $openAPINullables = [
        'clinic_selection_required' => false,
        'selection_token' => false,
        'expires_in' => false,
        'clinics' => false
    ];

    /**
     * If a nullable field gets set to null, insert it here
     *
     * @var boolean[]
     */
    protected array $openAPINullablesSetToNull = [];

    /**
     * Array of property to type mappings. Used for (de)serialization
     *
     * @return array
     */
    public static function openAPITypes()
    {
        return self::$openAPITypes;
    }

    /**
     * Array of property to format mappings. Used for (de)serialization
     *
     * @return array
     */
    public static function openAPIFormats()
    {
        return self::$openAPIFormats;
    }

    /**
     * Array of nullable properties
     *
     * @return array
     */
    protected static function openAPINullables(): array
    {
        return self::$openAPINullables;
    }

    /**
     * Array of nullable field names deliberately set to null
     *
     * @return boolean[]
     */
    private function getOpenAPINullablesSetToNull(): array
    {
        return $this->openAPINullablesSetToNull;
    }

    /**
     * Setter - Array of nullable field names deliberately set to null
     *
     * @param boolean[] $openAPINullablesSetToNull
     */
    private function setOpenAPINullablesSetToNull(array $openAPINullablesSetToNull): void
    {
        $this->openAPINullablesSetToNull = $openAPINullablesSetToNull;
    }

    /**
     * Checks if a property is nullable
     *
     * @param string $property
     * @return bool
     */
    public static function isNullable(string $property): bool
    {
        return self::openAPINullables()[$property] ?? false;
    }

    /**
     * Checks if a nullable property is set to null.
     *
     * @param string $property
     * @return bool
     */
    public function isNullableSetToNull(string $property): bool
    {
        return in_array($property, $this->getOpenAPINullablesSetToNull(), true);
    }

    /**
     * Array of attributes where the key is the local name,
     * and the value is the original name
     *
     * @var string[]
     */
    protected static $attributeMap = [
        'clinic_selection_required' => 'clinic_selection_required',
        'selection_token' => 'selection_token',
        'expires_in' => 'expires_in',
        'clinics' => 'clinics'
    ];

    /**
     * Array of attributes to setter functions (for deserialization of responses)
     *
     * @var string[]
     */
    protected static $setters = [
        'clinic_selection_required' => 'setClinicSelectionRequired',
        'selection_token' => 'setSelectionToken',
        'expires_in' => 'setExpiresIn',
        'clinics' => 'setClinics'
    ];

    /**
     * Array of attributes to getter functions (for serialization of requests)
     *
     * @var string[]
     */
    protected static $getters = [
        'clinic_selection_required' => 'getClinicSelectionRequired',
        'selection_token' => 'getSelectionToken',
        'expires_in' => 'getExpiresIn',
        'clinics' => 'getClinics'
    ];

    /**
     * Array of attributes where the key is the local name,
     * and the value is the original name
     *
     * @return array
     */
    public static function attributeMap()
    {
        return self::$attributeMap;
    }

    /**
     * Array of attributes to setter functions (for deserialization of responses)
     *
     * @return array
     */
    public static function setters()
    {
        return self::$setters;
    }

    /**
     * Array of attributes to getter functions (for serialization of requests)
     *
     * @return array
     */
    public static function getters()
    {
        return self::$getters;
    }

    /**
     * The original name of the model.
     *
     * @return string
     */
    public function getModelName()
    {
        return self::$openAPIModelName;
    }


    /**
     * Associative array for storing property values
     *
     * @var mixed[]
     */
    protected $container = [];

    /**
     * Constructor
     *
     * @param mixed[]|null $data Associated array of property values
     *                      initializing the model
     */
    public function __construct(?array $data = null)
    {
        $this->setIfExists('clinic_selection_required', $data ?? [], null);
        $this->setIfExists('selection_token', $data ?? [], null);
        $this->setIfExists('expires_in', $data ?? [], null);
        $this->setIfExists('clinics', $data ?? [], null);
    }

    /**
     * Sets $this->container[$variableName] to the given data or to the given default Value; if $variableName
     * is nullable and its value is set to null in the $fields array, then mark it as "set to null" in the
     * $this->openAPINullablesSetToNull array
     *
     * @param string $variableName
     * @param array  $fields
     * @param mixed  $defaultValue
     */
    private function setIfExists(string $variableName, array $fields, $defaultValue): void
    {
        if (self::isNullable($variableName) && array_key_exists($variableName, $fields) && is_null($fields[$variableName])) {
            $this->openAPINullablesSetToNull[] = $variableName;
        }

        $this->container[$variableName] = $fields[$variableName] ?? $defaultValue;
    }

    /**
     * Show all the invalid properties with reasons.
     *
     * @return array invalid properties with reasons
     */
    public function listInvalidProperties()
    {
        $invalidProperties = [];

        if ($this->container['clinic_selection_required'] === null) {
            $invalidProperties[] = "'clinic_selection_required' can't be null";
        }
        if ($this->container['selection_token'] === null) {
            $invalidProperties[] = "'selection_token' can't be null";
        }
        if ($this->container['clinics'] === null) {
            $invalidProperties[] = "'clinics' can't be null";
        }
        return $invalidProperties;
    }

    /**
     * Validate all the properties in the model
     * return true if all passed
     *
     * @return bool True if all properties are valid
     */
    public function valid()
    {
        return count($this->listInvalidProperties()) === 0;
    }


    /**
     * Gets clinic_selection_required
     *
     * @return bool
     */
    public function getClinicSelectionRequired()
    {
        return $this->container['clinic_selection_required'];
    }

    /**
     * Sets clinic_selection_required
     *
     * @param bool $clinic_selection_required clinic_selection_required
     *
     * @return self
     */
    public function setClinicSelectionRequired($clinic_selection_required)
    {
        if (is_null($clinic_selection_required)) {
            throw new \InvalidArgumentException('non-nullable clinic_selection_required cannot be null');
        }
        $this->container['clinic_selection_required'] = $clinic_selection_required;

        return $this;
    }

    /**
     * Gets selection_token
     *
     * @return string
     */
    public function getSelectionToken()
    {
        return $this->container['selection_token'];
    }

    /**
     * Sets selection_token
     *
     * @param string $selection_token selection_token
     *
     * @return self
     */
    public function setSelectionToken($selection_token)
    {
        if (is_null($selection_token)) {
            throw new \InvalidArgumentException('non-nullable selection_token cannot be null');
        }
        $this->container['selection_token'] = $selection_token;

        return $this;
    }

    /**
     * Gets expires_in
     *
     * @return int|null
     */
    public function getExpiresIn()
    {
        return $this->container['expires_in'];
    }

    /**
     * Sets expires_in
     *
     * @param int|null $expires_in expires_in
     *
     * @return self
     */
    public function setExpiresIn($expires_in)
    {
        if (is_null($expires_in)) {
            throw new \InvalidArgumentException('non-nullable expires_in cannot be null');
        }
        $this->container['expires_in'] = $expires_in;

        return $this;
    }

    /**
     * Gets clinics
     *
     * @return \PIndustries\SunCharts\Generated\Model\ClinicMembership[]
     */
    public function getClinics()
    {
        return $this->container['clinics'];
    }

    /**
     * Sets clinics
     *
     * @param \PIndustries\SunCharts\Generated\Model\ClinicMembership[] $clinics clinics
     *
     * @return self
     */
    public function setClinics($clinics)
    {
        if (is_null($clinics)) {
            throw new \InvalidArgumentException('non-nullable clinics cannot be null');
        }
        $this->container['clinics'] = $clinics;

        return $this;
    }
    /**
     * Returns true if offset exists. False otherwise.
     *
     * @param integer|string $offset Offset
     *
     * @return boolean
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->container[$offset]);
    }

    /**
     * Gets offset.
     *
     * @param integer|string $offset Offset
     *
     * @return mixed|null
     */
    #[\ReturnTypeWillChange]
    public function offsetGet(mixed $offset)
    {
        return $this->container[$offset] ?? null;
    }

    /**
     * Sets value based on offset.
     *
     * @param int|null $offset Offset
     * @param mixed    $value  Value to be set
     *
     * @return void
     */
    public function offsetSet($offset, $value): void
    {
        if (is_null($offset)) {
            $this->container[] = $value;
        } else {
            $this->container[$offset] = $value;
        }
    }

    /**
     * Unsets offset.
     *
     * @param integer|string $offset Offset
     *
     * @return void
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->container[$offset]);
    }

    /**
     * Serializes the object to a value that can be serialized natively by json_encode().
     * @link https://www.php.net/manual/en/jsonserializable.jsonserialize.php
     *
     * @return mixed Returns data which can be serialized by json_encode(), which is a value
     * of any type other than a resource.
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
       return ObjectSerializer::sanitizeForSerialization($this);
    }

    /**
     * Gets the string presentation of the object
     *
     * @return string
     */
    public function __toString()
    {
        return json_encode(
            ObjectSerializer::sanitizeForSerialization($this),
            JSON_PRETTY_PRINT
        );
    }

    /**
     * Gets a header-safe presentation of the object
     *
     * @return string
     */
    public function toHeaderValue()
    {
        return json_encode(ObjectSerializer::sanitizeForSerialization($this));
    }
}


