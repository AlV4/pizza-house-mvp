# Architecture Decisions

Key decisions made during design, with rationale and trade-offs. Format is lightweight ADR.

## 1. Implementation path: hybrid (Option 3)

Three contexts implemented in full (Kitchen, Storage, Restaurant), three documented only (Personnel, Procurement, Sales).

**Why.** Option 1 (full app) at 8h budget produces wide-but-shallow code that hides DDD depth. Option 2 (single context) is too narrow for a senior signal. Hybrid demonstrates strategic judgment (knowing what to cut) and tactical depth (full DDD/CQRS in implemented contexts).

## 2. Modular monolith, not microservices

Single deployable, bounded contexts isolated by directory and integrated via events.

**Why.** Microservices at 8h is fantasy. A modular monolith with strict context boundaries proves the same architectural understanding without infrastructure overhead. Splitting later is mechanical when contexts are properly isolated.

## 3. Three Messenger buses (command / query / event)

Distinct buses with different semantics rather than one shared bus.

**Why.** Demonstrates CQRS explicitly. Different middleware per bus (transactions on commands, none on queries). Different handler cardinalities (one for commands/queries, many for events). One bus would obscure this.

## 4. Doctrine transport for async events, not RabbitMQ

Events persist in the same Postgres via `doctrine_messenger`.

**Why.** One less container, no broker setup, atomic with the write transaction (outbox-like semantics for free). Trade-off: not suitable for high throughput or multi-service topology. Documented as MVP-only; swapping to AMQP is a config change.

## 5. XML mappings for Doctrine, not attributes

Mappings live under `Infrastructure/Persistence/Doctrine/Mappings/`.

**Why.** Domain entities stay 100% framework-free. Attributes on aggregates would leak Doctrine into the domain layer. Trade-off: XML is more verbose. Accepted for architectural cleanliness.

## 6. Domain events routed by interface

`App\Shared\Domain\DomainEvent` interface is the routing key in messenger config.

**Why.** Any new event class is automatically async without per-class configuration. Reduces config churn as contexts grow.

## 7. Recipe snapshot inside CookingOrder

CookingOrder holds a frozen copy of recipe data, not a reference.

**Why.** If a recipe is edited mid-cooking (price change, ingredient swap), the in-flight order must reflect what was ordered, not what changed. Classic cross-aggregate-reference pitfall.

## 8. Depletion event only on threshold crossing

`IngredientStockDepleted` fires when consume takes stock from `>= threshold` to `< threshold`, not on every consumption below threshold.

**Why.** Avoids event spam. Aggregate compares before-state and after-state inside `consume()` to detect the transition. Trade-off: a fresh consumer of the event misses historically-below stocks — mitigated by `findAllBelowThreshold()` repository method.

## 9. Stock cannot go negative; out-of-stock is an event, not a mutation

If `consume()` would underflow, `Stock` records `IngredientOutOfStock` and rejects the operation.

**Why.** Strong invariant protects integrity. Kitchen does not react to this event in MVP — documented as a known gap that would require a saga/compensation in production.

## 10. OrderItem tracks `readyCount`, not boolean ready

Per-item counter `0..quantity`, increments on each `PizzaCooked`.

**Why.** Order with `quantity=3` produces three cooking orders and three events. Agreggate handles aggregation logic; no external orchestrator. Order auto-transitions to `READY` when all items have `readyCount == quantity`.

## 11. Cross-context query allowed in PlaceCustomerOrder

Restaurant calls Kitchen's `GetRecipe` via `QueryBus` to snapshot recipe name and price at order time.

**Why.** Alternatives considered: (a) anti-corruption layer with local recipe projection — more correct, more code; (b) client-supplied price — lets clients lie. Chosen approach is a deliberate compromise. Crosses contexts at Application layer only (via Published Language DTO), not Domain layer. Documented as a known coupling.

## 12. No shared kernel for Money / Unit duplication

`Money` exists in both Kitchen and Restaurant. `Unit` exists in both Kitchen and Storage. Both duplicated, not extracted.

**Why.** Shared kernel introduces coupling that's harder to undo than to maintain duplicates of two simple VOs. Re-evaluate when there's a third consumer.

## 13. ConsumeIngredient and StartCookingOrder not exposed via HTTP

Both are only invoked from event handlers.

**Why.** Manual HTTP triggers would let operators silently corrupt the system (stock without cooking, cooking without order). These are integration points, not user gestures.

## 14. No event versioning in MVP

If a payload shape changes, introduce a new event class rather than mutating the existing one.

**Why.** Versioning machinery is overkill at MVP scale. Adding `V2` events is mechanical when needed.

## 15. Single messenger consumer worker

One `messenger-consumer` container, not a worker pool.

**Why.** Serialized consumption gives deterministic event ordering, which matters for the `PizzaCooked` → `markItemReady` logic. Scaling out requires either partitioning by aggregate id or idempotent handlers — out of MVP scope.

## 16. AI-assisted development is part of the deliverable

`CLAUDE.md`, `.claude/agents/*`, and `docs/ai-workflow.md` are first-class repository artifacts.

**Why.** The task explicitly required AI-driven development. Treating agent configuration as code (versioned, reviewed, structured) is the credible interpretation. Three specialized sub-agents enforce architectural rules that a single general agent would drift away from over a long session.
