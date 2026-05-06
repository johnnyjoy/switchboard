<?php

declare(strict_types=1);

/**
 * Minimal PHP example app — single endpoint, one class handler.
 *
 * The endpoint config points at handler_class "Minimal\Health" and
 * handler_method "handle". The runtime includes this file from
 * SWITCHBOARD_HANDLERS_PATH and dispatches the normalized request to the class.
 *
 * @link docs/php-app-contract.md PHP app and handler contract
 */

namespace Minimal;

use Switchboard\Runtime\SwitchboardAppInterface;
use Switchboard\Runtime\SwitchboardContext;
use Switchboard\Runtime\SwitchboardRequest;
use Switchboard\Runtime\SwitchboardResponse;

final class Health implements SwitchboardAppInterface
{
    public function handle(SwitchboardRequest $request, ?SwitchboardContext $context = null): SwitchboardResponse|array
    {
        return [
            'status'  => 200,
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => ['ok' => true, 'service' => 'minimal-handler'],
        ];
    }
}
