# Kitchen Context

**Purpose.** Manage the menu of pizzas (recipes) and the act of preparing them for customer orders (cooking orders).

This context is fully implemented. See [`docs/context-map.md`](../context-map.md) for how it relates to Storage and Restaurant.

## Ubiquitous Language

| Term              | Meaning                                                                                       |
| ----------------- | --------------------------------------------------------------------------------------------- |
| **Recipe**        | The definition of a pizza we sell: name, list of required ingredients, price, cooking time.   |
| **Ingredient requirement** | A pair of (ingredient name, quantity, unit) that a recipe needs. Pure data, no ID. |
| **Cooking order** | The act of cooking one pizza for one customer order item. Has a lifecycle.                    |
| **Pizza**         | Used informally — in this context, a pizza is the output of executing a recipe.               |

Note: there is no `Pizza` aggregate. The conceptual "pizza" is fully covered by `Recipe` (the template) and `CookingOrder` (the act of producing one).

## Aggregates

### Recipe (aggregate root)

The menu item. Created by the kitchen chief, referenced by customer orders, used by cooking orders.

**Identity:** `RecipeId` (ULID, value object).

**State:**
- `name: RecipeName` — value object; non-empty, unique across active recipes.
- `ingredients: list of IngredientRequirement` — at least one required.
- `price: Money` — must be positive.
- `cookingTimeMinutes: int` — must be in `[1, 120]`.

**Invariants:**
1. A recipe MUST have a non-empty name.
2. A recipe MUST have at least one ingredient.
3. The price MUST be strictly positive.
4. The cooking time MUST be in the range [1, 120] minutes.
5. The same ingredient name MUST NOT appear twice in the ingredient list.

**Behaviors:**
- `Recipe::create(id, name, ingredients, price, cookingTime): Recipe` — factory; validates all invariants; records `RecipeCreated`.
- `addIngredient(IngredientRequirement)` — adds an ingredient; rejects duplicates; records `RecipeIngredientAdded`.
- `removeIngredient(IngredientName)` — removes an ingredient; rejects if it would leave the recipe empty; records `RecipeIngredientRemoved`.
- `changePrice(Money newPrice)` — validates positivity; no-ops if unchanged; records `RecipePriceChanged`.

### CookingOrder (aggregate root)

A single cooking job: cook one pizza of a given recipe, as part of a customer order.

**Identity:** `CookingOrderId` (ULID, value object).

**State:**
- `customerOrderId: string` — opaque reference to the Restaurant context's order. NOT a typed `CustomerOrderId` from Restaurant; we hold the raw string to avoid coupling.
- `recipeId: RecipeId`
- `recipeSnapshot: RecipeSnapshot` — frozen at order time: name, ingredient list, cooking time. Why a snapshot is required is explained below.
- `status: CookingStatus` — `PENDING | IN_PROGRESS | READY | CANCELLED`.
- `startedAt: ?DateTimeImmutable`
- `completedAt: ?DateTimeImmutable`

**Why a recipe snapshot.** If a recipe is edited (price change, new ingredient) while a cooking order is in flight, the cooking order MUST continue to use the recipe as it was when the order was placed. We don't want to retroactively change what we're cooking. The snapshot is a value object embedded in the aggregate, populated by the application layer from the Recipe at creation time.

**Invariants:**
1. Status transitions are strictly ordered: `PENDING → IN_PROGRESS → READY`. `CANCELLED` may be reached only from `PENDING` or `IN_PROGRESS`.
2. `startedAt` is set if and only if status has been past `PENDING`.
3. `completedAt` is set if and only if status is `READY`.

**Behaviors:**
- `CookingOrder::create(id, customerOrderId, recipe): CookingOrder` — factory; takes a full `Recipe`, builds a snapshot internally; records `CookingOrderCreated`.
- `startCooking()` — transitions to `IN_PROGRESS`; sets `startedAt`; records `CookingOrderStarted` carrying the ingredient list from the snapshot.
- `markAsReady()` — transitions to `READY`; sets `completedAt`; records `PizzaCooked`.
- `cancel(reason: string)` — transitions to `CANCELLED`; records `CookingOrderCancelled`.

## Value Objects

