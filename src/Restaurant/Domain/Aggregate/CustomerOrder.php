<?php

declare(strict_types=1);

namespace App\Restaurant\Domain\Aggregate;

use App\Restaurant\Domain\Event\CustomerOrderAccepted;
use App\Restaurant\Domain\Event\CustomerOrderCancelled;
use App\Restaurant\Domain\Event\CustomerOrderDelivered;
use App\Restaurant\Domain\Event\CustomerOrderPlaced;
use App\Restaurant\Domain\Event\CustomerOrderReady;
use App\Restaurant\Domain\Event\OrderItemReady;
use App\Restaurant\Domain\Exception\InvalidStatusTransition;
use App\Restaurant\Domain\ValueObject\CustomerInfo;
use App\Restaurant\Domain\ValueObject\CustomerOrderId;
use App\Restaurant\Domain\ValueObject\Money;
use App\Restaurant\Domain\ValueObject\OrderItemId;
use App\Restaurant\Domain\ValueObject\OrderStatus;
use App\Shared\Domain\AggregateRoot;

/**
 * A customer-placed order, composed of one or more OrderItem entities.
 *
 * Lifecycle (see OrderStatus):
 *   PLACED ──accept()──▶ PREPARING ──(all items ready)──▶ READY ──deliver()──▶ DELIVERED
 *      │
 *      └──cancel()──▶ CANCELLED         (cancellation allowed from PLACED only)
 *
 * Transitions are idempotent on the destination state (re-issuing a transition
 * that has already happened is a no-op) but reject illegal jumps. The order
 * transitions to READY automatically when every item is fully cooked.
 */
final class CustomerOrder extends AggregateRoot
{
    private OrderStatus $status;
    private ?\DateTimeImmutable $readyAt = null;
    private ?\DateTimeImmutable $deliveredAt = null;

    /**
     * @param list<OrderItem> $items
     */
    private function __construct(
        private readonly CustomerOrderId $id,
        private readonly CustomerInfo $customer,
        private readonly array $items,
        private readonly Money $totalAmount,
        private readonly \DateTimeImmutable $placedAt,
    ) {
        $this->status = OrderStatus::Placed;
    }

    /**
     * Places a new order in PLACED state.
     *
     * Enforces: items non-empty, all item currencies identical; computes the
     * total as the sum of each item's line total. Records CustomerOrderPlaced
     * carrying the full item list so Kitchen can spin up cooking orders.
     *
     * @param OrderItem[] $items
     */
    public static function place(
        CustomerOrderId $id,
        CustomerInfo $customer,
        array $items,
    ): self {
        $items = array_values($items);

        if ($items === []) {
            throw new \InvalidArgumentException('A customer order must contain at least one item.');
        }

        $currency = $items[0]->pricePerUnit()->currency();
        $total = new Money(0, $currency);

        foreach ($items as $item) {
            if ($item->pricePerUnit()->currency() !== $currency) {
                throw new \InvalidArgumentException(
                    'All order items must share the same currency.'
                );
            }

            $total = $total->add($item->lineTotal());
        }

        $placedAt = new \DateTimeImmutable();

        $order = new self($id, $customer, $items, $total, $placedAt);

        $order->recordEvent(new CustomerOrderPlaced(
            $id->value(),
            $customer->name(),
            $customer->phone(),
            $order->itemsPayload(),
            $total->amount(),
            $total->currency(),
            $placedAt,
        ));

        return $order;
    }

    /**
     * Accepts the order into preparation. No-op if already PREPARING; rejected
     * once the order is READY, DELIVERED or CANCELLED.
     */
    public function accept(): void
    {
        if ($this->status === OrderStatus::Preparing) {
            return;
        }

        if ($this->status !== OrderStatus::Placed) {
            throw InvalidStatusTransition::fromTo($this->status, OrderStatus::Preparing);
        }

        $this->status = OrderStatus::Preparing;

        $this->recordEvent(new CustomerOrderAccepted($this->id->value()));
    }

