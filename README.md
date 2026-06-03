# Pizza House

Modular monolith for managing a pizza business. Built as a technical demonstration of:

- **Domain-Driven Design** — bounded contexts, aggregates, value objects, domain events
- **CQRS** — three separate message buses for commands, queries, and events
- **Event-driven integration** — bounded contexts communicate exclusively through asynchronous domain events
- **Clean architecture** — strict layering between Domain, Application, Infrastructure, and UI

This MVP focuses on three contexts (`Kitchen`, `Storage`, `Restaurant`) with full implementations, while three more (`Personnel`, `Procurement`, `Sales`) are documented as bounded contexts without code. See `docs/architecture.md` for the full rationale.

## Quick Start

Requires Docker and `make`. One command to bring everything up:

```bash
make up
```

This builds the images, starts the stack, installs Composer dependencies, and runs database migrations. When it finishes, the API is reachable at:

- Health check: <http://localhost:8080/health>

To verify everything is wired correctly:

```bash
curl http://localhost:8080/health
```

Expected response:
```json
{
  "service": "pizza-house",
  "status": "ok",
  "checks": { "database": "ok" },
  "time": "2026-..."
}
```

## Common Commands

```bash
make help              # list all available targets
make up                # build and start the stack
make down              # stop the stack
make test              # run the full test suite
make test-unit         # run only unit tests
make test-integration  # run only integration tests
make shell             # open a shell in the PHP container
make console ARGS="cache:clear"   # run any Symfony command
make migrate           # run database migrations
make fresh             # drop, recreate, migrate (destructive)
make logs              # tail container logs
```

## Stack

- PHP 8.3 (FPM, Alpine)
- Symfony 7.x — used for HTTP kernel, DI, and Messenger only
- Doctrine ORM 3.x — with XML mappings isolated to the Infrastructure layer
- PostgreSQL 16
- PHPUnit 11

## Architecture

For the full architecture write-up — bounded contexts, layering rules, CQRS conventions, and the trade-offs deliberately taken for the MVP — see [`docs/architecture.md`](docs/architecture.md).

## AI-Assisted Development

This project was developed with Claude Code (Anthropic). The configuration that drove the agents lives at:

- [`CLAUDE.md`](CLAUDE.md) — root project context and architectural rules
- [`.claude/agents/`](.claude/agents/) — specialized sub-agents:
  - `domain-modeler` — designs and implements pure Domain layer code
  - `test-writer` — writes PHPUnit tests, never modifies production code
  - `code-reviewer` — enforces architectural rules before each commit, read-only

A narrative of how AI was actually used during development — prompts, sub-agent delegation, places where the human intervened and why — is in [`docs/ai-workflow.md`](docs/ai-workflow.md).

## Project Structure

```
src/
  Shared/             # cross-context base classes (AggregateRoot, bus interfaces, ...)
  Kitchen/            # recipes, cooking orders, pizza preparation
  Storage/            # ingredient stock, consumption signals
  Restaurant/         # customer orders, table service
tests/
  Unit/               # domain + application unit tests
  Integration/        # cross-context flow tests
config/               # Symfony configuration
docs/                 # architecture, context map, user stories, AI workflow
.claude/              # AI agent configuration
.docker/              # Dockerfile + nginx config
```

## License

Proprietary — built for technical demonstration purposes.
