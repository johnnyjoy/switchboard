<?php

declare(strict_types=1);

namespace Switchboard\Runtime\Tests;

use PHPUnit\Framework\TestCase;
use Switchboard\Runtime\Dispatcher;
use Switchboard\Runtime\Matcher;
use Switchboard\Runtime\NormalizedRequest;
use Switchboard\Runtime\PathParser;
use Switchboard\Runtime\Registry;
use Switchboard\Runtime\Validator;

/**
 * Tests the full routing pipeline: path parse → match → validate → dispatch.
 * Uses fixtures so config is deterministic; no dependency on config/endpoints.json.
 */
final class RouterPipelineTest extends TestCase
{
    private static ?string $configPath = null;
    private static ?string $handlersPath = null;

    public static function setUpBeforeClass(): void
    {
        $base = dirname(__DIR__, 2);
        self::$configPath = $base . '/tests/php/fixtures/endpoints-pipeline.json';
        self::$handlersPath = $base . '/examples/minimal-handler';
        if (!is_file(self::$configPath)) {
            self::markTestSkipped('fixtures/endpoints-pipeline.json not found');
        }
    }

    protected function tearDown(): void
    {
        Dispatcher::setHandlersPath(null);
    }

    public function testHealthRoutePipeline(): void
    {
        $registry = Registry::load(self::$configPath);
        $request = new NormalizedRequest(
            'localhost',
            '/sb/minimal/v1/health',
            'GET',
            [],
            [],
            [],
            null,
            null,
            null,
            null
        );

        $pathPrefix = PathParser::parsePrefix($request->path);
        $this->assertNotNull($pathPrefix, 'Path should have /sb/app/version/ prefix');
        $this->assertSame('minimal', $pathPrefix['app_slug']);
        $this->assertSame('/health', $pathPrefix['path']);

        $endpoint = Matcher::match(
            $request,
            $registry->endpoints,
            $registry->apps,
            $pathPrefix
        );
        $this->assertNotNull($endpoint);
        $this->assertSame('e-minimal-health', $endpoint['id']);
        $this->assertContains('GET', $endpoint['methods']);

        $validations = $registry->getValidationsForEndpoint($endpoint['id']);
        $validation = Validator::validate(
            $endpoint,
            $request->query,
            $request->body,
            $request->contentType,
            [],
            $validations
        );
        $this->assertTrue($validation['valid'], 'Validation should pass: ' . implode('; ', $validation['errors']));

        if (is_dir(self::$handlersPath)) {
            Dispatcher::setHandlersPath(self::$handlersPath);
        }
        $result = Dispatcher::dispatch($endpoint, $request, $registry);
        $this->assertSame(200, $result['status']);
        $this->assertIsArray($result['body']);
        if (isset($result['body']['ok'], $result['body']['service'])) {
            $this->assertTrue($result['body']['ok']);
            $this->assertSame('minimal-handler', $result['body']['service']);
        } else {
            $this->assertArrayHasKey('handler_class', $result['body']);
            $this->assertSame('Minimal\\Health', $result['body']['handler_class']);
            $this->assertSame('handle', $result['body']['handler_method']);
        }
    }

    public function testUnknownPathMisses(): void
    {
        $registry = Registry::load(self::$configPath);
        $request = new NormalizedRequest(
            'localhost',
            '/sb/minimal/v1/unknown',
            'GET',
            [],
            [],
            [],
            null,
            null,
            null,
            null
        );

        $pathPrefix = PathParser::parsePrefix($request->path);
        $this->assertNotNull($pathPrefix);
        $endpoint = Matcher::match(
            $request,
            $registry->endpoints,
            $registry->apps,
            $pathPrefix
        );
        $this->assertNull($endpoint);
    }

    public function testWrongMethodMisses(): void
    {
        $registry = Registry::load(self::$configPath);
        $request = new NormalizedRequest(
            'localhost',
            '/sb/minimal/v1/health',
            'POST',
            [],
            [],
            [],
            null,
            null,
            null,
            null
        );

        $pathPrefix = PathParser::parsePrefix($request->path);
        $this->assertNotNull($pathPrefix);
        $endpoint = Matcher::match(
            $request,
            $registry->endpoints,
            $registry->apps,
            $pathPrefix
        );
        $this->assertNull($endpoint);
    }
}
