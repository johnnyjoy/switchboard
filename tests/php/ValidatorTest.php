<?php

declare(strict_types=1);

namespace Switchboard\Runtime\Tests;

use PHPUnit\Framework\TestCase;
use Switchboard\Runtime\Validator;

final class ValidatorTest extends TestCase
{
    private function endpoint(): array
    {
        return ['id' => 'e1', 'path' => '/health', 'method' => 'GET'];
    }

    public function testNoRulesPass(): void
    {
        $result = Validator::validate(
            $this->endpoint(),
            [],
            null,
            null,
            [],
            []
        );
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function testMissingQueryParamFails(): void
    {
        $params = [
            ['endpoint_id' => 'e1', 'in' => 'query', 'name' => 'limit', 'required' => true, 'type' => 'integer'],
        ];
        $result = Validator::validate(
            $this->endpoint(),
            [],
            null,
            null,
            $params,
            []
        );
        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('limit', $result['errors'][0]);
    }

    public function testRequiredQueryParamPasses(): void
    {
        $params = [
            ['endpoint_id' => 'e1', 'in' => 'query', 'name' => 'limit', 'required' => true, 'type' => 'integer'],
        ];
        $result = Validator::validate(
            $this->endpoint(),
            ['limit' => '10'],
            null,
            null,
            $params,
            []
        );
        $this->assertTrue($result['valid']);
    }

    public function testBadQueryTypeFails(): void
    {
        $params = [
            ['endpoint_id' => 'e1', 'in' => 'query', 'name' => 'limit', 'required' => true, 'type' => 'integer'],
        ];
        $result = Validator::validate(
            $this->endpoint(),
            ['limit' => 'not-a-number'],
            null,
            null,
            $params,
            []
        );
        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('integer', $result['errors'][0]);
    }

    public function testBodySchemaPasses(): void
    {
        $validations = [
            [
                'endpoint_id' => 'e1',
                'content_type' => 'application/json',
                'schema' => ['properties' => ['title' => []], 'required' => ['title']],
            ],
        ];
        $result = Validator::validate(
            $this->endpoint(),
            [],
            '{"title":"Hello"}',
            'application/json',
            [],
            $validations
        );
        $this->assertTrue($result['valid']);
    }

    public function testMissingBodyKeyFails(): void
    {
        $validations = [
            [
                'endpoint_id' => 'e1',
                'content_type' => 'application/json',
                'schema' => ['properties' => ['title' => []], 'required' => ['title']],
            ],
        ];
        $result = Validator::validate(
            $this->endpoint(),
            [],
            '{}',
            'application/json',
            [],
            $validations
        );
        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('schema', $result['errors'][0]);
    }

    public function testBadJsonFails(): void
    {
        $validations = [
            [
                'endpoint_id' => 'e1',
                'content_type' => 'application/json',
                'schema' => [],
            ],
        ];
        $result = Validator::validate(
            $this->endpoint(),
            [],
            'not json',
            'application/json',
            [],
            $validations
        );
        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('JSON', $result['errors'][0]);
    }
}
