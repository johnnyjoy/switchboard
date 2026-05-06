<?php

declare(strict_types=1);

/**
 * Interface for app handlers invoked by the runtime.
 *
 * Implement this interface (or provide a callable with the same signature) so
 * the runtime can dispatch the normalized request and receive a normalized response.
 * See docs/php-app-contract.md.
 *
 * @link docs/php-app-contract.md PHP app contract
 */

namespace Switchboard\Runtime;

interface SwitchboardAppInterface
{
    /**
     * Handle the request and return a normalized response.
     *
     * @param SwitchboardRequest $request Normalized request from the runtime.
     * @param SwitchboardContext|null $context Optional app/endpoint context (when provided by runtime).
     * @return SwitchboardResponse|array{status: int, headers: array<string, string>, body: string|array<mixed>}
     */
    public function handle(SwitchboardRequest $request, ?SwitchboardContext $context = null): SwitchboardResponse|array;
}
