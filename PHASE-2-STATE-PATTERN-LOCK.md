# Phase 2 Lock: Strict GoF State Pattern for DataTable

Status: Locked
Date: 2026-04-10
Scope: `Laravel\Prompts\DataTablePrompt` and related datatable state classes

## Baseline (Committed)
This phase starts from the committed baseline implementation in:
- Commit: `afc5663`
- Subject: `feat(datatable): add dto state model with sorting modes`

Treat that commit as the stable starting point. Do not re-implement from scratch.

## Purpose
Lock the next implementation phase to a stricter GoF-style State pattern.

The baseline implementation introduces DTO + mode classes, but transitions are still mostly orchestrated in context methods. This phase requires moving transition ownership into state objects.

## Non-Negotiable Architecture Rules
1. State classes own transition decisions.
2. `DataTablePrompt` (Context) must not contain key->mode transition rules.
3. `DataTablePrompt` may expose transition APIs (for example `transitionTo(...)`) and domain operations (for example `applySort...`, `invalidate...`), but state classes decide when to call them.
4. Mode/state classes may call Context transition APIs directly.
5. Avoid `instanceof` mode branching in Context for behavior routing.
6. Preserve prompt lifecycle states (`initial`, `active`, `error`, `submit`, `cancel`) as prompt-level concerns.
7. Datatable interaction modes (`browse`, `search`, `sort`, future modes) remain separate from prompt lifecycle state.

## Functional Behavior That Must Be Preserved
1. Re-entering search mode opens with the active query intact.
2. `Ctrl+H` toggles help while in search mode.
3. Date sorting supports configured date patterns.
4. Sort mode exits after a successful sort selection.
5. Existing datatable behavior outside these features must remain compatible.

## Required Design Direction
1. Keep `TableState` as the datatable DTO/domain state holder.
2. Keep mode interface/classes, but evolve API to be state-driven (for example `handleKey(Context, key)` returns action/transition intent or executes transition directly).
3. Context should delegate key handling to current mode with minimal branching.
4. Help text should remain mode-specific.

## Acceptance Criteria
1. All tests pass (`vendor/bin/pest`).
2. PHPStan passes for touched files.
3. `DataTablePrompt` no longer owns modal transition mapping logic.
4. New/updated tests explicitly verify state-owned transitions.
5. No regression from commit `afc5663` behaviors.

## Suggested Test Additions for This Phase
1. Assert mode transitions are triggered from mode handlers (behavioral tests, not implementation-coupled).
2. Assert search->browse and browse->sort flows still satisfy preserved behavior.
3. Assert `Ctrl+H` in search does not mutate query text.

## Out of Scope
1. Introducing a command mode (`:`) in this phase.
2. New filtering DSLs in this phase.
3. UX redesign of renderer beyond what is required for preserved behavior.

## Handoff Instruction for Next Session
1. Start from commit `afc5663` (or later if this lock file commit is included).
2. Read this file first and treat it as binding implementation guidance.
3. Refactor transition ownership only; preserve baseline behavior.
