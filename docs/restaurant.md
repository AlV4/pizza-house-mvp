# Restaurant Context

**Purpose.** Capture orders placed by customers, track them through the lifecycle from placement to delivery, and orchestrate the upstream signal to the Kitchen.

This context is fully implemented. See [`docs/context-map.md`](../context-map.md) for how it relates to Kitchen.

## Ubiquitous Language

| Term                  | Meaning                                                                                       |
| --------------------- | --------------------------------------------------------------------------------------------- |
| **Customer order**    | A single order placed by a customer, consisting of one or more pizzas.                        |
| **Order item**        | One line of an order: a recipe reference, a quantity, a unit price snapshot.                  |
| **Order status**      | The lifecycle state of the order.                                                             |
| **Item readiness**    | Whether a specific item in the order has been cooked. Tracked per item, drives order status.  |

There is no `Customer` aggregate. Customers are identified by a name + a phone number captured on the order. A real system would model customers separately; that's out of MVP scope.

## Aggregates

### CustomerOrder (aggregate root)

A customer-placed order.

**Identity:** `CustomerOrderId` (ULID, value object).

**State:**
- `customer: CustomerInfo` — value object containing name and phone.
- `items: list of OrderItem` — non-empty, ordered.
- `status: OrderStatus` — `PLACED | PREPARING | READY | DELIVERED | CANCELLED`.
- `totalAmount: Money` — sum of `pricePerUnit * quantity` across items, in a single currency.
- `placedAt: DateTimeImmutable`
- `readyAt: ?DateTimeImmutable`
- `deliveredAt: ?DateTimeImmutable`

### OrderItem (entity, not aggregate root)

Lives only inside `CustomerOrder`.

**State:**
- `itemId: OrderItemId` — unique within the order (sequence or ULID; using ULID for simplicity).
- `recipeId: string` — opaque reference to Kitchen's `RecipeId`. NOT a typed cross-context import.
- `recipeName: string` — snapshot at order time, for display.
- `quantity: int` — `>= 1`.
- `pricePerUnit: Money`.
- `readyCount: int` — how many of the `quantity` units have been cooked so far. `0..quantity`.

**Invariants on CustomerOrder:**
1. `items` MUST be non-empty.
2. All `items[].pricePerUnit.currency` MUST be the same currency.
3. `totalAmount.currency` matches items.
4. `totalAmount.amount` equals the sum of items.
5. Status transitions are strictly ordered: `PLACED → PREPARING → READY → DELIVERED`. `CANCELLED` may be reached from `PLACED` only (an order being cooked cannot be cancelled in the MVP).
6. `readyAt` is set if and only if status has been past `PREPARING`.
7. `deliveredAt` is set if and only if status is `DELIVERED`.
8. The order transitions to `READY` automatically when, for every item, `readyCount == quantity`.

**Behaviors:**
- `CustomerOrder::place(id, customer, items): CustomerOrder` — factory; validates invariants; status starts as `PLACED`; records `CustomerOrderPlaced` carrying the full item list (with recipe ids and quantities — Kitchen needs this).
- `accept()` — transitions to `PREPARING`. (Optional auto-transition: the system may move from `PLACED` to `PREPARING` immediately on the placement event being consumed by Kitchen — for the MVP we keep this explicit for clarity. Records `CustomerOrderAccepted`.)
- `markItemReady(itemId, count = 1)` — increments `readyCount` for the item by `count` (default 1). Asserts `readyCount <= quantity`. If after the update every item has `readyCount == quantity`, transitions to `READY` and records `CustomerOrderReady`. Always records `OrderItemReady` for the specific item.
- `deliver()` — transitions to `DELIVERED`; sets `deliveredAt`; records `CustomerOrderDelivered`.
- `cancel(reason)` — transitions to `CANCELLED` only if current status is `PLACED`. Records `CustomerOrderCancelled`.

### Why `markItemReady` and not just "complete order"

Each pizza is cooked individually (one `CookingOrder` per item-unit). The Restaurant context observes each completion via `PizzaCooked` events and increments the per-item ready counter. Only when all units of all items are accounted for does the order itself transition to `READY`. This is the meaningful unit-of-work granularity for the order lifecycle.

