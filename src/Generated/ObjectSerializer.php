<?php
/**
 * ObjectSerializer
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

namespace PIndustries\SunCharts\Generated;

use GuzzleHttp\Psr7\Utils;
use PIndustries\SunCharts\Generated\Model\ModelInterface;

/**
 * ObjectSerializer Class Doc Comment
 *
 * @category Class
 * @package  PIndustries\SunCharts\Generated
 * @author   OpenAPI Generator team
 * @link     https://openapi-generator.tech
 */
class ObjectSerializer
{
    /** @var string */
    private static $dateTimeFormat = \DateTime::ATOM;

    /**
     * Change the date format
     *
     * @param string $format   the new date format to use
     */
    public static function setDateTimeFormat($format)
    {
        self::$dateTimeFormat = $format;
    }

    /**
     * Serialize data
     *
     * @param mixed  $data   the data to serialize
     * @param string|null $type   the OpenAPIToolsType of the data
     * @param string|null $format the format of the OpenAPITools type of the data
     *
     * @return scalar|object|array|null serialized form of $data
     */
    public static function sanitizeForSerialization($data, $type = null, $format = null)
    {
        if (is_scalar($data) || null === $data) {
            return $data;
        }

        if ($data instanceof \DateTime) {
            return ($format === 'date') ? $data->format('Y-m-d') : $data->format(self::$dateTimeFormat);
        }

        if (is_array($data)) {
            foreach ($data as $property => $value) {
                $data[$property] = self::sanitizeForSerialization($value);
            }
            return $data;
        }

        if (is_object($data)) {
            $values = [];
            if ($data instanceof ModelInterface) {
                $formats = $data::openAPIFormats();
                foreach ($data::openAPITypes() as $property => $openAPIType) {
                    $getter = $data::getters()[$property];
                    $value = $data->$getter();
                    if ($value !== null && !in_array($openAPIType, ['\DateTime', '\SplFileObject', 'array', 'bool', 'boolean', 'byte', 'float', 'int', 'integer', 'mixed', 'number', 'object', 'string', 'void'], true)) {
                        $callable = [$openAPIType, 'getAllowableEnumValues'];
                        if (is_callable($callable)) {
                            /** array $callable */
                            $allowedEnumTypes = $callable();
                            if (!in_array($value, $allowedEnumTypes, true)) {
                                $imploded = implode("', '", $allowedEnumTypes);
                                throw new \InvalidArgumentException("Invalid value for enum '$openAPIType', must be one of: '$imploded'");
                            }
                        }
                    }
                    if (($data::isNullable($property) && $data->isNullableSetToNull($property)) || $value !== null) {
                        $values[$data::attributeMap()[$property]] = self::sanitizeForSerialization($value, $openAPIType, $formats[$property]);
                    }
                }
            } else {
                foreach ($data as $property => $value) {
                    $values[$property] = self::sanitizeForSerialization($value);
                }
            }
            return (object) $values;
        } else {
            return (string)$data;
        }
    }

    /**
     * Sanitize filename by removing path.
     * e.g. ../../sun.gif becomes sun.gif
     *
     * @param string $filename filename to be sanitized
     *
     * @return string the sanitized filename
     */
    public static function sanitizeFilename($filename)
    {
        if (preg_match("/.*[\/\\\\](.*)$/", $filename, $match)) {
            return $match[1];
        } else {
            return $filename;
        }
    }

    /**
     * Shorter timestamp microseconds to 6 digits length.
     *
     * @param string $timestamp Original timestamp
     *
     * @return string the shorten timestamp
     */
    public static function sanitizeTimestamp($timestamp)
    {
        if (!is_string($timestamp)) {
            return $timestamp;
        }

        return preg_replace('/(:\d{2}.\d{6})\d*/', '$1', $timestamp);
    }

    /**
     * Take value and turn it into a string suitable for inclusion in
     * the path, by url-encoding.
     *
     * @param string $value a string which will be part of the path
     *
     * @return string the serialized object
     */
    public static function toPathValue($value)
    {
        return rawurlencode(self::toString($value));
    }

