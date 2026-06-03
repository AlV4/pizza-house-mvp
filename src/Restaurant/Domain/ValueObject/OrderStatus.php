<?php

declare(strict_types=1);

namespace App\Restaurant\Domain\ValueObject;

/**
 * Lifecycle of a customer order.
 *
 * Transition rules are owned by the CustomerOrder aggregate; the enum exposes
 * only the closed set of states and a couple of predicates used to express
 * those rules.
 *
 *   PLACED ──▶ PREPARING ──▶ READY ──▶ DELIVERED     (DELIVERED is terminal)
 *      │
 *      ▼
 *   CANCELLED                                          (CANCELLED is terminal)
 */
enum OrderStatus: string
{
    case Placed = 'PLACED';
    case Preparing = 'PREPARING';
    case Ready = 'READY';
    case Delivered = 'DELIVERED';
    case Cancelled = 'CANCELLED';

    public function isTerminal(): bool
    {
        return $this === self::Delivered || $this === self::Cancelled;
    }

    public function equals(self $other): bool
    {
        return $this === $other;
    }
}
