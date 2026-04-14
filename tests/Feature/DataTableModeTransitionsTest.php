<?php

use Laravel\Prompts\DataTable\Modes\BrowseMode;
use Laravel\Prompts\DataTable\Modes\ColumnMode;
use Laravel\Prompts\DataTable\Modes\SearchMode;
use Laravel\Prompts\DataTable\Modes\SelectMode;
use Laravel\Prompts\DataTable\Modes\SortedMode;
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

it('transitions from select to column after a unique typed match through the active mode handler', function () {
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

    expect($prompt->tableState->mode()->name())->toBe(SelectMode::NAME);

    $prompt->tableState->mode()->handleKey($prompt, 'n');

    expect($prompt->tableState->mode()->name())->toBe(ColumnMode::NAME)
        ->and($prompt->displayHeaders()[0])->toContain(' -');
});

it('transitions from column to sorted on s and toggles direction with another s', function () {
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
    $prompt->tableState->mode()->handleKey($prompt, 'n');
    $prompt->tableState->mode()->handleKey($prompt, 's');

    expect($prompt->tableState->mode()->name())->toBe(SortedMode::NAME)
        ->and($prompt->displayHeaders()[0])->toContain('˄');

    $prompt->tableState->mode()->handleKey($prompt, 's');

    expect($prompt->displayHeaders()[0])->toContain('˅');
});

it('walks the escape chain sorted to column to select to normal while preserving order', function () {
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
    $prompt->tableState->mode()->handleKey($prompt, 'n');
    $prompt->tableState->mode()->handleKey($prompt, 's');
    $prompt->tableState->mode()->handleKey($prompt, 's');
    $prompt->tableState->mode()->handleKey($prompt, Key::ESCAPE);

    expect($prompt->tableState->mode()->name())->toBe(ColumnMode::NAME);

    $prompt->tableState->mode()->handleKey($prompt, Key::ESCAPE);

    expect($prompt->tableState->mode()->name())->toBe(SelectMode::NAME);

    $prompt->tableState->mode()->handleKey($prompt, Key::ESCAPE);

    expect($prompt->tableState->mode()->name())->toBe(BrowseMode::NAME)
        ->and($prompt->displayHeaders()[0])->toContain('˅');
});

it('keeps the search query while staying in search mode', function () {
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

    expect($prompt->tableState->mode()->name())->toBe(SearchMode::NAME)
        ->and($prompt->searchValue())->toBe('b');
});
