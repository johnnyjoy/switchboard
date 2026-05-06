<?php

declare(strict_types=1);

/**
 * Normalized response shape per docs/php-app-contract.md.
 *
 * Implements SwitchboardResponse for backend app type-hinting. Handlers may
 * return this or an array with status, headers, body.
 *
 * @link docs/php-app-contract.md PHP app contract
 */

namespace Switchboard\Runtime;

final class NormalizedResponse implements SwitchboardResponse
{
    /** @var int */
    public readonly int $status;

    /** @var array<string, string> */
    public readonly array $headers;

    /**
     * Response body (string, array for JSON, or resource).
     *
     * @var string|array<mixed>|resource
     */
    public readonly mixed $body;

    /**
     * Build a normalized response instance.
     *
     * @param int                 $status  HTTP status code.
     * @param array<string, string> $headers Response headers.
     * @param string|array<mixed> $body   Response body (default empty string).
     */
    public function __construct(int $status, array $headers, string|array $body = '')
    {
        $this->status = $status;
        $this->headers = $headers;
        $this->body = $body;
    }

    /** @inheritDoc */
    public function getStatus(): int
    {
        return $this->status;
    }

    /** @inheritDoc */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /** @inheritDoc */
    public function getBody(): string|array
    {
        return is_resource($this->body) ? (string) $this->body : $this->body;
    }
}
