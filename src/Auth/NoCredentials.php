<?php

declare(strict_types=1);

namespace PIndustries\SunCharts\Auth;

use Psr\Http\Message\RequestInterface;

final class NoCredentials implements Credentials
{
    public function authenticate(RequestInterface $request): RequestInterface
    {
        return $request;
    }
}
