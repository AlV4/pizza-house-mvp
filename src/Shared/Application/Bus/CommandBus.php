<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus;

/**
 * Dispatches a command to its single handler synchronously, inside a transaction.
 *
 * Commands mutate state. They MUST NOT return domain objects;
 * if a value is needed, expose a query for it.
 */
interface CommandBus
{
    public function dispatch(object $command): void;
}
