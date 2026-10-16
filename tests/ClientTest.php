<?php

declare(strict_types=1);

namespace PIndustries\SunCharts\Tests;

use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response as G;
use PIndustries\SunCharts\Auth\ApiKey;
use PIndustries\SunCharts\Auth\ClientCredentials;
use PIndustries\SunCharts\Client;
use PIndustries\SunCharts\Exception\ApiException;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    /** @var list<array{request: \Psr\Http\Message\RequestInterface}> */
    private array $history = [];

    private function http(G ...$responses): Guzzle
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        return new Guzzle(['handler' => $stack, 'http_errors' => false]);
    }

    public function test_api_key_header_and_user_token(): void
    {
        $f = new HttpFactory();
        $c = new Client('https://api.test/', new ApiKey('k1'), $this->http(new G(200, [], '{"date":"2026-10-16","timeline":[]}')), $f, $f);
        $out = $c->withUserToken('u1')->today(['tz' => 'America/Chicago']);
        $this->assertSame('2026-10-16', $out['date']);
        $req = $this->history[0]['request'];
        $this->assertSame('https://api.test/api/v1/today?tz=America%2FChicago', (string) $req->getUri());
        $this->assertSame('Bearer k1', $req->getHeaderLine('X-Partner-Authorization'));
        $this->assertSame('Bearer u1', $req->getHeaderLine('Authorization'));
    }

    public function test_errors_map_to_api_exception(): void
    {
        $f = new HttpFactory();
        $c = new Client('https://api.test', new ApiKey('k'), $this->http(new G(401, [], '{"error":"mfa_required","message":"x"}')), $f, $f);
        try {
            $c->login('a@b.test', 'pw');
            $this->fail('expected exception');
        } catch (ApiException $e) {
            $this->assertSame(401, $e->status());
            $this->assertSame('mfa_required', $e->errorCode());
            $this->assertFalse($e->isUnauthorized());
        }
    }

    public function test_client_credentials_caches_token_until_expiry(): void
    {
        $f = new HttpFactory();
        $http = $this->http(
            new G(200, [], '{"access_token":"t1","expires_in":120}'),
            new G(200, [], '{}'),
            new G(200, [], '{}'),
            new G(200, [], '{"access_token":"t2","expires_in":120}'),
            new G(200, [], '{}'),
        );
        $now = 1000;
        $cc = new ClientCredentials('https://api.test/oauth/token', 'id', 'secret', $http, $f, $f, clock: function () use (&$now) {
            return $now;
        });
        $c = new Client('https://api.test', $cc, $http, $f, $f);
        $c->health();
        $c->health();
        $now += 100; // within 30 s of expiry -> refresh
        $c->health();
        $this->assertCount(5, $this->history);
        $tok = $this->history[0]['request'];
        $this->assertSame('POST', $tok->getMethod());
        $this->assertStringContainsString('grant_type=client_credentials', (string) $tok->getBody());
        $this->assertSame('Basic '.base64_encode('id:secret'), $tok->getHeaderLine('Authorization'));
        $this->assertSame('Bearer t1', $this->history[2]['request']->getHeaderLine('X-Partner-Authorization'));
        $this->assertSame('Bearer t2', $this->history[4]['request']->getHeaderLine('X-Partner-Authorization'));
    }

    public function test_forward_filters_headers_and_returns_raw(): void
    {
        $f = new HttpFactory();
        $c = new Client('https://api.test', new ApiKey('k'), $this->http(new G(428, ['X-Powered-By' => 'x', 'Content-Type' => 'application/json'], '{"error":"e"}')), $f, $f);
        $r = $c->forward('GET', '/api/v1/inbox?x=1', ['Authorization' => 'Bearer u', 'Cookie' => 'c', 'X-Forwarded-For' => '1.2.3.4'], '');
        $this->assertSame(428, $r->status);
        $this->assertSame(['content-type' => 'application/json'], $r->safeHeaders());
        $req = $this->history[0]['request'];
        $this->assertSame('', $req->getHeaderLine('Cookie'));
        $this->assertSame('', $req->getHeaderLine('X-Forwarded-For'));
        $this->assertSame('Bearer u', $req->getHeaderLine('Authorization'));
    }
}
