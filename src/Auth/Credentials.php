<?php

declare(strict_types=1);

namespace PIndustries\SunCharts\Auth;

use Psr\Http\Message\RequestInterface;

/** Adds partner credentials to an outgoing request. */
interface Credentials
{
    public function authenticate(RequestInterface $request): RequestInterface;
}
