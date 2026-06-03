# Pizza House

Modular monolith for managing a pizza business. Built as a technical demonstration of:

- Domain-Driven Design (strategic + tactical patterns)
- CQRS with separate command, query, and event buses
- Event-driven inter-context integration
- Clean architecture (hexagonal/onion layering)

This is an MVP. Scope is deliberately constrained — see `docs/architecture.md` for rationale and trade-offs.

## Stack

- PHP 8.3
- Symfony 7.x (HTTP kernel, DI, Messenger only — not used as a full-stack framework)
- Doctrine ORM 3.x (mapping isolated to the Infrastructure layer)
- PostgreSQL 16
- PHPUnit 11
- Docker Compose for local environment

No additional frameworks. No CMS. No admin generators. No API Platform.

## Bounded Contexts

**Fully implemented** (Domain + Application + Infrastructure + UI):

- `Kitchen` — recipes, cooking orders, pizza preparation
- `Storage` — ingredient stock, consumption, availability signals
- `Restaurant` — customer orders, table service

**Documented only** (in `docs/context-map.md`, no code):

- `Personnel`, `Procurement`, `Sales`

Each implemented context has its own `CLAUDE.md` describing local invariants and events.

## Directory Structure

```
src/
  Shared/                            # cross-context abstractions only
    Domain/                          # AggregateRoot, DomainEvent, ValueObject base classes
    Application/                     # CommandBus, QueryBus, EventBus interfaces
    Infrastructure/                  # Messenger adapters, base Doctrine repositories
  Kitchen/
    Domain/                          # aggregates, VOs, events, repository interfaces — pure PHP
    Application/                     # command/query/event handlers, DTOs
    Infrastructure/                  # Doctrine repos, Messenger config, external adapters
    UI/Http/                         # controllers, request/response models
  Storage/                           # same internal structure
  Restaurant/                        # same internal structure
tests/
  Unit/                              # mirrors src/, domain + application
  Integration/                       # cross-context flows, real DB
config/
docs/
.claude/
  agents/
  commands/
```

## Layering Rules (NON-NEGOTIABLE)

**Domain layer:**
- No `use Doctrine\…`, no `use Symfony\…`, no infrastructure imports
- No framework attributes on domain classes (Doctrine mapping lives in Infrastructure)
- Aggregates expose behavior, not setters
- Value objects are immutable and self-validating
- Domain events are immutable; names are past tense (`PizzaCooked`, not `CookPizza`)
- Repository interfaces live here; implementations do not

**Application layer:**
- Thin use-case orchestrators
- One command/query = one handler
- May depend on Domain and on bus interfaces from `Shared/Application`
- May NOT depend on Infrastructure or UI
- Returns DTOs, not domain objects, to the outside world

**Infrastructure layer:**
- All framework integration lives here
- Doctrine entity mappings as XML files under `Infrastructure/Persistence/Doctrine/Mappings/` (NOT attributes on domain entities)
- Messenger transport config
- Repository implementations
- External service adapters

**UI layer:**
- HTTP controllers, CLI commands
- Translates external input to Application commands/queries
- Translates Application DTOs to HTTP responses
- No business logic, ever

**Cross-context rule:**
- One context's `Domain/` MUST NOT import from another context's `Domain/`
- Integration happens via domain events on the event bus
- If you want a direct cross-context call, publish an event instead

## CQRS Conventions

Three separate Messenger buses configured in `config/packages/messenger.yaml`:

- `command.bus` — write operations, sync handling, exactly one handler per command
- `query.bus` — read operations, sync handling, returns DTO
- `event.bus` — domain events, async handling (Doctrine transport), multiple handlers allowed

Naming:
- Commands: imperative verb + noun (`StartCookingOrder`, `RegisterIngredientDelivery`)
- Queries: `Get` + noun (`GetCookingOrderStatus`, `GetIngredientAvailability`)
- Events: past tense (`CookingOrderStarted`, `IngredientStockDepleted`)

Handlers live next to their commands/queries:

```
Kitchen/Application/StartCookingOrder/
  StartCookingOrder.php           # command (immutable DTO)
  StartCookingOrderHandler.php    # handler
```

## Persistence Pragmatism

Doctrine entity mappings use XML configuration under `src/{Context}/Infrastructure/Persistence/Doctrine/Mappings/`. This keeps domain entities free of framework concerns without the overhead of separate persistence models.

This is a deliberate trade-off documented in `docs/architecture.md`. A full system would introduce explicit persistence models and mappers.

## Testing Policy

- Domain aggregates: covered by unit tests, constructed directly, no mocks
- Application handlers: unit-tested with mocked repositories and buses
- Cross-context flows: at least one integration test per major flow, real DB, real bus (sync)
- No tests for getters, framework wiring, or controllers without logic

Naming:
- Test class: `{ClassUnderTest}Test`
- Test method: `test_does_something_when_condition` (snake_case for readability)

## Commit Format

Conventional commits, scoped by context:

```
feat(kitchen): add CookingOrder aggregate
test(storage): cover stock depletion scenarios
docs: add context map
chore(infra): configure messenger buses
```

## Sub-Agents

Three specialized sub-agents live in `.claude/agents/`:

- `domain-modeler` — designs and implements Domain layer code. Use for work inside `Domain/`.
- `test-writer` — writes PHPUnit tests. Use after a domain or application change is complete.
- `code-reviewer` — reviews uncommitted changes against project rules. Use before every commit, especially for `Shared/`, base classes, and messenger config.

Do not let one agent do work belonging to another. If the main session is asked to "write tests for X", delegate to `test-writer` rather than inlining the work.

## Hard Rules

1. NEVER add framework imports inside `Domain/` directories.
2. NEVER let two bounded contexts share aggregates or call each other directly.
3. NEVER make domain events mutable.
4. NEVER skip the Application layer (controllers do not call repositories).
5. ALWAYS run `code-reviewer` before committing changes to `Shared/`, base classes, or messenger config.
6. ALWAYS prefer publishing an event over coupling two contexts.

## Out of Scope (Document, Do Not Implement)

- Authentication and authorization
- Frontend
- Payment processing
- `Personnel`, `Procurement`, `Sales` implementations
- RabbitMQ (Doctrine transport is sufficient for MVP)
- Read models and projections (the same DB serves reads — documented trade-off)
