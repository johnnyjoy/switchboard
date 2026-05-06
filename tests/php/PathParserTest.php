<?php

declare(strict_types=1);

namespace Switchboard\Runtime\Tests;

use PHPUnit\Framework\TestCase;
use Switchboard\Runtime\PathParser;

final class PathParserTest extends TestCase
{
    public function testParsesPrefix(): void
    {
        $result = PathParser::parsePrefix('/sb/minimal/v1/health');
        $this->assertNotNull($result);
        $this->assertSame('minimal', $result['app_slug']);
        $this->assertSame('v1', $result['version']);
        $this->assertSame('/health', $result['path']);
    }

    public function testMissingPrefixFails(): void
    {
        $this->assertNull(PathParser::parsePrefix('/other/path'));
        $this->assertNull(PathParser::parsePrefix('/sb/minimal'));
    }

    public function testNormalizesPath(): void
    {
        $this->assertSame('/health', PathParser::normalizePath('health'));
        $this->assertSame('/health', PathParser::normalizePath('/health/'));
        $this->assertSame('/', PathParser::normalizePath(''));
    }

    public function testIncompletePrefixFails(): void
    {
        $this->assertNull(PathParser::parsePrefix('/sb/minimal'));
        $this->assertNull(PathParser::parsePrefix('/sb/minimal/'));
    }

    public function testParsesNestedPath(): void
    {
        $result = PathParser::parsePrefix('/sb/minimal/v1/items/123');
        $this->assertNotNull($result);
        $this->assertSame('minimal', $result['app_slug']);
        $this->assertSame('v1', $result['version']);
        $this->assertSame('/items/123', $result['path']);
    }

    public function testParsesMountedApiPath(): void
    {
        $result = PathParser::parseMount('/foo/api/health', 'foo', '/foo', '/api');
        $this->assertNotNull($result);
        $this->assertSame('foo', $result['app_slug']);
        $this->assertSame('mounted', $result['version']);
        $this->assertSame('/health', $result['path']);
    }

    public function testMountedStaticPathFails(): void
    {
        $this->assertNull(PathParser::parseMount('/foo', 'foo', '/foo', '/api'));
        $this->assertNull(PathParser::parseMount('/foo/products', 'foo', '/foo', '/api'));
        $this->assertNull(PathParser::parseMount('/bar/api/health', 'foo', '/foo', '/api'));
    }
}
