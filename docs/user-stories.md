# User Stories

Six stories covering the implemented contexts (Kitchen, Storage, Restaurant) and the main order-fulfillment flow. Each maps to concrete commands/queries — see `docs/domain-models/`.

## US-1 — Chef creates a recipe

**As a** kitchen chief
**I want** to define a pizza, its required ingredients, and its price
**So that** it can be offered to customers and cooked consistently.

**Acceptance criteria**
- Given a unique name, at least one ingredient, and a positive price, the recipe is created and `RecipeCreated` is published.
- Creating a recipe with a duplicate name is rejected.
- Creating a recipe with no ingredients or a non-positive price is rejected.

Maps to: `CreateRecipe`.

## US-2 — Customer places an order

**As a** front-desk clerk
**I want** to place a customer order for one or more pizzas
**So that** the kitchen starts preparing it and the customer is invoiced.

**Acceptance criteria**
- Given valid recipe references and quantities, the order is created in status `PLACED`, the total is computed from current recipe prices, and `CustomerOrderPlaced` is published.
- The Kitchen begins a cooking order for each item asynchronously.
- An order with no items is rejected.

Maps to: `PlaceCustomerOrder`; triggers `StartCookingOrder` in Kitchen.

## US-3 — Kitchen is notified of new orders

**As a** kitchen staff member
**I want** new orders to appear automatically with their details
**So that** I can start cooking without manual hand-off.

**Acceptance criteria**
- When `CustomerOrderPlaced` is published, a `CookingOrder` is created and started for each item-unit, and `CookingOrderStarted` is published carrying the required ingredients.
- Cooking order status is queryable by customer order id.

Maps to: `OnCustomerOrderPlaced` handler, `StartCookingOrder`, `GetCookingOrderStatus`.

## US-4 — Stock decreases as pizzas are cooked

**As a** store manager
**I want** ingredient stock to decrease automatically when cooking starts
**So that** availability reflects real consumption without manual entry.

**Acceptance criteria**
- When `CookingOrderStarted` is published, each required ingredient's stock is decremented by the consumed quantity.
- A consumption that exceeds available stock is rejected and `IngredientOutOfStock` is published.
- Stock never goes negative.

Maps to: `OnCookingOrderStarted` handler, `ConsumeIngredient`.

## US-5 — Low stock triggers a depletion signal

**As a** store manager
**I want** to be signaled when an ingredient falls below its threshold
**So that** procurement can be triggered before we run out.

**Acceptance criteria**
- When a consumption takes an ingredient from at-or-above threshold to below it, `IngredientStockDepleted` is published exactly once.
- Stocks already below threshold do not re-emit the signal on further consumption.
- Current low-stock ingredients are queryable on demand.

Maps to: `Stock::consume` transition logic, `ListLowStockIngredients`.

## US-6 — Order is tracked from cooking to delivery

**As a** front-desk clerk
**I want** an order to advance to ready when all its pizzas are cooked, then mark it delivered
**So that** I know exactly when to hand it to the customer.

**Acceptance criteria**
- Each `PizzaCooked` event increments the matching item's ready count.
- When every item is fully cooked, the order transitions to `READY` and `CustomerOrderReady` is published.
- A `DeliverOrder` command transitions a ready order to `DELIVERED` and publishes `CustomerOrderDelivered`.
- Querying the order at any point returns its current status and per-item readiness.

Maps to: `OnPizzaCooked` handler, `MarkItemReady`, `DeliverOrder`, `GetCustomerOrder`.
