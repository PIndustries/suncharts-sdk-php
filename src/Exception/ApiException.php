<?php

declare(strict_types=1);

namespace PIndustries\SunCharts\Exception;

/** Non-2xx response from the API. `errorCode()` is the API's machine-readable `error` field. */
class ApiException extends \RuntimeException
{
    /** @param array<string, mixed> $body */
    public function __construct(
        private readonly int $status,
        private readonly string $errorCode,
        string $message,
        private readonly array $body = [],
    ) {
        parent::__construct($message, $status);
    }

    /** @param array<string, mixed> $body */
    public static function fromResponse(int $status, array $body, string $fallback = 'request failed'): self
    {
        $code = is_string($body['error'] ?? null) ? $body['error'] : 'http_'.$status;
        $msg = is_string($body['message'] ?? null) ? $body['message'] : $fallback;

        return new self($status, $code, $msg, $body);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    /** @return array<string, mixed> */
    public function body(): array
    {
        return $this->body;
    }

    public function isUnauthorized(): bool
    {
        return $this->status === 401 && ! in_array($this->errorCode, ['mfa_required', 'mfa_invalid'], true);
    }
}