A subtle issue: an order item with `quantity = 3` means three separate cooking orders, and three separate `PizzaCooked` events. The Restaurant handler doesn't know which of the three a given event refers to — it just increments. The aggregate enforces `readyCount <= quantity`.

## Value Objects

| Value Object         | Fields                                            | Validation                                   |
| -------------------- | ------------------------------------------------- | -------------------------------------------- |
| `CustomerOrderId`    | `value: string` (ULID)                            | Must be a valid ULID.                        |
| `OrderItemId`        | `value: string` (ULID)                            | Must be a valid ULID.                        |
| `CustomerInfo`       | `name: string`, `phone: string`                   | Name 2–80 chars, phone matches a simple E.164-ish pattern. |
| `OrderStatus`        | enum-like                                         | Closed set.                                  |
| `Money`              | `amount: int`, `currency: string`                 | Same shape as Kitchen's `Money`. Documented duplication. |

`Money` is duplicated between Kitchen and Restaurant. Extracting a shared kernel is a deliberate non-goal of the MVP — a shared kernel introduces coupling, and for two value objects this is cheap to maintain. Documented as a trade-off.

## Domain Events

### Published by Restaurant

| Event                       | When                                              | Payload                                                              | Consumers          |
| --------------------------- | ------------------------------------------------- | -------------------------------------------------------------------- | ------------------ |
| `CustomerOrderPlaced`       | An order is placed.                               | `customerOrderId`, `customer.name`, `customer.phone`, **`items`** (list of `{itemId, recipeId, quantity, pricePerUnit, currency}`), `totalAmount`, `currency`, `placedAt`, `occurredOn` | **Kitchen**.       |
| `CustomerOrderAccepted`     | Order moves to `PREPARING`.                       | `customerOrderId`, `occurredOn`                                      | None.              |
| `OrderItemReady`            | One unit of an item is completed.                 | `customerOrderId`, `itemId`, `readyCount`, `quantity`, `occurredOn`  | None.              |
| `CustomerOrderReady`        | All items are fully cooked.                       | `customerOrderId`, `readyAt`, `occurredOn`                           | None.              |
| `CustomerOrderDelivered`    | Order is delivered to the customer.               | `customerOrderId`, `deliveredAt`, `occurredOn`                       | None implemented; Personnel would consume. |
| `CustomerOrderCancelled`    | Order is cancelled while still in `PLACED`.       | `customerOrderId`, `reason`, `occurredOn`                            | None implemented; Kitchen would consume to cancel any pending cooking. |

### Consumed by Restaurant

| Event           | From    | Handler responsibility                                                                                                  |
| --------------- | ------- | ----------------------------------------------------------------------------------------------------------------------- |
| `PizzaCooked`   | Kitchen | Load the customer order by id (event carries `customerOrderId`). Identify the matching item by `recipeId`. Increment its `readyCount` via `markItemReady`. Save and dispatch resulting events. |

The matching strategy is `first item with matching recipeId and readyCount < quantity`. If quantity for that recipe is 3 across the order, the three `PizzaCooked` events will each find the same item and increment it one at a time. This is correct because the event handler is serialized per transport in the MVP.

## Repository Interface

```php
interface CustomerOrderRepository
{
    public function findById(CustomerOrderId $id): ?CustomerOrder;
    /** @return CustomerOrder[] */
    public function findByStatus(OrderStatus $status): array;
    public function save(CustomerOrder $order): void;
}
```

## Commands (write side)

| Command                       | Handler responsibility                                                                                            |
| ----------------------------- | ----------------------------------------------------------------------------------------------------------------- |
| `PlaceCustomerOrder`          | Validate item list against Kitchen's recipe catalogue (load each recipe via Kitchen's query bus to fetch name + price snapshot — this is the **only** cross-context read, documented below). Build `CustomerOrder::place()`, save, dispatch. |
| `AcceptCustomerOrder`         | Load order, call `accept()`, save, dispatch.                                                                       |
| `MarkItemReady`               | Load order, call `markItemReady()`, save, dispatch. Triggered by the `PizzaCooked` event handler.                  |
| `DeliverOrder`                | Load order, call `deliver()`, save, dispatch.                                                                       |
| `CancelOrder`                 | Load order, call `cancel()`, save, dispatch.                                                                        |

