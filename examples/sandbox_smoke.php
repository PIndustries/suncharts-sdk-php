<?php
// Sandbox smoke test: OAuth2 token + read calls (hand-written helper and generated operation).
//   SUNCHARTS_CLIENT_ID=pc_… SUNCHARTS_CLIENT_SECRET=… php examples/sandbox_smoke.php
declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use PIndustries\SunCharts\Client;
use PIndustries\SunCharts\Generated\Api\PartnerApi;

$c = Client::clientCredentials(
    getenv('SUNCHARTS_BASE_URL') ?: Client::SANDBOX,
    (string) getenv('SUNCHARTS_CLIENT_ID'),
    (string) getenv('SUNCHARTS_CLIENT_SECRET'),
    scope: 'partner.read tenants.read',
);

$me = $c->api(PartnerApi::class)->partnerMe();              // generated, typed
echo 'partner: ', $me->getPartner()?->getName(), PHP_EOL;
$slugs = array_map(fn ($t) => $t['slug'], $c->listTenants()); // hand-written helper
echo 'tenants: ', implode(', ', $slugs), PHP_EOL;
