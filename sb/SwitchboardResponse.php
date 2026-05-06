<?php

declare(strict_types=1);

/**
 * Contract for the normalized response returned by app handlers to the runtime.
 *
 * Handlers return a value implementing this interface (or an array with
 * status, headers, body). The runtime converts it to HTTP. See docs/php-app-contract.md.
 *
 * @link docs/php-app-contract.md PHP app contract
 */

namespace Switchboard\Runtime;

interface SwitchboardResponse
{
    public function getStatus(): int;

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array;

    /** @return string|array<mixed> */
    public function getBody(): string|array;
}
