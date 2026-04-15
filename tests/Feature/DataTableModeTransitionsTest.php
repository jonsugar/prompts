<?php

use Laravel\Prompts\DataTable\Modes\BrowseMode;
use Laravel\Prompts\DataTable\Modes\ColumnSelectionMode;
use Laravel\Prompts\DataTable\Modes\SearchMode;
use Laravel\Prompts\DataTablePrompt;
use Laravel\Prompts\Key;
use Laravel\Prompts\Prompt;

it('transitions from browse to search through the active mode handler', function () {
    Prompt::fake();

    $prompt = new DataTablePrompt(
        headers: ['Name'],
        rows: [
            ['Alice'],
            ['Bob'],
        ],
        sort: ['Name' => 'alpha'],
    );

    $prompt->tableState->mode()->handleKey($prompt, '/');

    expect($prompt->tableState->mode()->name())->toBe(SearchMode::NAME);
});

it('transitions from search to browse through the active mode handler while keeping query', function () {
    Prompt::fake();

    $prompt = new DataTablePrompt(
        headers: ['Name'],
        rows: [
            ['Alice'],
            ['Bob'],
        ],
        sort: ['Name' => 'alpha'],
    );

    $prompt->emit('key', '/');
    $prompt->emit('key', 'b');
    $prompt->tableState->mode()->handleKey($prompt, Key::ENTER);

    expect($prompt->tableState->mode()->name())->toBe(BrowseMode::NAME)
        ->and($prompt->searchValue())->toBe('b');
});

it('enters column selection mode with c from browse mode', function () {
    Prompt::fake();

    $prompt = new DataTablePrompt(
        headers: ['Name', 'Status'],
        rows: [
            ['Bob', 'Active'],
            ['Alice', 'Archived'],
        ],
        sort: ['Name' => 'alpha', 'Status' => false],
    );

    $prompt->emit('key', 'c');

    expect($prompt->tableState->mode()->name())->toBe(ColumnSelectionMode::NAME)
        ->and($prompt->tableState->selectedColumnIndex)->toBe(0);
});

it('does not enter column selection mode from browse when pressing s', function () {
    Prompt::fake();

    $prompt = new DataTablePrompt(
        headers: ['Name'],
        rows: [
            ['Bob'],
            ['Alice'],
        ],
        sort: ['Name' => 'alpha'],
    );

    $prompt->emit('key', 's');

    expect($prompt->tableState->mode()->name())->toBe(BrowseMode::NAME)
        ->and($prompt->displayHeaders()[0])->toContain(' -');
});

it('transitions from column selection to search with slash and exits search back to browse', function () {
    Prompt::fake();

    $prompt = new DataTablePrompt(
        headers: ['Name'],
        rows: [
            ['Alice'],
            ['Bob'],
        ],
        sort: ['Name' => 'alpha'],
    );

    $prompt->emit('key', 'c');
    $prompt->tableState->mode()->handleKey($prompt, '/');

    expect($prompt->tableState->mode()->name())->toBe(SearchMode::NAME);

    $prompt->tableState->mode()->handleKey($prompt, Key::ENTER);

    expect($prompt->tableState->mode()->name())->toBe(BrowseMode::NAME);
});

it('returns directly to browse from column selection mode on escape while preserving active sort', function () {
    Prompt::fake();

    $prompt = new DataTablePrompt(
        headers: ['Name'],
        rows: [
            ['Bob'],
            ['Alice'],
        ],
        sort: ['Name' => 'alpha'],
    );

    $prompt->emit('key', 'c');
    $prompt->tableState->mode()->handleKey($prompt, 's');

    expect($prompt->displayHeaders()[0])->toContain('˄')
        ->and($prompt->tableState->mode()->name())->toBe(ColumnSelectionMode::NAME);

    $prompt->tableState->mode()->handleKey($prompt, Key::ESCAPE);

    expect($prompt->tableState->mode()->name())->toBe(BrowseMode::NAME)
        ->and($prompt->displayHeaders()[0])->toContain('˄');
});
