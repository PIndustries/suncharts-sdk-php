<?php

declare(strict_types=1);

namespace PIndustries\SunCharts;

/** A raw API response (used by the pass-through). */
final class Response
{
    /** Response headers safe to hand to end users; everything else (server, vendor, cookies) is dropped. */
    public const SAFE_HEADERS = ['content-type', 'content-disposition', 'cache-control', 'etag', 'last-modified', 'location', 'retry-after'];

    /** @param array<string, string> $headers lower-cased names */
    public function __construct(public readonly int $status, public readonly array $headers, public readonly string $body)
    {
    }

    /** @return array<string, mixed>|null */
    public function json(): ?array
    {
        $v = json_decode($this->body, true);

        return is_array($v) ? $v : null;
    }

    /** @return array<string, string> */
    public function safeHeaders(): array
    {
        return array_filter($this->headers, fn (string $k) => in_array($k, self::SAFE_HEADERS, true), ARRAY_FILTER_USE_KEY);
    }
}
