<?php

declare(strict_types=1);

/**
 * Contract for the normalized request passed from the runtime to app handlers.
 *
 * Backend apps type-hint this interface. The runtime builds a single
 * server-agnostic request (no $_GET/$_POST/$_SERVER). See docs/php-app-contract.md.
 *
 * @link docs/php-app-contract.md PHP app contract
 */

namespace Switchboard\Runtime;

interface SwitchboardRequest
{
    public function getHost(): string;

    public function getPath(): string;

    public function getMethod(): string;

    /**
     * @return array<string, string|array<string>>
     */
    public function getQuery(): array;

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array;

    /**
     * @return array<string, string>
     */
    public function getCookies(): array;

    public function getBody(): ?string;

    public function getContentType(): ?string;

    public function getRemoteAddr(): ?string;

    public function getForwardedFor(): ?string;

    /**
     * @return array<string, string>
     */
    public function getPathParams(): array;
}
