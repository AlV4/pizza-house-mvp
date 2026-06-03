<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus;

/**
 * Dispatches a query to its single handler synchronously.
 *
 * Queries are read-only. Handlers MUST NOT mutate state.
 */
interface QueryBus
{
    public function ask(object $query): mixed;
}