| Value Object                  | Fields                                            | Validation                          |
| ----------------------------- | ------------------------------------------------- | ----------------------------------- |
| `RecipeId`                    | `value: string` (ULID)                            | Must be a valid ULID.               |
| `RecipeName`                  | `value: string`                                   | 2–80 chars, trimmed, non-empty.     |
| `Money`                       | `amount: int` (minor units, cents), `currency: string` | Currency must be ISO-4217, amount integer. |
| `IngredientRequirement`       | `name: string`, `quantity: float`, `unit: Unit`   | `quantity > 0`, name non-empty.     |
| `Unit`                        | enum-like: `g`, `kg`, `ml`, `l`, `piece`          | Closed set.                         |
| `RecipeSnapshot`              | `name`, `ingredients`, `cookingTimeMinutes`       | All required, validated on creation.|
| `CookingOrderId`              | `value: string` (ULID)                            | Must be a valid ULID.               |
| `CookingStatus`               | enum-like                                         | Closed set.                         |

All value objects are `final readonly class`, implement equality by value, and validate in their constructors.

## Domain Events

### Published by Kitchen

| Event                       | When                                              | Payload                                                            | Consumers                  |
| --------------------------- | ------------------------------------------------- | ------------------------------------------------------------------ | -------------------------- |
| `RecipeCreated`             | A new recipe is added to the menu.                | `recipeId`, `name`, `price.amount`, `price.currency`, `occurredOn` | None implemented; Sales would consume. |
| `RecipeIngredientAdded`     | An ingredient is appended to an existing recipe.  | `recipeId`, `ingredientName`, `quantity`, `unit`, `occurredOn`     | None.                      |
| `RecipeIngredientRemoved`   | An ingredient is removed.                         | `recipeId`, `ingredientName`, `occurredOn`                         | None.                      |
| `RecipePriceChanged`        | Price is updated.                                 | `recipeId`, `oldAmount`, `newAmount`, `currency`, `occurredOn`     | None implemented; Sales.   |
| `CookingOrderCreated`       | A cooking order is registered (status `PENDING`). | `cookingOrderId`, `customerOrderId`, `recipeId`, `occurredOn`      | None.                      |
| `CookingOrderStarted`       | Cooking begins.                                   | `cookingOrderId`, `customerOrderId`, `recipeId`, **`ingredients`** (list of `{name, quantity, unit}`), `occurredOn` | **Storage**.        |
| `PizzaCooked`               | Cooking is complete.                              | `cookingOrderId`, `customerOrderId`, `recipeId`, `occurredOn`      | **Restaurant**.            |
| `CookingOrderCancelled`     | Order is cancelled before completion.             | `cookingOrderId`, `customerOrderId`, `reason`, `occurredOn`        | None implemented; Restaurant would consume. |

### Consumed by Kitchen

| Event                  | From       | Handler responsibility                                                       |
| ---------------------- | ---------- | ---------------------------------------------------------------------------- |
| `CustomerOrderPlaced`  | Restaurant | For each item in the order, dispatch a `StartCookingOrder` command.          |

## Repository Interfaces

Both interfaces live under `src/Kitchen/Domain/Repository/`.

```php
interface RecipeRepository
{
    public function findById(RecipeId $id): ?Recipe;
    public function findByName(RecipeName $name): ?Recipe;
    public function save(Recipe $recipe): void;
    public function remove(Recipe $recipe): void;
}

interface CookingOrderRepository
{
    public function findById(CookingOrderId $id): ?CookingOrder;
    /** @return CookingOrder[] */
    public function findByCustomerOrderId(string $customerOrderId): array;
    public function save(CookingOrder $cookingOrder): void;
}
```

No `findBy()` style queries. No fluent builders. Reads beyond these methods are served by query handlers reading from dedicated read models or directly via DBAL — Doctrine repositories are for writes.

## Commands (write side)

| Command                          | Handler responsibility                                                                                              |
| -------------------------------- | ------------------------------------------------------------------------------------------------------------------- |
| `CreateRecipe`                   | Validate name uniqueness, load nothing, build `Recipe::create()`, save, dispatch recorded events.                   |
| `AddIngredientToRecipe`          | Load recipe, call `addIngredient()`, save, dispatch events.                                                         |
| `ChangeRecipePrice`              | Load recipe, call `changePrice()`, save, dispatch events.                                                           |
| `StartCookingOrder`              | Load recipe, call `CookingOrder::create()`, immediately `startCooking()`, save, dispatch events (incl. `CookingOrderStarted`). Triggered by `CustomerOrderPlaced` handler. |
| `MarkCookingOrderReady`          | Load cooking order, call `markAsReady()`, save, dispatch `PizzaCooked`.                                             |
| `CancelCookingOrder`             | Load cooking order, call `cancel()`, save, dispatch events.                                                         |

