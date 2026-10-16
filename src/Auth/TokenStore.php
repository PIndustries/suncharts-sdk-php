<?php

declare(strict_types=1);

namespace PIndustries\SunCharts\Auth;

/** Optional shared cache for partner access tokens (e.g. backed by Redis or a framework cache). */
interface TokenStore
{
    /** @return array{0: string, 1: int}|null [token, expiresAtUnix] */
    public function get(string $key): ?array;

    public function put(string $key, string $token, int $expiresAt): void;

    public function forget(string $key): void;
}
