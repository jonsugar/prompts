# Phase: Extract DataTable Display Formatters from TableState

## Goal
Refactor datatable display-formatting logic out of `src/DataTable/TableState.php` into dedicated classes while preserving all current behavior and public API compatibility.

## Why
`TableState` currently mixes state management, sorting, config normalization, and display formatter implementation details. This phase isolates formatter behavior to improve maintainability and make future formatter additions low-risk.

## Scope
- In scope:
  - Extract formatter resolution/implementation from `TableState` into dedicated classes.
  - Keep current formatter capabilities unchanged:
    - `currency` (with `NumberFormatter` preference + fallback)
    - `duration`
    - `printf` (including shorthand pattern string)
    - callable display formatter
  - Keep current config shape and aliases unchanged.
  - Keep help rendering behavior unchanged (mode-specific help always shown).
  - Keep sorting semantics unchanged (sort on raw values, not formatted display values).
- Out of scope:
  - New formatter types.
  - UX behavior changes.
  - Datatable key/mode/interaction changes.
  - Reworking test strategy beyond what is needed for parity.

## Hard Guard Rails
1. Do not change the public `datatable()` helper signature.
2. Do not change existing `sort`/`display` config keys or aliases.
3. Do not change sorting order semantics.
4. Do not remove current fallback behavior for currency when `ext-intl` is unavailable.
5. Keep changes focused; avoid opportunistic unrelated refactors.
6. Do not revert unrelated local changes in the worktree.

## Current Behavior Baseline
- Display formatting is configured through `sort[*]['display']`.
- Formatted values are used only for rendering (active/cancel/submit display).
- Raw values remain the source for filtering/sorting comparisons.
- Help footer always shows mode-specific help text.

## Recommended Design
- Introduce formatter abstractions in `src/DataTable/Formatting`:
  - `DisplayFormatter` interface:
    - `format(string $value, array $row): string`
  - Concrete formatters:
    - `CurrencyDisplayFormatter`
    - `DurationDisplayFormatter`
    - `PrintfDisplayFormatter`
    - `CallableDisplayFormatter`
  - Resolver/factory:
    - `DisplayFormatterResolver` that maps config to formatter instances.
- Keep `ColumnDefinition` storing either:
  - a formatter instance implementing `DisplayFormatter`, or
  - a closure wrapper if you prefer minimal touch.
- `TableState` should delegate resolution/formatting work to the resolver/formatters.

## File Targets
- Primary refactor target:
  - `src/DataTable/TableState.php`
- Likely new files:
  - `src/DataTable/Formatting/DisplayFormatter.php`
  - `src/DataTable/Formatting/DisplayFormatterResolver.php`
  - `src/DataTable/Formatting/CurrencyDisplayFormatter.php`
  - `src/DataTable/Formatting/DurationDisplayFormatter.php`
  - `src/DataTable/Formatting/PrintfDisplayFormatter.php`
  - `src/DataTable/Formatting/CallableDisplayFormatter.php`
- Likely touch points:
  - `src/DataTable/ColumnDefinition.php`
  - `src/DataTablePrompt.php` (only if typing/docs require updates)
  - `src/helpers.php` (only if typing/docs require updates)
  - `tests/Feature/DataTablePromptTest.php`

## Acceptance Criteria
1. Existing datatable feature tests pass:
   - `vendor/bin/pest tests/Feature/DataTablePromptTest.php`
2. No regression in:
   - numeric/date/alpha sort behavior
   - display formatting output
   - mode-specific help output
3. New architecture clearly separates formatter logic from `TableState`.
4. No public API breaking changes.
5. Static syntax checks pass for touched PHP files.

## Suggested Execution Plan
1. Create formatter interface + concrete formatter classes.
2. Create resolver/factory to map current config into formatter instances.
3. Update `ColumnDefinition` and `TableState` to use resolver/formats via interface.
4. Keep behavior parity with existing tests; update only where structure changed.
5. Run feature tests and syntax checks.
6. Summarize what moved and why.

## Verification Commands
```bash
php -l src/DataTable/TableState.php
vendor/bin/pest tests/Feature/DataTablePromptTest.php
```

## Deliverables
- Refactor commit with clear message describing separation of concerns and no behavior change.
- Short before/after note describing:
  - what remained in `TableState`
  - what moved into formatter classes
  - how backward compatibility was preserved.
