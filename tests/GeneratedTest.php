<?php

declare(strict_types=1);

namespace PIndustries\SunCharts\Tests;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response as Psr7Response;
use PIndustries\SunCharts\Auth\ApiKey;
use PIndustries\SunCharts\Client;
use PIndustries\SunCharts\Generated\Api\PartnerApi;
use PIndustries\SunCharts\Generated\Api\AppointmentsApi;
use PHPUnit\Framework\TestCase;

final class GeneratedTest extends TestCase
{
    private function client(): Client
    {
        $f = new HttpFactory();

        return new Client('https://x.test', new ApiKey('sk_test_1'), new \GuzzleHttp\Client(), $f, $f);
    }

    public function testGeneratedOperationCarriesPartnerCredentialsAndRetries429(): void
    {
        $seen = [];
        $mock = new MockHandler([
            new Psr7Response(429, ['Retry-After' => '0', 'Content-Type' => 'application/json'], '{"error":"rate_limited","message":"slow"}'),
            new Psr7Response(200, ['Content-Type' => 'application/json'], '{"tenants":[{"id":"t","name":"C1","slug":"c1","status":"active"}],"next_cursor":null}'),
        ]);
        $handler = function ($req, $opts) use ($mock, &$seen) {
            $seen[] = $req;

            return $mock($req, $opts);
        };
        $page = $this->client()->api(PartnerApi::class, 3, $handler)->partnerListTenants();
        self::assertSame('c1', $page->getTenants()[0]->getSlug());
        self::assertCount(2, $seen);
        self::assertSame('Bearer sk_test_1', $seen[1]->getHeaderLine('X-Partner-Authorization'));
        self::assertSame('/api/v1/partner/tenants', $seen[1]->getUri()->getPath());
    }

    public function testUserTokenGoesInAuthorization(): void
    {
        $seen = [];
        $mock = new MockHandler([new Psr7Response(200, ['Content-Type' => 'application/json'], '{"timeline":[]}')]);
        $handler = function ($req, $opts) use ($mock, &$seen) {
            $seen[] = $req;

            return $mock($req, $opts);
        };
        $this->client()->withUserToken('user-at')->api(AppointmentsApi::class, 3, $handler)->today(null, '2026-10-16');
        self::assertSame('Bearer user-at', $seen[0]->getHeaderLine('Authorization'));
        self::assertSame('Bearer sk_test_1', $seen[0]->getHeaderLine('X-Partner-Authorization'));
        self::assertStringContainsString('date=2026-10-16', $seen[0]->getUri()->getQuery());
    }

    public function testHandWrittenPathsAreDocumented(): void
    {
        $spec = json_decode((string) file_get_contents(__DIR__.'/../openapi.json'), true);
        $paths = array_keys($spec['paths']);
        preg_match_all("#'(/(?:api/v1|health)[^'{]*)'#", (string) file_get_contents(__DIR__.'/../src/Client.php'), $m);
        foreach (array_unique($m[1]) as $used) {
            $ok = false;
            foreach ($paths as $p) {
                $ok = $ok || str_starts_with($p, rtrim($used, '/'));
            }
            self::assertTrue($ok, "$used is not in openapi.json");
        }
    }
}
