<?php

declare(strict_types=1);

namespace Switchboard\Runtime\Tests;

use PHPUnit\Framework\TestCase;
use Switchboard\Runtime\Matcher;
use Switchboard\Runtime\NormalizedRequest;

final class MatcherTest extends TestCase
{
    private function minimalApps(): array
    {
        return [
            ['id' => 'minimal-php-app', 'slug' => 'minimal', 'name' => 'Minimal', 'enabled' => true],
        ];
    }

    private function minimalEndpoints(): array
    {
        return [
            [
                'id' => 'e-minimal-health',
                'app_id' => 'minimal-php-app',
                'host' => null,
                'path' => '/health',
                'method' => 'GET',
                'handler_class' => 'Minimal\\Health', 'handler_method' => 'handle',
                'enabled' => true,
            ],
        ];
    }

    private function request(string $host, string $path, string $method): NormalizedRequest
    {
        return new NormalizedRequest($host, $path, $method, [], [], [], null, null, null, null);
    }

    public function testPrefixMatch(): void
    {
        $prefix = ['app_slug' => 'minimal', 'version' => 'v1', 'path' => '/health'];
        $req = $this->request('localhost', '/sb/minimal/v1/health', 'GET');
        $endpoint = Matcher::match($req, $this->minimalEndpoints(), $this->minimalApps(), $prefix);
        $this->assertNotNull($endpoint);
        $this->assertSame('e-minimal-health', $endpoint['id']);
        $this->assertSame('/health', $endpoint['path']);
        $this->assertSame('Minimal\\Health', $endpoint['handler_class']);
        $this->assertSame('handle', $endpoint['handler_method']);
    }

    public function testMountedApiMatch(): void
    {
        $prefix = ['app_slug' => 'minimal', 'version' => 'mounted', 'path' => '/health'];
        $req = $this->request('localhost', '/minimal/api/health', 'GET');
        $endpoint = Matcher::match($req, $this->minimalEndpoints(), $this->minimalApps(), $prefix);
        $this->assertNotNull($endpoint);
        $this->assertSame('e-minimal-health', $endpoint['id']);
    }

    public function testWrongPathMisses(): void
    {
        $prefix = ['app_slug' => 'minimal', 'version' => 'v1', 'path' => '/other'];
        $req = $this->request('localhost', '/sb/minimal/v1/other', 'GET');
        $endpoint = Matcher::match($req, $this->minimalEndpoints(), $this->minimalApps(), $prefix);
        $this->assertNull($endpoint);
    }

    public function testWrongMethodMisses(): void
    {
        $prefix = ['app_slug' => 'minimal', 'version' => 'v1', 'path' => '/health'];
        $req = $this->request('localhost', '/sb/minimal/v1/health', 'POST');
        $endpoint = Matcher::match($req, $this->minimalEndpoints(), $this->minimalApps(), $prefix);
        $this->assertNull($endpoint);
    }

    public function testExactHostWins(): void
    {
        $endpoints = [
            [
                'id' => 'e-any',
                'app_id' => 'minimal-php-app',
                'host' => null,
                'path' => '/health',
                'method' => 'GET',
                'handler_class' => 'Minimal\\HealthAny', 'handler_method' => 'handle',
                'enabled' => true,
            ],
            [
                'id' => 'e-localhost',
                'app_id' => 'minimal-php-app',
                'host' => 'localhost',
                'path' => '/health',
                'method' => 'GET',
                'handler_class' => 'Minimal\\Health', 'handler_method' => 'handle',
                'enabled' => true,
            ],
        ];
        $prefix = ['app_slug' => 'minimal', 'version' => 'v1', 'path' => '/health'];
        $req = $this->request('localhost', '/sb/minimal/v1/health', 'GET');
        $endpoint = Matcher::match($req, $endpoints, $this->minimalApps(), $prefix);
        $this->assertNotNull($endpoint);
        $this->assertSame('e-localhost', $endpoint['id']);
        $this->assertSame('Minimal\\Health', $endpoint['handler_class']);
        $this->assertSame('handle', $endpoint['handler_method']);
    }

    public function testHostIsCaseInsensitive(): void
    {
        $endpoints = [
            [
                'id' => 'e-example',
                'app_id' => 'minimal-php-app',
                'host' => 'example.com',
                'path' => '/health',
                'method' => 'GET',
                'handler_class' => 'Minimal\\Health',
                'handler_method' => 'handle',
                'enabled' => true,
            ],
        ];

        $prefix = ['app_slug' => 'minimal', 'version' => 'v1', 'path' => '/health'];
        $req = $this->request('Example.COM', '/sb/minimal/v1/health', 'GET');
        $endpoint = Matcher::match($req, $endpoints, $this->minimalApps(), $prefix);

        $this->assertNotNull($endpoint);
        $this->assertSame('e-example', $endpoint['id']);
    }

