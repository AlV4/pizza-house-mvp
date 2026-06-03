<?php

declare(strict_types=1);

namespace App\Kitchen\Domain\ValueObject;

/**
 * Lifecycle of a cooking order.
 *
 * Transition rules are owned by the CookingOrder aggregate; the enum exposes
 * only the closed set of states and a couple of predicates used to express
 * those rules.
 *
 *   PENDING ──▶ IN_PROGRESS ──▶ READY        (READY is terminal)
 *      │             │
 *      └──────┬──────┘
 *             ▼
 *         CANCELLED                           (CANCELLED is terminal)
 */
enum CookingStatus: string
{
    case Pending = 'PENDING';
    case InProgress = 'IN_PROGRESS';
    case Ready = 'READY';
    case Cancelled = 'CANCELLED';

    public function isTerminal(): bool
    {
        return $this === self::Ready || $this === self::Cancelled;
    }

    public function equals(self $other): bool
    {
        return $this === $other;
    }
}