### Note on the cross-context read in `PlaceCustomerOrder`

When placing an order, we need the recipe name and price *as they exist at the moment of placement* to build the order item snapshot. We have three options:

1. **Cross-context query via `QueryBus`.** Kitchen exposes `GetRecipe` returning a DTO. Restaurant's handler calls it. This is allowed because it crosses contexts at the *application* layer, not the *domain* layer, and uses a stable Published Language (the DTO). This is the chosen approach for the MVP.
2. **Anti-corruption layer.** Restaurant maintains its own projection of recipes, kept in sync via Kitchen's events (`RecipeCreated`, `RecipePriceChanged`). More correct, more code, deferred.
3. **Client-supplied snapshot.** The HTTP request includes recipe name and price; Restaurant trusts them. Simplest, but lets clients lie about prices. Rejected.

Option 1 is documented in `docs/architecture.md` as a deliberate compromise.

## Queries (read side)

| Query                         | Returns                                                                                          |
| ----------------------------- | ------------------------------------------------------------------------------------------------ |
| `GetCustomerOrder`            | `CustomerOrderView` DTO with order details, items, status, timestamps.                           |
| `ListOrdersByStatus`          | `CustomerOrderView[]` filtered by status.                                                        |
| `ListPendingOrders`           | `CustomerOrderView[]` where status is `PLACED` or `PREPARING`.                                   |

## File Layout

```
src/Restaurant/
├── CLAUDE.md
├── Domain/
│   ├── Aggregate/
│   │   ├── CustomerOrder.php
│   │   └── OrderItem.php                       # entity inside CustomerOrder
│   ├── ValueObject/
│   │   ├── CustomerOrderId.php
│   │   ├── OrderItemId.php
│   │   ├── CustomerInfo.php
│   │   ├── OrderStatus.php
│   │   └── Money.php
│   ├── Event/
│   │   ├── CustomerOrderPlaced.php
│   │   ├── CustomerOrderAccepted.php
│   │   ├── OrderItemReady.php
│   │   ├── CustomerOrderReady.php
│   │   ├── CustomerOrderDelivered.php
│   │   └── CustomerOrderCancelled.php
│   ├── Repository/
│   │   └── CustomerOrderRepository.php
│   └── Exception/
│       ├── CustomerOrderNotFound.php
│       ├── InvalidStatusTransition.php
│       └── ItemAlreadyFullyReady.php
├── Application/
│   ├── PlaceCustomerOrder/
│   ├── AcceptCustomerOrder/
│   ├── MarkItemReady/
│   ├── DeliverOrder/
│   ├── CancelOrder/
│   ├── GetCustomerOrder/
│   ├── ListOrdersByStatus/
│   ├── ListPendingOrders/
│   └── EventHandler/
│       └── OnPizzaCooked.php                   # consumes Kitchen event
├── Infrastructure/
│   └── Persistence/Doctrine/
│       ├── DoctrineCustomerOrderRepository.php
│       └── Mappings/
│           ├── CustomerOrder.orm.xml
│           └── OrderItem.orm.xml
└── UI/Http/
    └── CustomerOrderController.php
```

## HTTP Endpoints

| Method | Path                                                    | Maps to                      |
| ------ | ------------------------------------------------------- | ---------------------------- |
| POST   | `/restaurant/orders`                                    | `PlaceCustomerOrder`         |
| POST   | `/restaurant/orders/{id}/accept`                        | `AcceptCustomerOrder`        |
| POST   | `/restaurant/orders/{id}/deliver`                       | `DeliverOrder`               |
| POST   | `/restaurant/orders/{id}/cancel`                        | `CancelOrder`                |
| GET    | `/restaurant/orders/{id}`                               | `GetCustomerOrder`           |
| GET    | `/restaurant/orders?status=preparing`                   | `ListOrdersByStatus`         |
| GET    | `/restaurant/orders/pending`                            | `ListPendingOrders`          |

`MarkItemReady` is not exposed via HTTP. It is only invoked by the `OnPizzaCooked` handler. Manual marking would let the front desk fake order completion without the kitchen — an integrity violation.
