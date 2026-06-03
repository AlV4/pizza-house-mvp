<?php

declare(strict_types=1);

namespace App\Kitchen\Domain\Aggregate;

use App\Kitchen\Domain\Event\CookingOrderCancelled;
use App\Kitchen\Domain\Event\CookingOrderCreated;
use App\Kitchen\Domain\Event\CookingOrderStarted;
use App\Kitchen\Domain\Event\PizzaCooked;
use App\Kitchen\Domain\Exception\InvalidStatusTransition;
use App\Kitchen\Domain\ValueObject\CookingOrderId;
use App\Kitchen\Domain\ValueObject\CookingStatus;
use App\Kitchen\Domain\ValueObject\IngredientRequirement;
use App\Kitchen\Domain\ValueObject\RecipeId;
use App\Kitchen\Domain\ValueObject\RecipeSnapshot;
use App\Shared\Domain\AggregateRoot;

/**
 * A single cooking job: cook one pizza of a given recipe for one customer order
 * item.
 *
 * Lifecycle (see CookingStatus):
 *   PENDING ──startCooking()──▶ IN_PROGRESS ──markAsReady()──▶ READY
 *      │                              │
 *      └────────────cancel()─────────┘ ──▶ CANCELLED
 *
 * Transitions are idempotent on the destination state (re-issuing a transition
 * that has already happened is a no-op) but reject illegal jumps.
 */
final class CookingOrder extends AggregateRoot
{
    private CookingStatus $status;
    private ?\DateTimeImmutable $startedAt = null;
    private ?\DateTimeImmutable $completedAt = null;

    private function __construct(
        private readonly CookingOrderId $id,
        private readonly string $customerOrderId,
        private readonly RecipeId $recipeId,
        private readonly RecipeSnapshot $recipeSnapshot,
    ) {
        $this->status = CookingStatus::Pending;
    }

    /**
     * Registers a new cooking order in PENDING state, freezing the recipe into
     * a snapshot so later edits to the recipe never affect this order.
     */
    public static function create(
        CookingOrderId $id,
        string $customerOrderId,
        Recipe $recipe,
    ): self {
        $trimmedCustomerOrderId = trim($customerOrderId);

        if ($trimmedCustomerOrderId === '') {
            throw new \InvalidArgumentException('Customer order id must not be empty.');
        }

        $order = new self(
            $id,
            $trimmedCustomerOrderId,
            $recipe->id(),
            RecipeSnapshot::fromRecipe($recipe),
        );

        $order->recordEvent(new CookingOrderCreated(
            $id->value(),
            $trimmedCustomerOrderId,
            $recipe->id()->value(),
        ));

        return $order;
    }

    /**
     * Begins cooking. Emits CookingOrderStarted carrying the frozen ingredient
     * list so Storage can consume stock. No-op if already in progress.
     */
    public function startCooking(): void
    {
        if ($this->status === CookingStatus::InProgress) {
            return;
        }

        if ($this->status !== CookingStatus::Pending) {
            throw InvalidStatusTransition::fromTo($this->status, CookingStatus::InProgress);
        }

        $this->status = CookingStatus::InProgress;
        $this->startedAt = new \DateTimeImmutable();

        $this->recordEvent(new CookingOrderStarted(
            $this->id->value(),
            $this->customerOrderId,
            $this->recipeId->value(),
            $this->ingredientsPayload(),
        ));
    }

    /**
     * Marks the pizza as cooked. No-op if already ready.
     */
    public function markAsReady(): void
    {
        if ($this->status === CookingStatus::Ready) {
            return;
        }

        if ($this->status !== CookingStatus::InProgress) {
            throw InvalidStatusTransition::fromTo($this->status, CookingStatus::Ready);
        }

        $this->status = CookingStatus::Ready;
        $this->completedAt = new \DateTimeImmutable();

        $this->recordEvent(new PizzaCooked(
            $this->id->value(),
            $this->customerOrderId,
            $this->recipeId->value(),
        ));
    }

    /**
     * Cancels the order before completion. Allowed from PENDING or IN_PROGRESS.
     * No-op if already cancelled; rejected once the pizza is READY.
     */
    public function cancel(string $reason): void
    {
        if ($this->status === CookingStatus::Cancelled) {
            return;
        }

        if ($this->status->isTerminal()) {
            throw InvalidStatusTransition::fromTo($this->status, CookingStatus::Cancelled);
        }

        $trimmedReason = trim($reason);

        if ($trimmedReason === '') {
            throw new \InvalidArgumentException('Cancellation reason must not be empty.');
        }

        $this->status = CookingStatus::Cancelled;

        $this->recordEvent(new CookingOrderCancelled(
            $this->id->value(),
            $this->customerOrderId,
            $trimmedReason,
        ));
    }

    public function id(): CookingOrderId
    {
        return $this->id;
    }

    public function customerOrderId(): string
    {
        return $this->customerOrderId;
    }

    public function recipeId(): RecipeId
    {
        return $this->recipeId;
    }

    public function recipeSnapshot(): RecipeSnapshot
    {
        return $this->recipeSnapshot;
    }

    public function status(): CookingStatus
    {
        return $this->status;
    }

    public function startedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function completedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    /**
     * @return list<array{name: string, quantity: float, unit: string}>
     */
    private function ingredientsPayload(): array
    {
        return array_map(
            static fn (IngredientRequirement $ingredient): array => [
                'name' => $ingredient->name(),
                'quantity' => $ingredient->quantity(),
                'unit' => $ingredient->unit()->value,
            ],
            $this->recipeSnapshot->ingredients(),
        );
    }
}
