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
 * Integration tests: full pipeline (path → match → validate → dispatch) with real config.
 */
final class IntegrationTest extends TestCase
{
    private static ?string $configPath = null;

    public static function setUpBeforeClass(): void
    {
        $base = dirname(__DIR__, 2);
        self::$configPath = $base . '/config/endpoints.json';
        if (!is_file(self::$configPath)) {
            self::markTestSkipped('config/endpoints.json not found');
        }
    }

    public function testHealthPipeline(): void
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
        $this->assertNotNull($pathPrefix);
        $endpoint = Matcher::match(
            $request,
            $registry->endpoints,
            $registry->apps,
            $pathPrefix
        );
        $this->assertNotNull($endpoint, 'Expected match for /sb/minimal/v1/health');
        $this->assertSame('e-minimal-health', $endpoint['id']);

        $validations = $registry->getValidationsForEndpoint($endpoint['id']);
        $validation = Validator::validate(
            $endpoint,
            $request->query,
            $request->body,
            $request->contentType,
            [],
            $validations
        );
        $this->assertTrue($validation['valid'], 'Validation should pass for health');

        $handlersPath = dirname(__DIR__, 2) . '/examples/minimal-handler';
        Dispatcher::setHandlersPath(is_dir($handlersPath) ? $handlersPath : null);

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

    public function testPipelineMiss(): void
    {
        $registry = Registry::load(self::$configPath);
        $request = new NormalizedRequest(
            'localhost',
            '/sb/minimal/v1/nonexistent',
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
        $this->assertNull($endpoint, 'Expected no match for /sb/minimal/v1/nonexistent');
    }
}
