# Storage Context

**Purpose.** Track the available quantity of each ingredient, accept deliveries that increase stock, accept consumption requests from the kitchen that decrease stock, and signal when stock falls below a configured threshold.

This context is fully implemented. See [`docs/context-map.md`](../context-map.md) for how it relates to Kitchen and (the documented-only) Procurement.

## Ubiquitous Language

| Term                | Meaning                                                                                          |
| ------------------- | ------------------------------------------------------------------------------------------------ |
| **Stock**           | The available quantity of a single ingredient, with its threshold and unit of measure.           |
| **Delivery**        | A registered receipt of additional quantity that increases stock.                                |
| **Consumption**     | A registered withdrawal that decreases stock — driven by cooking activity.                        |
| **Threshold**       | The level below which stock is considered low and a depletion signal is emitted.                 |
| **Out of stock**    | The condition where a requested consumption exceeds available quantity. Treated as a fault.       |

There is no `Ingredient` aggregate separate from `Stock`. We don't need a catalogue of ingredients independent of their stock; the two are inseparable in this MVP.

## Aggregates

### Stock (aggregate root)

A single ingredient's running quantity, threshold, and history of operations (history is implicit via events; not stored on the aggregate).

**Identity:** `StockId` (ULID, value object).

**State:**
- `ingredientName: IngredientName` — value object; unique across active stocks (enforced at the repository level for the MVP).
- `unit: Unit` — same enum as Kitchen's `Unit`. We tolerate the duplication: it's a shared kernel candidate but not extracted here to avoid premature shared kernel.
- `availableQuantity: Quantity` — non-negative.
- `threshold: Quantity` — non-negative.

**Invariants:**
1. `availableQuantity` MUST be `>= 0` at all times.
2. `threshold` MUST be `>= 0`.
3. `unit` MUST match the unit of any delivery or consumption operation.
4. Once registered, `unit` and `ingredientName` are immutable. To change them, the stock must be retired and a new one registered.

**Behaviors:**
- `Stock::register(id, ingredientName, unit, initialQuantity, threshold): Stock` — factory; validates all invariants; records `IngredientStockRegistered`.
- `addDelivery(Quantity delivered)` — increases `availableQuantity`; unit must match; records `IngredientStockReplenished`. If the new quantity rises above the threshold from previously below, records `IngredientStockReplenishedAboveThreshold` (informational, not implemented as a consumer event).
- `consume(Quantity requested)` — decreases `availableQuantity`; unit must match. If `requested > availableQuantity`, the operation FAILS and records `IngredientOutOfStock` instead of modifying state. If the operation succeeds AND the new quantity falls below threshold from above-or-equal, records `IngredientStockDepleted`.
- `adjustThreshold(Quantity newThreshold)` — sets a new threshold; records `IngredientThresholdChanged`. May or may not re-evaluate depletion — for the MVP, it does not (depletion fires only on consume).

### Why depletion fires only "on transition"

It's tempting to emit `IngredientStockDepleted` every time `availableQuantity` is below the threshold. That would spam the bus. Instead, the event is emitted *exactly when the boundary is crossed downward* — when a consume operation takes the quantity from `>= threshold` to `< threshold`. The aggregate tracks this by comparing before-state and after-state inside the `consume` method.

This is a critical detail for the `domain-modeler` agent: the event is not "stock is below threshold" but "stock just crossed the threshold."

## Value Objects

| Value Object       | Fields                                            | Validation                                   |
| ------------------ | ------------------------------------------------- | -------------------------------------------- |
| `StockId`          | `value: string` (ULID)                            | Must be a valid ULID.                        |
| `IngredientName`   | `value: string`                                   | 2–80 chars, trimmed, non-empty, lowercased for comparison purposes. |
| `Unit`             | enum-like: `g`, `kg`, `ml`, `l`, `piece`          | Closed set. Mirrors Kitchen's `Unit`.        |
| `Quantity`         | `value: float`, `unit: Unit`                      | `value >= 0`. Arithmetic only between matching units. |

`Quantity` supports `add(Quantity): Quantity` and `subtract(Quantity): Quantity`. Subtraction below zero throws a domain exception (used by `Stock::consume` for the out-of-stock check).

## Domain Events

### Published by Storage

| Event                                | When                                                          | Payload                                                              | Consumers                          |
| ------------------------------------ | ------------------------------------------------------------- | -------------------------------------------------------------------- | ---------------------------------- |
| `IngredientStockRegistered`          | A new stock is registered.                                    | `stockId`, `ingredientName`, `unit`, `initialQuantity`, `threshold`, `occurredOn` | None implemented.                  |
| `IngredientStockReplenished`         | A delivery is registered.                                     | `stockId`, `ingredientName`, `addedQuantity`, `unit`, `newAvailable`, `occurredOn` | None implemented.                  |
| `IngredientStockDepleted`            | A consume operation crosses the threshold downward.           | `stockId`, `ingredientName`, `availableQuantity`, `threshold`, `unit`, `occurredOn` | **None implemented. Procurement would consume.** |
| `IngredientOutOfStock`               | A consume operation is rejected because requested > available. | `stockId`, `ingredientName`, `requestedQuantity`, `availableQuantity`, `unit`, `occurredOn` | None implemented. Kitchen would consume in a future iteration. |
| `IngredientThresholdChanged`         | The threshold is adjusted.                                    | `stockId`, `oldThreshold`, `newThreshold`, `occurredOn`              | None.                              |