    /**
     * Records that $count more units of the given item have been cooked.
     *
     * Requires the order to be PREPARING so READY is never reached without
     * passing through PREPARING. Always records OrderItemReady. When every item
     * is fully ready, the order transitions to READY automatically.
     */
    public function markItemReady(OrderItemId $itemId, int $count = 1): void
    {
        if ($this->status !== OrderStatus::Preparing) {
            throw InvalidStatusTransition::fromTo($this->status, OrderStatus::Ready);
        }

        $item = $this->itemById($itemId);

        if ($item === null) {
            throw new \InvalidArgumentException(
                sprintf('Order item "%s" does not belong to this order.', $itemId->value())
            );
        }

        $item->markReady($count);

        $this->recordEvent(new OrderItemReady(
            $this->id->value(),
            $itemId->value(),
            $item->readyCount(),
            $item->quantity(),
        ));

        if ($this->allItemsFullyReady()) {
            $this->status = OrderStatus::Ready;
            $this->readyAt = new \DateTimeImmutable();

            $this->recordEvent(new CustomerOrderReady(
                $this->id->value(),
                $this->readyAt,
            ));
        }
    }

    /**
     * Delivers the order to the customer. No-op if already DELIVERED; rejected
     * from any non-READY state.
     */
    public function deliver(): void
    {
        if ($this->status === OrderStatus::Delivered) {
            return;
        }

        if ($this->status !== OrderStatus::Ready) {
            throw InvalidStatusTransition::fromTo($this->status, OrderStatus::Delivered);
        }

        $this->status = OrderStatus::Delivered;
        $this->deliveredAt = new \DateTimeImmutable();

        $this->recordEvent(new CustomerOrderDelivered(
            $this->id->value(),
            $this->deliveredAt,
        ));
    }

    /**
     * Cancels the order. Allowed from PLACED only — an order in preparation
     * cannot be cancelled in the MVP. No-op if already CANCELLED.
     */
    public function cancel(string $reason): void
    {
        if ($this->status === OrderStatus::Cancelled) {
            return;
        }

        if ($this->status !== OrderStatus::Placed) {
            throw InvalidStatusTransition::fromTo($this->status, OrderStatus::Cancelled);
        }

        $trimmedReason = trim($reason);

        if ($trimmedReason === '') {
            throw new \InvalidArgumentException('Cancellation reason must not be empty.');
        }

        $this->status = OrderStatus::Cancelled;

        $this->recordEvent(new CustomerOrderCancelled(
            $this->id->value(),
            $trimmedReason,
        ));
    }

    public function id(): CustomerOrderId
    {
        return $this->id;
    }

    public function customer(): CustomerInfo
    {
        return $this->customer;
    }

    /**
     * @return list<OrderItem>
     */
    public function items(): array
    {
        return $this->items;
    }

    public function status(): OrderStatus
    {
        return $this->status;
    }

    public function totalAmount(): Money
    {
        return $this->totalAmount;
    }

    public function placedAt(): \DateTimeImmutable
    {
        return $this->placedAt;
    }

    public function readyAt(): ?\DateTimeImmutable
    {
        return $this->readyAt;
    }

    public function deliveredAt(): ?\DateTimeImmutable
    {
        return $this->deliveredAt;
    }

    private function itemById(OrderItemId $itemId): ?OrderItem
    {
        foreach ($this->items as $item) {
            if ($item->itemId()->equals($itemId)) {
                return $item;
            }
        }

        return null;
    }

    private function allItemsFullyReady(): bool
    {
        foreach ($this->items as $item) {
            if (!$item->isFullyReady()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<array{itemId: string, recipeId: string, quantity: int, pricePerUnit: int, currency: string}>
     */
    private function itemsPayload(): array
    {
        return array_map(
            static fn (OrderItem $item): array => [
                'itemId' => $item->itemId()->value(),
                'recipeId' => $item->recipeId(),
                'quantity' => $item->quantity(),
                'pricePerUnit' => $item->pricePerUnit()->amount(),
                'currency' => $item->pricePerUnit()->currency(),
            ],
            $this->items,
        );
    }
}
