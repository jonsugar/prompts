<?php

use Laravel\Prompts\DataTable\Modes\BrowseMode;
use Laravel\Prompts\DataTable\Modes\SearchMode;
use Laravel\Prompts\DataTable\Modes\SortColumnMode;
use Laravel\Prompts\DataTable\Modes\SortMode;
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

it('transitions from sort to sort column after a unique typed match through the active mode handler', function () {
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

    expect($prompt->tableState->mode()->name())->toBe(SortMode::NAME);

    $prompt->tableState->mode()->handleKey($prompt, 'n');
    $prompt->tableState->mode()->handleKey($prompt, Key::ENTER);

    expect($prompt->tableState->mode()->name())->toBe(SortColumnMode::NAME)
        ->and($prompt->displayHeaders()[0])->toContain('˄');
});

it('transitions from sort column back to sort on escape while preserving order', function () {
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
    $prompt->tableState->mode()->handleKey($prompt, Key::ENTER);
    $prompt->tableState->mode()->handleKey($prompt, Key::ESCAPE);

    expect($prompt->tableState->mode()->name())->toBe(SortMode::NAME)
        ->and($prompt->displayHeaders()[0])->toContain('˄');
});

it('exits sort mode with a second escape while preserving order', function () {
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
    $prompt->tableState->mode()->handleKey($prompt, Key::ENTER);
    $prompt->tableState->mode()->handleKey($prompt, Key::ESCAPE);
    $prompt->tableState->mode()->handleKey($prompt, Key::ESCAPE);

    expect($prompt->tableState->mode()->name())->toBe(BrowseMode::NAME)
        ->and($prompt->displayHeaders()[0])->toContain('˄');
});

it('keeps the search query when toggling help with ctrl+h in search mode', function () {
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
    $prompt->tableState->mode()->handleKey($prompt, Key::CTRL_H);

    expect($prompt->tableState->mode()->name())->toBe(SearchMode::NAME)
        ->and($prompt->searchValue())->toBe('b')
        ->and($prompt->isHelpVisible())->toBeTrue();
});