    /**
     * Checks if a value is empty, based on its OpenAPI type.
     *
     * @param mixed  $value
     * @param string $openApiType
     *
     * @return bool true if $value is empty
     */
    private static function isEmptyValue($value, string $openApiType): bool
    {
        // If empty() returns false, it is not empty regardless of its type.
        if (!empty($value)) {
            return false;
        }

        // Null is always empty, as we cannot send a real "null" value in a query parameter.
        if ($value === null) {
            return true;
        }

        switch ($openApiType) {
            // For numeric values, false and '' are considered empty.
            // This comparison is safe for floating point values, since the previous call to empty() will
            // filter out values that don't match 0.
            case 'int':
            case 'integer':
                return $value !== 0;

            case 'number':
            case 'float':
                return $value !== 0 && $value !== 0.0;

            // For boolean values, '' is considered empty
            case 'bool':
            case 'boolean':
                return !in_array($value, [false, 0], true);

            // For string values, '' is considered empty.
            case 'string':
                return $value === '';

            // For all the other types, any value at this point can be considered empty.
            default:
                return true;
        }
    }

    /**
     * Take query parameter properties and turn it into an array suitable for
     * native http_build_query or GuzzleHttp\Psr7\Query::build.
     *
     * @param mixed  $value       Parameter value
     * @param string $paramName   Parameter name
     * @param string $openApiType OpenAPIType eg. array or object
     * @param string $style       Parameter serialization style
     * @param bool   $explode     Parameter explode option
     * @param bool   $required    Whether query param is required or not
     *
     * @return array
     */
    public static function toQueryValue(
        $value,
        string $paramName,
        string $openApiType = 'string',
        string $style = 'form',
        bool $explode = true,
        bool $required = true
    ): array {

        // Check if we should omit this parameter from the query. This should only happen when:
        //  - Parameter is NOT required; AND
        //  - its value is set to a value that is equivalent to "empty", depending on its OpenAPI type. For
        //    example, 0 as "int" or "boolean" is NOT an empty value.
        if (self::isEmptyValue($value, $openApiType)) {
            if ($required) {
                return ["{$paramName}" => ''];
            } else {
                return [];
            }
        }

        // Handle DateTime objects in query
        if ($openApiType === "\\DateTime" && $value instanceof \DateTime) {
            return ["{$paramName}" => $value->format(self::$dateTimeFormat)];
        }

        $query = [];
        $value = (in_array($openApiType, ['object', 'array'], true)) ? (array)$value : $value;

        // since \GuzzleHttp\Psr7\Query::build fails with nested arrays
        // need to flatten array first
        $flattenArray = function ($arr, $name, &$result = []) use (&$flattenArray, $style, $explode) {
            if (!is_array($arr)) {
                return $arr;
            }

            foreach ($arr as $k => $v) {
                $prop = ($style === 'deepObject') ? "{$name}[{$k}]" : $k;

                if (is_array($v)) {
                    $flattenArray($v, $prop, $result);
                } else {
                    if ($style !== 'deepObject' && !$explode) {
                        // push key itself
                        $result[] = $prop;
                    }
                    $result[$prop] = $v;
                }
            }
            return $result;
        };

        $value = $flattenArray($value, $paramName);

        // https://github.com/OAI/OpenAPI-Specification/blob/main/versions/3.1.0.md#style-values
        if ($openApiType === 'array' && $style === 'deepObject' && $explode) {
            return $value;
        }

        if ($openApiType === 'object' && ($style === 'deepObject' || $explode)) {
            return $value;
        }

        if ('boolean' === $openApiType && is_bool($value)) {
            $value = self::convertBoolToQueryStringFormat($value);
        }

        // handle style in serializeCollection
        $query[$paramName] = ($explode) ? $value : self::serializeCollection((array)$value, $style);

        return $query;
    }

    /**
     * Convert boolean value to format for query string.
     *
     * @param bool $value Boolean value
     *
     * @return int|string Boolean value in format
     */
    public static function convertBoolToQueryStringFormat(bool $value)
    {
        if (Configuration::BOOLEAN_FORMAT_STRING == Configuration::getDefaultConfiguration()->getBooleanFormatForQueryString()) {
            return $value ? 'true' : 'false';
        }

        return (int) $value;
    }

    /**
     * Take value and turn it into a string suitable for inclusion in
     * the header. If it's a string, pass through unchanged
     * If it's a datetime object, format it in ISO8601
     *
     * @param string $value a string which will be part of the header
     *
     * @return string the header string
     */
    public static function toHeaderValue($value)
    {
        $callable = [$value, 'toHeaderValue'];
        if (is_callable($callable)) {
            return $callable();
        }

        return self::toString($value);
    }