Each command is a `final readonly class` in `src/Kitchen/Application/{CommandName}/`. Each handler is in the same directory.

## Queries (read side)

| Query                            | Returns                                                  |
| -------------------------------- | -------------------------------------------------------- |
| `GetRecipe`                      | `RecipeView` DTO with id, name, price, ingredients.      |
| `ListRecipes`                    | `RecipeView[]`.                                          |
| `GetCookingOrderStatus`          | `CookingOrderStatusView` with id, status, timestamps.    |
| `ListCookingOrdersByCustomer`    | `CookingOrderStatusView[]` for a given customerOrderId.  |

DTOs are flat structures, primitives only. Domain objects never cross the application boundary.

## File Layout

```
src/Kitchen/
├── CLAUDE.md                                          # local context rules (copy of relevant sections of this file)
├── Domain/
│   ├── Aggregate/
│   │   ├── Recipe.php
│   │   └── CookingOrder.php
│   ├── ValueObject/
│   │   ├── RecipeId.php
│   │   ├── RecipeName.php
│   │   ├── Money.php
│   │   ├── IngredientRequirement.php
│   │   ├── Unit.php
│   │   ├── RecipeSnapshot.php
│   │   ├── CookingOrderId.php
│   │   └── CookingStatus.php
│   ├── Event/
│   │   ├── RecipeCreated.php
│   │   ├── RecipeIngredientAdded.php
│   │   ├── RecipeIngredientRemoved.php
│   │   ├── RecipePriceChanged.php
│   │   ├── CookingOrderCreated.php
│   │   ├── CookingOrderStarted.php
│   │   ├── PizzaCooked.php
│   │   └── CookingOrderCancelled.php
│   ├── Repository/
│   │   ├── RecipeRepository.php
│   │   └── CookingOrderRepository.php
│   └── Exception/
│       ├── RecipeNotFound.php
│       ├── CookingOrderNotFound.php
│       ├── InvalidStatusTransition.php
│       └── DuplicateIngredient.php
├── Application/
│   ├── CreateRecipe/
│   ├── AddIngredientToRecipe/
│   ├── ChangeRecipePrice/
│   ├── StartCookingOrder/
│   ├── MarkCookingOrderReady/
│   ├── CancelCookingOrder/
│   ├── GetRecipe/
│   ├── ListRecipes/
│   ├── GetCookingOrderStatus/
│   ├── ListCookingOrdersByCustomer/
│   └── EventHandler/
│       └── OnCustomerOrderPlaced.php           # consumes Restaurant event
├── Infrastructure/
│   ├── Persistence/Doctrine/
│   │   ├── DoctrineRecipeRepository.php
│   │   ├── DoctrineCookingOrderRepository.php
│   │   └── Mappings/
│   │       ├── Recipe.orm.xml
│   │       └── CookingOrder.orm.xml
│   └── ReadModel/                              # optional; can use DBAL directly
└── UI/Http/
    ├── RecipeController.php
    └── CookingOrderController.php
```

## HTTP Endpoints

| Method | Path                                       | Maps to                          |
| ------ | ------------------------------------------ | -------------------------------- |
| POST   | `/kitchen/recipes`                         | `CreateRecipe`                   |
| POST   | `/kitchen/recipes/{id}/ingredients`        | `AddIngredientToRecipe`          |
| PATCH  | `/kitchen/recipes/{id}/price`              | `ChangeRecipePrice`              |
| GET    | `/kitchen/recipes/{id}`                    | `GetRecipe`                      |
| GET    | `/kitchen/recipes`                         | `ListRecipes`                    |
| GET    | `/kitchen/cooking-orders/{id}`             | `GetCookingOrderStatus`          |
| POST   | `/kitchen/cooking-orders/{id}/ready`       | `MarkCookingOrderReady`          |

`StartCookingOrder` is intentionally not exposed via HTTP — it is only triggered by the `CustomerOrderPlaced` event handler. Exposing it directly would create a path to bypass the customer order, which is invalid in the business model.