    public function testQueryPredicatePasses(): void
    {
        $endpoints = [
            [
                'id' => 'e-with-query',
                'app_id' => 'minimal-php-app',
                'host' => null,
                'path' => '/health',
                'method' => 'GET',
                'handler_class' => 'Minimal\\Health', 'handler_method' => 'handle',
                'enabled' => true,
                'predicates' => [
                    ['source' => 'query', 'name' => 'key', 'op' => 'equals', 'value' => 'secret'],
                ],
            ],
        ];
        $prefix = ['app_slug' => 'minimal', 'version' => 'v1', 'path' => '/health'];
        $req = new NormalizedRequest('localhost', '/sb/minimal/v1/health', 'GET', ['key' => 'secret'], [], [], null, null, null, null);
        $endpoint = Matcher::match($req, $endpoints, $this->minimalApps(), $prefix);
        $this->assertNotNull($endpoint);
        $this->assertSame('e-with-query', $endpoint['id']);
    }

    public function testQueryPredicateMisses(): void
    {
        $endpoints = [
            [
                'id' => 'e-with-query',
                'app_id' => 'minimal-php-app',
                'host' => null,
                'path' => '/health',
                'method' => 'GET',
                'handler_class' => 'Minimal\\Health', 'handler_method' => 'handle',
                'enabled' => true,
                'predicates' => [
                    ['source' => 'query', 'name' => 'key', 'op' => 'equals', 'value' => 'secret'],
                ],
            ],
        ];
        $prefix = ['app_slug' => 'minimal', 'version' => 'v1', 'path' => '/health'];
        $req = new NormalizedRequest('localhost', '/sb/minimal/v1/health', 'GET', [], [], [], null, null, null, null);
        $endpoint = Matcher::match($req, $endpoints, $this->minimalApps(), $prefix);
        $this->assertNull($endpoint);
    }

    public function testIpAllowPasses(): void
    {
        $endpoints = [
            [
                'id' => 'e-ip-allow',
                'app_id' => 'minimal-php-app',
                'host' => null,
                'path' => '/health',
                'method' => 'GET',
                'handler_class' => 'Minimal\\Health', 'handler_method' => 'handle',
                'enabled' => true,
                'conditions' => [
                    'ip_allow' => ['127.0.0.1', '10.0.0.0/8'],
                ],
            ],
        ];
        $prefix = ['app_slug' => 'minimal', 'version' => 'v1', 'path' => '/health'];
        $req = new NormalizedRequest('localhost', '/sb/minimal/v1/health', 'GET', [], [], [], null, null, '127.0.0.1', null);
        $endpoint = Matcher::match($req, $endpoints, $this->minimalApps(), $prefix);
        $this->assertNotNull($endpoint);
        $this->assertSame('e-ip-allow', $endpoint['id']);
    }

    public function testUserAgentPasses(): void
    {
        $endpoints = [
            [
                'id' => 'e-ua',
                'app_id' => 'minimal-php-app',
                'host' => null,
                'path' => '/health',
                'method' => 'GET',
                'handler_class' => 'Minimal\\Health', 'handler_method' => 'handle',
                'enabled' => true,
                'conditions' => [
                    'user_agent' => 'AdminBot',
                ],
            ],
        ];
        $prefix = ['app_slug' => 'minimal', 'version' => 'v1', 'path' => '/health'];
        $req = new NormalizedRequest('localhost', '/sb/minimal/v1/health', 'GET', [], ['user-agent' => 'Mozilla/5.0 AdminBot/1.0'], [], null, null, null, null);
        $endpoint = Matcher::match($req, $endpoints, $this->minimalApps(), $prefix);
        $this->assertNotNull($endpoint);
        $this->assertSame('e-ua', $endpoint['id']);
    }

    public function testEmptyConditionsPass(): void
    {
        $endpoints = [
            [
                'id' => 'e-no-conditions',
                'app_id' => 'minimal-php-app',
                'host' => null,
                'path' => '/health',
                'method' => 'GET',
                'handler_class' => 'Minimal\\Health', 'handler_method' => 'handle',
                'enabled' => true,
                'conditions' => [],
            ],
        ];
        $prefix = ['app_slug' => 'minimal', 'version' => 'v1', 'path' => '/health'];
        $req = $this->request('localhost', '/sb/minimal/v1/health', 'GET');
        $endpoint = Matcher::match($req, $endpoints, $this->minimalApps(), $prefix);
        $this->assertNotNull($endpoint);
        $this->assertSame('e-no-conditions', $endpoint['id']);
    }
}