    /**
     * Take value and turn it into a string suitable for inclusion in
     * the parameter. If it's a string, pass through unchanged
     * If it's a datetime object, format it in ISO8601
     * If it's a boolean, convert it to "true" or "false".
     *
     * @param float|int|bool|\DateTime $value the value of the parameter
     *
     * @return string the header string
     */
    public static function toString($value)
    {
        if ($value instanceof \DateTime) { // datetime in ISO8601 format
            return $value->format(self::$dateTimeFormat);
        } elseif (is_bool($value)) {
            return $value ? 'true' : 'false';
        } else {
            return (string) $value;
        }
    }

    /**
     * Serialize an array to a string.
     *
     * @param array  $collection                 collection to serialize to a string
     * @param string $style                      the format use for serialization (csv,
     * ssv, tsv, pipes, multi)
     * @param bool   $allowCollectionFormatMulti allow collection format to be a multidimensional array
     *
     * @return string
     */
    public static function serializeCollection(array $collection, $style, $allowCollectionFormatMulti = false)
    {
        if ($allowCollectionFormatMulti && ('multi' === $style)) {
            // http_build_query() almost does the job for us. We just
            // need to fix the result of multidimensional arrays.
            return preg_replace('/%5B[0-9]+%5D=/', '=', http_build_query($collection, '', '&'));
        }
        switch ($style) {
            case 'pipeDelimited':
            case 'pipes':
                return implode('|', $collection);

            case 'tsv':
                return implode("\t", $collection);

            case 'spaceDelimited':
            case 'ssv':
                return implode(' ', $collection);

            case 'simple':
            case 'csv':
                // Deliberate fall through. CSV is default format.
            default:
                return implode(',', $collection);
        }
    }

    /**
     * Deserialize a JSON string into an object
     *
     * @param mixed    $data          object or primitive to be deserialized
     * @param string   $class         class name is passed as a string
     * @param string[]|null $httpHeaders   HTTP headers
     *
     * @return object|array|null a single or an array of $class instances
     */
    public static function deserialize($data, $class, $httpHeaders = null)
    {
        if (null === $data) {
            return null;
        }

        if (strcasecmp(substr($class, -2), '[]') === 0) {
            $data = is_string($data) ? json_decode($data) : $data;

            if (!is_array($data)) {
                throw new \InvalidArgumentException("Invalid array '$class'");
            }

            $subClass = substr($class, 0, -2);
            $values = [];
            foreach ($data as $key => $value) {
                $values[] = self::deserialize($value, $subClass, null);
            }
            return $values;
        }

        if (preg_match('/^(array<|map\[)/', $class)) { // for associative array e.g. array<string,int>
            $data = is_string($data) ? json_decode($data) : $data;
            settype($data, 'array');
            $inner = substr($class, 4, -1);
            $deserialized = [];
            if (strrpos($inner, ",") !== false) {
                $subClass_array = explode(',', $inner, 2);
                $subClass = $subClass_array[1];
                foreach ($data as $key => $value) {
                    $deserialized[$key] = self::deserialize($value, $subClass, null);
                }
            }
            return $deserialized;
        }

        if ($class === 'object') {
            settype($data, 'array');
            return $data;
        } elseif ($class === 'mixed') {
            settype($data, gettype($data));
            return $data;
        }

        if ($class === '\DateTime') {
            // Some APIs return an invalid, empty string as a
            // date-time property. DateTime::__construct() will return
            // the current time for empty input which is probably not
            // what is meant. The invalid empty string is probably to
            // be interpreted as a missing field/value. Let's handle
            // this graceful.
            if (!empty($data)) {
                try {
                    return new \DateTime($data);
                } catch (\Exception $exception) {
                    // Some APIs return a date-time with too high nanosecond
                    // precision for php's DateTime to handle.
                    // With provided regexp 6 digits of microseconds saved
                    return new \DateTime(self::sanitizeTimestamp($data));
                }
            } else {
                return null;
            }
        }

        if ($class === '\SplFileObject') {
            $data = Utils::streamFor($data);

            /** @var \Psr\Http\Message\StreamInterface $data */

            // determine file name
            if (
                is_array($httpHeaders)
                && array_key_exists('Content-Disposition', $httpHeaders)
                && preg_match('/inline; filename=[\'"]?([^\'"\s]+)[\'"]?$/i', $httpHeaders['Content-Disposition'], $match)
            ) {
                $filename = Configuration::getDefaultConfiguration()->getTempFolderPath() . DIRECTORY_SEPARATOR . self::sanitizeFilename($match[1]);
            } else {
                $filename = tempnam(Configuration::getDefaultConfiguration()->getTempFolderPath(), '');
            }

            $file = fopen($filename, 'w');
            while ($chunk = $data->read(200)) {
                fwrite($file, $chunk);
            }
            fclose($file);

            return new \SplFileObject($filename, 'r');
        }

        /** @psalm-suppress ParadoxicalCondition */
        if (in_array($class, ['\DateTime', '\SplFileObject', 'array', 'bool', 'boolean', 'byte', 'float', 'int', 'integer', 'mixed', 'number', 'object', 'string', 'void'], true)) {
            settype($data, $class);
            return $data;
        }


        if (method_exists($class, 'getAllowableEnumValues')) {
            if (!in_array($data, $class::getAllowableEnumValues(), true)) {
                $imploded = implode("', '", $class::getAllowableEnumValues());
                throw new \InvalidArgumentException("Invalid value for enum '$class', must be one of: '$imploded'");
            }
            return $data;
        } else {
            $data = is_string($data) ? json_decode($data) : $data;

            if (is_array($data)) {
                $data = (object) $data;
            }

            // If a discriminator is defined and points to a valid subclass, use it.
            $discriminator = $class::DISCRIMINATOR;
            if (!empty($discriminator) && isset($data->{$discriminator}) && is_string($data->{$discriminator})) {
                $subclass = '\PIndustries\SunCharts\Generated\Model\\' . $data->{$discriminator};
                if (is_subclass_of($subclass, $class)) {
                    $class = $subclass;
                }
            }

            /** @var ModelInterface $instance */
            $instance = new $class();
            foreach ($instance::openAPITypes() as $property => $type) {
                $propertySetter = $instance::setters()[$property];

                if (!isset($propertySetter)) {
                    continue;
                }

                if (!isset($data->{$instance::attributeMap()[$property]})) {
                    if ($instance::isNullable($property)) {
                        $instance->$propertySetter(null);
                    }

                    continue;
                }

                if (isset($data->{$instance::attributeMap()[$property]})) {
                    $propertyValue = $data->{$instance::attributeMap()[$property]};
                    $instance->$propertySetter(self::deserialize($propertyValue, $type, null));
                }
            }
            return $instance;
        }
    }

