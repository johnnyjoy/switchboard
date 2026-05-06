<?php

declare(strict_types=1);

/**
 * Validate request against endpoint parameters and validations (registry).
 *
 * Returns 400-style errors for missing required params, wrong content-type,
 * or schema violations. Aligns with runtime/validator.js and docs/endpoint-schema.md.
 *
 * @link docs/endpoint-schema.md Parameters and validations
 */

namespace Switchboard\Runtime;

final class Validator
{
    /**
     * Validate query parameters, content-type, and body against endpoint config.
     *
     * @param array<string, mixed>                $endpoint    Matched endpoint record.
     * @param array<string, string|array<string>> $query       Query parameters.
     * @param string|null                        $body        Raw request body.
     * @param string|null                        $contentType Content-Type header.
     * @param array<int, array<string, mixed>>   $parameters  Endpoint parameter definitions.
     * @param array<int, array<string, mixed>>   $validations Endpoint validation definitions.
     * @return array{valid: bool, errors: list<string>} Validation result and error messages.
     */
    public static function validate(
        array $endpoint,
        array $query,
        ?string $body,
        ?string $contentType,
        array $parameters,
        array $validations
    ): array {
        $errors = [];

        foreach ($parameters as $p) {
            if (($p['in'] ?? '') !== 'query') {
                continue;
            }
            $name = $p['name'];
            $value = $query[$name] ?? null;
            $missing = $value === null || $value === '' || (is_array($value) && empty($value));
            if (!empty($p['required']) && $missing) {
                $errors[] = "Missing required parameter: {$name} (query)";
                continue;
            }
            if (!$missing && $value !== null && isset($p['type']) && $p['type'] !== '') {
                $v = is_array($value) ? ($value[0] ?? '') : $value;
                if (!self::checkType((string) $v, $p['type'])) {
                    $errors[] = "Parameter {$name} must be of type {$p['type']}";
                }
            }
        }

        $pathParams = is_array($endpoint['path_params'] ?? null) ? $endpoint['path_params'] : [];
        foreach ($parameters as $p) {
            if (($p['in'] ?? '') !== 'path') {
                continue;
            }
            $name = $p['name'];
            $value = $pathParams[$name] ?? null;
            $missing = $value === null || $value === '';
            if (!empty($p['required']) && $missing) {
                $errors[] = "Missing required parameter: {$name} (path)";
                continue;
            }
            if (!$missing && isset($p['type']) && $p['type'] !== '' && !self::checkType((string) $value, $p['type'])) {
                $errors[] = "Parameter {$name} must be of type {$p['type']}";
            }
        }

        $contentType = $contentType !== null ? strtolower(trim($contentType)) : '';
        $hasBody = $body !== null && $body !== '';

        if (!empty($validations) && $hasBody) {
            $v = $validations[0];
            $expectedCt = isset($v['content_type']) ? strtolower(trim((string) $v['content_type'])) : '';
            if ($expectedCt !== '' && strpos($contentType, $expectedCt) === false && strpos($expectedCt, $contentType) !== 0) {
                $errors[] = 'Expected Content-Type: ' . ($v['content_type'] ?? '');
            }
            if (isset($v['schema']) && $v['schema'] !== null && is_string($body) && strpos($contentType, 'json') !== false) {
                try {
                    $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException) {
                    $errors[] = 'Invalid JSON body';
                    $decoded = null;
                }

                if ($decoded !== null && !self::validateMinimalSchema($decoded, $v['schema'])) {
                    $errors[] = 'Body does not match schema';
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Check that a string value matches a declared parameter type.
     *
     * @param string $value Query/string value.
     * @param string $type  Declared type (string, number, integer, boolean).
     * @return bool True if value is valid for the type.
     */
    private static function checkType(string $value, string $type): bool
    {
        switch ($type) {
            case 'string':
                return true;
            case 'number':
                return is_numeric($value) && $value !== '';
            case 'integer':
                return (string) (int) $value === trim($value);
            case 'boolean':
                return $value === 'true' || $value === 'false';
            default:
                return true;
        }
    }

    /**
     * Validate decoded body against a minimal schema (required keys from properties).
     *
     * @param mixed $data   Decoded request body (array or other).
     * @param mixed $schema Schema with optional 'properties' and 'required' keys.
     * @return bool True if data satisfies required keys.
     */
    private static function validateMinimalSchema(mixed $data, mixed $schema): bool
    {
        if ($schema === null || !is_array($schema)) {
            return true;
        }
        if (isset($schema['properties']) && is_array($schema['properties'])) {
            $required = $schema['required'] ?? [];
            if (is_array($required)) {
                $d = is_array($data) ? $data : [];
                foreach ($required as $key) {
                    if (!array_key_exists($key, $d)) {
                        return false;
                    }
                }
            }
        }
        return true;
    }
}
