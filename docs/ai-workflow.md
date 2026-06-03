# AI-Assisted Development Workflow

This project was built with Claude Code (Anthropic) under a deliberate, structured workflow rather than ad-hoc prompting. This document explains how AI was used, where the human directed and intervened, and why the setup is shaped the way it is.

## Principle

The AI is treated as a team of specialists with enforced boundaries, not a single general assistant. Architectural intent lives in version-controlled configuration (`CLAUDE.md`, `.claude/agents/`, `docs/`), so the AI's behavior is reproducible and reviewable — the same way code is.

## Configuration as code

- **`CLAUDE.md`** (root) — the architectural contract: stack, layering rules, CQRS conventions, naming, hard rules. Loaded automatically by Claude Code in the project directory, so every session starts aligned.
- **`.claude/agents/`** — three specialized sub-agents with scoped tools and responsibilities:
  - `domain-modeler` — designs and writes only Domain-layer code; forbidden from framework imports, tests, and multi-context work.
  - `test-writer` — writes PHPUnit tests; cannot modify production code.
  - `code-reviewer` — read-only; reports rule violations by severity before commits.
- **`docs/`** — the specification the agents follow: `context-map.md` (strategic), `domain-models/*` (tactical), `decisions.md` (rationale).

Splitting one agent into three prevents drift: a general agent under time pressure tends to leak abstractions (Doctrine into the domain, business logic into controllers). Scoped agents with explicit prohibitions hold the line over long sessions.

## Session structure

Work was decomposed into focused sessions, one bounded context's element per session, never mixing contexts:

1. Design and implement Kitchen → `Recipe` aggregate (domain-modeler)
2. Kitchen → `CookingOrder` aggregate (domain-modeler)
3. Kitchen → application handlers, then HTTP (main session)
4. Tests for Kitchen (test-writer)
5. Review and commit (code-reviewer)
6. Repeat for Storage, then Restaurant
7. Wire cross-context event handlers
8. Integration test for the full order-to-delivery flow

Each session opened with an explicit read instruction (`Read CLAUDE.md, docs/context-map.md, docs/domain-models/<context>.md`) and, for modeling work, a forced design-before-code step: the agent restated invariants and events for human confirmation before writing anything.

## Where the human directed

- **Strategic choices** — implementation path (hybrid), context boundaries, which contexts to cut. Captured in `docs/decisions.md`.
- **Domain modeling review** — every aggregate's invariants and event set were confirmed by the human before code was written. The agent proposed; the human decided.
- **Architecture-critical files** — `messenger.yaml`, `services.yaml`, and `Shared/` base classes were reviewed line by line; no auto-accept.
- **Trade-off calls** — Doctrine transport vs. RabbitMQ, XML mappings vs. attributes, cross-context query vs. ACL. The human made these; the agent documented them.

## Where AI accelerated the work

- Boilerplate generation: value objects, event classes, repository implementations, Doctrine XML mappings, controller scaffolding.
- Test authoring: unit tests for aggregates and handlers, following a fixed convention.
- Consistency enforcement: the `code-reviewer` agent caught layering violations and naming drift before each commit.

## Effectiveness notes

- Forcing a design-before-code step on the `domain-modeler` was the single highest-leverage rule — it eliminated rounds of regenerating plausible-but-wrong code.
- Keeping each session to one context kept the agent's context window focused and prevented cross-contamination of vocabulary.
- The read-only `code-reviewer` provided a genuine second opinion precisely because it could not "fix and forget" — it had to surface problems for a human decision.

## Reproducing the setup

Anyone cloning this repository inherits the full AI configuration. Opening Claude Code in the project root loads `CLAUDE.md` automatically; the sub-agents are available immediately; the specification in `docs/` defines the work. The development process is as version-controlled as the code it produced.