    /**
     * Build a query string from an array of key value pairs.
     *
     * This function can use the return value of `parse()` to build a query
     * string. This function does not modify the provided keys when an array is
     * encountered (like `http_build_query()` would).
     *
     * The function is copied from https://github.com/guzzle/psr7/blob/a243f80a1ca7fe8ceed4deee17f12c1930efe662/src/Query.php#L59-L112
     * with a modification which is described in https://github.com/guzzle/psr7/pull/603
     *
     * @param array     $params              Query string parameters.
     * @param int|false $encoding            Set to false to not encode, PHP_QUERY_RFC3986
     *                                       to encode using RFC3986, or PHP_QUERY_RFC1738
     *                                       to encode using RFC1738.
     */
    public static function buildQuery(array $params, $encoding = PHP_QUERY_RFC3986): string
    {
        if (!$params) {
            return '';
        }

        if ($encoding === false) {
            $encoder = function (string $str): string {
                return $str;
            };
        } elseif ($encoding === PHP_QUERY_RFC3986) {
            $encoder = 'rawurlencode';
        } elseif ($encoding === PHP_QUERY_RFC1738) {
            $encoder = 'urlencode';
        } else {
            throw new \InvalidArgumentException('Invalid type');
        }

        $castBool = Configuration::BOOLEAN_FORMAT_INT == Configuration::getDefaultConfiguration()->getBooleanFormatForQueryString()
            ? function ($v) { return (int) $v; }
            : function ($v) { return $v ? 'true' : 'false'; };

        $qs = '';
        foreach ($params as $k => $v) {
            $k = $encoder((string) $k);
            if (!is_array($v)) {
                $qs .= $k;
                $v = is_bool($v) ? $castBool($v) : $v;
                if ($v !== null) {
                    $qs .= '='.$encoder((string) $v);
                }
                $qs .= '&';
            } else {
                foreach ($v as $vv) {
                    $qs .= $k;
                    $vv = is_bool($vv) ? $castBool($vv) : $vv;
                    if ($vv !== null) {
                        $qs .= '='.$encoder((string) $vv);
                    }
                    $qs .= '&';
                }
            }
        }

        return $qs ? substr($qs, 0, -1) : '';
    }
}