### Consumed by Storage

| Event                      | From    | Handler responsibility                                                                                                          |
| -------------------------- | ------- | ------------------------------------------------------------------------------------------------------------------------------- |
| `CookingOrderStarted`      | Kitchen | For each ingredient in the event's `ingredients` payload, dispatch a `ConsumeIngredient` command targeting the matching stock by name. |

The handler does not call back into Kitchen if a stock is missing or an `IngredientOutOfStock` event fires — that's intentional. The MVP treats these as observable faults, not as compensating-transaction triggers.

## Repository Interface

```php
interface StockRepository
{
    public function findById(StockId $id): ?Stock;
    public function findByIngredientName(IngredientName $name): ?Stock;
    /** @return Stock[] */
    public function findAllBelowThreshold(): array;
    public function save(Stock $stock): void;
    public function remove(Stock $stock): void;
}
```

`findAllBelowThreshold()` is exposed because the depletion-event-only model means a fresh consumer of `IngredientStockDepleted` would miss historically-below stocks. A bulk query lets us recover that information on demand. This is a deliberate departure from the strict "no findBy" rule in the root `CLAUDE.md` and is justified by a real consumer need (a hypothetical Procurement dashboard).

## Commands (write side)

| Command                      | Handler responsibility                                                                                                |
| ---------------------------- | --------------------------------------------------------------------------------------------------------------------- |
| `RegisterStock`              | Validate uniqueness by name, build `Stock::register()`, save, dispatch.                                               |
| `RegisterIngredientDelivery` | Load stock by id (or by name — chef's call), call `addDelivery()`, save, dispatch.                                    |
| `ConsumeIngredient`          | Load stock by name (Kitchen events carry names, not ids), call `consume()`, save, dispatch. Triggered by `CookingOrderStarted` handler. |
| `AdjustThreshold`            | Load stock, call `adjustThreshold()`, save, dispatch.                                                                  |

## Queries (read side)

| Query                          | Returns                                                                  |
| ------------------------------ | ------------------------------------------------------------------------ |
| `GetIngredientAvailability`    | `StockView` DTO for a single ingredient by name or id.                   |
| `ListAllStocks`                | `StockView[]`.                                                           |
| `ListLowStockIngredients`      | `StockView[]` filtered to entries below threshold.                       |

## File Layout

```
src/Storage/
├── CLAUDE.md
├── Domain/
│   ├── Aggregate/
│   │   └── Stock.php
│   ├── ValueObject/
│   │   ├── StockId.php
│   │   ├── IngredientName.php
│   │   ├── Unit.php
│   │   └── Quantity.php
│   ├── Event/
│   │   ├── IngredientStockRegistered.php
│   │   ├── IngredientStockReplenished.php
│   │   ├── IngredientStockDepleted.php
│   │   ├── IngredientOutOfStock.php
│   │   └── IngredientThresholdChanged.php
│   ├── Repository/
│   │   └── StockRepository.php
│   └── Exception/
│       ├── StockNotFound.php
│       ├── UnitMismatch.php
│       └── InsufficientStock.php
├── Application/
│   ├── RegisterStock/
│   ├── RegisterIngredientDelivery/
│   ├── ConsumeIngredient/
│   ├── AdjustThreshold/
│   ├── GetIngredientAvailability/
│   ├── ListAllStocks/
│   ├── ListLowStockIngredients/
│   └── EventHandler/
│       └── OnCookingOrderStarted.php
├── Infrastructure/
│   └── Persistence/Doctrine/
│       ├── DoctrineStockRepository.php
│       └── Mappings/
│           └── Stock.orm.xml
└── UI/Http/
    └── StockController.php
```

## HTTP Endpoints

| Method | Path                                       | Maps to                              |
| ------ | ------------------------------------------ | ------------------------------------ |
| POST   | `/storage/stocks`                          | `RegisterStock`                      |
| POST   | `/storage/stocks/{id}/deliveries`          | `RegisterIngredientDelivery`         |
| PATCH  | `/storage/stocks/{id}/threshold`           | `AdjustThreshold`                    |
| GET    | `/storage/stocks/{id}`                     | `GetIngredientAvailability` (by id)  |
| GET    | `/storage/stocks?name=mozzarella`          | `GetIngredientAvailability` (by name)|
| GET    | `/storage/stocks`                          | `ListAllStocks`                      |
| GET    | `/storage/stocks/low`                      | `ListLowStockIngredients`            |

`ConsumeIngredient` is not exposed via HTTP. It is invoked only from the `OnCookingOrderStarted` event handler. Manual consumption would let the front desk silently corrupt stock — that's an integration point, not a user gesture.
